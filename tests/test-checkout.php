<?php
/**
 * At checkout the code becomes order meta, on the classic checkout and on the block checkout.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Socommerce\WooCommerce\Carrier;
use Socommerce\WooCommerce\Checkout;
use WC_Product_Simple;

/**
 * The order carries `_socommerce_ref`: from the session first, else from the cookie, else not at all.
 */
class Test_Checkout extends Plugin_Test_Case {

	/**
	 * A cart with one product in it, as a shopper about to pay has.
	 */
	public function set_up() {
		parent::set_up();
		$product = new WC_Product_Simple();
		$product->set_name( 'Collagen Glow' );
		$product->set_regular_price( '29.95' );
		$product->save();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id() );
	}

	/**
	 * Places the order the way the classic (shortcode) checkout does.
	 */
	private function classic_checkout(): \WC_Order {
		$order_id = WC()->checkout()->create_order(
			array(
				'billing_email'  => 'shopper@example.com',
				'payment_method' => 'cod',
			)
		);
		$this->assertIsInt( $order_id, 'the classic checkout created an order' );
		return wc_get_order( $order_id );
	}

	/**
	 * Places the order the way the block checkout (Store API) does: the order exists, then the hook fires.
	 */
	private function block_checkout(): \WC_Order {
		$order = wc_create_order();
		do_action( 'woocommerce_store_api_checkout_order_processed', $order );
		return wc_get_order( $order->get_id() );
	}

	/**
	 * The classic checkout writes the session's code as order meta.
	 */
	public function test_the_classic_checkout_writes_the_code_from_the_session() {
		WC()->session->set( Carrier::SESSION_KEY, self::CODE );

		$order = $this->classic_checkout();

		$this->assertSame( self::CODE, $order->get_meta( Checkout::META_KEY ) );
	}

	/**
	 * The block checkout writes the session's code as order meta, and it is saved.
	 */
	public function test_the_block_checkout_writes_the_code_from_the_session() {
		WC()->session->set( Carrier::SESSION_KEY, self::CODE );

		$order = $this->block_checkout();

		$this->assertSame( self::CODE, $order->get_meta( Checkout::META_KEY ) );
	}

	/**
	 * Two days on, the session is gone; the consented cookie still carries the code.
	 */
	public function test_the_cookie_carries_the_code_when_the_session_has_none() {
		$this->cookies->seed( Carrier::COOKIE, self::CODE );

		$this->assertSame( self::CODE, $this->classic_checkout()->get_meta( Checkout::META_KEY ) );
		$this->assertSame( self::CODE, $this->block_checkout()->get_meta( Checkout::META_KEY ) );
	}

	/**
	 * The session is the more recent click, so it wins over an older cookie.
	 */
	public function test_the_session_wins_over_the_cookie() {
		WC()->session->set( Carrier::SESSION_KEY, self::CODE );
		$this->cookies->seed( Carrier::COOKIE, 'older1' );

		$this->assertSame( self::CODE, $this->classic_checkout()->get_meta( Checkout::META_KEY ) );
	}

	/**
	 * A shopper who clicked no link places an order with no meta of ours at all.
	 */
	public function test_an_order_without_a_code_carries_no_meta() {
		$classic = $this->classic_checkout();
		$block   = $this->block_checkout();

		$this->assertFalse( $classic->meta_exists( Checkout::META_KEY ) );
		$this->assertFalse( $block->meta_exists( Checkout::META_KEY ) );
	}

	/**
	 * A cookie that somebody tampered with is not a code and writes nothing.
	 */
	public function test_a_tampered_cookie_writes_nothing() {
		$this->cookies->seed( Carrier::COOKIE, '<b>x</b>' );

		$this->assertFalse( $this->classic_checkout()->meta_exists( Checkout::META_KEY ) );
	}
}
