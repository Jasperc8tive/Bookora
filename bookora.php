<?php
/**
 * Plugin Name:       Bookora — Appointment Booking
 * Plugin URI:        https://bookora.app
 * Description:       The fastest WordPress appointment booking platform. Paystack-native, Flutterwave-native, WhatsApp-native, Elementor-first.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Bookora
 * Author URI:        https://bookora.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bookora
 * Domain Path:       /languages
 *
 * @package Bookora
 */

defined( 'ABSPATH' ) || exit;

/*
 * --------------------------------------------------------------------------
 * Constants
 * --------------------------------------------------------------------------
 */
define( 'BOOKORA_VERSION', '0.1.0' );
define( 'BOOKORA_DB_VERSION', '1' );
define( 'BOOKORA_FILE', __FILE__ );
define( 'BOOKORA_PATH', plugin_dir_path( __FILE__ ) );
define( 'BOOKORA_URL', plugin_dir_url( __FILE__ ) );
define( 'BOOKORA_BASENAME', plugin_basename( __FILE__ ) );
define( 'BOOKORA_PREFIX', 'bkra_' );
define( 'BOOKORA_MIN_PHP', '8.2' );
define( 'BOOKORA_MIN_WP', '6.8' );

/*
 * --------------------------------------------------------------------------
 * Environment guard
 * --------------------------------------------------------------------------
 *
 * Fail soft (admin notice + bail) rather than fataling on unsupported PHP.
 */
if ( version_compare( PHP_VERSION, BOOKORA_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			$message = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				esc_html__( 'Bookora requires PHP %1$s or higher. You are running %2$s. The plugin has been halted.', 'bookora' ),
				esc_html( BOOKORA_MIN_PHP ),
				esc_html( PHP_VERSION )
			);
			printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
		}
	);
	return;
}

/*
 * --------------------------------------------------------------------------
 * Autoloader
 * --------------------------------------------------------------------------
 */
$bookora_autoload = BOOKORA_PATH . 'vendor/autoload.php';
if ( ! is_readable( $bookora_autoload ) ) {
	add_action(
		'admin_notices',
		static function () {
			$message = esc_html__( 'Bookora could not find its autoloader. Run "composer install" in the plugin directory.', 'bookora' );
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
		}
	);
	return;
}
require $bookora_autoload;

/*
 * --------------------------------------------------------------------------
 * Lifecycle hooks
 * --------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( \Bookora\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Bookora\Core\Deactivator::class, 'deactivate' ) );

/*
 * --------------------------------------------------------------------------
 * Boot
 * --------------------------------------------------------------------------
 */
add_action(
	'plugins_loaded',
	static function () {
		\Bookora\Core\Plugin::instance()->boot();
	}
);
