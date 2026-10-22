<?php
/**
 * The public status endpoint the Socommerce service polls.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

/**
 * GET /wp-json/socommerce/v1/status answers four facts and nothing else.
 */
class Test_Status extends Plugin_Test_Case {

	const ROUTE = '/socommerce/v1/status';

	/**
	 * The answer, as the service reads it.
	 */
	private function status(): array {
		$response = $this->rest( 'GET', self::ROUTE );
		$this->assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	/**
	 * The version is the one the plugin header, the constant and the readme all carry.
	 */
	public function test_the_version_is_the_plugins_version() {
		$status = $this->status();

		$header = get_plugin_data( SOCOMMERCE_FOR_WOOCOMMERCE_FILE, false, false );
		$readme = (string) file_get_contents( dirname( SOCOMMERCE_FOR_WOOCOMMERCE_FILE ) . '/readme.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of ours, read in a test.
		preg_match( '/^Stable tag:\s*(\S+)/m', $readme, $stable );

		$this->assertSame( SOCOMMERCE_FOR_WOOCOMMERCE_VERSION, $status['version'] );
		$this->assertSame( SOCOMMERCE_FOR_WOOCOMMERCE_VERSION, $header['Version'] );
		$this->assertSame( SOCOMMERCE_FOR_WOOCOMMERCE_VERSION, $stable[1] ?? null );
	}

	/**
	 * The WooCommerce version is the shop's.
	 */
	public function test_the_woocommerce_version_is_the_shops() {
		$this->assertSame( WC()->version, $this->status()['woocommerce_version'] );
	}

	/**
	 * Order Attribution's state is read live from the feature switch.
	 */
	public function test_order_attribution_follows_the_feature_switch() {
		update_option( 'woocommerce_feature_order_attribution_enabled', 'yes' );
		$this->assertTrue( $this->status()['order_attribution'] );

		update_option( 'woocommerce_feature_order_attribution_enabled', 'no' );
		$this->assertFalse( $this->status()['order_attribution'] );
	}

	/**
	 * This shop has no translation plugin.
	 */
	public function test_the_translation_plugin_is_none_here() {
		$this->assertSame( 'none', $this->status()['translation_plugin'] );
	}

	/**
	 * Four facts, no secrets, no shopper data; readable by anybody, never cached.
	 */
	public function test_the_answer_is_exactly_four_facts_and_not_cacheable() {
		wp_set_current_user( 0 );
		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( array( 'version', 'woocommerce_version', 'order_attribution', 'translation_plugin' ), array_keys( $response->get_data() ) );
		$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] ?? null );
	}
}
