<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Unit\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRateRequestBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class UpsRateRequestBuilderTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('packageWeightProvider')]
    public function thePackageWeighsTheBasketOrTheDefaultPackageWeight(int $basketWeight, int $defaultPackageWeight, string $expectedWeight): void
    {
        $request = (new UpsRateRequestBuilder())->build(
            $this->context($basketWeight, 5000),
            $this->configuration(defaultPackageWeight: $defaultPackageWeight)
        );

        $this->assertSame($expectedWeight, $request['RateRequest']['Shipment']['Package'][0]['PackageWeight']['Weight']);
    }

    public static function packageWeightProvider(): \Generator
    {
        yield 'a weighed basket' => ['basketWeight' => 2500, 'defaultPackageWeight' => 500, 'expectedWeight' => '2.5'];
        yield 'an unweighed basket takes the default package weight' => ['basketWeight' => 0, 'defaultPackageWeight' => 500, 'expectedWeight' => '0.5'];
        yield 'an unweighed basket without default still sends a positive weight' => ['basketWeight' => 0, 'defaultPackageWeight' => 0, 'expectedWeight' => '0.1'];
    }

    #[Test]
    #[DataProvider('declaredValueProvider')]
    public function theGoodsValueIsDeclaredUpToTheLimit(int $goodsCents, int $limitCents, ?string $expectedValue): void
    {
        $request = (new UpsRateRequestBuilder())->build(
            $this->context(2500, $goodsCents),
            $this->configuration(declaredValueLimit: $limitCents)
        );

        $declaredValue = $request['RateRequest']['Shipment']['Package'][0]['PackageServiceOptions']['DeclaredValue'] ?? null;
        $this->assertSame(
            $expectedValue,
            $declaredValue['MonetaryValue'] ?? null
        );
        if ($expectedValue !== null) {
            $this->assertSame('EUR', $declaredValue['CurrencyCode']);
        }
    }

    public static function declaredValueProvider(): \Generator
    {
        yield 'below the limit the goods value' => ['goodsCents' => 248000, 'limitCents' => 1000000, 'expectedValue' => '2480.00'];
        yield 'above the limit the limit' => ['goodsCents' => 1450000, 'limitCents' => 1000000, 'expectedValue' => '10000.00'];
        yield 'no limit declares nothing' => ['goodsCents' => 248000, 'limitCents' => 0, 'expectedValue' => null];
    }

    private function context(int $weight, int $goodsCents): ShippingContext
    {
        return new ShippingContext([], $weight, Money::fromCents($goodsCents), 'EUR', 'DE', '93047');
    }

    private function configuration(int $defaultPackageWeight = 0, int $declaredValueLimit = 0): UpsConfiguration
    {
        return new UpsConfiguration(
            environment: UpsEnvironment::Sandbox,
            clientId: 'cid',
            clientSecret: 'secret',
            accountNumber: '',
            originPostCode: '93047',
            originCountryCode: 'DE',
            originCity: 'Regensburg',
            weightUnit: 'KGS',
            usedServices: [],
            defaultPackageWeight: $defaultPackageWeight,
            declaredValueLimit: $declaredValueLimit,
        );
    }
}
