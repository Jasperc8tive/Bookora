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

		$known   = $this->data_tables( true );
		$results = array();

		foreach ( $payload['tables'] as $table => $rows ) {
			$table = (string) $table;
			// Only restore tables we own and recognise — ignore anything else.
			if ( ! in_array( $table, $known, true ) || ! is_array( $rows ) ) {
				continue;
			}

			$name = $this->schema->table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query( "TRUNCATE TABLE `{$name}`" );

			$inserted = 0;
			foreach ( $rows as $row ) {
				if ( is_array( $row ) && false !== $this->wpdb->insert( $name, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					++$inserted;
				}
			}
			$results[ $table ] = $inserted;
		}

		if ( $with_settings && isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$this->settings->update( $payload['settings'] );
		}

		$this->audit->log( 'data.imported', array( 'tables' => array_keys( $results ) ) );

		return $results;
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
