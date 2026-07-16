:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-layering:

Extension configuration and site settings
=============================================

Configuration is **layered**, resolved by the shared
:php:`GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`: system-wide defaults live in the
extension configuration, and any of them can be overridden per site. A non-empty site setting overrides the
extension-configuration default; an **empty** site setting inherits it. This lets one installation carry a
global default while a multi-shop instance runs different credentials, or a different origin address, per
site.

*   **Extension configuration** — :guilabel:`Admin Tools > Settings > Extension Configuration >
    products_shipping_ups`. The system-wide defaults.
*   **Site settings** — activate the :guilabel:`Products UPS Shipping` site set, then adjust its settings
    under :guilabel:`Site Management > Sites > Edit settings`, category :guilabel:`Shipping & Handling >
    UPS`. The keys are ``products.shipping.ups.*``.

:php:`GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfigurationFactory` is the only place that
reads either source; it builds the immutable
:php:`GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration` value object every other class in
the extension consumes, so no service resolves settings or the request itself.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.shipping.ups.environment
        :type: string (sandbox | production)
        :Default: sandbox

        Which UPS environment to call. ``sandbox`` uses the UPS Customer Integration Environment
        (``wwwcie.ups.com``); ``production`` uses ``onlinetools.ups.com``. Sandbox credentials only work
        against the sandbox environment, and vice versa.

    ..  confval:: products.shipping.ups.clientId
        :type: string
        :Default: (empty)

        Your UPS OAuth 2.0 client id, from a UPS developer app at
        `developer.ups.com <https://developer.ups.com>`__.

    ..  confval:: products.shipping.ups.clientSecret
        :type: string
        :Default: (empty)

        Your UPS OAuth 2.0 client secret. Keep it out of version control — store it in the extension
        configuration (Install Tool storage) or reference an environment variable from the site setting.

    ..  confval:: products.shipping.ups.accountNumber
        :type: string
        :Default: (empty)

        Your six-character UPS account (shipper) number. Optional for published rates; required to receive
        negotiated rates.

    ..  confval:: products.shipping.ups.originPostCode
        :type: string
        :Default: (empty)

        Postal code the shipment is sent from. Together with `products.shipping.ups.originCountryCode`,
        this is required for
        :php:`GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration::isComplete()` to hold —
        without it, the carrier stays silent and the table-rate fallback serves every basket.

    ..  confval:: products.shipping.ups.originCountryCode
        :type: string
        :Default: DE

        ISO 3166-1 alpha-2 country the shipment is sent from.

    ..  confval:: products.shipping.ups.originCity
        :type: string
        :Default: (empty)

        City the shipment is sent from. Optional — postal code and country are enough for UPS to rate a
        shipment.

    ..  confval:: products.shipping.ups.usedServices
        :type: string
        :Default: (empty)

        A comma-separated allow-list of UPS service codes to offer, e.g. ``11,65,07``. Leave empty to offer
        every service UPS returns. See
        :php:`GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration::offersService()`.

    ..  confval:: products.shipping.ups.weightUnit
        :type: string (KGS | LBS)
        :Default: KGS

        The unit the basket weight is sent to UPS in — kilograms or pounds. Any other value falls back to
        ``KGS``.

    ..  confval:: products.shipping.ups.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Sends the UPS calls (both the OAuth token endpoint and the Rating API) to this host
        instead of the environment's real UPS host — for a proxy, or a local mock server used in tests.
        Leave empty to use the environment default
        (:php:`GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment::baseUrl()`).

..  _configuration-how-rating-behaves:

How rating behaves
====================

At checkout the customer sees live UPS services and prices, pooled into one list together with any other
carrier's options (including the shop's own table-rate methods). UPS is a real carrier, so it supersedes
the table-rate methods whenever it returns at least one option for the basket. If UPS is unconfigured (any
of client id, client secret, origin postcode or origin country is empty), unreachable, returns an error, or
has no rate for that shipment, it offers nothing and the table-rate methods serve the basket instead — the
customer is never left without a shipping option. See
:ref:`Relationship to table-rate shipping <introduction-table-rate-fallback>`.

..  _configuration-troubleshooting:

Troubleshooting
==================

If no UPS options appear at checkout:

*   Check `products.shipping.ups.clientId`, `products.shipping.ups.clientSecret` and
    `products.shipping.ups.accountNumber`, and that `products.shipping.ups.environment` matches the
    credentials (sandbox credentials only work against the sandbox environment).
*   Check `products.shipping.ups.originPostCode` and `products.shipping.ups.originCountryCode` are set —
    without both, the configuration is incomplete and the carrier never calls UPS at all.
*   Check the basket ships to a country and postcode UPS actually serves from that origin.
*   Check the core shop's own shipping-cost setting (EXT:products_core) is enabled — without it, no
    carrier, UPS included, is asked for options.

The extension logs an error whenever a UPS call genuinely fails (unreachable, transport fault, unexpected
response), so a site's PHP log is the next place to look if the above does not explain it. An incomplete
configuration or a UPS "no rate for this shipment" answer logs nothing beyond an info-level note — both are
an expected empty result, not a failure.
