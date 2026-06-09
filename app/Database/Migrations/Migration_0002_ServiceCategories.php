<?php
/**
 * Adds the service_categories table.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Migrations;

use Bookora\Database\MigrationInterface;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Creates `wp_bkra_service_categories`, the referent for `services.category_id`.
 */
final class Migration_0002_ServiceCategories implements MigrationInterface {

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
		return '0002';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $this->schema->table( 'service_categories' );
		$collate = $this->schema->charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				tenant_id bigint unsigned NOT NULL DEFAULT 0,
				name varchar(191) NOT NULL,
				slug varchar(191) DEFAULT NULL,
				description longtext DEFAULT NULL,
				color char(7) DEFAULT NULL,
				sort_order int NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY tenant (tenant_id),
				KEY slug (slug),
				KEY status (status),
				KEY sort_order (sort_order),
				KEY deleted (deleted_at)
			) {$collate};"
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		$table = $this->schema->table( 'service_categories' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}
}
