:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

===================================
Developer / Extension Points
===================================

This extension has no backend module and no Extbase controllers of its own — its public surface is a small
set of PSR-14 events and one overridable DI alias, all fired or resolved from
:php:`GoldeneZeiten\Products\Shipping\Ups\Shipping\UpsShippingProvider` and
:php:`GoldeneZeiten\Products\Shipping\Ups\Rating\HttpUpsRatingClient`.

..  contents:: Table of contents
    :local:

..  _developer-modify-rate-request:

ModifyUpsRateRequestEvent
============================

:php:`GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsRateRequestEvent` is dispatched by
:php:`HttpUpsRatingClient` just before a rate request is sent to UPS. :php:`getPayload()` /
:php:`setPayload()` expose the request array — the associative array serialised to the UPS ``RateRequest``
JSON, as built by :php:`GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRateRequestBuilder`. :php:`getContext()`
returns the basket's :php:`ShippingContext` and :php:`getConfiguration()` the resolved
:php:`UpsConfiguration` — both read-only, for a listener that needs to decide *how* to adjust the payload.

Use it to add package dimensions (the builder sends a weight-only package, no dimensions), split the basket
into several packages, force a different packaging type, or add negotiated-rate parameters — anything the
shop's own configuration does not already cover.

Mutable: Yes (via :php:`setPayload(array $payload)`)

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/AddPackageDimensionsListener.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsRateRequestEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    final class AddPackageDimensionsListener
    {
        public function __invoke(ModifyUpsRateRequestEvent $event): void
        {
            $payload = $event->getPayload();
            $payload['RateRequest']['Shipment']['Package'][0]['Dimensions'] = [
                'UnitOfMeasurement' => ['Code' => 'CM'],
                'Length' => '30',
                'Width' => '20',
                'Height' => '10',
            ];
            $event->setPayload($payload);
        }
    }

..  _developer-modify-shipping-options:

ModifyUpsShippingOptionsEvent
=================================

:php:`GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsShippingOptionsEvent` is dispatched by
:php:`UpsShippingProvider` after the UPS rates have been mapped to core :php:`ShippingOption` objects (and
already filtered by the `products.shipping.ups.usedServices` allow-list and the currency guard), but before
they are returned from :php:`quote()`. :php:`getOptions()` / :php:`setOptions()` expose that
:php:`ShippingOption[]`; :php:`getContext()` and :php:`getConfiguration()` are the same read-only basket
context and resolved configuration :ref:`ModifyUpsRateRequestEvent <developer-modify-rate-request>` gets.

Use it to drop a service UPS itself does not restrict, reorder options, or relabel/surcharge one — anything
specific to UPS's own options, before they are pooled with every other carrier's. Core's own
:php:`ShippingOptionsCollectedEvent` (EXT:products_core) fires afterwards, once all carriers' options —
UPS's included — have been pooled into the one list the checkout actually shows; use that one instead for
adjustments that should apply across every carrier, not just UPS.

Mutable: Yes (via :php:`setOptions(ShippingOption[] $options)`)

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/DropExpressForHazmatListener.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsShippingOptionsEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    final class DropExpressForHazmatListener
    {
        public function __invoke(ModifyUpsShippingOptionsEvent $event): void
        {
            if (!in_array('hazmat', $event->getContext()->getShippingClasses(), true)) {
                return;
            }

            // UPS Express (service code 07) does not carry hazardous goods for this shop's account.
            $options = array_values(array_filter(
                $event->getOptions(),
                static fn($option): bool => $option->getOptionIdentifier() !== '07',
            ));
            $event->setOptions($options);
        }
    }

..  _developer-service-catalog:

UpsServiceCatalog
====================

:php:`GoldeneZeiten\Products\Shipping\Ups\Rating\UpsServiceCatalog` maps a UPS service code (e.g. ``11``)
to the human-readable label shown at checkout (e.g. "UPS Standard"). UPS service codes mean different
products depending on the shipment's *origin* country, so this is deliberately a DI alias, not a hardcoded
lookup: the shipped implementation,
:php:`GoldeneZeiten\Products\Shipping\Ups\Rating\GermanOriginServiceCatalog` (registered via
:php:`#[AsAlias(UpsServiceCatalog::class)]`), names the codes UPS returns for a German/EU origin —

*   ``07`` UPS Express
*   ``08`` UPS Expedited
*   ``11`` UPS Standard
*   ``54`` UPS Express Plus
*   ``65`` UPS Saver
*   ``96`` UPS Worldwide Express Freight

— and falls back to a generic ``UPS service <code>`` label for any code it does not know, so a service is
never dropped just because it is unnamed.

A shop shipping from a different origin (`products.shipping.ups.originCountryCode` set to something other
than ``DE``) sees the wrong names for its own account's services unless it supplies its own catalog. Do
this by implementing the interface and overriding the alias in your own ``Services.yaml``:

..  code-block:: php
    :caption: EXT:my_extension/Classes/Shipping/UsOriginServiceCatalog.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Shipping;

    use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsServiceCatalog;

    final class UsOriginServiceCatalog implements UpsServiceCatalog
    {
        private const LABELS = [
            '03' => 'UPS Ground',
            '02' => 'UPS 2nd Day Air',
            '01' => 'UPS Next Day Air',
        ];

        public function label(string $serviceCode): string
        {
            return self::LABELS[$serviceCode] ?? sprintf('UPS service %s', $serviceCode);
        }
    }

..  code-block:: yaml
    :caption: EXT:my_extension/Configuration/Services.yaml

    services:
      GoldeneZeiten\Products\Shipping\Ups\Rating\UpsServiceCatalog:
        alias: MyVendor\MyExtension\Shipping\UsOriginServiceCatalog

Symfony resolves the last-registered alias for an interface, so this override wins over the extension's own
``#[AsAlias]`` regardless of load order between the two extensions.
