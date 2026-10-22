<?php
/**
 * Shared footing for the plugin's tests: a clean session, a clean request, recorded cookies.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Automattic\Jetpack\Constants;
use Socommerce\WooCommerce\Carrier;
use Socommerce\WooCommerce\Plugin;
use WP_UnitTestCase;

/**
 * Every test starts from a shopper who has arrived with nothing.
 */
abstract class Plugin_Test_Case extends WP_UnitTestCase {

	const CODE = 'sbaa64cyxe';

	/**
	 * The cookies the plugin wrote this test.
	 *
	 * @var Recording_Cookies
	 */
	protected $cookies;

	/**
	 * A fresh shopper: no code in the session, no cookies, no query string, no consent.
	 */
	public function set_up() {
		parent::set_up();
		// PHPUnit has already written to stdout, so headers are sent and WooCommerce cannot set its
		// session cookie; under WP_DEBUG it says so with a notice. The cookie is WooCommerce's to
		// prove, not ours: silence the notice the way WooCommerce's own tests do.
		Constants::set_constant( 'WP_DEBUG', false );
		$this->cookies                       = new Recording_Cookies();
		Plugin::instance()->carrier->cookies = $this->cookies;
		unset( $_GET['ref'], $_COOKIE['wp_consent_marketing'] );
		wp_set_current_user( 0 );
		// WooCommerce keeps one session handler for the whole process; a new shopper needs a new one.
		WC()->session = null;
		WC()->initialize_session();
	}

	/**
	 * Back to a plain request so the next test class starts clean.
	 */
	public function tear_down() {
		unset( $_GET['ref'], $_COOKIE['wp_consent_marketing'] );
		if ( WC()->session ) {
			WC()->session->set( Carrier::SESSION_KEY, null );
		}
		Constants::clear_constants();
		parent::tear_down();
	}

	/**
	 * The shop runs an opt-in banner and this shopper has accepted marketing cookies.
	 */
	protected function given_marketing_consent(): void {
		add_filter( 'wp_get_consent_type', array( $this, 'optin' ) );
		$_COOKIE['wp_consent_marketing'] = 'allow';
	}

	/**
	 * The shop runs an opt-in banner and this shopper has declined, or not answered yet.
	 */
	protected function given_no_marketing_consent(): void {
		add_filter( 'wp_get_consent_type', array( $this, 'optin' ) );
		$_COOKIE['wp_consent_marketing'] = 'deny';
	}

	/**
	 * The consent type an opt-in banner reports.
	 */
	public function optin(): string {
		return 'optin';
	}

	/**
	 * What the shopper's session holds as the code.
	 */
	protected function code_in_session(): ?string {
		$held = WC()->session->get( Carrier::SESSION_KEY );
		return is_string( $held ) ? $held : null;
	}

	/**
	 * One REST request, answered as WordPress would answer it.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route under /wp-json.
	 * @param array  $body   JSON body, for a POST.
	 */
	protected function rest( string $method, string $route, array $body = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}
}
