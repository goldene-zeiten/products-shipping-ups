# Developing `products_shipping_ups`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **shipping provider** plugged into the core shop's carrier seam.

- `Shipping/UpsShippingProvider` implements the core `ShippingProviderInterface` (autoconfigured onto the
  `products.shipping_provider` tag) as a **non-fallback** provider with priority `100`, so it supersedes
  the built-in `TableRateShippingProvider`. Its `quote()` returns `[]` whenever it cannot serve the basket
  (unconfigured, UPS unreachable, error, or no rate), which is what lets the core registry fall back to the
  table-rate methods. `resolve()` re-quotes and matches the chosen service code.
- Everything the provider needs is resolved from the request-independent `UpsConfiguration` value object,
  so the provider itself is free of settings and request handling.

OAuth, HTTP transport and layered config resolution are **not** implemented in this package — they live in
the shared `goldene-zeiten/products-api-client` package and are only wired up here. That split is what lets
a future carrier/gateway package (a second courier, a payment provider) reuse the same OAuth-client-
credentials and config-layering code instead of reimplementing it.

## Configuration resolution

`Configuration/UpsConfigurationFactory` is the only place in this package that reads configuration, and
even it does not read either source directly: it delegates the layering (system-wide extension configuration
under a site's settings, non-empty site setting wins, empty inherits) to the shared
`GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`, and resolves the current site via the
shared `CurrentSiteResolver`. `forCurrentRequest()` resolves the site from the current request;
`forSite()` takes a site explicitly (used in tests). The factory's own job is just mapping the resolver's
flat `array<string, string>` onto the typed `UpsConfiguration` value object — parsing the environment enum,
uppercasing the country code, normalizing the weight unit, splitting the service allow-list CSV, and
trimming a trailing slash off `apiBaseUrl`.

`UpsConfiguration::baseUrl()` returns the `apiBaseUrl` override when set, otherwise the environment's real
host (`UpsEnvironment::baseUrl()`) — this is the seam the local mock and any proxy use.
`UpsConfiguration::isComplete()` gates rating on client id, client secret, origin postcode and origin
country all being non-empty; anything missing keeps the carrier silent so the table-rate fallback serves
the basket.

## OAuth and HTTP

There is no UPS-specific OAuth code left in this package. `Rating/HttpUpsRatingClient` is constructed with
the shared `GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider` (client-
credentials grant: HTTP Basic credentials, `grant_type=client_credentials` form body, token cached and
reused until shortly before it expires) and the shared `GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient`
for the actual request. `Configuration/Services.yaml` wires a dedicated provider instance,
`products_shipping_ups.oauth_token_provider`, with this extension's own `products_shipping_ups_token`
cache, so UPS tokens never share storage with another integration built on the same shared package. That
cache is defined in `Services.yaml` via a `CacheManager::getCache()` factory, because TYPO3 only exposes
`cache.*` DI services for its own built-in caches — a plain `@cache.products_shipping_ups_token` reference
fails to compile.

`OAuth2Credentials` (token URL + client id + client secret) is built per-call in
`HttpUpsRatingClient::credentials()` from the resolved `UpsConfiguration`, since the token endpoint's host
depends on the environment (and any `apiBaseUrl` override) just like the rating endpoint does.

## Rating

`Rating/HttpUpsRatingClient` (interface `UpsRatingClient`, aliased via `#[AsAlias]`) builds the request
with `Rating/UpsRateRequestBuilder`, dispatches `ModifyUpsRateRequestEvent`, and `POST`s to
`/api/rating/v2409/Shop` with a Bearer token obtained from the shared OAuth provider. Notable behaviour:

- Weight-only body with postal code + country is enough for a Shop quote; no dimensions are sent (an
  integrator adds them via the event).
- A single rated shipment comes back from UPS as an object, several as a list — both are handled.
- HTTP 400 ("no rate available for this shipment") is a **business empty result**, returned as `[]`, not an
  error. Only transport failures (wrapped from the shared package's `ApiTransportException`) and unexpected
  statuses raise `UpsRatingException`.
- A 401 triggers one retry with a freshly minted token (`$forceRefresh = true` on the shared provider).

The provider maps each `UpsRate` to a core `ShippingOption` (label from `UpsServiceCatalog`, cost via
`Money::fromDecimalString`), filters by the `usedServices` allow-list, and **skips any rate quoted in a
currency other than the basket's** so a foreign-currency amount is never presented as the shop's own. It
then dispatches `ModifyUpsShippingOptionsEvent`.

## Extension points

`Event/ModifyUpsRateRequestEvent`, `Event/ModifyUpsShippingOptionsEvent`, and the overridable
`Rating/UpsServiceCatalog` DI alias — documented for integrators, with worked examples, in
`Documentation/Developer/Index.rst`.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — one WireMock server
mocks every third-party API, so there is no per-API mock to build or publish. The UPS stubs live under
`Build/mocks/wiremock/mappings/shipping/ups/` (mirroring the namespace and the URL: `oauth/` for the token
endpoint, `rating/` for the Shop endpoint), with UPS's real OpenAPI specs pinned under
`Build/mocks/specs/shipping/ups/` as the reference. See `Build/mocks/README.md`.

`runTests.sh -s functional` starts one `wiremock/wiremock` container with those mappings mounted,
`waitFor`s port 8080 (so tests never race it), and passes `MOCK_BASE_URL` to the test container, which
reaches it by name on the shared network. The extension is pointed at it purely through the `apiBaseUrl`
override — `MOCK_BASE_URL/shipping/ups` — so no client change is needed. To run it by hand:

```shell
podman run --rm -p 8080:8080 -v "$PWD/Build/mocks/wiremock:/home/wiremock:ro" docker.io/wiremock/wiremock:3.10.0
```

Behaviour is **request-driven and stateful**: the rating stubs key on the destination country (`XX` → 400
no-rate, `YY` → 500, `ZZ` → transport fault, `SG` → single-object response, `RT` → 401-then-200 via a
WireMock Scenario) and the OAuth stub on the client id (`authfail` → 401). A test selects a case just by
varying the inputs it already controls.

## Testing

Every HTTP path is exercised against the real mock over HTTP — no client double.
`Tests/Functional/AbstractUpsMockTestCase` extends the shared package's
`GoldeneZeiten\Products\Testing\AbstractApiMockTestCase` (from `packages-dev/products_testing`), which
skips the whole suite when `MOCK_BASE_URL` is unset (a plain phpunit run) and otherwise resets the mock's
scenario state and request journal per test, and exposes `recordedRequests()` / `loggedRequests()` against
WireMock's admin API. `AbstractUpsMockTestCase` itself adds only what is UPS-specific: loading
`goldene-zeiten/products-api-client` and this extension, flushing the `products_shipping_ups_token` cache
per test, and a `configuration()` helper building an `UpsConfiguration` pointed at the mock.

- `Tests/Unit/Configuration/UpsConfigurationFactoryTest` — the config layering and parsing, against the
  shared `ApiSettingsResolver` and `CurrentSiteResolver`. Uses a PHPUnit mock of `ExtensionConfiguration`
  (**not** an anonymous subclass — `ExtensionConfiguration` is `readonly` in TYPO3 v14, so subclassing it is
  a fatal there).
- `Tests/Functional/Rating/HttpUpsRatingClientTest` — response mapping (list & single object), no-rate,
  401-retry (asserted through WireMock's request journal, via the shared
  `OAuth2ClientCredentialsProvider`), server error, transport fault, and the outgoing Shop request shape.
- `Tests/Functional/Shipping/UpsShippingProviderTest` — mapping, allow-list, currency guard, and the
  empty-result cases that yield to the fallback. Fakes the `UpsRatingClient` *interface* (not a client),
  which is legitimate isolation from HTTP.

There is no test left in this package for OAuth token acquisition itself (caching, force-refresh, Basic-
auth/grant request shape) — that behaviour now lives in, and is tested by,
`goldene-zeiten/products-api-client`'s own `Tests/Functional/Authentication/
OAuth2ClientCredentialsProviderTest`. This package's tests only prove it is wired up correctly (the 401
retry in `HttpUpsRatingClientTest`, and the dedicated token cache in `AbstractUpsMockTestCase::setUp()`).

## Planned: labels & tracking (phase 2)

Rating is synchronous and uses none of TYPO3's messaging stack. Labels and tracking are a separate,
backend-side phase:

- **Label generation** belongs on the TYPO3 message bus (Symfony Messenger) — dispatched asynchronously on
  order placement and consumed by `messenger:consume`, calling the UPS *Shipping* API out of the request
  cycle so it never blocks order confirmation.
- **Inbound tracking notifications** use TYPO3 **Reactions** (incoming webhooks), not the outgoing Webhooks
  system (which is fire-and-forget, TYPO3 → external, and returns no response).
