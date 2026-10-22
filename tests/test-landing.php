<?php
/**
 * A shopper lands on the shop with `?ref=CODE`.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Socommerce\WooCommerce\Carrier;
use Socommerce\WooCommerce\Landing;
use Socommerce\WooCommerce\Plugin;

/**
 * The server stores the code in the cart session, marks the page, and sets a cookie only with consent.
 */
class Test_Landing extends Plugin_Test_Case {

	/**
	 * WordPress would answer a test's bare request with a redirect to its canonical address,
	 * which means sending a header, which a test process cannot. The landing is not about that.
	 */
	public function set_up() {
		parent::set_up();
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	/**
	 * Arrives at the shop's front page with `?ref=` and runs what WordPress runs before the template.
	 *
	 * @param string $ref What the address carried.
	 */
	private function land_with( string $ref ): void {
		$this->go_to( home_url( '/?ref=' . rawurlencode( $ref ) ) );
		do_action( 'template_redirect' );
	}

	/**
	 * What the page's head holds.
	 */
	private function head(): string {
		return $this->output_of( 'wp_head' );
	}

	/**
	 * What the page's footer holds.
	 */
	private function footer(): string {
		return $this->output_of( 'wp_footer' );
	}

	/**
	 * What a theme hook prints.
	 *
	 * @param string $hook The hook's name.
	 */
	private function output_of( string $hook ): string {
		ob_start();
		do_action( $hook );
		return (string) ob_get_clean();
	}

	/**
	 * The code lands in WooCommerce's own cart session: the footing Shopify's cart link had.
	 */
	public function test_the_code_is_stored_in_the_cart_session() {
		$this->land_with( self::CODE );

		$this->assertSame( self::CODE, $this->code_in_session() );
	}

	/**
	 * A guest with an empty cart has no session yet; landing starts one, or the code is lost.
	 */
	public function test_a_guest_without_a_session_is_given_one() {
		$this->assertFalse( WC()->session->has_session(), 'a fresh guest has no session' );

		$this->land_with( self::CODE );

		$this->assertTrue( WC()->session->has_session() );
		$this->assertSame( self::CODE, $this->code_in_session() );
	}

	/**
	 * The page says the server ran, so the script on it need not post the code again.
	 */
	public function test_the_page_is_marked_with_the_code_the_server_stored() {
		$this->land_with( self::CODE );

		$this->assertStringContainsString( '<meta name="' . Landing::MARKER . '" content="' . self::CODE . '"', $this->head() );
	}

	/**
	 * A page that stored the code tells every page cache to leave it alone, or the cached copy
	 * would carry the marker to a visitor whose session holds nothing.
	 */
	public function test_a_landing_that_stored_the_code_is_not_cacheable() {
		$this->land_with( self::CODE );

		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
	}

	/**
	 * Without marketing consent there is no cookie of ours: the session carries the code alone.
	 */
	public function test_no_cookie_is_set_without_marketing_consent() {
		$this->given_no_marketing_consent();

		$this->land_with( self::CODE );

		$this->assertSame( self::CODE, $this->code_in_session() );
		$this->assertSame( array(), $this->cookies->written );
	}

	/**
	 * A shop without the WP Consent API reports no consent, so no cookie either.
	 */
	public function test_no_cookie_is_set_when_no_banner_reports_consent() {
		remove_all_filters( 'wp_get_consent_type' );
		add_filter( 'wp_get_consent_type', array( $this, 'optin' ) );

		$this->land_with( self::CODE );

		$this->assertSame( array(), $this->cookies->written );
	}

	/**
	 * The WP Consent API answers "allowed" while no banner has told it a consent type: by its own
	 * contract there is no consent management on such a shop, and the plugin takes it at its word.
	 */
	public function test_the_api_without_a_banner_reports_consent_and_the_cookie_follows() {
		remove_all_filters( 'wp_get_consent_type' );

		$this->land_with( self::CODE );

		$this->assertSame( self::CODE, $this->cookies->written[ Carrier::COOKIE ]['value'] ?? null );
	}

	/**
	 * With marketing accepted, a first-party cookie of thirty days carries the code past the session.
	 */
	public function test_a_thirty_day_cookie_is_set_with_marketing_consent() {
		$this->given_marketing_consent();

		$this->land_with( self::CODE );

		$this->assertArrayHasKey( Carrier::COOKIE, $this->cookies->written );
		$this->assertSame( self::CODE, $this->cookies->written[ Carrier::COOKIE ]['value'] );
		$this->assertEqualsWithDelta( time() + 30 * DAY_IN_SECONDS, $this->cookies->written[ Carrier::COOKIE ]['expires_at'], 5 );
	}

	/**
	 * Something that is not a code is ignored: nothing stored, nothing marked.
	 */
	public function test_a_malformed_ref_is_ignored() {
		$this->given_marketing_consent();

		$this->land_with( '<script>alert(1)</script>' );

		$this->assertNull( $this->code_in_session() );
		$this->assertSame( array(), $this->cookies->written );
		$this->assertStringNotContainsString( Landing::MARKER, $this->head() );
	}

	/**
	 * A page without `?ref=` touches nothing and carries no marker.
	 */
	public function test_a_page_without_ref_stores_nothing_and_is_not_marked() {
		$this->go_to( home_url( '/' ) );
		do_action( 'template_redirect' );

		$this->assertNull( $this->code_in_session() );
		$this->assertStringNotContainsString( Landing::MARKER, $this->head() );
	}

	/**
	 * Every page carries the script that hands a code the server did not see to the endpoint,
	 * and listens for the banner so a later consent can earn the cookie.
	 */
	public function test_every_page_carries_the_fallback_script() {
		// The whole footer runs, and WordPress 7.1's own footer still calls a function it deprecated.
		$this->setExpectedDeprecated( 'the_block_template_skip_link' );

		$footer = $this->footer();

		$this->assertStringContainsString( rest_url( Plugin::REST_NAMESPACE . '/ref' ), $footer );
		$this->assertStringContainsString( 'meta[name="' . Landing::MARKER . '"]', $footer );
		$this->assertStringContainsString( 'wp_listen_for_consent_change', $footer );
	}
}
