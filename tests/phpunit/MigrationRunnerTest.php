<?php
/**
 * Migration runner / initial schema tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests;

use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Database\MigrationRunner
 * @covers \Bookora\Database\Migrations\Migration_0001_InitialSchema
 */
class MigrationRunnerTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private Schema $schema;

	/**
	 * Core tables expected after migration.
	 *
	 * @var array<int, string>
	 */
	private array $tables = array(
		'services',
		'staff',
		'staff_availability',
		'customers',
		'appointments',
		'payments',
		'notes',
		'notifications',
		'waitlist',
		'resources',
		'integrations',
		'activity_logs',
	);

	public function set_up(): void {
		parent::set_up();
		$this->schema = new Schema();
		$this->runner = new MigrationRunner( null, $this->schema );
		$this->runner->rollback();
		$this->runner->migrate();
	}

	public function tear_down(): void {
		// This class owns its schema (it tests migrate/rollback). Leave the
		// canonical, fully-migrated schema in place so later test classes — which
		// rely on the suite-wide schema built in bootstrap.php — are unaffected.
		$this->runner->migrate();
		parent::tear_down();
	}

	public function test_all_core_tables_are_created(): void {
		foreach ( $this->tables as $table ) {
			$this->assertTrue(
				$this->schema->table_exists( $table ),
				"Expected table {$table} to exist after migration."
			);
		}
	}

	public function test_tracking_table_records_initial_version(): void {
		$this->assertContains( '0001', $this->runner->applied_versions() );
	}

	public function test_migrate_is_idempotent(): void {
		$ran = $this->runner->migrate();
		$this->assertSame( array(), $ran, 'Re-running migrate() should apply nothing new.' );
	}

	public function test_soft_delete_column_present_on_appointments(): void {
		global $wpdb;
		$table   = $this->schema->table( 'appointments' );
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" ); // phpcs:ignore
		$this->assertContains( 'deleted_at', $columns );
		$this->assertContains( 'idempotency_key', $columns );
		$this->assertContains( 'start_at', $columns );
	}

	public function test_rollback_drops_tables(): void {
		$this->runner->rollback();
		$this->assertFalse( $this->schema->table_exists( 'appointments' ) );

		// Restore for tear_down symmetry.
		$this->runner->migrate();
	}
}
