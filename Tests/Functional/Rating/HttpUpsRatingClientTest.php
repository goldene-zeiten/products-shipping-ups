<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Rating;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsRatingException;
use GoldeneZeiten\Products\Shipping\Ups\Rating\HttpUpsRatingClient;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRateRequestBuilder;
use GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\AbstractUpsMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheManager;

final class HttpUpsRatingClientTest extends AbstractUpsMockTestCase
{
    private const RATING_PATH = '/shipping/ups/api/rating/v2409/Shop';

    #[Test]
    public function fetchesAndMapsRatesOverHttp(): void
    {
        $rates = $this->subject()->rate($this->context('BE'), $this->configuration());

        $this->assertCount(2, $rates);
        $this->assertSame('11', $rates[0]->serviceCode);
        $this->assertSame('9.99', $rates[0]->amount);
        $this->assertSame('EUR', $rates[0]->currencyCode);
        $this->assertSame('65', $rates[1]->serviceCode);
    }

    #[Test]
    public function mapsASingleShipmentReturnedAsAnObject(): void
    {
        $rates = $this->subject()->rate($this->context('SG'), $this->configuration());

        $this->assertCount(1, $rates);
        $this->assertSame('11', $rates[0]->serviceCode);
        $this->assertSame('7.50', $rates[0]->amount);
    }

    #[Test]
    public function treatsANoRateAnswerAsEmpty(): void
    {
        $this->assertSame([], $this->subject()->rate($this->context('XX'), $this->configuration()));
    }

    #[Test]
    public function retriesOnceWithAFreshTokenAfterUnauthorized(): void
    {
        $rates = $this->subject()->rate($this->context('RT'), $this->configuration());

        $this->assertCount(1, $rates);
        $this->assertSame(2, $this->recordedRequests(self::RATING_PATH), 'The rate request was retried once.');
    }

    #[Test]
    public function raisesOnAServerError(): void
    {
        $this->expectException(UpsRatingException::class);
        $this->subject()->rate($this->context('YY'), $this->configuration());
    }

    #[Test]
    public function raisesOnATransportFault(): void
    {
        $this->expectException(UpsRatingException::class);
        $this->subject()->rate($this->context('ZZ'), $this->configuration());
    }

    #[Test]
    public function sendsAShopRequestWithTheDestinationAndWeight(): void
    {
        $this->subject()->rate($this->context('BE'), $this->configuration());

        $body = json_decode((string)$this->loggedRequests(self::RATING_PATH)[0]['body'], true);
        $this->assertSame('Shop', $body['RateRequest']['Request']['RequestOption']);
        $this->assertSame('BE', $body['RateRequest']['Shipment']['ShipTo']['Address']['CountryCode']);
        $this->assertSame('KGS', $body['RateRequest']['Shipment']['Package'][0]['PackageWeight']['UnitOfMeasurement']['Code']);
        $this->assertSame('2.5', $body['RateRequest']['Shipment']['Package'][0]['PackageWeight']['Weight']);
    }

    private function subject(): HttpUpsRatingClient
    {
        $apiHttpClient = new ApiHttpClient($this->httpClient());

        return new HttpUpsRatingClient(
            $apiHttpClient,
            new OAuth2ClientCredentialsProvider(
                $apiHttpClient,
                $this->get(CacheManager::class)->getCache('products_shipping_ups_token'),
            ),
            new UpsRateRequestBuilder(),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    private function context(string $countryCode): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', $countryCode, '1000');
    }
}
