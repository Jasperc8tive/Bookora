<?php
/**
 * Booking form Elementor widget.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Mounts the Bookora booking wizard.
 */
final class BookingFormWidget extends AbstractBookoraWidget {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'bookora_booking_form';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title() {
		return __( 'Bookora Booking Form', 'bookora' );
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'bookora' ) ) );
		$this->add_control(
			'service',
			array(
				'label'       => __( 'Pre-selected service ID', 'bookora' ),
				'type'        => 'number',
				'default'     => '',
				'description' => __( 'Optional: open the wizard on a specific service.', 'bookora' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		// Renderer returns trusted, internally-escaped HTML (mount point).
		echo $this->renderer()->booking_form( array( 'service' => $settings['service'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
