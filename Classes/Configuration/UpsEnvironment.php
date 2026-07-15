<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Configuration;

/**
 * The UPS environment the API calls go to. Sandbox is the UPS Customer Integration Environment (CIE).
 */
enum UpsEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public static function fromSetting(string $value): self
    {
        return self::tryFrom($value) ?? self::Sandbox;
    }

    /**
     * Base URL for both the OAuth token endpoint and the Rating API in this environment.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Sandbox => 'https://wwwcie.ups.com',
            self::Production => 'https://onlinetools.ups.com',
        };
    }
}
