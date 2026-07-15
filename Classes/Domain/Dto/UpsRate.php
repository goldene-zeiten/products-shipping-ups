<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Domain\Dto;

/**
 * A single rate as UPS returned it, before it is mapped onto the shop's shipping option. Kept separate
 * from {@see \GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption} so the rating client stays
 * concerned only with UPS, and the provider owns the translation into the shop's own model.
 */
final readonly class UpsRate
{
    public function __construct(
        public string $serviceCode,
        public string $amount,
        public string $currencyCode,
        public string $businessDaysInTransit = '',
    ) {}
}
