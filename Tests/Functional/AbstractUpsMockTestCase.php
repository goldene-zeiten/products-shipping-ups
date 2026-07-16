<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional;

use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Base for the UPS tests that exercise the real HTTP path against the shared WireMock mock. The generic
 * WireMock wiring - the MOCK_BASE_URL gate, per-test scenario/journal reset, request-journal helpers -
 * lives in {@see AbstractApiMockTestCase}; this class adds only what is UPS-specific: loading the
 * extension, flushing its token cache, and building a configuration pointed at the mock.
 */
abstract class AbstractUpsMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-shipping-ups',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(CacheManager::class)->getCache('products_shipping_ups_token')->flush();
    }

    /**
     * @param string[] $usedServices
     */
    protected function configuration(string $clientId = 'mock-client', array $usedServices = []): UpsConfiguration
    {
        return new UpsConfiguration(
            UpsEnvironment::Sandbox,
            $clientId,
            'secret',
            'ACC123',
            '80331',
            'DE',
            '',
            'KGS',
            $usedServices,
            $this->mockRoot . '/shipping/ups',
        );
    }
}
