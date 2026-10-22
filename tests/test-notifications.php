<?php
/**
 * The notifications the shop sends Socommerce are trimmed to the order id.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use WC_Webhook;

/**
 * A delivery to our address carries `{"id": N}`; a delivery to anybody else is untouched.
 */
class Test_Notifications extends Plugin_Test_Case {

	const OURS = 'https://khalil-48843--socommerce-ingestion-woocommerce-webhook.modal.run';

	/**
	 * A saved webhook on the shop, as Connect creates one.
	 *
	 * @param string $delivery_url Where it delivers.
	 * @param string $topic        Its topic.
	 */
	private function webhook( string $delivery_url, string $topic = 'order.created' ): WC_Webhook {
		$webhook = new WC_Webhook();
		$webhook->set_name( 'test' );
		$webhook->set_topic( $topic );
		$webhook->set_delivery_url( $delivery_url );
		$webhook->set_status( 'active' );
		$webhook->set_user_id( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$webhook->set_api_version( 'wp_api_v3' );
		$webhook->save();
		return $webhook;
	}

	/**
	 * An order with a shopper on it.
	 */
	private function order(): \WC_Order {
		$order = wc_create_order();
		$order->set_billing_first_name( 'Ada' );
		$order->set_billing_email( 'ada@example.com' );
		$order->save();
		return $order;
	}

	/**
	 * A created or updated order delivered to Socommerce is the id and nothing else.
	 */
	public function test_a_delivery_to_socommerce_carries_the_order_id_only() {
		$order = $this->order();

		foreach ( array( 'order.created', 'order.updated' ) as $topic ) {
			$payload = $this->webhook( self::OURS, $topic )->build_payload( $order->get_id() );
			$this->assertSame( array( 'id' => $order->get_id() ), $payload, $topic );
		}
	}

	/**
	 * A delivery to anybody else is WooCommerce's whole order, as before.
	 */
	public function test_a_delivery_elsewhere_is_untouched() {
		$order = $this->order();

		$payload = $this->webhook( 'https://example.com/hooks/orders' )->build_payload( $order->get_id() );

		$this->assertSame( $order->get_id(), $payload['id'] );
		$this->assertSame( 'ada@example.com', $payload['billing']['email'] );
	}

	/**
	 * Which addresses are Socommerce's: a delivery there is trimmed, anywhere else it is whole.
	 *
	 * @dataProvider addresses
	 *
	 * @param string $url  A delivery URL.
	 * @param bool   $ours Whether it is ours.
	 */
	public function test_which_addresses_are_ours( string $url, bool $ours ) {
		$order = $this->order();

		$payload = $this->webhook( $url )->build_payload( $order->get_id() );

		$this->assertSame( $ours, array( 'id' => $order->get_id() ) === $payload );
	}

	/**
	 * Addresses and whether they are ours.
	 */
	public function addresses(): array {
		return array(
			'the service'                  => array( self::OURS, true ),
			'with a trailing slash'        => array( self::OURS . '/', true ),
			'a regional modal host'        => array( 'https://khalil-48843--socommerce-ingestion-woocommerce-webhook.us-east.modal.run', true ),
			'another workspace'            => array( 'https://other-ws--socommerce-ingestion-woocommerce-webhook.modal.run', true ),
			'a socommerce.io host'         => array( 'https://hooks.socommerce.io/woocommerce', true ),
			'socommerce.io itself'         => array( 'https://socommerce.io/hooks', true ),
			'another modal function'       => array( 'https://khalil-48843--socommerce-ingestion-install-callback.modal.run', false ),
			'our host in the query string' => array( 'https://evil.example/?' . self::OURS, false ),
			'our host as a prefix'         => array( 'https://khalil-48843--socommerce-ingestion-woocommerce-webhook.modal.run.evil.example/', false ),
			'a look-alike domain'          => array( 'https://notsocommerce.io/hooks', false ),
			'a shop elsewhere'             => array( 'https://example.com/hooks', false ),
			'not a url'                    => array( 'nonsense', false ),
		);
	}
}
