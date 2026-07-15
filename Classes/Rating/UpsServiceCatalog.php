<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Rating;

/**
 * Maps a UPS service code to a human-readable label. UPS service codes mean different products depending
 * on the shipment's origin country, so this is an extension point: override the DI alias to ship a
 * catalog for a different origin.
 */
interface UpsServiceCatalog
{
    public function label(string $serviceCode): string;
}
