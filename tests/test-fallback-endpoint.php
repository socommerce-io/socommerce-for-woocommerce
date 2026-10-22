<?php
/**
 * A cached page's script hands the code to the plugin's own endpoint.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Socommerce\WooCommerce\Carrier;

/**
 * POST /wp-json/socommerce/v1/ref stores a well-formed code the way the landing does, and nothing else.
 */
class Test_Fallback_Endpoint extends Plugin_Test_Case {

	const ROUTE = '/socommerce/v1/ref';

	/**
	 * A well-formed code is stored in the cart session and the request answered with nothing.
	 */
	public function test_a_code_is_stored_in_the_cart_session() {
		$response = $this->rest( 'POST', self::ROUTE, array( 'ref' => self::CODE ) );

		$this->assertSame( 204, $response->get_status() );
		$this->assertSame( self::CODE, $this->code_in_session() );
	}

	/**
	 * The endpoint runs as a REST request, where WooCommerce has started no session yet.
	 */
	public function test_a_session_is_started_when_the_request_has_none() {
		WC()->session = null;

		$response = $this->rest( 'POST', self::ROUTE, array( 'ref' => self::CODE ) );

		$this->assertSame( 204, $response->get_status() );
		$this->assertNotNull( WC()->session );
		$this->assertSame( self::CODE, $this->code_in_session() );
	}

	/**
	 * The cookie follows the same rule as on landing: only with marketing consent.
	 */
	public function test_the_cookie_follows_consent() {
		$this->given_no_marketing_consent();
		$this->rest( 'POST', self::ROUTE, array( 'ref' => self::CODE ) );
		$this->assertSame( array(), $this->cookies->written );

		$this->given_marketing_consent();
		$this->rest( 'POST', self::ROUTE, array( 'ref' => self::CODE ) );
		$this->assertSame( self::CODE, $this->cookies->written[ Carrier::COOKIE ]['value'] );
	}

	/**
	 * Anything that is not a code is refused and leaves no trace.
	 */
	public function test_a_malformed_code_is_refused() {
		$this->given_marketing_consent();

		$response = $this->rest( 'POST', self::ROUTE, array( 'ref' => 'not a code!' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertNull( $this->code_in_session() );
		$this->assertSame( array(), $this->cookies->written );
	}

	/**
	 * A request naming no code is refused too.
	 */
	public function test_a_missing_code_is_refused() {
		$response = $this->rest( 'POST', self::ROUTE, array( 'other' => self::CODE ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertNull( $this->code_in_session() );
	}

	/**
	 * The endpoint is write-only: there is nothing to read from it.
	 */
	public function test_the_endpoint_answers_nothing_to_a_read() {
		$response = $this->rest( 'GET', self::ROUTE );

		$this->assertSame( 404, $response->get_status() );
	}
}
