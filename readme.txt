=== Socommerce for WooCommerce ===
Contributors: socommerce
Tags: woocommerce, affiliate, attribution, creators, tracking
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 9.0
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Carries a creator's tracking code from the ad click to the order, for shops connected to Socommerce.

== Description ==

[Socommerce](https://socommerce.io) pays creators a commission on the sales their videos make. A shop connected to Socommerce gives each creator a landing URL for a product: the product's own address with `?ref=CODE` on the end. This plugin carries that code from the click to the order so the sale is credited to the right creator:

* **On landing**, the code is kept in WooCommerce's own cart session for about two days. No cookie of ours is involved.
* **With marketing consent**, reported by your consent banner through the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/), a first-party cookie (`socommerce_ref`, 30 days) keeps the code past the session. Without that consent no cookie is set, and the sale still counts through the session.
* **Behind a page cache**, a few lines of script on each page hand a code the server did not see to the plugin's own endpoint on your site, which keeps it the same way.
* **At checkout**, classic or block, the code is written on the order as the meta field `_socommerce_ref`. High-Performance Order Storage is supported.

The plugin also:

* answers a public, read-only status check at `/wp-json/socommerce/v1/status` with its version, your WooCommerce version, whether Order Attribution is on and which translation plugin is active, so your Socommerce Integration tab can say the plugin is active;
* trims the order notifications your shop sends Socommerce down to the order number, so your shoppers' names, addresses and emails never leave your shop;
* has WooCommerce notify Socommerce again when you add a note to a sample's order (an order Socommerce created to ship a creator a free sample), so the tracking you type reaches the creator within a minute whether you add it before or after marking the order Completed. The plugin itself still sends nothing.

= How this plugin relates to the Socommerce service =

This plugin is for shops that have connected to Socommerce. Connecting happens on [socommerce.io](https://socommerce.io), not in this plugin: there you approve a WooCommerce REST API key for Socommerce and WooCommerce creates webhooks that notify Socommerce of new and updated orders. Socommerce reads each notified order through the key and keeps only what commission needs (the order's id, status, dates, totals, lines and refunds, and the tracking code); it never stores the shopper's name, address, email, phone, IP address or browser.

**The plugin itself sends nothing to Socommerce.** It makes no outbound request. What leaves your shop is what WooCommerce's own webhooks send, and with this plugin installed those are reduced to the order number. The status endpoint above is read by Socommerce; it holds nothing secret and nothing about any person.

Socommerce's [terms of service](https://socommerce.io/terms) and [privacy policy](https://socommerce.io/privacy) apply to the service. The plugin stores nothing about the shopper beyond the tracking code described above, and registers no personal data exporter or eraser because it holds no personal data.

== Installation ==

1. Download the plugin zip from your Socommerce Integration tab, or from this directory.
2. In WordPress, go to Plugins → Add New → Upload Plugin, choose the zip and activate it.
3. Your Socommerce Integration tab will say "Plugin active" at Socommerce's next check of your shop, within six hours.

Nothing needs configuring. If your shop uses a consent banner that supports the WP Consent API (Complianz, Moove GDPR Cookie Compliance, CookieYes and others), the marketing cookie follows the shopper's choice automatically.

== Frequently Asked Questions ==

= Does the plugin work without a consent banner? =

Yes. The code lives in WooCommerce's cart session, which needs no consent. The 30-day cookie follows the WP Consent API: with an opt-in banner it is set only after the shopper accepts marketing cookies; with an opt-out banner until the shopper declines them; and, as the API itself rules, on a shop that has the API installed but no banner managing consent. A shop without the WP Consent API sets no cookie at all.

= Does it work with my page cache? =

Yes. A page that stored the code is marked not cacheable; a page served from cache carries a small script that hands the code to the plugin's endpoint instead.

= Does it work with the block checkout? =

Yes, with the classic (shortcode) checkout and the block checkout, and with High-Performance Order Storage.

= What does it send to Socommerce? =

Nothing by itself. See "How this plugin relates to the Socommerce service" above.

== Changelog ==

= 1.1.2 =
* Every file of the plugin refuses being opened directly, as the WordPress.org directory asks. Nothing a shop or shopper sees changes.

= 1.1.1 =
* The fallback script is built from plain strings, as the WordPress.org Plugin Check asks. It prints exactly what it did before; nothing a shop or shopper sees changes.

= 1.1.0 =
* A note added to a sample's order saves the order again, so WooCommerce delivers it to Socommerce and the creator sees the tracking at once — whether the note comes before or after marking the order Completed. WooCommerce's own notes, a by-hand status change's included, change nothing; the plugin still sends nothing itself.

= 1.0.2 =
* Activating or deactivating the plugin purges its status answer from LiteSpeed Cache and WP Rocket, so a 404 cached before installation does not outlive it.

= 1.0.1 =
* The status endpoint and the code endpoint tell page caches to leave their answers alone, so a shop whose cache keeps REST answers (LiteSpeed Cache does) shows a deactivation at once.

= 1.0.0 =
* First release: the code carried from landing to order on both checkouts, behind a page cache and after a declined banner; the status endpoint; trimmed notifications.
