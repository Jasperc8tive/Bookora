<?php
/**
 * Customer dashboard Elementor widget.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Mounts the customer portal (populated by the portal bundle in Stage 14).
 */
final class CustomerDashboardWidget extends AbstractBookoraWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'bookora_customer_dashboard';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return __( 'Bookora Customer Dashboard', 'bookora' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_icon() {
		return 'eicon-person';
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		echo $this->renderer()->customer_dashboard(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
