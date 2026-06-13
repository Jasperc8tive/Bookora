<?php
/**
 * Base Elementor widget for Bookora.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Elementor\Widgets;

use Bookora\Elementor\WidgetRenderer;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Shared behaviour for Bookora's Elementor widgets.
 *
 * Elementor instantiates widgets itself (with $data/$args), so the renderer is
 * built here rather than injected. This file is excluded from PHPStan because it
 * extends an Elementor class not present as a dependency; all real logic lives
 * in the analysed, unit-tested {@see WidgetRenderer}.
 */
abstract class AbstractBookoraWidget extends \Elementor\Widget_Base {

	/**
	 * Elementor category.
	 *
	 * @return array<int, string>
	 */
	public function get_categories() {
		return array( 'bookora' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Build the shared renderer.
	 *
	 * @return WidgetRenderer
	 */
	protected function renderer(): WidgetRenderer {
		return new WidgetRenderer( new ServiceRepository(), new StaffRepository() );
	}

	/**
	 * Register a simple "limit" control used by the grid widgets.
	 *
	 * @return void
	 */
	protected function register_limit_control(): void {
		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'bookora' ) ) );
		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Maximum items', 'bookora' ),
				'type'    => 'number',
				'default' => 12,
				'min'     => 1,
				'max'     => 100,
			)
		);
		$this->end_controls_section();
	}
}
