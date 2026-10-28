<?php
/**
 * The shape of a tracking code.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * A tracking code is what Socommerce mints for a creator's place on a product: six to
 * thirty-two of a-z and 0-9 (the service's own constraint). It is not a secret; it travels in
 * the landing URL of an ad. The plugin never checks whether a code exists, only whether it is
 * shaped like one, so nothing but a well-formed code is ever stored or written to an order.
 */
final class Code {

	const SHAPE = '/\A[a-z0-9]{6,32}\z/';

	/**
	 * The code in what arrived, or null when it is not one. Case and surrounding whitespace are
	 * forgiven because a link can be retyped; nothing else is.
	 *
	 * @param mixed $raw What the address, the request or the cookie carried.
	 */
	public static function normalise( $raw ): ?string {
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$code = strtolower( trim( $raw ) );
		return preg_match( self::SHAPE, $code ) ? $code : null;
	}
}
