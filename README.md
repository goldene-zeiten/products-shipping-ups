# TYPO3 extension `products_shipping_ups`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Live UPS shipping rates for the
[Products](https://github.com/goldene-zeiten/products-core) shop system. At checkout the customer
sees real UPS services and prices, fetched from the UPS Rating API through the shop's existing
shipping-provider seam. When UPS is unreachable or has no rate for a basket, the shop's built-in
table-rate shipping automatically takes over, so checkout never dead-ends.

This first release covers **rate quotes only**. Label printing and tracking are planned as a
separate, backend-side phase.

## Installation

```shell
composer require goldene-zeiten/products-shipping-ups
```

Add the "Products UPS Shipping" site set to your site, then configure your UPS OAuth credentials and
origin address (see the documentation).

## Documentation

- **Integrators / editors:** see `Documentation/` (rendered on the TYPO3 documentation site) —
  installation, configuration, the table-rate fallback, and the public extension points.
- **Contributors:** see [DEVELOPERS.md](DEVELOPERS.md) for the architecture, the OAuth/rating
  internals, the dockerized UPS mock server, and how the tests are structured.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`
- UPS developer OAuth credentials (client id, client secret, account number)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
