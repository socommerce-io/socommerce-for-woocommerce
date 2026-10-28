<?php
/**
 * The code becomes order meta.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Order;

/**
 * Writes the code the shopper arrived with as `_socommerce_ref` on the order, on the classic
 * checkout's order-creation hook and on the Store API's checkout-processed hook. Through the
 * order object only, so High-Performance Order Storage holds.
 */
class Checkout {

	const META_KEY = '_socommerce_ref';

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
	 * Hooks both checkouts.
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'write' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'write_and_save' ) );
	}

	/**
	 * Writes the code on an order WooCommerce is about to save.
	 *
	 * @param WC_Order $order The order.
	 */
	public function write( WC_Order $order ): void {
		$code = $this->carrier->recall();
		if ( null !== $code ) {
			$order->update_meta_data( self::META_KEY, $code );
		}
	}

	/**
	 * Writes the code on an order WooCommerce has already saved.
	 *
	 * @param WC_Order $order The order.
	 */
	public function write_and_save( WC_Order $order ): void {
		$this->write( $order );
		$order->save();
	}
}
