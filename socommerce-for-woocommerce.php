<?php
/**
 * Plugin Name:       Socommerce for WooCommerce
 * Plugin URI:        https://github.com/socommerce-io/socommerce-for-woocommerce
 * Description:       Carries a creator's tracking code from the ad click to the order, so a shop connected to Socommerce attributes its sales reliably: behind a page cache, after a declined cookie banner, on the classic and the block checkout. Sends nothing to Socommerce on its own and stores nothing about the shopper.
 * Version:           1.0.1
 * Author:            Socommerce
 * Author URI:        https://socommerce.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       socommerce-for-woocommerce
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.0
 * WC tested up to:   11.2
 *
 * @package Socommerce\WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'SOCOMMERCE_FOR_WOOCOMMERCE_VERSION', '1.0.1' );
define( 'SOCOMMERCE_FOR_WOOCOMMERCE_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-code.php';
require_once __DIR__ . '/includes/class-cookies.php';
require_once __DIR__ . '/includes/class-carrier.php';
require_once __DIR__ . '/includes/class-landing.php';
require_once __DIR__ . '/includes/class-checkout.php';
require_once __DIR__ . '/includes/class-status.php';
require_once __DIR__ . '/includes/class-notifications.php';
require_once __DIR__ . '/includes/class-plugin.php';

// Orders are read and written through WooCommerce's order object only, so High-Performance
// Order Storage and the block checkout both hold; say so, or WooCommerce warns the shop.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

// The WP Consent API asks a plugin that sets cookies to say it follows the API; this one does.
add_filter( 'wp_consent_api_registered_' . plugin_basename( __FILE__ ), '__return_true' );

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Socommerce for WooCommerce needs WooCommerce 9.0 or newer to be installed and active.', 'socommerce-for-woocommerce' ) . '</p></div>';
				}
			);
			return;
		}
		\Socommerce\WooCommerce\Plugin::boot( __FILE__ );
	}
);
