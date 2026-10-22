<?php
/**
 * Where the code lives between the click and the order.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

/**
 * Keeps the code in WooCommerce's own cart session (about two days, no cookie of ours) and,
 * only when the shop's banner reports marketing consent through the WP Consent API, in a
 * first-party cookie of thirty days. Reads it back session first, then cookie.
 */
class Carrier {

	const SESSION_KEY = 'socommerce_ref';
	const COOKIE      = 'socommerce_ref';
	const COOKIE_DAYS = 30;

	/**
	 * The shopper's cookies. Public so the tests can replace it with a jar they can read.
	 *
	 * @var Cookies
	 */
	public $cookies;

	/**
	 * The plugin's basename, `socommerce-for-woocommerce/socommerce-for-woocommerce.php`, which
	 * the WP Consent API knows the plugin by.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Constructor.
	 *
	 * @param Cookies $cookies         The shopper's cookies.
	 * @param string  $plugin_basename The plugin's basename.
	 */
	public function __construct( Cookies $cookies, string $plugin_basename ) {
		$this->cookies         = $cookies;
		$this->plugin_basename = $plugin_basename;
	}

	/**
	 * Keeps a well-formed code for this shopper.
	 *
	 * @param string $code A code `Code::normalise` accepted.
	 */
	public function remember( string $code ): void {
		$session = $this->session();
		$session->set( self::SESSION_KEY, $code );
		if ( $session instanceof \WC_Session_Handler && ! $session->has_session() ) {
			// A guest who has nothing in the cart has no session yet; start one, or the code is
			// gone with this request. A shop's own session handler is trusted to manage itself.
			$session->set_customer_session_cookie( true );
		}

		if ( $this->has_marketing_consent() ) {
			$this->cookies->write( self::COOKIE, $code, time() + self::COOKIE_DAYS * DAY_IN_SECONDS );
		}
	}

	/**
	 * The code this shopper arrived with, if any: the session's (the more recent click) first,
	 * then the cookie's. Anything not shaped like a code is as good as absent.
	 */
	public function recall(): ?string {
		$held = $this->in_session();
		if ( null !== $held ) {
			return $held;
		}
		return Code::normalise( $this->cookies->read( self::COOKIE ) );
	}

	/**
	 * The code in this shopper's cart session, if the request has a session and it holds one.
	 */
	public function in_session(): ?string {
		$session = WC()->session;
		return $session ? Code::normalise( $session->get( self::SESSION_KEY ) ) : null;
	}

	/**
	 * Whether the shop's consent banner reports marketing consent through the WP Consent API.
	 * A shop without the API reports nothing, so: no cookie.
	 */
	private function has_marketing_consent(): bool {
		return function_exists( 'wp_has_consent' ) && wp_has_consent( 'marketing', $this->plugin_basename );
	}

	/**
	 * WooCommerce's session for this request, started if this request (a REST one, say) has
	 * none yet.
	 */
	private function session(): \WC_Session {
		if ( ! WC()->session ) {
			wc_load_cart();
		}
		return WC()->session;
	}
}
