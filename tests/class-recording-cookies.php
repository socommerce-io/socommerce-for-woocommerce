<?php
/**
 * A cookie jar the tests can read back: what the plugin would have sent the browser.
 *
 * @package Socommerce\WooCommerce
 */

namespace Socommerce\WooCommerce\Tests;

use Socommerce\WooCommerce\Cookies;

/**
 * Records every cookie written instead of sending it, and answers reads from what it holds.
 */
class Recording_Cookies extends Cookies {

	/**
	 * Cookies written, by name: `[ 'value' => string, 'expires_at' => int ]`.
	 *
	 * @var array<string, array{value: string, expires_at: int}>
	 */
	public $written = array();

	/**
	 * Cookies the browser would be sending, by name.
	 *
	 * @var array<string, string>
	 */
	private $held = array();

	/**
	 * Pretends the browser sent this cookie.
	 *
	 * @param string $name  Cookie name.
	 * @param string $value Cookie value.
	 */
	public function seed( string $name, string $value ): void {
		$this->held[ $name ] = $value;
	}

	/**
	 * Reads a cookie the browser sent, or one written in this request.
	 *
	 * @param string $name Cookie name.
	 */
	public function read( string $name ): ?string {
		if ( isset( $this->written[ $name ] ) ) {
			return $this->written[ $name ]['value'];
		}
		return $this->held[ $name ] ?? null;
	}

	/**
	 * Records a write instead of sending a header.
	 *
	 * @param string $name       Cookie name.
	 * @param string $value      Cookie value.
	 * @param int    $expires_at Unix time the cookie expires.
	 */
	public function write( string $name, string $value, int $expires_at ): void {
		$this->written[ $name ] = array(
			'value'      => $value,
			'expires_at' => $expires_at,
		);
	}
}
