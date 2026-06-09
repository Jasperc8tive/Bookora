<?php
/**
 * PHPUnit bootstrap for Bookora integration tests.
 *
 * Loads the WordPress test library and the plugin. Provide the test library
 * path via the WP_TESTS_DIR or WP_PHPUNIT__DIR environment variable, or rely on
 * the bundled wp-phpunit package.
 *
 * @package Bookora
 */

declare(strict_types=1);

$bookora_root = dirname( __DIR__, 2 );

require $bookora_root . '/vendor/autoload.php';

$bookora_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $bookora_tests_dir ) {
	$bookora_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( ! $bookora_tests_dir && is_dir( $bookora_root . '/vendor/wp-phpunit/wp-phpunit' ) ) {
	$bookora_tests_dir = $bookora_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! $bookora_tests_dir || ! file_exists( $bookora_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test library. Set WP_TESTS_DIR.\n" );
	exit( 1 );
}

require $bookora_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $bookora_root ): void {
		require $bookora_root . '/bookora.php';
	}
);

require $bookora_tests_dir . '/includes/bootstrap.php';
