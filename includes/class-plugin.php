<?php
/**
 * The plugin, wired.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

/**
 * Builds the parts once WooCommerce is loaded and hooks them. Held as a single instance so the
 * tests can reach the parts they need to look into.
 */
final class Plugin {

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
	 * Builds the parts.
	 *
	 * @param string $plugin_file The plugin's main file.
	 */
	private function __construct( string $plugin_file ) {
		$this->carrier       = new Carrier( new Cookies(), $plugin_file );
		$this->landing       = new Landing( $this->carrier );
		$this->checkout      = new Checkout( $this->carrier );
		$this->status        = new Status();
		$this->notifications = new Notifications();
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
		add_action( 'init', array( $this, 'declare_cookie' ) );
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
