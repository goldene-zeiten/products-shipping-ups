<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfigurationFactory;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class UpsConfigurationFactoryTest extends UnitTestCase
{
    private const EXTENSION_DEFAULTS = [
        'environment' => 'sandbox',
        'clientId' => 'ext-client',
        'clientSecret' => 'ext-secret',
        'accountNumber' => 'ACC123',
        'originPostCode' => '80331',
        'originCountryCode' => 'DE',
        'originCity' => 'Munich',
        'usedServices' => '',
        'weightUnit' => 'KGS',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteOverrides(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(UpsEnvironment::Sandbox, $configuration->environment);
        $this->assertSame('ext-client', $configuration->clientId);
        $this->assertSame('ACC123', $configuration->accountNumber);
        $this->assertSame('80331', $configuration->originPostCode);
        $this->assertSame('DE', $configuration->originCountryCode);
        $this->assertTrue($configuration->isComplete());
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['shipping' => ['ups' => [
            'environment' => 'production',
            'clientId' => 'site-client',
            'originPostCode' => '10115',
            // clientSecret / originCountryCode left empty -> inherited from the extension configuration
        ]]]]]);

        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite($site);

        $this->assertSame(UpsEnvironment::Production, $configuration->environment);
        $this->assertSame('site-client', $configuration->clientId);
        $this->assertSame('ext-secret', $configuration->clientSecret, 'Empty site value inherits the extension secret.');
        $this->assertSame('10115', $configuration->originPostCode);
        $this->assertSame('DE', $configuration->originCountryCode, 'Empty site value inherits the extension origin country.');
    }

    #[Test]
    public function anUnconfiguredExtensionYieldsAnIncompleteConfiguration(): void
    {
        $configuration = $this->factory([])->forSite(null);

        $this->assertSame('', $configuration->clientId);
        $this->assertFalse($configuration->isComplete());
    }

    #[Test]
    public function usedServicesAreParsedAndFilterServices(): void
    {
        $configuration = $this->factory(['usedServices' => '11, 65 ,'] + self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(['11', '65'], $configuration->usedServices);
        $this->assertTrue($configuration->offersService('11'));
        $this->assertFalse($configuration->offersService('07'));
    }

    #[Test]
    public function anEmptyServiceListOffersEveryService(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertTrue($configuration->offersService('07'));
        $this->assertTrue($configuration->offersService('99'));
    }

    #[Test]
    public function weightUnitFallsBackToKilogramsForAnythingButPounds(): void
    {
        $this->assertSame('LBS', $this->factory(['weightUnit' => 'LBS'] + self::EXTENSION_DEFAULTS)->forSite(null)->weightUnit);
        $this->assertSame('KGS', $this->factory(['weightUnit' => 'nonsense'] + self::EXTENSION_DEFAULTS)->forSite(null)->weightUnit);
    }

    #[Test]
    public function apiBaseUrlOverridesTheEnvironmentHostWhenSet(): void
    {
        $overridden = $this->factory(['apiBaseUrl' => 'http://localhost:4010'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('http://localhost:4010', $overridden->baseUrl());

        $default = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('https://wwwcie.ups.com', $default->baseUrl());
    }

    /**
     * @param array<string, string> $extensionConfiguration
     */
    private function factory(array $extensionConfiguration): UpsConfigurationFactory
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === []) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1752581100));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new UpsConfigurationFactory($extensionConfigurationService);
    }
}
