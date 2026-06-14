<?php
/**
 * Import / export of all Bookora data.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\DataTransfer;

use Bookora\Core\Settings;
use Bookora\Database\Schema;
use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Serialises the plugin's settings and all `bkra_` data tables to a portable
 * JSON document and restores them. Tables are discovered dynamically from the
 * database (never a hard-coded list), excluding the migrations ledger and —
 * unless explicitly requested — the append-only activity log.
 *
 * Import is destructive (replace semantics) and gated behind an explicit
 * confirmation flag at the controller layer.
 */
final class DataPortability {

	private const FORMAT_VERSION = 1;

	/**
	 * Tables never round-tripped by default.
	 */
	private const SKIP = array( 'migrations', 'activity_logs' );

	/**
	 * Canonical parents-first restore order. Tables not listed are appended
	 * (and deleted first) so the order is always deterministic. Foreign-key
	 * checks are suspended for the atomic restore block, but inserting parents
	 * before children keeps the data valid even if a future change re-enables
	 * checks mid-transaction.
	 */
	private const ORDER = array(
		'service_categories',
		'services',
		'staff',
		'customers',
		'resources',
		'memberships',
		'coupons',
		'gift_cards',
		'staff_services',
		'staff_availability',
		'appointments',
		'booking_holds',
		'payments',
		'notifications',
		'notes',
		'waitlist',
		'customer_memberships',
		'integrations',
	);

	private Schema $schema;
	private Settings $settings;
	private ActivityLogger $audit;
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param Schema         $schema   Schema.
	 * @param Settings       $settings Settings.
	 * @param ActivityLogger $audit    Audit logger.
	 * @param \wpdb|null     $wpdb     Database handle (defaults to global).
	 */
	public function __construct( Schema $schema, Settings $settings, ActivityLogger $audit, ?\wpdb $wpdb = null ) {
		$this->schema   = $schema;
		$this->settings = $settings;
		$this->audit    = $audit;
		$this->wpdb     = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Build a full export document.
	 *
	 * @param bool $include_logs Whether to include the activity log.
	 * @return array<string, mixed>
	 */
	public function export( bool $include_logs = false ): array {
		$tables = array();
		foreach ( $this->data_tables( $include_logs ) as $table ) {
			$name = $this->schema->table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rows             = $this->wpdb->get_results( "SELECT * FROM `{$name}`", ARRAY_A );
			$tables[ $table ] = is_array( $rows ) ? $rows : array();
		}

		return array(
			'format'       => self::FORMAT_VERSION,
			'plugin'       => defined( 'BOOKORA_VERSION' ) ? BOOKORA_VERSION : '',
			'generated_at' => gmdate( 'c' ),
			'settings'     => $this->settings->all(),
			'tables'       => $tables,
		);
	}

	/**
	 * Restore from an export document (replace semantics).
	 *
	 * @param array<string, mixed> $payload      Decoded export document.
	 * @param bool                 $with_settings Whether to also restore settings.
	 * @return array<string, int>|WP_Error Per-table inserted-row counts, or error.
	 */
	public function import( array $payload, bool $with_settings = true ): array|WP_Error {
		if ( (int) ( $payload['format'] ?? 0 ) !== self::FORMAT_VERSION ) {
			return new WP_Error( 'bookora_import_format', __( 'Unsupported or missing export format.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( ! isset( $payload['tables'] ) || ! is_array( $payload['tables'] ) ) {
			return new WP_Error( 'bookora_import_empty', __( 'The export document contains no tables.', 'bookora' ), array( 'status' => 422 ) );
		}

		// Restrict the payload to tables we own, in a deterministic parents-first order.
		$known   = $this->data_tables( true );
		$targets = array();
		foreach ( $this->ordered( $known ) as $table ) {
			if ( isset( $payload['tables'][ $table ] ) && is_array( $payload['tables'][ $table ] ) ) {
				$targets[ $table ] = $payload['tables'][ $table ];
			}
		}

		// Atomic restore: suspend FK checks, wipe + reload inside one transaction,
		// roll back on any failure so a botched import never leaves partial data.
		$suppress = $this->wpdb->suppress_errors( true );
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		$this->wpdb->query( 'START TRANSACTION' );

		$results = array();
		$failure = null;

		// Delete children → parents (reverse of insert order).
		foreach ( array_reverse( array_keys( $targets ) ) as $table ) {
			$name = $this->schema->table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			if ( false === $this->wpdb->query( "DELETE FROM `{$name}`" ) ) {
				$failure = "delete:{$table}";
				break;
			}
		}

		// Insert parents → children, with schema-validated columns only.
		if ( null === $failure ) {
			foreach ( $targets as $table => $rows ) {
				$name     = $this->schema->table( $table );
				$columns  = $this->columns_for( $name );
				$inserted = 0;
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$clean = array_intersect_key( $row, $columns );
					if ( array() === $clean ) {
						continue;
					}
					if ( false === $this->wpdb->insert( $name, $clean ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
						$failure = "insert:{$table}";
						break 2;
					}
					++$inserted;
				}
				$results[ $table ] = $inserted;
			}
		}

		if ( null === $failure ) {
			$this->wpdb->query( 'COMMIT' );
		} else {
			$this->wpdb->query( 'ROLLBACK' );
		}
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
		$this->wpdb->suppress_errors( $suppress );

		if ( null !== $failure ) {
			return new WP_Error(
				'bookora_import_failed',
				__( 'Import failed and was rolled back; no data was changed.', 'bookora' ),
				array( 'status' => 500 )
			);
		}

		if ( $with_settings && isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$this->settings->update( $payload['settings'] );
		}

		$this->audit->log( 'data.imported', array( 'tables' => array_keys( $results ) ) );

		return $results;
	}

	/**
	 * Order known tables parents-first per {@see self::ORDER}; unknown tables
	 * are appended so the sequence is always deterministic.
	 *
	 * @param array<int, string> $known Discovered table names.
	 * @return array<int, string>
	 */
	private function ordered( array $known ): array {
		$ordered = array();
		foreach ( self::ORDER as $table ) {
			if ( in_array( $table, $known, true ) ) {
				$ordered[] = $table;
			}
		}
		foreach ( $known as $table ) {
			if ( ! in_array( $table, $ordered, true ) ) {
				$ordered[] = $table;
			}
		}

		return $ordered;
	}

	/**
	 * The set of real column names for a table, as a lookup map.
	 *
	 * @param string $name Prefixed table name.
	 * @return array<string, true>
	 */
	private function columns_for( string $name ): array {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$cols = $this->wpdb->get_col( "SHOW COLUMNS FROM `{$name}`" );
		$map  = array();
		foreach ( is_array( $cols ) ? $cols : array() as $col ) {
			$map[ (string) $col ] = true;
		}

		return $map;
	}

	/**
	 * Discover this plugin's data tables from the database.
	 *
	 * @param bool $include_logs Whether to include the activity log.
	 * @return array<int, string> Unprefixed table names.
	 */
	private function data_tables( bool $include_logs ): array {
		$prefix = $this->schema->prefix();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$found = $this->wpdb->get_col( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $this->wpdb->esc_like( $prefix ) . '%' ) );

		$skip = self::SKIP;
		if ( $include_logs ) {
			$skip = array_diff( $skip, array( 'activity_logs' ) );
		}

		$tables = array();
		foreach ( is_array( $found ) ? $found : array() as $full ) {
			$short = substr( (string) $full, strlen( $prefix ) );
			if ( '' !== $short && ! in_array( $short, $skip, true ) ) {
				$tables[] = $short;
			}
		}

		return $tables;
	}
}
