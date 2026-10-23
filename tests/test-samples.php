<?php
/**
 * A person's note on a sample's order has WooCommerce deliver the order again.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Socommerce\WooCommerce\Samples;
use WC_Order;

/**
 * The nudge: a sample's order is saved again, with a fresh modification date, when a person
 * adds a note to it — and nothing happens for any other order, or for WooCommerce's own notes.
 */
class Test_Samples extends Plugin_Test_Case {

	/**
	 * How often `woocommerce_update_order` fired — what a webhook listens to — and for which order.
	 *
	 * @var int[]
	 */
	private $updated = array();

	/**
	 * Counts the saves WooCommerce would deliver, and signs in as a shop manager so a note can be
	 * added by hand.
	 */
	public function set_up() {
		parent::set_up();
		$this->updated = array();
		add_action( 'woocommerce_update_order', array( $this, 'count_update' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	/**
	 * Stops counting.
	 */
	public function tear_down() {
		remove_action( 'woocommerce_update_order', array( $this, 'count_update' ) );
		parent::tear_down();
	}

	/**
	 * Records one delivery-worthy save.
	 *
	 * @param int $order_id The order saved.
	 */
	public function count_update( $order_id ): void {
		$this->updated[] = (int) $order_id;
	}

	/**
	 * A sample's order, as Socommerce creates one: free, processing, naming the request.
	 */
	private function sample_order(): WC_Order {
		$order = wc_create_order( array( 'status' => 'processing' ) );
		$order->update_meta_data( Samples::META_KEY, '43e7cdc6-4d62-403f-939f-f16f3a99bb40' );
		$order->save();
		$this->updated = array();
		return $order;
	}

	/**
	 * Any other order.
	 */
	private function sale(): WC_Order {
		$order = wc_create_order( array( 'status' => 'processing' ) );
		$order->save();
		$this->updated = array();
		return $order;
	}

	/**
	 * Marks the order Completed the way the admin's status dropdown does: by hand.
	 *
	 * @param WC_Order $order The order.
	 */
	private function complete_by_hand( WC_Order $order ): void {
		$order->set_status( 'completed', '', true );
		$order->save();
	}

	/**
	 * A note typed on a completed sample's order saves it again, with a later modification date.
	 */
	public function test_a_persons_note_on_a_sample_order_has_it_delivered_again() {
		$order = $this->sample_order();
		$this->complete_by_hand( $order );
		$order->set_date_modified( time() - 120 );
		$order->save();
		$before        = $order->get_date_modified()->getTimestamp();
		$this->updated = array();

		$order->add_order_note( 'PostNL 3SABCD123456789 https://postnl.be/track/3SABCD123456789', 0, true );

		$this->assertSame( array( $order->get_id() ), $this->updated );
		$this->assertGreaterThan( $before, wc_get_order( $order->get_id() )->get_date_modified()->getTimestamp() );
	}

	/**
	 * A note on a sale is nobody's business: the order is not saved again.
	 */
	public function test_a_note_on_any_other_order_changes_nothing() {
		$order = $this->sale();

		$order->add_order_note( 'Customer rang about the delivery.', 0, true );

		$this->assertSame( array(), $this->updated );
	}

	/**
	 * WooCommerce's own notes — a status change, the one it writes under the manager's name
	 * when the status is changed by hand included — are not a person's word to the creator.
	 */
	public function test_woocommerces_own_notes_change_nothing() {
		$order = $this->sample_order();

		$this->complete_by_hand( $order );
		// The completion itself is one save; the status note it wrote added none.
		$this->assertSame( 1, count( $this->updated ) );
		$order->add_order_note( 'Stock levels reduced.' );
		$this->assertSame( 1, count( $this->updated ) );

		// What was guarded against: WooCommerce signs a by-hand status change with the manager's
		// name, so only the sentence tells it from a note the manager typed.
		$status_notes = array_filter(
			wc_get_order_notes( array( 'order_id' => $order->get_id() ) ),
			function ( $note ) {
				return false !== strpos( $note->content, 'to Completed.' );
			}
		);
		$this->assertCount( 1, $status_notes );
		$this->assertNotSame( 'system', array_values( $status_notes )[0]->added_by );
	}

	/**
	 * The status sentence is told apart in the shop's own language, with the statuses left open.
	 */
	public function test_the_status_sentence_is_the_shops_translation() {
		$order = $this->sample_order();
		add_filter(
			'gettext_woocommerce',
			function ( $translation, $text ) {
				return 'Order status changed from %1$s to %2$s.' === $text ? 'Bestellingsstatus gewijzigd van %1$s naar %2$s.' : $translation;
			},
			10,
			2
		);

		$order->add_order_note( 'Bestellingsstatus gewijzigd van In behandeling naar Afgerond.', 0, true );
		$this->assertSame( array(), $this->updated );

		$order->add_order_note( 'Order status changed from Processing to Completed.', 0, true );
		$this->assertSame( array( $order->get_id() ), $this->updated, 'the English sentence is a person\'s on a Dutch shop' );
	}
}
