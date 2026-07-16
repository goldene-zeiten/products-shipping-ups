:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: what changes at checkout once UPS is configured. See
:ref:`Configuration <configuration>` for the technical settings.

..  contents:: Table of contents
    :local:

..  _users-manual-checkout:

UPS options at checkout
==========================

Once configured, the checkout's shipping-method step includes live UPS services alongside (and, whenever
UPS can serve the basket, ahead of) the shop's own table-rate shipping methods — for example
:guilabel:`UPS Standard` or :guilabel:`UPS Saver`, each with a real, live price and, where UPS provides one,
a delivery-time estimate (e.g. "2 business day(s)"). Which UPS service names are offered, and what they are
labelled, depends on the shipment's origin country — see
:ref:`UPS service catalog <developer-service-catalog>`.

There is nothing to maintain per record for UPS itself: no shipping-method records, no manual price list.
The backend shipping-method records already maintained for the shop (storage folder record list, per
EXT:products_core's own "Shipping costs" chapter) keep working exactly as before — they simply become the
automatic fallback whenever UPS has nothing to offer for a given basket. See
:ref:`How rating behaves <configuration-how-rating-behaves>`.

..  _users-manual-fallback:

When UPS is not shown
========================

A customer sees no UPS options, and only the table-rate methods, whenever UPS is unconfigured, unreachable,
returns an error, or genuinely has no rate for that shipment (an unsupported destination, for instance).
This is by design: an order nobody can ship must not become unpayable just because one carrier had a
problem. Nothing about this needs day-to-day editor attention — it self-heals the moment UPS is reachable
again — but see :ref:`Troubleshooting <configuration-troubleshooting>` if UPS options never appear at all.

..  _users-manual-order-storage:

What shows up on an order
============================

When a customer chooses a UPS option, the order records it the same way any other carrier's choice is
recorded in EXT:products_core: a provider identifier (``ups``), the chosen UPS service code as the option
identifier, and the human-readable label shown at checkout — so the backend order module and order history
keep showing the correct shipping method even if the extension is later uninstalled.
