<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Configuration;

/**
 * Immutable, request-independent snapshot of the resolved UPS configuration for one site.
 *
 * Built by {@see UpsConfigurationFactory} by layering the extension configuration under a site's
 * settings, so every consuming service is free of both the settings source and the request.
 */
final readonly class UpsConfiguration
{
    /**
     * @param string[] $usedServices UPS service codes to offer; empty offers every service UPS returns
     */
    public function __construct(
        public UpsEnvironment $environment,
        public string $clientId,
        public string $clientSecret,
        public string $accountNumber,
        public string $originPostCode,
        public string $originCountryCode,
        public string $originCity,
        public string $weightUnit,
        public array $usedServices,
        public string $apiBaseUrl = '',
    ) {}

    /**
     * Base URL for the UPS API. Normally derived from the environment; an explicit override lets the
     * calls go through a proxy or a local mock without changing anything else.
     */
    public function baseUrl(): string
    {
        return $this->apiBaseUrl !== '' ? $this->apiBaseUrl : $this->environment->baseUrl();
    }

    /**
     * Rating is attempted only when the credentials and origin needed for a quote are present. Anything
     * missing keeps the carrier silent, so the table-rate fallback serves the basket instead.
     */
    public function isComplete(): bool
    {
        return $this->clientId !== ''
            && $this->clientSecret !== ''
            && $this->originPostCode !== ''
            && $this->originCountryCode !== '';
    }

    /**
     * Whether the given UPS service code should be offered under the configured allow-list.
     */
    public function offersService(string $serviceCode): bool
    {
        return $this->usedServices === [] || in_array($serviceCode, $this->usedServices, true);
    }
}
