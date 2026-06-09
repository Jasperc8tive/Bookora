<?php
/**
 * Schema helpers (table naming, charset, existence checks).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Centralises Bookora's table-naming and low-level schema utilities so the
 * `{$wpdb->prefix}bkra_` convention lives in exactly one place.
 */
class Schema {

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb Optional handle (defaults to the global).
	 */
	public function __construct( ?\wpdb $wpdb = null ) {
		$this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Full table prefix, e.g. "wp_bkra_".
	 *
	 * @return string
	 */
	public function prefix(): string {
		return $this->wpdb->prefix . BOOKORA_PREFIX;
	}

	/**
	 * Resolve an unprefixed table name to its full name, e.g. "services" -> "wp_bkra_services".
	 *
	 * @param string $name Unprefixed table name.
	 * @return string
	 */
	public function table( string $name ): string {
		return $this->prefix() . $name;
	}

	/**
	 * The charset/collate clause for CREATE TABLE statements.
	 *
	 * @return string
	 */
	public function charset_collate(): string {
		return $this->wpdb->get_charset_collate();
	}

	/**
	 * Whether a Bookora table physically exists.
	 *
	 * @param string $name Unprefixed table name.
	 * @return bool
	 */
	public function table_exists( string $name ): bool {
		$table = $this->table( $name );
		$sql   = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $this->wpdb->get_var( $sql );

		return $found === $table;
	}
}
