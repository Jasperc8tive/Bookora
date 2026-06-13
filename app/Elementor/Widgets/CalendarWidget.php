<?php
/**
 * Calendar Elementor widget.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Mounts the booking flow, whose date/time step is the customer-facing calendar.
 */
final class CalendarWidget extends AbstractBookoraWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'bookora_calendar';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return __( 'Bookora Calendar', 'bookora' );
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		echo $this->renderer()->calendar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
