<?php
/**
 * The public status endpoint.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /wp-json/socommerce/v1/status: the plugin's version, the WooCommerce version, whether
 * Order Attribution is on, which translation plugin is active. Read-only, no key, no secrets,
 * no shopper data. Socommerce polls it, so a brand's Integration tab can say the plugin is
 * active minutes after installation; WordPress's own 404 says it is not.
 */
class Status {

	/**
	 * Hooks the route.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * The route.
	 */
	public function register_route(): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'answer' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * The answer, never cached so a deactivation shows at once.
	 */
	public function answer(): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'version'             => SOCOMMERCE_FOR_WOOCOMMERCE_VERSION,
				'woocommerce_version' => (string) WC()->version,
				'order_attribution'   => $this->order_attribution_enabled(),
				'translation_plugin'  => $this->translation_plugin(),
			)
		);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Whether WooCommerce's Order Attribution feature is on, read live from its switch.
	 */
	private function order_attribution_enabled(): bool {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) && method_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class, 'feature_is_enabled' ) ) {
			return (bool) \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'order_attribution' );
		}
		return 'yes' === get_option( 'woocommerce_feature_order_attribution_enabled', 'no' );
	}

	/**
	 * Which translation plugin is active: `wpml`, `polylang` or `none`.
	 */
	private function translation_plugin(): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' ) ) {
			return 'wpml';
		}
		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}
		return 'none';
	}
}
