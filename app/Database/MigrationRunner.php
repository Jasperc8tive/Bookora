<?php
/**
 * Migration runner.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database;

use Bookora\Database\Migrations\Migration_0001_InitialSchema;
use Bookora\Database\Migrations\Migration_0002_ServiceCategories;
use Bookora\Database\Migrations\Migration_0003_StaffServices;

defined( 'ABSPATH' ) || exit;

/**
 * Discovers, tracks, and applies/reverts schema migrations.
 *
 * Applied versions are recorded in the `{prefix}migrations` table so that
 * migrate() is idempotent and safe to call on every activation/upgrade.
 */
class MigrationRunner {

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
	 * Registered migration class names, in version order.
	 *
	 * @var array<int, class-string<MigrationInterface>>
	 */
	private array $migrations = array(
		Migration_0001_InitialSchema::class,
		Migration_0002_ServiceCategories::class,
		Migration_0003_StaffServices::class,
	);

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null  $wpdb   Optional handle (defaults to the global).
	 * @param Schema|null $schema Optional schema helper.
	 */
	public function __construct( ?\wpdb $wpdb = null, ?Schema $schema = null ) {
		$this->wpdb   = $wpdb ?? $GLOBALS['wpdb'];
		$this->schema = $schema ?? new Schema( $this->wpdb );
	}

	/**
	 * Apply all pending migrations.
	 *
	 * @return array<int, string> The versions applied during this run.
	 */
	public function migrate(): array {
		$this->ensure_tracking_table();
		$applied = $this->applied_versions();
		$ran     = array();

		foreach ( $this->instances() as $migration ) {
			if ( in_array( $migration->version(), $applied, true ) ) {
				continue;
			}

			$migration->up();
			$this->mark_applied( $migration->version() );
			$ran[] = $migration->version();
		}

		return $ran;
	}

	/**
	 * Revert all applied migrations in reverse order.
	 *
	 * @return array<int, string> The versions rolled back.
	 */
	public function rollback(): array {
		if ( ! $this->schema->table_exists( 'migrations' ) ) {
			return array();
		}

		$applied = $this->applied_versions();
		$ran     = array();

		foreach ( array_reverse( $this->instances() ) as $migration ) {
			if ( ! in_array( $migration->version(), $applied, true ) ) {
				continue;
			}

			$migration->down();
			$this->mark_reverted( $migration->version() );
			$ran[] = $migration->version();
		}

		return $ran;
	}

	/**
	 * Currently applied migration versions.
	 *
	 * @return array<int, string>
	 */
	public function applied_versions(): array {
		if ( ! $this->schema->table_exists( 'migrations' ) ) {
			return array();
		}

		$table = $this->schema->table( 'migrations' );
		// Identifier is from a hardcoded allowlist (see Schema::table); safe to interpolate.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_col( "SELECT version FROM `{$table}`" );

		return array_map( 'strval', (array) $rows );
	}

	/**
	 * Build migration instances in version order.
	 *
	 * @return array<int, MigrationInterface>
	 */
	private function instances(): array {
		$instances = array_map(
			static fn ( string $class ): MigrationInterface => new $class(),
			$this->migrations
		);

		usort(
			$instances,
			static fn ( MigrationInterface $a, MigrationInterface $b ): int => strcmp( $a->version(), $b->version() )
		);

		return $instances;
	}

	/**
	 * Create the migration-tracking table if absent.
	 *
	 * @return void
	 */
	private function ensure_tracking_table(): void {
		if ( $this->schema->table_exists( 'migrations' ) ) {
			return;
		}

		$table   = $this->schema->table( 'migrations' );
		$collate = $this->schema->charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			"CREATE TABLE `{$table}` (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				version VARCHAR(20) NOT NULL,
				applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY version (version)
			) {$collate};"
		);
	}

	/**
	 * Record a version as applied.
	 *
	 * @param string $version Version string.
	 * @return void
	 */
	private function mark_applied( string $version ): void {
		$this->wpdb->insert(
			$this->schema->table( 'migrations' ),
			array(
				'version'    => $version,
				'applied_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Remove a version's applied record.
	 *
	 * @param string $version Version string.
	 * @return void
	 */
	private function mark_reverted( string $version ): void {
		$this->wpdb->delete(
			$this->schema->table( 'migrations' ),
			array( 'version' => $version ),
			array( '%s' )
		);
	}
}
