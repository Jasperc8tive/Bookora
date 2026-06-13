<?php
/**
 * WidgetRenderer tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Elementor;

use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Elementor\WidgetRenderer;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Elementor\WidgetRenderer
 */
class WidgetRendererTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private WidgetRenderer $renderer;
	private ServiceRepository $services;
	private StaffRepository $staff;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$schema         = new Schema();
		$this->services = new ServiceRepository( null, $schema );
		$this->staff    = new StaffRepository( null, $schema );
		$this->renderer = new WidgetRenderer( $this->services, $this->staff );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	public function test_service_grid_lists_active_services_only(): void {
		$this->services->create( array( 'name' => 'Massage', 'duration_min' => 60, 'price' => 100, 'currency' => 'NGN', 'status' => 'active' ) );
		$this->services->create( array( 'name' => 'Hidden', 'status' => 'inactive' ) );

		$html = $this->renderer->service_grid();
		$this->assertStringContainsString( 'Massage', $html );
		$this->assertStringContainsString( '60 min', $html );
		$this->assertStringContainsString( 'NGN', $html );
		$this->assertStringNotContainsString( 'Hidden', $html );
	}

	public function test_service_grid_empty_state(): void {
		$this->assertStringContainsString( 'No services', $this->renderer->service_grid() );
	}

	public function test_staff_grid_lists_active_staff(): void {
		$this->staff->create( array( 'display_name' => 'Ada', 'bio' => 'Senior stylist', 'status' => 'active' ) );
		$this->staff->create( array( 'display_name' => 'Ghost', 'status' => 'inactive' ) );

		$html = $this->renderer->staff_grid();
		$this->assertStringContainsString( 'Ada', $html );
		$this->assertStringNotContainsString( 'Ghost', $html );
	}

	public function test_grid_output_is_escaped(): void {
		$this->staff->create( array( 'display_name' => '<script>x</script>Eve', 'status' => 'active' ) );
		$html = $this->renderer->staff_grid();

		$this->assertStringNotContainsString( '<script>x</script>', $html );
		$this->assertStringContainsString( 'Eve', $html );
	}

	public function test_booking_form_renders_mount(): void {
		$html = $this->renderer->booking_form( array( 'service' => 5 ) );
		$this->assertStringContainsString( 'id="bookora-booking-root"', $html );
		$this->assertStringContainsString( 'data-service="5"', $html );
	}

	public function test_customer_dashboard_renders_portal_mount(): void {
		$this->assertStringContainsString( 'id="bookora-portal-root"', $this->renderer->customer_dashboard() );
	}
}
