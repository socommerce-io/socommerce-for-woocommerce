<?php
/**
 * The shopper's cookies, read and written.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * The one place a cookie is read or sent, so the tests can replace it with a jar they can look
 * into. A written cookie is readable in the same request, as WordPress's own cookies are.
 */
class Cookies {

	/**
	 * The cookie's value, or null when the browser sent none.
	 *
	 * @param string $name Cookie name.
	 */
	public function read( string $name ): ?string {
		if ( ! isset( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) ) {
			return null;
		}
		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}

	/**
	 * Sends a first-party cookie: HttpOnly, Secure on HTTPS, SameSite Lax so it survives the
	 * shopper arriving from an ad.
	 *
	 * @param string $name       Cookie name.
	 * @param string $value      Cookie value.
	 * @param int    $expires_at Unix time the cookie expires.
	 */
	public function write( string $name, string $value, int $expires_at ): void {
		if ( ! headers_sent() ) {
			setcookie(
				$name,
				$value,
				array(
					'expires'  => $expires_at,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
		$_COOKIE[ $name ] = $value;
	}
}
