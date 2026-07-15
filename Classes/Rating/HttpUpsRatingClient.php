<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\Ups\Authentication\UpsOAuthTokenProvider;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Domain\Dto\UpsRate;
use GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsRateRequestEvent;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsRatingException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\Stream;

#[AsAlias(UpsRatingClient::class)]
final class HttpUpsRatingClient implements UpsRatingClient
{
    private const RATING_PATH = '/api/rating/v2409/Shop';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly UpsOAuthTokenProvider $tokenProvider,
        private readonly UpsRateRequestBuilder $requestBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @return UpsRate[]
     */
    public function rate(ShippingContext $context, UpsConfiguration $configuration): array
    {
        $payload = $this->buildPayload($context, $configuration);

        return $this->extractRates($this->sendWithRetry($configuration, $payload));
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
        $response = $this->send($configuration, $payload, $this->tokenProvider->getToken($configuration));
        if ($response->getStatusCode() === 401) {
            // The cached token was rejected (UPS can revoke early); retry once with a fresh one.
            $response = $this->send($configuration, $payload, $this->tokenProvider->getToken($configuration, true));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function send(UpsConfiguration $configuration, array $payload, string $token): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode($payload, JSON_THROW_ON_ERROR));
        $body->rewind();
        $request = new Request(
            $configuration->baseUrl() . self::RATING_PATH,
            'POST',
            $body,
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        );

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new UpsRatingException('UPS rate request failed at transport level.', 1752580100, $exception);
        }
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
