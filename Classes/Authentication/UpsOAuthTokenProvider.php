<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Authentication;

use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsAuthenticationException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Obtains and caches UPS OAuth 2.0 bearer tokens via the client-credentials grant.
 *
 * Tokens are reused across requests until shortly before they expire, keyed by environment and client
 * id so a multi-shop instance never mixes tokens. A caller that hits a 401 (a token UPS revoked early)
 * asks again with $forceRefresh to bypass the cache once.
 */
final class UpsOAuthTokenProvider
{
    private const TOKEN_PATH = '/security/v1/oauth/token';

    /**
     * Renew a little before the real expiry so an in-flight rate request never races the cut-off.
     */
    private const REFRESH_SAFETY_FACTOR = 0.8;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly FrontendInterface $tokenCache,
    ) {}

    public function getToken(UpsConfiguration $configuration, bool $forceRefresh = false): string
    {
        $cacheIdentifier = $this->cacheIdentifier($configuration);
        if (!$forceRefresh) {
            $cached = $this->tokenCache->get($cacheIdentifier);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        return $this->requestAndCacheToken($configuration, $cacheIdentifier);
    }

    private function requestAndCacheToken(UpsConfiguration $configuration, string $cacheIdentifier): string
    {
        $data = $this->requestToken($configuration);
        $token = (string)($data['access_token'] ?? '');
        if ($token === '') {
            throw new UpsAuthenticationException('UPS returned no access token.', 1752580000);
        }

        $lifetime = max(60, (int)((int)($data['expires_in'] ?? 3600) * self::REFRESH_SAFETY_FACTOR));
        $this->tokenCache->set($cacheIdentifier, $token, [], $lifetime);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestToken(UpsConfiguration $configuration): array
    {
        $response = $this->send($configuration);
        if ($response->getStatusCode() !== 200) {
            throw new UpsAuthenticationException(
                sprintf('UPS token endpoint returned HTTP %d.', $response->getStatusCode()),
                1752580001,
            );
        }

        $data = json_decode((string)$response->getBody(), true);
        if (!is_array($data)) {
            throw new UpsAuthenticationException('UPS token response was not valid JSON.', 1752580002);
        }

        return $data;
    }

    private function send(UpsConfiguration $configuration): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write('grant_type=client_credentials');
        $body->rewind();
        $request = new Request(
            $configuration->baseUrl() . self::TOKEN_PATH,
            'POST',
            $body,
            [
                'Authorization' => 'Basic ' . base64_encode($configuration->clientId . ':' . $configuration->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
        );

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new UpsAuthenticationException('UPS token request failed at transport level.', 1752580003, $exception);
        }
    }

    private function cacheIdentifier(UpsConfiguration $configuration): string
    {
        return 'token_' . md5($configuration->environment->value . '|' . $configuration->clientId);
    }
}
