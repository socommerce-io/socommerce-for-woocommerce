<?php
/**
 * The plugin, wired.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the parts once WooCommerce is loaded and hooks them. Held as a single instance so the
 * tests can reach the parts they need to look into.
 */
final class Plugin {

	const REST_NAMESPACE = 'socommerce/v1';

	/**
	 * The one instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Where the code lives between the click and the order.
	 *
	 * @var Carrier
	 */
	public $carrier;

	/**
	 * The landing, the script and the endpoint.
	 *
	 * @var Landing
	 */
	public $landing;

	/**
	 * The order meta.
	 *
	 * @var Checkout
	 */
	public $checkout;

	/**
	 * The status endpoint.
	 *
	 * @var Status
	 */
	public $status;

	/**
	 * The trimmed deliveries.
	 *
	 * @var Notifications
	 */
	public $notifications;

	/**
	 * A sample's order delivered again when a person notes it.
	 *
	 * @var Samples
	 */
	public $samples;

	/**
	 * Builds the parts.
	 *
	 * @param string $plugin_file The plugin's main file.
	 */
	private function __construct( string $plugin_file ) {
		$this->carrier       = new Carrier( new Cookies(), plugin_basename( $plugin_file ) );
		$this->landing       = new Landing( $this->carrier );
		$this->checkout      = new Checkout( $this->carrier );
		$this->status        = new Status();
		$this->notifications = new Notifications();
		$this->samples       = new Samples();
	}

	/**
	 * Boots the plugin once.
	 *
	 * @param string $plugin_file The plugin's main file.
	 */
	public static function boot( string $plugin_file ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
			self::$instance->register();
		}
		return self::$instance;
	}

	/**
	 * The booted plugin, or null before `boot`.
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * Hooks every part and tells the WP Consent API about the one cookie.
	 */
	private function register(): void {
		$this->landing->register();
		$this->checkout->register();
		$this->status->register();
		$this->notifications->register();
		$this->samples->register();
		add_action( 'init', array( $this, 'declare_cookie' ) );
	}

	/**
	 * Tells every page cache to leave this answer alone. LiteSpeed Cache, WP Rocket, W3 Total
	 * Cache and WP Super Cache honour the constant; the headers reach the rest. A landing that
	 * stored the code, the endpoint that stores it and the status answer all change per request
	 * or per activation, and a cached copy of any of them misleads.
	 */
	public static function forbid_caching(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the page caches' own switch.
		}
		nocache_headers();
	}

	/**
	 * Drops the status answer from the shop's page cache, on activation and deactivation, so
	 * Socommerce's next poll sees the plugin as it is. LiteSpeed Cache and WP Rocket are asked by
	 * name; other caches are told per answer by `forbid_caching`, which cannot reach an answer
	 * cached before the plugin existed.
	 */
	public static function purge_status_answer(): void {
		$url = rest_url( self::REST_NAMESPACE . '/status' );
		do_action( 'litespeed_purge_url', $url ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own action.
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( $url );
		}
	}

	/**
	 * Lists the cookie with the WP Consent API, so a banner can show it under marketing.
	 */
	public function declare_cookie(): void {
		if ( ! function_exists( 'wp_add_cookie_info' ) ) {
			return;
		}
		wp_add_cookie_info(
			Carrier::COOKIE,
			'socommerce-for-woocommerce',
			'marketing',
			/* translators: how long the cookie lasts */
			__( '30 days', 'socommerce-for-woocommerce' ),
			__( 'Remembers which creator\'s link brought you to the shop, so a purchase can be credited to them.', 'socommerce-for-woocommerce' ),
			'',
			false,
			false,
			'HTTP'
		);
	}
}
