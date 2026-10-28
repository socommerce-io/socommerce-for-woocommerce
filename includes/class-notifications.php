<?php
/**
 * What the shop's webhooks deliver to Socommerce.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * A WooCommerce order webhook delivers the whole order, the shopper included, and no setting
 * omits them. Socommerce treats a delivery as a ping and re-reads the order without the shopper
 * (ADR-0028), so a delivery to Socommerce's address is trimmed here to the order id: with the
 * plugin, the shopper's data never leaves the shop. Deliveries to any other address are untouched.
 */
class Notifications {

	/**
	 * Socommerce's ingestion service answers on Modal: one host per function, in any workspace
	 * and any region; or, one day, under socommerce.io.
	 */
	const MODAL_HOST = '/\A[a-z0-9-]+--socommerce-ingestion-woocommerce-webhook(?:\.[a-z0-9-]+)*\.modal\.run\z/';

	/**
	 * Hooks the payload filter, last, so nothing added by others survives either.
	 */
	public function register(): void {
		add_filter( 'woocommerce_webhook_payload', array( $this, 'trim' ), PHP_INT_MAX, 4 );
	}

	/**
	 * The payload a webhook delivers, trimmed to the order id when it delivers to Socommerce.
	 *
	 * @param mixed  $payload       What WooCommerce built.
	 * @param string $resource_type The webhook's resource: `order`, `product`, and so on.
	 * @param int    $resource_id   The order's id.
	 * @param int    $webhook_id    The webhook's id.
	 *
	 * @return mixed
	 */
	public function trim( $payload, $resource_type, $resource_id, $webhook_id ) {
		if ( 'order' !== $resource_type ) {
			return $payload;
		}
		$webhook = wc_get_webhook( (int) $webhook_id );
		if ( ! $webhook || ! $this->is_ours( (string) $webhook->get_delivery_url() ) ) {
			return $payload;
		}
		return array( 'id' => (int) $resource_id );
	}

	/**
	 * Whether a delivery URL is Socommerce's: its host, nothing else, decides.
	 *
	 * @param string $url A webhook's delivery URL.
	 */
	private function is_ours( string $url ): bool {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';
		return 'socommerce.io' === $host
			|| '.socommerce.io' === substr( $host, -14 )
			|| 1 === preg_match( self::MODAL_HOST, $host );
	}
}
