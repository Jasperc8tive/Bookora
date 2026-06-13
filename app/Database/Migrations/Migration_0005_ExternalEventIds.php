<?php
/**
 * Adds a provider-keyed external event id map to appointments.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Migrations;

use Bookora\Database\MigrationInterface;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Adds `appointments.external_ids` (JSON map of provider => event id) so a
 * single appointment can be synced to multiple calendars (Google + Outlook)
 * without the single `external_event_id` column colliding.
 */
final class Migration_0005_ExternalEventIds implements MigrationInterface {

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
		return '0005';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		if ( $this->column_exists( 'appointments', 'external_ids' ) ) {
			return;
		}
		$table = $this->schema->table( 'appointments' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN external_ids longtext DEFAULT NULL AFTER external_event_id" );
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		if ( ! $this->column_exists( 'appointments', 'external_ids' ) ) {
			return;
		}
		$table = $this->schema->table( 'appointments' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN external_ids" );
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
