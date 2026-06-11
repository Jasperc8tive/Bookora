<?php
/**
 * Adds the booking_holds soft-hold table.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Migrations;

use Bookora\Database\MigrationInterface;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Short-lived reservations that prevent two customers from grabbing the same
 * slot during checkout. Rows expire and are purged; they are not soft-deleted.
 */
final class Migration_0004_BookingHolds implements MigrationInterface {

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Schema helper.
	 *
	 * @var Schema
	 */
	private Schema $schema;

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
		return '0004';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $this->schema->table( 'booking_holds' );
		$collate = $this->schema->charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				tenant_id bigint unsigned NOT NULL DEFAULT 0,
				service_id bigint unsigned NOT NULL,
				staff_id bigint unsigned NOT NULL,
				start_at datetime NOT NULL,
				end_at datetime NOT NULL,
				session_token varchar(64) NOT NULL,
				expires_at datetime NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY staff_start (staff_id, start_at),
				KEY expires (expires_at),
				KEY token (session_token)
			) {$collate};"
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		$table = $this->schema->table( 'booking_holds' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}
}
