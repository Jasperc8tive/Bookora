<?php
/**
 * Uninstall integrity tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Core;

use Bookora\Core\Uninstaller;
use Bookora\Customers\CustomerRepository;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Appointments\AppointmentRepository;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Core\Uninstaller
 */
class UninstallTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private Schema $schema;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();
		$this->schema = new Schema();
	}

	public function tear_down(): void {
		// Uninstall removes schema, roles AND options. Fully restore via the
		// production activation path so later test classes find a canonical
		// Bookora environment (matching the one bootstrap.php builds).
		\Bookora\Core\Activator::activate();
		parent::tear_down();
	}

	/**
	 * HOTFIX-006: with the delete-data flag set, uninstall drops every table
	 * despite foreign keys, and removes options. The old order-dependent DROP
	 * loop failed on FK-referenced parents and left data behind.
	 */
	public function test_uninstall_removes_all_fk_linked_tables_and_options(): void {
		// Seed an FK chain: service ← appointment → customer, staff.
		$services = new ServiceRepository( null, $this->schema );
		$service  = $services->create(
			array( 'name' => 'X', 'duration_min' => 30, 'price' => 10, 'currency' => 'NGN', 'status' => 'active' )
		);
		$customers = new CustomerRepository( null, $this->schema );
		$customer  = $customers->create( array( 'name' => 'Joy' ) );
		$staff     = new StaffRepository( null, $this->schema );
		$member    = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );
		$appts     = new AppointmentRepository( null, $this->schema );
		$appts->create(
			array(
				'service_id'  => $service,
				'staff_id'    => $member,
				'customer_id' => $customer,
				'start_at'    => '2030-01-01 09:00:00',
				'end_at'      => '2030-01-01 09:30:00',
				'status'      => 'confirmed',
				'total'       => 10,
				'currency'    => 'NGN',
			)
		);

		update_option( 'bookora_settings', array( 'delete_data_on_uninstall' => true ) );
		update_option( 'bookora_db_version', '1' );

		Uninstaller::uninstall();

		// Every FK-referenced parent and child table must be gone.
		foreach ( array( 'services', 'customers', 'staff', 'appointments', 'payments', 'waitlist', 'staff_availability' ) as $table ) {
			$this->assertFalse( $this->schema->table_exists( $table ), "Table {$table} should be dropped." );
		}

		$this->assertFalse( get_option( 'bookora_db_version', false ) );
	}

	/**
	 * Safety: without the flag, uninstall is a no-op (data retained).
	 */
	public function test_uninstall_is_noop_without_flag(): void {
		update_option( 'bookora_settings', array( 'delete_data_on_uninstall' => false ) );

		Uninstaller::uninstall();

		$this->assertTrue( $this->schema->table_exists( 'services' ) );
	}
}
