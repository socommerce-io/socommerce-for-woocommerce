<?php
/**
 * The three ways a code reaches the carrier: the landing, the page's script, the endpoint.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * On a request carrying `?ref=`, the server stores the code and marks the page. A few lines of
 * script on every page read `ref` from the address and, when the marker is absent (the page
 * came from a cache), post it to the plugin's endpoint, which stores it the same way. The
 * script also listens for the banner, so consent given after landing still earns the cookie.
 */
class Landing {

	const MARKER = 'socommerce-ref';

	/**
	 * Where the code is kept.
	 *
	 * @var Carrier
	 */
	private $carrier;

	/**
	 * Constructor.
	 *
	 * @param Carrier $carrier Where the code is kept.
	 */
	public function __construct( Carrier $carrier ) {
		$this->carrier = $carrier;
	}

	/**
	 * Hooks the three entrances.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'land' ) );
		add_action( 'wp_head', array( $this, 'print_marker' ) );
		add_action( 'wp_footer', array( $this, 'print_script' ) );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * The code the address carries, if it carries one.
	 */
	private function requested_code(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a landing from an ad carries no nonce by nature; the value is validated to a shape, nothing else.
		if ( ! isset( $_GET['ref'] ) ) {
			return null;
		}
		return Code::normalise( wp_unslash( $_GET['ref'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalise() is the sanitiser.
		// phpcs:enable
	}

	/**
	 * Stores the code a landing carries, before any output.
	 */
	public function land(): void {
		$code = $this->requested_code();
		if ( null === $code ) {
			return;
		}

		$this->carrier->remember( $code );

		// A page that stored the code must not be cached: a cached copy would carry the marker to
		// a visitor whose session holds nothing. LiteSpeed Cache, WP Rocket, W3 Total Cache and
		// WP Super Cache honour the constant; the headers reach the rest.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page caches' own switch.
		}
		nocache_headers();
	}

	/**
	 * Marks the page with the code the server stored, so the script leaves it be. The mark is a
	 * fact of this request, the address's code being in this shopper's session, not a memory.
	 */
	public function print_marker(): void {
		$code = $this->requested_code();
		if ( null === $code || $this->carrier->in_session() !== $code ) {
			return;
		}
		printf( '<meta name="%s" content="%s">' . "\n", esc_attr( self::MARKER ), esc_attr( $code ) );
	}

	/**
	 * Prints the fallback script on every page.
	 */
	public function print_script(): void {
		$endpoint = wp_json_encode( esc_url_raw( rest_url( Plugin::REST_NAMESPACE . '/ref' ) ), JSON_UNESCAPED_SLASHES );
		$marker   = self::MARKER;
		$script   = <<<JS
(function(){var r=new URLSearchParams(location.search).get("ref");if(!r)return;var m=document.querySelector('meta[name="{$marker}"]');function p(){fetch({$endpoint},{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json"},body:JSON.stringify({ref:r})}).catch(function(){});}if(!m||m.content!==r)p();document.addEventListener("wp_listen_for_consent_change",function(e){if(e.detail&&e.detail.marketing==="allow")p();});})();
JS;
		wp_print_inline_script_tag( $script );
	}

	/**
	 * The endpoint the script posts to.
	 */
	public function register_route(): void {
		register_rest_route(
			Plugin::REST_NAMESPACE,
			'/ref',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'store' ),
				'permission_callback' => '__return_true',
				'show_in_index'       => false,
				'args'                => array(
					'ref' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Stores a well-formed code and answers nothing; refuses anything else.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function store( WP_REST_Request $request ) {
		$code = Code::normalise( $request->get_param( 'ref' ) );
		if ( null === $code ) {
			return new WP_Error( 'socommerce_not_a_code', __( 'Not a tracking code.', 'socommerce-for-woocommerce' ), array( 'status' => 400 ) );
		}
		$this->carrier->remember( $code );
		return new WP_REST_Response( null, 204 );
	}
}
