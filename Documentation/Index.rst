..  _start:

=====================
Products UPS Shipping
=====================

:Extension key:
    products_shipping_ups

:Package name:
    goldene-zeiten/products-shipping-ups

:Version:
    |release|

:Language:
    en

:Author:
    Markus Hofmann

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

Live UPS shipping rates at checkout for the Products shop system.

----

Introduction
============

At checkout the customer sees real UPS services and prices, fetched live from the UPS Rating API through
the shop's existing shipping-provider seam. This first release covers **rate quotes only**; label printing
and tracking are planned as a separate, backend-side phase.

UPS supersedes the shop's built-in table-rate shipping whenever it returns options. When UPS is
unconfigured, unreachable, errors, or has no rate for a basket, the table-rate shipping methods take over
automatically, so checkout never dead-ends.

Installation
============

..  code-block:: bash

    composer require goldene-zeiten/products-shipping-ups

Add the :guilabel:`Products UPS Shipping` site set to your site. You need UPS developer OAuth credentials
(client id, client secret and your six-character account number) from
`developer.ups.com <https://developer.ups.com>`__, then configure them as described below.

Configuration
=============

Configuration is **layered**: system-wide defaults live in the extension configuration, and any of them
can be overridden per site. This lets one installation carry a global default while a multi-shop instance
runs a different sender or different credentials per site.

- **Extension configuration** — :guilabel:`Admin Tools > Settings > Extension Configuration >
  products_shipping_ups`. The system-wide defaults.
- **Site settings** — :guilabel:`Settings > Products > UPS`, keys ``products.shipping.ups.*``. A non-empty
  site setting overrides the extension-configuration default; an **empty** site setting inherits it.

..  confval:: environment
    :type: string (sandbox | production)
    :Default: sandbox

    Which UPS environment to call. ``sandbox`` uses the UPS Customer Integration Environment
    (``wwwcie.ups.com``); ``production`` uses ``onlinetools.ups.com``. Sandbox credentials only work against
    the sandbox environment. Site setting: ``products.shipping.ups.environment``.

..  confval:: clientId / clientSecret
    :type: string

    Your UPS OAuth 2.0 credentials. Keep the secret out of version control — store it in the extension
    configuration (Install Tool storage) or reference an environment variable from the site setting. Site
    settings: ``products.shipping.ups.clientId`` / ``products.shipping.ups.clientSecret``.

..  confval:: accountNumber
    :type: string

    Your six-character UPS account (shipper) number. Optional for published rates; required to receive
    negotiated rates. Site setting: ``products.shipping.ups.accountNumber``.

..  confval:: originPostCode / originCountryCode / originCity
    :type: string
    :Default: originCountryCode = DE

    The ship-from address. Postal code and country are enough to rate; the city is optional. Site settings:
    ``products.shipping.ups.originPostCode`` / ``originCountryCode`` / ``originCity``.

..  confval:: usedServices
    :type: string
    :Default: (empty)

    A comma-separated allow-list of UPS service codes to offer, e.g. ``11,65,07``. Leave empty to offer
    every service UPS returns. Site setting: ``products.shipping.ups.usedServices``.

..  confval:: weightUnit
    :type: string (KGS | LBS)
    :Default: KGS

    The unit the basket weight is sent to UPS in. Site setting: ``products.shipping.ups.weightUnit``.

..  confval:: apiBaseUrl
    :type: string
    :Default: (empty)

    Advanced. Sends the UPS calls to this host instead of the environment's real UPS host — for a proxy or
    a local mock server. Leave empty to use the environment default. Site setting:
    ``products.shipping.ups.apiBaseUrl``.

How rating behaves
------------------

At checkout the customer sees live UPS services and prices, chosen from one list together with any other
carrier's options. UPS is a real carrier, so it supersedes the manual table-rate shipping methods whenever
it returns at least one option for the basket. If UPS is unconfigured, unreachable, returns an error, or
has no rate for that shipment, it stays silent and the table-rate methods serve the basket instead — the
customer is never left without a shipping option.

Troubleshooting
---------------

If no UPS options appear:

- Check the client id, secret and account number, and that the ``environment`` matches the credentials
  (sandbox credentials only work against the sandbox environment).
- Check the origin postcode and country are set.
- Check the basket ships to a country and postcode UPS actually serves from that origin.

For editors
===========

At checkout, shipping options now include live UPS services (for example :guilabel:`UPS Standard` or
:guilabel:`UPS Saver`) with real prices and, where available, a delivery-time estimate. There is nothing to
maintain per record for UPS. The backend shipping-method records you already maintain keep working as the
fallback: they are shown when UPS has no rate for a basket.

Extension points
================

Integrators can adjust the UPS behaviour through the following public API.

..  confval:: ModifyUpsRateRequestEvent
    :type: PSR-14 event

    ``GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsRateRequestEvent`` is fired just before a rate
    request is sent to UPS. ``getPayload()`` / ``setPayload()`` expose the request array (the UPS
    ``RateRequest``); ``getContext()`` and ``getConfiguration()`` provide the basket context and resolved
    configuration. Use it to add package dimensions, split the basket into several packages, force a
    packaging type, or request negotiated rates.

    ..  code-block:: php

        #[AsEventListener]
        final class AddDimensionsListener
        {
            public function __invoke(ModifyUpsRateRequestEvent $event): void
            {
                $payload = $event->getPayload();
                $payload['RateRequest']['Shipment']['Package'][0]['Dimensions'] = [
                    'UnitOfMeasurement' => ['Code' => 'CM'],
                    'Length' => '30', 'Width' => '20', 'Height' => '10',
                ];
                $event->setPayload($payload);
            }
        }

..  confval:: ModifyUpsShippingOptionsEvent
    :type: PSR-14 event

    ``GoldeneZeiten\Products\Shipping\Ups\Event\ModifyUpsShippingOptionsEvent`` is fired after the UPS rates
    have been mapped to shipping options. ``getOptions()`` / ``setOptions()`` expose the
    ``ShippingOption[]`` before they reach the basket. Use it to drop, reorder, relabel or surcharge
    options.

..  confval:: UpsServiceCatalog
    :type: DI interface

    UPS service codes mean different products depending on the shipment's origin country. The default
    ``GoldeneZeiten\Products\Shipping\Ups\Rating\UpsServiceCatalog`` implementation
    (``GermanOriginServiceCatalog``) names the codes for a German/EU origin (07 Express, 08 Expedited, 11
    Standard, 54 Express Plus, 65 Saver, 96 Worldwide Express Freight). To ship from another origin,
    implement the interface and override the alias in your ``Services.yaml``:

    ..  code-block:: yaml

        GoldeneZeiten\Products\Shipping\Ups\Rating\UpsServiceCatalog:
          alias: Vendor\MySitePackage\Shipping\UsOriginServiceCatalog

Core's own ``ShippingOptionsCollectedEvent`` also applies, across every registered carrier.
