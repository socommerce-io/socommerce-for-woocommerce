<?php
/**
 * PHPUnit bootstrap: WordPress's test library with WooCommerce and the WP Consent API
 * loaded, then this plugin. Runs inside wp-env's tests container (`npm run test:php`),
 * where WP_TESTS_DIR points at the library and the plugins sit in WP_PLUGIN_DIR.
 *
 * @package Socommerce\WooCommerce
 */

$socommerce_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $socommerce_tests_dir ) {
	$socommerce_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $socommerce_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$socommerce_tests_dir}/includes/functions.php. Run the suite inside wp-env: npm run test:php" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a path of ours, on a terminal.
	exit( 1 );
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $socommerce_tests_dir . '/includes/functions.php';

/**
 * Loads WooCommerce, the WP Consent API and this plugin the way WordPress would.
 */
function socommerce_tests_load_plugins() {
	require_once socommerce_tests_plugin_file( 'woocommerce' );
	require_once socommerce_tests_plugin_file( 'wp-consent-api' );
	require_once dirname( __DIR__ ) . '/socommerce-for-woocommerce.php';
}

/**
 * A plugin's main file under WP_PLUGIN_DIR. wp-env unpacks a plugin fetched by URL into a
 * folder named after the download (`woocommerce.latest-stable`), a normal install into `woocommerce`.
 *
 * @param string $slug The plugin's slug, which names its folder and its main file.
 */
function socommerce_tests_plugin_file( string $slug ): string {
	$found = glob( WP_PLUGIN_DIR . '/' . $slug . '*/' . $slug . '.php' );
	if ( ! $found ) {
		echo "Could not find the {$slug} plugin under " . WP_PLUGIN_DIR . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a path of ours, on a terminal.
		exit( 1 );
	}
	return $found[0];
}
tests_add_filter( 'muplugins_loaded', 'socommerce_tests_load_plugins' );

/**
 * Installs WooCommerce's tables and roles on the test database, fresh every run.
 */
function socommerce_tests_install_woocommerce() {
	define( 'WP_UNINSTALL_PLUGIN', true );
	define( 'WC_REMOVE_ALL_DATA', true );
	include dirname( socommerce_tests_plugin_file( 'woocommerce' ) ) . '/uninstall.php';

	WC_Install::install();

	if ( class_exists( '\Automattic\WooCommerce\Admin\Install' ) ) {
		\Automattic\WooCommerce\Admin\Install::create_tables();
		\Automattic\WooCommerce\Admin\Install::create_events();
	}

	// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
	$GLOBALS['wp_roles'] = null;
	wp_roles();
}
tests_add_filter( 'setup_theme', 'socommerce_tests_install_woocommerce' );

require $socommerce_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/class-recording-cookies.php';
require_once __DIR__ . '/class-plugin-test-case.php';
