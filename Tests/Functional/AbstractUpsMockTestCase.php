<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional;

use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use Psr\Http\Client\ClientInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Base for the tests that exercise the real HTTP path against the shared WireMock mock. The mock's base
 * URL is provided by the functional runner (runTests.sh starts WireMock and passes MOCK_BASE_URL); a
 * plain phpunit run without it skips these tests rather than failing.
 *
 * Behaviour is selected purely through the request - the rating stubs key on the destination country,
 * the OAuth stub on the client id - so a test never has to reach into the client. See Build/mocks.
 */
abstract class AbstractUpsMockTestCase extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-ups',
    ];

    protected string $mockRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $mockBaseUrl = (string)getenv('MOCK_BASE_URL');
        if ($mockBaseUrl === '') {
            $this->markTestSkipped('MOCK_BASE_URL is not set; the WireMock mock is wired by runTests.sh -s functional.');
        }
        $this->mockRoot = $mockBaseUrl;
        $this->get(CacheManager::class)->getCache('products_shipping_ups_token')->flush();
        // The WireMock container is shared across the whole functional run, so reset its scenario state
        // and request journal per test - the request counts below assert token caching and retries.
        $this->send('POST', $mockBaseUrl . '/__admin/scenarios/reset');
        $this->send('DELETE', $mockBaseUrl . '/__admin/requests');
    }

    protected function httpClient(): ClientInterface
    {
        return $this->get(ClientInterface::class);
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

    /**
     * How many requests WireMock recorded for a path, from its journal - lets a test prove token caching
     * and retries without a request-counting client double.
     */
    protected function recordedRequests(string $urlPath): int
    {
        $response = $this->send('POST', $this->mockRoot . '/__admin/requests/count', ['method' => 'POST', 'urlPath' => $urlPath]);
        $data = json_decode((string)$response->getBody(), true);

        return (int)($data['count'] ?? 0);
    }

    /**
     * The requests WireMock recorded for a path, so a test can assert the outgoing payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loggedRequests(string $urlPath): array
    {
        $response = $this->send('POST', $this->mockRoot . '/__admin/requests/find', ['method' => 'POST', 'urlPath' => $urlPath]);
        $data = json_decode((string)$response->getBody(), true);

        return is_array($data['requests'] ?? null) ? $data['requests'] : [];
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function send(string $method, string $url, ?array $json = null): \Psr\Http\Message\ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        if ($json !== null) {
            $body->write(json_encode($json, JSON_THROW_ON_ERROR));
            $body->rewind();
        }

        return $this->httpClient()->sendRequest(new Request($url, $method, $body, ['Content-Type' => 'application/json']));
    }
}
