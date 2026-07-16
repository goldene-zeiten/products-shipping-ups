:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   EXT:products_core (``goldene-zeiten/products-core``), for the shop and its shipping-provider seam
*   A UPS developer account: OAuth client id, client secret, and your UPS account (shipper) number, from
    `developer.ups.com <https://developer.ups.com>`__

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-shipping-ups

This also pulls in ``goldene-zeiten/products-api-client``, the shared package this extension uses for
OAuth token handling, layered configuration resolution and the HTTP client — nothing further needs to be
required by hand.

Then activate the :guilabel:`Products UPS Shipping` site set (``goldene-zeiten/products-shipping-ups``) on
the site(s) that should offer UPS rates, and configure the credentials and origin address described under
:ref:`Configuration <configuration>`. The core shop's own :guilabel:`Shipping cost calculation enabled`
setting (EXT:products_core) must also be on, since this extension only adds a carrier to an already-enabled
shipping step — it does not turn shipping costs on by itself.

If UPS is left unconfigured, the site keeps working exactly as before: checkout simply falls back to
whatever table-rate shipping methods are already configured. See
:ref:`Relationship to table-rate shipping <introduction-table-rate-fallback>`.
