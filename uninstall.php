<?php
/**
 * Bookora uninstall entry point.
 *
 * Fired by WordPress when the plugin is deleted. Delegates to the Uninstaller,
 * which only removes data when the operator opted in via settings.
 *
 * @package Bookora
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$bookora_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $bookora_autoload ) ) {
	require $bookora_autoload;

	if ( ! defined( 'BOOKORA_PREFIX' ) ) {
		define( 'BOOKORA_PREFIX', 'bkra_' );
	}

	\Bookora\Core\Uninstaller::uninstall();
}
