<?php
/**
 * Adds staff_services join table and staff.skills column.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Migrations;

use Bookora\Database\MigrationInterface;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Creates `wp_bkra_staff_services` (assigned services) and adds a `skills`
 * column to `wp_bkra_staff`.
 */
final class Migration_0003_StaffServices implements MigrationInterface {

	/**
	 * Schema helper.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param Schema|null $schema Optional schema helper.
	 */
	public function __construct( ?Schema $schema = null ) {
		$this->wpdb   = $GLOBALS['wpdb'];
		$this->schema = $schema ?? new Schema( $this->wpdb );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return '0003';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $this->schema->table( 'staff_services' );
		$collate = $this->schema->charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				staff_id bigint unsigned NOT NULL,
				service_id bigint unsigned NOT NULL,
				price_override decimal(12,2) DEFAULT NULL,
				duration_override int unsigned DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY staff_service (staff_id, service_id),
				KEY staff (staff_id),
				KEY service (service_id)
			) {$collate};"
		);

		$this->add_skills_column();
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		$table = $this->schema->table( 'staff_services' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

		$staff = $this->schema->table( 'staff' );
		if ( $this->column_exists( 'staff', 'skills' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query( "ALTER TABLE `{$staff}` DROP COLUMN skills" );
		}
	}

	/**
	 * Add the `skills` column to staff if it is not present.
	 *
	 * @return void
	 */
	private function add_skills_column(): void {
		if ( $this->column_exists( 'staff', 'skills' ) ) {
			return;
		}
		$staff = $this->schema->table( 'staff' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( "ALTER TABLE `{$staff}` ADD COLUMN skills text DEFAULT NULL AFTER bio" );
	}

	/**
	 * Whether a column exists on a Bookora table.
	 *
	 * @param string $table  Unprefixed table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private function column_exists( string $table, string $column ): bool {
		$full = $this->schema->table( $table );
		$sql  = $this->wpdb->prepare(
			'SELECT COLUMN_NAME FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
			$full,
			$column
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$found = $this->wpdb->get_results( $sql );

		return ! empty( $found );
	}
}
