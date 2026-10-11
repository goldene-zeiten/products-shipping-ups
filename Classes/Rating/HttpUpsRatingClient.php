<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Rating;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider;
use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2Credentials;
use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Domain\Dto\UpsRate;
use GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsRateRequestEvent;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsRatingException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Rates are cached for a few minutes per rate request and account, so the repeated quotes of one checkout
 * (shipping step, review, placement) cost a single live call.
 */
#[AsAlias(UpsRatingClient::class)]
final class HttpUpsRatingClient implements UpsRatingClient
{
    private const RATE_CACHE_LIFETIME = 300;

    private const RATING_PATH = '/api/rating/v2409/Shop';

    private const TOKEN_PATH = '/security/v1/oauth/token';

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly OAuth2ClientCredentialsProvider $tokenProvider,
        private readonly UpsRateRequestBuilder $requestBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FrontendInterface $rateCache,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return UpsRate[]
     */
    public function rate(ShippingContext $context, UpsConfiguration $configuration): array
    {
        $payload = $this->buildPayload($context, $configuration);
        $cacheIdentifier = $this->cacheIdentifier($configuration, $payload);
        $cached = $this->rateCache->get($cacheIdentifier);
        if (is_array($cached)) {
            return array_map(static fn(array $rate): UpsRate => new UpsRate(...$rate), $cached);
        }

        $rates = $this->extractRates($this->sendWithRetry($configuration, $payload));
        $this->rateCache->set(
            $cacheIdentifier,
            array_map(static fn(UpsRate $rate): array => get_object_vars($rate), $rates),
            [],
            self::RATE_CACHE_LIFETIME
        );

        return $rates;
    }

    /**
     * The full rate request plus the account it is rated for: a different shipment or other credentials
     * never share an answer. The identifier is a hash, so no credential is stored in clear.
     *
     * @param array<string, mixed> $payload
     */
    private function cacheIdentifier(UpsConfiguration $configuration, array $payload): string
    {
        return hash('sha256', (string)json_encode([
            $configuration->baseUrl(),
            $configuration->clientId,
            $configuration->clientSecret,
            $payload,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(ShippingContext $context, UpsConfiguration $configuration): array
    {
        $event = new ModifyUpsRateRequestEvent(
            $this->requestBuilder->build($context, $configuration),
            $context,
            $configuration,
        );
        $this->eventDispatcher->dispatch($event);

        return $event->getPayload();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendWithRetry(UpsConfiguration $configuration, array $payload): ResponseInterface
    {
        $credentials = $this->credentials($configuration);
        $response = $this->send($configuration, $payload, $this->tokenProvider->getToken($credentials));
        if ($response->getStatusCode() === 401) {
            // The cached token was rejected (UPS can revoke early); retry once with a fresh one.
            $response = $this->send($configuration, $payload, $this->tokenProvider->getToken($credentials, true));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(UpsConfiguration $configuration, array $payload, string $token): ResponseInterface
    {
        try {
            return $this->httpClient->postJson(
                $configuration->baseUrl() . self::RATING_PATH,
                $payload,
                ['Authorization' => 'Bearer ' . $token],
            );
        } catch (ApiTransportException $exception) {
            throw new UpsRatingException('UPS rate request failed at transport level.', 1752580100, $exception);
        }
    }

    private function credentials(UpsConfiguration $configuration): OAuth2Credentials
    {
        return new OAuth2Credentials(
            $configuration->baseUrl() . self::TOKEN_PATH,
            $configuration->clientId,
            $configuration->clientSecret,
        );
    }

    /**
     * @return UpsRate[]
     */
    private function extractRates(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $data = json_decode((string)$response->getBody(), true);
        if ($status === 400) {
            // "No rate available for this shipment" is a 400 - a business empty result, not a failure.
            $this->logger->info('UPS returned no rate for the shipment.', ['errors' => $this->errors($data)]);
            return [];
        }
        if ($status !== 200 || !is_array($data)) {
            throw new UpsRatingException(sprintf('UPS rating returned HTTP %d.', $status), 1752580101);
        }

        return $this->mapRates($data);
    }

    /**
     * @param array<string, mixed> $data
     * @return UpsRate[]
     */
    private function mapRates(array $data): array
    {
        $ratedShipments = $data['RateResponse']['RatedShipment'] ?? [];
        if (isset($ratedShipments['Service'])) {
            // UPS returns a single rated shipment as an object rather than a one-element list.
            $ratedShipments = [$ratedShipments];
        }

        return array_values(array_filter(array_map($this->toRate(...), $ratedShipments)));
    }

    private function toRate(mixed $ratedShipment): ?UpsRate
    {
        if (!is_array($ratedShipment)) {
            return null;
        }
        $serviceCode = (string)($ratedShipment['Service']['Code'] ?? '');
        $amount = (string)($ratedShipment['TotalCharges']['MonetaryValue'] ?? '');
        if ($serviceCode === '' || $amount === '') {
            return null;
        }

        return new UpsRate(
            $serviceCode,
            $amount,
            (string)($ratedShipment['TotalCharges']['CurrencyCode'] ?? ''),
            (string)($ratedShipment['GuaranteedDelivery']['BusinessDaysInTransit'] ?? ''),
        );
    }

    /**
     * @return array<int, mixed>
     */
    private function errors(mixed $data): array
    {
        return is_array($data) ? (array)($data['response']['errors'] ?? []) : [];
    }
}
