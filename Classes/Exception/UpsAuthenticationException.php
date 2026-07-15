<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Exception;

/**
 * Thrown when an OAuth access token cannot be obtained from UPS. The shipping provider catches it and
 * offers no options, so the table-rate fallback serves the basket instead of the checkout breaking.
 */
final class UpsAuthenticationException extends \RuntimeException {}
