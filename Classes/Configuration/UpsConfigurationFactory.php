<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Resolves the effective UPS configuration by layering the system-wide extension configuration under a
 * site's settings: a non-empty site setting overrides the extension-configuration default, an empty one
 * inherits it. This lets a single installation carry a global default while a multi-shop instance runs a
 * different sender or credentials per site.
 *
 * It is the only place that reads either source, keeping every consuming service request- and
 * settings-agnostic.
 */
final readonly class UpsConfigurationFactory
{
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
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function forCurrentRequest(): UpsConfiguration
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $site = $request instanceof ServerRequestInterface ? $request->getAttribute('site') : null;

        return $this->forSite($site instanceof Site ? $site : null);
    }

    public function forSite(?Site $site): UpsConfiguration
    {
        $defaults = $this->extensionDefaults();
        $overrides = $this->siteOverrides($site);
        $value = static fn(string $key): string => ($overrides[$key] ?? '') !== ''
            ? $overrides[$key]
            : ($defaults[$key] ?? '');

        return new UpsConfiguration(
            environment: UpsEnvironment::fromSetting($value('environment')),
            clientId: $value('clientId'),
            clientSecret: $value('clientSecret'),
            accountNumber: $value('accountNumber'),
            originPostCode: $value('originPostCode'),
            originCountryCode: strtoupper($value('originCountryCode')),
            originCity: $value('originCity'),
            weightUnit: strtoupper($value('weightUnit')) === 'LBS' ? 'LBS' : 'KGS',
            usedServices: $this->parseServices($value('usedServices')),
            apiBaseUrl: rtrim($value('apiBaseUrl'), '/'),
        );
    }

    /**
     * @return array<string, string>
     */
    private function extensionDefaults(): array
    {
        try {
            $config = $this->extensionConfiguration->get('products_shipping_ups');
        } catch (\Throwable) {
            return [];
        }

        return is_array($config)
            ? array_map(static fn(mixed $value): string => trim((string)$value), $config)
            : [];
    }

    /**
     * @return array<string, string>
     */
    private function siteOverrides(?Site $site): array
    {
        if ($site === null) {
            return [];
        }

        $settings = $site->getSettings();
        $overrides = [];
        foreach (self::FIELDS as $field) {
            $overrides[$field] = trim((string)$settings->get('products.shipping.ups.' . $field, ''));
        }

        return $overrides;
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
