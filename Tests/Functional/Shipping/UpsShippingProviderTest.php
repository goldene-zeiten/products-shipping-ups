<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Shipping;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfigurationFactory;
use GoldeneZeiten\Products\Shipping\Ups\Domain\Dto\UpsRate;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsRatingException;
use GoldeneZeiten\Products\Shipping\Ups\Rating\GermanOriginServiceCatalog;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRatingClient;
use GoldeneZeiten\Products\Shipping\Ups\Shipping\UpsShippingProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class UpsShippingProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-shipping-ups',
    ];

    #[Test]
    public function mapsRatesToPricedAndLabelledOptions(): void
    {
        $provider = $this->subject($this->ratingClient([
            new UpsRate('11', '9.99', 'EUR'),
            new UpsRate('65', '19.99', 'EUR', '2'),
        ]));

        $options = $provider->quote($this->context());

        $this->assertCount(2, $options);
        $this->assertSame('ups:11', $options[0]->getKey());
        $this->assertSame('UPS Standard', $options[0]->getLabel());
        $this->assertSame(999, $options[0]->getCost()->getCents());
        $this->assertSame('UPS Saver', $options[1]->getLabel());
        $this->assertSame('2 business day(s)', $options[1]->getDeliveryEstimate());
    }

    #[Test]
    public function filtersServicesByTheAllowList(): void
    {
        $provider = $this->subject(
            $this->ratingClient([new UpsRate('11', '9.99', 'EUR'), new UpsRate('65', '19.99', 'EUR')]),
            usedServices: '11',
        );

        $options = $provider->quote($this->context());

        $this->assertCount(1, $options);
        $this->assertSame('ups:11', $options[0]->getKey());
    }

    #[Test]
    public function skipsRatesQuotedInAnotherCurrency(): void
    {
        $provider = $this->subject($this->ratingClient([new UpsRate('11', '9.99', 'USD')]));

        $this->assertSame([], $provider->quote($this->context()));
    }

    #[Test]
    public function offersNothingWhenTheConfigurationIsIncomplete(): void
    {
        $provider = $this->subject($this->ratingClient([new UpsRate('11', '9.99', 'EUR')]), clientId: '');

        $this->assertSame([], $provider->quote($this->context()));
    }

    #[Test]
    public function offersNothingWhenRatingFails(): void
    {
        $provider = $this->subject($this->ratingClientThatThrows());

        $this->assertSame([], $provider->quote($this->context()));
    }

    #[Test]
    public function resolveReturnsTheChosenOptionOrNull(): void
    {
        $provider = $this->subject($this->ratingClient([
            new UpsRate('11', '9.99', 'EUR'),
            new UpsRate('65', '19.99', 'EUR'),
        ]));

        $this->assertSame('ups:65', $provider->resolve('65', $this->context())?->getKey());
        $this->assertNull($provider->resolve('99', $this->context()));
    }

    private function subject(UpsRatingClient $ratingClient, string $clientId = 'cid', string $usedServices = ''): UpsShippingProvider
    {
        $factory = new UpsConfigurationFactory(
            new ApiSettingsResolver($this->extensionConfiguration([
                'environment' => 'sandbox',
                'clientId' => $clientId,
                'clientSecret' => 'secret',
                'accountNumber' => 'ACC',
                'originPostCode' => '80331',
                'originCountryCode' => 'DE',
                'originCity' => '',
                'usedServices' => $usedServices,
                'weightUnit' => 'KGS',
            ])),
            new CurrentSiteResolver(),
        );

        return new UpsShippingProvider(
            $factory,
            $ratingClient,
            new GermanOriginServiceCatalog(),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    /**
     * @param UpsRate[] $rates
     */
    private function ratingClient(array $rates): UpsRatingClient
    {
        return new class ($rates) implements UpsRatingClient {
            /**
             * @param UpsRate[] $rates
             */
            public function __construct(private readonly array $rates) {}

            public function rate(ShippingContext $context, UpsConfiguration $configuration): array
            {
                return $this->rates;
            }
        };
    }

    private function ratingClientThatThrows(): UpsRatingClient
    {
        return new class () implements UpsRatingClient {
            public function rate(ShippingContext $context, UpsConfiguration $configuration): array
            {
                throw new UpsRatingException('boom', 1752582000);
            }
        };
    }

    /**
     * @param array<string, string> $config
     */
    private function extensionConfiguration(array $config): ExtensionConfiguration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($config);

        return $extensionConfiguration;
    }

    private function context(): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', 'BE', '1000');
    }
}
