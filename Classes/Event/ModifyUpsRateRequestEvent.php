<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;

/**
 * Fired just before a rate request is sent to UPS, so an integrator can adjust the outgoing payload -
 * add package dimensions, split the basket into several packages, force a packaging type, request
 * negotiated rates, and so on. The payload is the associative array serialised to the UPS RateRequest
 * JSON.
 */
final class ModifyUpsRateRequestEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array $payload,
        private readonly ShippingContext $context,
        private readonly UpsConfiguration $configuration,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getContext(): ShippingContext
    {
        return $this->context;
    }

    public function getConfiguration(): UpsConfiguration
    {
        return $this->configuration;
    }
}
