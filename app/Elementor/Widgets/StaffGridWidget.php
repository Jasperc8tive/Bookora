<?php
/**
 * Staff grid Elementor widget.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered grid of active staff.
 */
final class StaffGridWidget extends AbstractBookoraWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'bookora_staff_grid';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return __( 'Bookora Staff Grid', 'bookora' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return 'eicon-person';
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->register_limit_control();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		echo $this->renderer()->staff_grid( array( 'limit' => $settings['limit'] ?? 12 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
