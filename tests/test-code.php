<?php
/**
 * The shape of a tracking code.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Socommerce\WooCommerce\Code;
use WP_UnitTestCase;

/**
 * A code is six to thirty-two of a-z and 0-9; anything else is not a code.
 */
class Test_Code extends WP_UnitTestCase {

	/**
	 * A code as Socommerce mints it passes through unchanged.
	 */
	public function test_a_minted_code_is_kept() {
		$this->assertSame( 'sbaa64cyxe', Code::normalise( 'sbaa64cyxe' ) );
	}

	/**
	 * Upper case and surrounding whitespace are forgiven, because a link can be retyped.
	 */
	public function test_case_and_whitespace_are_forgiven() {
		$this->assertSame( 'sbaa64cyxe', Code::normalise( ' SBAA64CYXE ' ) );
	}

	/**
	 * The ends of the allowed length are allowed.
	 */
	public function test_six_and_thirty_two_characters_are_codes() {
		$this->assertSame( 'abc123', Code::normalise( 'abc123' ) );
		$this->assertSame( str_repeat( 'a1', 16 ), Code::normalise( str_repeat( 'a1', 16 ) ) );
	}

	/**
	 * Anything else is refused, whatever it is.
	 *
	 * @dataProvider not_codes
	 *
	 * @param mixed $raw Something that is not a code.
	 */
	public function test_anything_else_is_not_a_code( $raw ) {
		$this->assertNull( Code::normalise( $raw ) );
	}

	/**
	 * Things that are not codes.
	 */
	public function not_codes(): array {
		return array(
			'too short'      => array( 'abc12' ),
			'too long'       => array( str_repeat( 'a', 33 ) ),
			'empty'          => array( '' ),
			'a dash'         => array( 'abc-123' ),
			'a space inside' => array( 'abc 123' ),
			'a script'       => array( '<script>' ),
			'an array'       => array( array( 'sbaa64cyxe' ) ),
			'null'           => array( null ),
			'unicode'        => array( 'ábcdef' ),
		);
	}
}
