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

## Configuration resolution

`Configuration/UpsConfigurationFactory` is the only place that reads configuration. It layers the
`ExtensionConfiguration` (system-wide defaults) under the current site's settings: a non-empty site setting
overrides the extension default, an empty one inherits it. `forCurrentRequest()` resolves the site from
`$GLOBALS['TYPO3_REQUEST']`; `forSite()` takes a site explicitly (used in tests). `UpsConfiguration::baseUrl()`
returns the `apiBaseUrl` override when set, otherwise the environment's real host — this is the seam the
local mock and any proxy use.

## OAuth

`Authentication/UpsOAuthTokenProvider` performs the OAuth 2.0 client-credentials grant: `POST
/security/v1/oauth/token` with the credentials as an HTTP Basic header (not in the body) and
`grant_type=client_credentials` as a form body. Tokens are cached in the `products_shipping_ups_token`
cache and reused until shortly before they expire (renewed at 80% of `expires_in`, or on a 401 via the
`forceRefresh` path). The `expires_in` value is read dynamically — UPS shortened token lifetime from 4h to
1h in April 2026, so nothing about the duration is hardcoded.

The token cache is defined in `Configuration/Services.yaml` via a `CacheManager::getCache()` factory,
because TYPO3 only exposes `cache.*` DI services for its own built-in caches — a plain
`@cache.products_shipping_ups_token` reference fails to compile.

## Rating

`Rating/HttpUpsRatingClient` (interface `UpsRatingClient`) builds the request with
`Rating/UpsRateRequestBuilder`, dispatches `ModifyUpsRateRequestEvent`, and `POST`s to
`/api/rating/v2409/Shop` with a Bearer token. Notable behaviour:

- Weight-only body with postal code + country is enough for a Shop quote; no dimensions are sent (an
  integrator adds them via the event).
- A single rated shipment comes back from UPS as an object, several as a list — both are handled.
- HTTP 400 ("no rate available for this shipment") is a **business empty result**, returned as `[]`, not an
  error. Only transport failures and unexpected statuses raise `UpsRatingException`.
- A 401 triggers one retry with a freshly minted token.

The provider maps each `UpsRate` to a core `ShippingOption` (label from `UpsServiceCatalog`, cost via
`Money::fromDecimalString`), filters by the `usedServices` allow-list, and **skips any rate quoted in a
currency other than the basket's** so a foreign-currency amount is never presented as the shop's own. It
then dispatches `ModifyUpsShippingOptionsEvent`.

## Extension points

`Event/ModifyUpsRateRequestEvent`, `Event/ModifyUpsShippingOptionsEvent`, and the overridable
`Rating/UpsServiceCatalog` DI alias — documented for integrators in `Documentation/`.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — one WireMock server
mocks every third-party API, so there is no per-API mock to build or publish. The UPS stubs live under
`Build/mocks/wiremock/mappings/shipping/ups/` (mirroring the namespace and the URL), with UPS's real
OpenAPI specs pinned under `Build/mocks/specs/shipping/ups/` as the reference. See `Build/mocks/README.md`.

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
`AbstractUpsMockTestCase` skips when `MOCK_BASE_URL` is unset (a plain phpunit run), and otherwise resets
the mock's scenario state and request journal per test.

- `Tests/Unit/Configuration/UpsConfigurationFactoryTest` — the config layering and parsing. Uses a PHPUnit
  mock of `ExtensionConfiguration` (**not** an anonymous subclass — `ExtensionConfiguration` is `readonly`
  in TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Authentication/UpsOAuthTokenProviderTest` — token fetch, caching and force-refresh
  (asserted through WireMock's request journal), Basic-auth/grant request shape, and the auth failure.
- `Tests/Functional/Rating/HttpUpsRatingClientTest` — response mapping (list & single object), no-rate,
  401-retry, server error, transport fault, and the outgoing Shop request shape.
- `Tests/Functional/Shipping/UpsShippingProviderTest` — mapping, allow-list, currency guard, and the
  empty-result cases that yield to the fallback. Fakes the `UpsRatingClient` *interface* (not a client),
  which is legitimate isolation from HTTP.

## Planned: labels & tracking (phase 2)

Rating is synchronous and uses none of TYPO3's messaging stack. Labels and tracking are a separate,
backend-side phase:

- **Label generation** belongs on the TYPO3 message bus (Symfony Messenger) — dispatched asynchronously on
  order placement and consumed by `messenger:consume`, calling the UPS *Shipping* API out of the request
  cycle so it never blocks order confirmation.
- **Inbound tracking notifications** use TYPO3 **Reactions** (incoming webhooks), not the outgoing Webhooks
  system (which is fire-and-forget, TYPO3 → external, and returns no response).
