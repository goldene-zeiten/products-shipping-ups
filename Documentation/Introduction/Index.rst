:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_shipping_ups plugs live UPS rates into the checkout of EXT:products_core, through the core
shop's shipping-provider seam. Instead of maintaining shipping-method records for UPS by hand, the
extension asks the UPS Rating API for real services and prices for the customer's actual basket and
delivery address.

..  contents:: Table of contents
    :local:

..  _introduction-what-it-provides:

What it provides
=================

The extension registers one carrier,
:php:`GoldeneZeiten\Products\Shipping\Ups\Shipping\UpsShippingProvider`, with the core shop's carrier
registry. At checkout it:

*   builds a UPS "Shop" rate request from the basket's weight and the customer's delivery country/postcode,
*   authenticates with UPS via OAuth 2.0 client-credentials (through the shared
    :php:`goldene-zeiten/products-api-client` package),
*   maps every service UPS returns to a shipping option with a real price and, where UPS provides one, a
    delivery-time estimate,
*   and lets the results be filtered to an allow-list of service codes.

This first release covers **rate quotes only**. Label printing and tracking are planned as a separate,
backend-side phase (asynchronous label generation on order placement, and inbound tracking updates via
TYPO3 Reactions) — see the package's ``DEVELOPERS.md`` for the current thinking.

..  _introduction-table-rate-fallback:

Relationship to table-rate shipping
=====================================

UPS is a real (non-fallback) carrier: it supersedes the shop's built-in table-rate shipping methods
whenever it returns at least one option for the basket. When UPS is unconfigured, unreachable, returns an
error, or simply has no rate for that shipment (for example a destination or goods combination it will not
serve), it returns no options at all, and the table-rate shipping methods already configured in the shop
serve the basket instead — checkout never dead-ends just because UPS could not be reached. See
:ref:`How rating behaves <configuration-how-rating-behaves>` for the exact rules, and
:ref:`Developer <developer>` for the interface this fallback relies on
(:php:`GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface`, documented in EXT:products_core).

..  _introduction-when-to-use:

When to use this extension
============================

Install it whenever a shop wants to offer real UPS services and prices instead of, or alongside, manually
maintained shipping-method records — and has (or can get) a UPS developer account. A shop with no UPS
account, or that only ever ships via its own fixed-price methods, has no need for it; the core shop's
table-rate shipping works standalone.
