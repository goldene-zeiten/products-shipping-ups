<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Authentication;

use GoldeneZeiten\Products\Shipping\Ups\Authentication\UpsOAuthTokenProvider;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsAuthenticationException;
use GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\AbstractUpsMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;

final class UpsOAuthTokenProviderTest extends AbstractUpsMockTestCase
{
    private const TOKEN_PATH = '/shipping/ups/security/v1/oauth/token';

    #[Test]
    public function fetchesTheTokenOverHttpAndReusesTheCachedValue(): void
    {
        $subject = $this->subject();

        $this->assertSame('MOCK-ACCESS-TOKEN', $subject->getToken($this->configuration()));
        $this->assertSame('MOCK-ACCESS-TOKEN', $subject->getToken($this->configuration()));
        $this->assertSame(1, $this->recordedRequests(self::TOKEN_PATH), 'The token endpoint is called only once.');
    }

    #[Test]
    public function forceRefreshFetchesANewToken(): void
    {
        $subject = $this->subject();
        $subject->getToken($this->configuration());
        $subject->getToken($this->configuration(), true);

        $this->assertSame(2, $this->recordedRequests(self::TOKEN_PATH));
    }

    #[Test]
    public function sendsTheClientCredentialsGrantWithBasicAuth(): void
    {
        $this->subject()->getToken($this->configuration());

        $request = $this->loggedRequests(self::TOKEN_PATH)[0];
        $this->assertStringStartsWith('Basic ', $request['headers']['Authorization']);
        $this->assertSame('grant_type=client_credentials', $request['body']);
    }

    #[Test]
    public function anAuthFailureRaisesAnAuthenticationException(): void
    {
        $this->expectException(UpsAuthenticationException::class);
        $this->subject()->getToken($this->configuration('authfail'));
    }

    private function subject(): UpsOAuthTokenProvider
    {
        return new UpsOAuthTokenProvider(
            $this->httpClient(),
            $this->get(CacheManager::class)->getCache('products_shipping_ups_token'),
        );
    }
}
