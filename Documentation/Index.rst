..  include:: /Includes.rst.txt

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

:Rendered:
   |today|

----

Live UPS shipping rates at checkout for the Products shop system: a real carrier, quoted from the UPS
Rating API, plugged into the shop's shipping-provider seam alongside (and, when it can serve the basket,
in front of) the built-in table-rate shipping.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension provides, and how it relates to the shop's built-in table-rate shipping.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        UPS credentials, origin address and rating options — extension configuration and site settings.

    ..  card:: :ref:`Users Manual <users-manual>`

        What changes for shoppers and editors at checkout.

    ..  card:: :ref:`Developer <developer>`

        Extension points: PSR-14 events and the overridable service-catalog alias.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
