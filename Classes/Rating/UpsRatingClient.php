<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Domain\Dto\UpsRate;

/**
 * Fetches live rates from UPS for a basket. Returns an empty list when UPS has no rate for the shipment;
 * raises {@see \GoldeneZeiten\Products\Shipping\Ups\Exception\UpsRatingException} only on a genuine
 * failure.
 */
interface UpsRatingClient
{
    /**
     * @return UpsRate[]
     */
    public function rate(ShippingContext $context, UpsConfiguration $configuration): array;
}
