<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective UPS configuration for a site. The layering of the system-wide extension
 * configuration under a site's settings is done by the shared {@see ApiSettingsResolver}; this factory
 * only maps the resolved values onto the typed {@see UpsConfiguration} value object, so every consuming
 * service stays free of both the settings source and the request.
 */
final readonly class UpsConfigurationFactory
{
    private const EXTENSION_KEY = 'products_shipping_ups';

    private const SETTINGS_PREFIX = 'products.shipping.ups.';

    private const FIELDS = [
        'environment',
        'clientId',
        'clientSecret',
        'accountNumber',
        'originPostCode',
        'originCountryCode',
        'originCity',
        'usedServices',
        'weightUnit',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver,
    ) {}

    public function forCurrentRequest(): UpsConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): UpsConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new UpsConfiguration(
            environment: UpsEnvironment::fromSetting($value['environment']),
            clientId: $value['clientId'],
            clientSecret: $value['clientSecret'],
            accountNumber: $value['accountNumber'],
            originPostCode: $value['originPostCode'],
            originCountryCode: strtoupper($value['originCountryCode']),
            originCity: $value['originCity'],
            weightUnit: strtoupper($value['weightUnit']) === 'LBS' ? 'LBS' : 'KGS',
            usedServices: $this->parseServices($value['usedServices']),
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }

    /**
     * @return string[]
     */
    private function parseServices(string $csv): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $csv)),
            static fn(string $service): bool => $service !== '',
        ));
    }
}
