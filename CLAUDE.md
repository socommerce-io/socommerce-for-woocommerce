# Socommerce for WooCommerce

A WordPress plugin, PHP 7.4+, WooCommerce 9.0+, GPL-2.0-or-later. Read `README.md` for what
each entrance does and `readme.txt` for what the brand is told. The design is ADR-0028 in the
Socommerce repository (`docs/adr/0028-a-brand-connects-a-woocommerce-shop-through-a-key-and-a-plugin.md`);
the ticket was WOO-06 in ClickUp's `WooCommerce` list.

## Contracts shared with the Socommerce service

Changing any of these means changing `data-ingestion` too.

- **Status endpoint**: `GET /wp-json/socommerce/v1/status` answers `{"version": "…", …}`;
  the service reads `version` and treats WordPress's 404 as "not active".
- **Order meta key**: `_socommerce_ref`, read by the service as match method `woo_meta_code`.
- **Tracking code shape**: `^[a-z0-9]{6,32}$` (`Code::SHAPE`), the service's own constraint.
- **Trimmed delivery**: `{"id": N}` to any host matching `Notifications::MODAL_HOST`
  (`<workspace>--socommerce-ingestion-woocommerce-webhook[.region].modal.run`) or under
  `socommerce.io`.

## Rules

- The plugin phones nothing home and stores nothing about the person. No outbound request,
  no option holding shopper data, no personal data exporter because there is none to export.
- A cookie of ours is set only when `wp_has_consent( 'marketing' )` says so. Without the WP
  Consent API there is no cookie.
- Orders are touched through the order object only (`update_meta_data`), never `postmeta`,
  so High-Performance Order Storage holds.
- The version lives in three places kept equal by `Test_Status`: the plugin header, the
  constant, `readme.txt`'s `Stable tag`.
- WordPress coding standards (`.phpcs.xml.dist`), with every `phpcs:ignore` carrying its reason.

## Modules (`includes/`)

`Code` (the shape) · `Cookies` (the one place cookies are read or sent; tests swap in a
recording jar) · `Carrier` (session + consent-gated cookie; `remember`, `recall`) · `Landing`
(the `?ref=` landing, the page marker, the inline script, the `/ref` endpoint) · `Checkout`
(order meta on both checkouts) · `Status` (the status endpoint) · `Notifications` (the
trimmed payload) · `Plugin` (wiring, one instance).

## Testing

PHPUnit on wp-env with WooCommerce and the WP Consent API loaded (`tests/bootstrap.php`).
Tests exercise entrances, not internals: `do_action( 'template_redirect' )` after `go_to()`,
`rest_do_request`, `WC()->checkout()->create_order`, `WC_Webhook::build_payload`. Each test
starts with a fresh WooCommerce session handler and a `Recording_Cookies` jar
(`Plugin_Test_Case`). Run `npm run test:php` (full suite, well under a second once the
container is up) and `npm run lint:php`; CI runs both on every push.

What the suite cannot prove and the test shop must (runbook `docs/runbooks/woocommerce-test-shop.md`
in the Socommerce repository): the page served from cache on the second visit, a real banner
declined and accepted, the 404 before activation, HPOS showing no warning, `translation_plugin`
answering `polylang` or `wpml`.
