<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;

/**
 * Fired after the UPS rates have been mapped to shipping options, so an integrator can adjust, filter or
 * relabel them before they reach the basket - drop a service, add a handling surcharge to the label,
 * translate a service name, reorder them.
 */
final class ModifyUpsShippingOptionsEvent
{
    /**
     * @param ShippingOption[] $options
     */
    public function __construct(
        private array $options,
        private readonly ShippingContext $context,
        private readonly UpsConfiguration $configuration,
    ) {}

    /**
     * @return ShippingOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param ShippingOption[] $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
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
