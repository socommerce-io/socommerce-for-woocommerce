# Socommerce for WooCommerce

The WordPress plugin a WooCommerce brand installs once its shop is connected to
[Socommerce](https://socommerce.io). It carries a creator's tracking code from the ad click
to the order, so the sale is credited to the creator whose video made it: behind a page
cache, after a declined cookie banner, on the classic and the block checkout. It sends nothing
to Socommerce on its own and stores nothing about the shopper. `readme.txt` is the
WordPress.org description; `docs/adr/0028` in the Socommerce repository is the decision.

## What it does

| Entrance | What happens |
|---|---|
| `GET any-page?ref=CODE` (server) | The code is validated to a shape, kept in WooCommerce's cart session (about two days, no cookie of ours) and, only when the shop's banner reports marketing consent through the WP Consent API, in a first-party cookie of 30 days. The page is marked and made uncacheable. |
| Every page (script) | A few inline lines read `ref` from the address and, if the server's mark is absent (the page came from a cache), post it to the endpoint below. On a consent change to "allow marketing", they post again so the cookie is earned. |
| `POST /wp-json/socommerce/v1/ref` | Stores a well-formed code the way the landing does. Anything else is refused with 400. |
| Checkout, classic or block | The code (session first, then cookie) is written on the order as meta `_socommerce_ref`, through the order object, so HPOS holds. |
| `GET /wp-json/socommerce/v1/status` | `{version, woocommerce_version, order_attribution, translation_plugin}`. Public, read-only, never cached. Socommerce polls it; a 404 means the plugin is not active. |
| Webhook delivery to Socommerce | The payload is trimmed to `{"id": N}`. Deliveries to any other address are untouched. |
| A person's note on a sample's order | The order (one carrying Socommerce's `_socommerce_sample_request` meta) is saved again with a fresh modification date, so WooCommerce delivers its ordinary `order.updated` and Socommerce reads the note as the parcel's tracking at once. WooCommerce's own notes — a status change made by hand included, which carries the manager's name — change nothing. Any other order is untouched. |

## Developing

Everything runs in Docker through [wp-env](https://github.com/WordPress/gutenberg/tree/trunk/packages/env);
PHP and Composer are not needed on the host.

```sh
npm install                      # wp-env
npm run composer -- install      # PHPUnit, polyfills, WordPress coding standards (in a Composer container)
npm run env:start                # WordPress + WooCommerce + WP Consent API on :8888, a tests site on :8889
npm run test:php                 # the PHPUnit suite, on the tests site
npm run lint:php                 # phpcs with the WordPress standard
npm run zip                      # the release zip, from the committed tree
```

`test:php` and `lint:php` run in a fresh `tests-cli` container (`bin/in-tests-cli.sh`), not through
`wp-env run`: on Docker Desktop for macOS the long-lived container's nested plugin mounts go
stale when the host tree is rewritten under them. If the dev site at :8888 ever shows the plugin
as missing, recreate its containers:

```sh
docker compose -f "$(npx wp-env install-path)/docker-compose.yml" up -d --force-recreate --no-deps wordpress cli tests-wordpress tests-cli
```

## Releasing

Bump the version in three places, which a test keeps equal: the plugin header, the
`SOCOMMERCE_FOR_WOOCOMMERCE_VERSION` constant and `Stable tag` in `readme.txt`. Tag `vX.Y.Z` and
push the tag: the release workflow checks the three match, builds the zip from the tagged tree
(minus what `.gitattributes` marks `export-ignore`) and publishes it as a GitHub release asset,
which is what the Socommerce Integration tab links to.

## Licence

GPL-2.0-or-later.
