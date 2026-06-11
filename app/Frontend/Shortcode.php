<?php
/**
 * Front-end booking shortcode.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Frontend;

use Bookora\Core\ModuleScript;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `[bookora_booking]`, which mounts the React booking wizard.
 *
 * The shortcode works anywhere the_content runs — including Elementor's
 * Shortcode widget — making it Elementor-compatible. Native Elementor widgets
 * arrive in Stage 13.
 */
final class Shortcode {

	/**
	 * Script/style handle.
	 */
	private const HANDLE = 'bookora-frontend';

	/**
	 * Register (but do not enqueue) the front-end assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_script(
			self::HANDLE,
			BOOKORA_URL . 'assets/build/frontend.js',
			array(),
			BOOKORA_VERSION,
			true
		);
		wp_register_style(
			self::HANDLE,
			BOOKORA_URL . 'assets/build/frontend.css',
			array(),
			BOOKORA_VERSION
		);
		wp_localize_script(
			self::HANDLE,
			'BookoraBooking',
			array(
				'restUrl' => esc_url_raw( rest_url( 'bookora/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render the shortcode: enqueue assets and output the mount point.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'service' => '',
				'staff'   => '',
			),
			is_array( $atts ) ? $atts : array(),
			'bookora_booking'
		);

		wp_enqueue_script( self::HANDLE );
		wp_enqueue_style( self::HANDLE );
		ModuleScript::enable( self::HANDLE );

		return sprintf(
			'<div id="bookora-booking-root" data-service="%s" data-staff="%s"></div>',
			esc_attr( (string) $atts['service'] ),
			esc_attr( (string) $atts['staff'] )
		);
	}
}
