<?php
/**
 * Admin asset loader.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Admin;

use Bookora\Core\ModuleScript;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the built React/Vite bundle on Bookora admin screens and passes the
 * REST URL + nonce to the SPA.
 */
final class Assets {

	/**
	 * Script/style handle.
	 */
	private const HANDLE = 'bookora-admin';

	/**
	 * Hook menu registration.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue assets, but only on Bookora screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( ! $this->is_bookora_screen( $hook ) ) {
			return;
		}

		$build_url  = BOOKORA_URL . 'assets/build/';
		$build_path = BOOKORA_PATH . 'assets/build/';
		$script     = $build_path . 'admin.js';
		$version    = is_readable( $script ) ? (string) filemtime( $script ) : BOOKORA_VERSION;

		// The Service form uses the WordPress media library for images.
		wp_enqueue_media();

		wp_enqueue_script(
			self::HANDLE,
			$build_url . 'admin.js',
			array(),
			$version,
			true
		);
		ModuleScript::enable( self::HANDLE );

		// Vite emits the entry CSS alongside the JS.
		$style = $build_path . 'admin.css';
		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				self::HANDLE,
				$build_url . 'admin.css',
				array(),
				(string) filemtime( $style )
			);
		}

		wp_localize_script(
			self::HANDLE,
			'BookoraAdmin',
			array(
				'restUrl' => esc_url_raw( rest_url( 'bookora/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'version' => BOOKORA_VERSION,
				'screen'  => $this->current_screen(),
			)
		);
	}

	/**
	 * Whether the current admin screen belongs to Bookora.
	 *
	 * @param string $hook Page hook suffix.
	 * @return bool
	 */
	private function is_bookora_screen( string $hook ): bool {
		return str_contains( $hook, Menu::SLUG );
	}

	/**
	 * Initial SPA screen derived from the current admin page slug.
	 *
	 * @return string
	 */
	private function current_screen(): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return match ( $page ) {
			Menu::SLUG . '-calendar'  => 'calendar',
			Menu::SLUG . '-services'  => 'services',
			Menu::SLUG . '-staff'     => 'staff',
			Menu::SLUG . '-customers' => 'customers',
			default                   => 'dashboard',
		};
	}
}
