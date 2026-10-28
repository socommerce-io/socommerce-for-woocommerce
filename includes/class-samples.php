<?php
/**
 * A sample's order tells Socommerce about its notes.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Order;

/**
 * Socommerce ships a creator a free sample through an order it creates in the shop, and shows the
 * creator the latest note a person adds to that order as the parcel's tracking (WooCommerce has
 * no tracking field). But adding a note delivers nothing and changes no date: a shop manager who
 * marks the order Completed first and types the tracking afterwards is heard by nobody until
 * Socommerce's next six-hourly read of the shop.
 *
 * So when a person adds a note to a sample's order, the order is saved again, with a fresh
 * modification date, and WooCommerce delivers its ordinary `order.updated` as it does for any
 * other edit. Nothing is sent to Socommerce by the plugin itself and nothing is stored about
 * anyone; the shop does what it already does, a moment sooner. Notes WooCommerce writes itself —
 * a status change, an email sent, stock reduced — change nothing, even when the status change
 * was made by hand and so carries the manager's name.
 */
class Samples {

	/**
	 * The order meta Socommerce writes on a sample's order, naming the sample request. Only an
	 * order carrying it is a sample's; anything else is a sale, whose notes are nobody's business.
	 */
	const META_KEY = '_socommerce_sample_request';

	/**
	 * Hooks the note.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_note_added', array( $this, 'nudge' ), 10, 2 );
	}

	/**
	 * Saves a sample's order again when a person has added a note to it.
	 *
	 * @param int           $note_id The note's id.
	 * @param WC_Order|null $order   The order the note was added to.
	 */
	public function nudge( $note_id, $order ): void {
		if ( ! $order instanceof WC_Order || '' === (string) $order->get_meta( self::META_KEY ) ) {
			return;
		}
		if ( ! $this->by_a_person( (int) $note_id ) ) {
			return;
		}
		// Set by hand rather than left to the data store: with posts storage a save that changes
		// nothing leaves the modification date alone, and Socommerce reads an order whose date
		// has not moved as one it already holds.
		$order->set_date_modified( time() );
		$order->save();
	}

	/**
	 * Whether a note is a person's words, as Socommerce decides it: not WooCommerce's own, and
	 * not WooCommerce's status sentence under the name of the manager who changed the status.
	 *
	 * @param int $note_id The note's id.
	 */
	private function by_a_person( int $note_id ): bool {
		$note = wc_get_order_note( $note_id );
		if ( ! $note || 'system' === $note->added_by ) {
			return false;
		}
		return ! $this->is_status_change( (string) $note->content );
	}

	/**
	 * Whether a note is WooCommerce's "Order status changed from … to …", in the shop's own
	 * language: the shop's translation of the sentence, with its two statuses left open.
	 *
	 * @param string $content The note's text.
	 */
	private function is_status_change( string $content ): bool {
		/* translators: 1: the order's previous status, 2: its new status — WooCommerce's own sentence, as the shop translates it. */
		$sentence = __( 'Order status changed from %1$s to %2$s.', 'woocommerce' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's string, looked up in WooCommerce's domain on purpose.
		$pattern  = str_replace( array( '%1\$s', '%2\$s' ), '.+', preg_quote( $sentence, '~' ) );
		return 1 === preg_match( '~' . $pattern . '~u', $content );
	}
}
