<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Rating;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Default catalog for shipments originating in Germany (and the EU generally), covering the service
 * codes UPS returns for that origin. Unknown codes fall back to a generic label so a service is never
 * dropped just because it is not named here.
 */
#[AsAlias(UpsServiceCatalog::class)]
final class GermanOriginServiceCatalog implements UpsServiceCatalog
{
    private const LABELS = [
        '07' => 'UPS Express',
        '08' => 'UPS Expedited',
        '11' => 'UPS Standard',
        '54' => 'UPS Express Plus',
        '65' => 'UPS Saver',
        '96' => 'UPS Worldwide Express Freight',
    ];

    public function label(string $serviceCode): string
    {
        return self::LABELS[$serviceCode] ?? sprintf('UPS service %s', $serviceCode);
    }
}
