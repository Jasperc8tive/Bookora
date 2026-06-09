<?php
/**
 * Admin dashboard page.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the mount point for the React admin SPA.
 */
final class DashboardPage {

	/**
	 * Output the page shell. The React app hydrates #bookora-admin-root.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bookora' ) );
		}

		printf(
			'<div class="wrap"><div id="bookora-admin-root" data-loading="%s">%s</div></div>',
			esc_attr__( 'Loading…', 'bookora' ),
			'<noscript>' . esc_html__( 'Bookora requires JavaScript to be enabled.', 'bookora' ) . '</noscript>'
		);
	}
}
