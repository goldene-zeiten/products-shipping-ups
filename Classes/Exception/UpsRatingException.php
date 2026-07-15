<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Exception;

/**
 * Thrown on a transport or unexpected API failure while requesting rates from UPS. A UPS "no rate
 * available for this shipment" answer is NOT an error - it is an empty result - so it never raises this.
 * The shipping provider catches it and offers no options, letting the table-rate fallback take over.
 */
final class UpsRatingException extends \RuntimeException {}
