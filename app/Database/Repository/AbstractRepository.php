<?php
/**
 * Base repository: prepared-statement CRUD with soft-delete support.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Repository;

use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Concrete repositories extend this and implement table_name().
 *
 * All queries use `$wpdb->prepare()` or `$wpdb` helper methods; identifiers
 * (table/column names) are validated against an allowlist pattern before being
 * interpolated, never user values.
 */
abstract class AbstractRepository implements RepositoryInterface {

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 */
	protected \wpdb $wpdb;

	/**
	 * Schema helper.
	 *
	 * @var Schema
	 */
	protected Schema $schema;

	/**
	 * Whether this table uses a `deleted_at` soft-delete column.
	 *
	 * @var bool
	 */
	protected bool $soft_deletes = true;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null  $wpdb   Optional handle (defaults to global).
	 * @param Schema|null $schema Optional schema helper.
	 */
	public function __construct( ?\wpdb $wpdb = null, ?Schema $schema = null ) {
		$this->wpdb   = $wpdb ?? $GLOBALS['wpdb'];
		$this->schema = $schema ?? new Schema( $this->wpdb );
	}

	/**
	 * Unprefixed table name this repository manages.
	 *
	 * @return string
	 */
	abstract protected function table_name(): string;

	/**
	 * Fully-qualified table name.
	 *
	 * @return string
	 */
	protected function table(): string {
		return $this->schema->table( $this->table_name() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function find( int $id, bool $include_trashed = false ): ?array {
		$table = $this->table();
		$sql   = "SELECT * FROM `{$table}` WHERE id = %d";
		if ( $this->soft_deletes && ! $include_trashed ) {
			$sql .= ' AND deleted_at IS NULL';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$row = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find the first row matching equality conditions.
	 *
	 * @param array<string, scalar|null> $conditions      Column => value.
	 * @param bool                       $include_trashed Include soft-deleted rows.
	 * @return array<string, mixed>|null
	 */
	public function find_by( array $conditions, bool $include_trashed = false ): ?array {
		$rows = $this->all(
			array(
				'where'           => $conditions,
				'limit'           => 1,
				'include_trashed' => $include_trashed,
			)
		);

		return $rows[0] ?? null;
	}

	/**
	 * List rows.
	 *
	 * @param array{
	 *     where?: array<string, scalar|null>,
	 *     orderby?: string,
	 *     order?: string,
	 *     limit?: int,
	 *     offset?: int,
	 *     include_trashed?: bool
	 * } $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function all( array $args = array() ): array {
		$table = $this->table();

		list( $where_sql, $values ) = $this->build_where(
			$args['where'] ?? array(),
			(bool) ( $args['include_trashed'] ?? false )
		);

		$order_sql = $this->build_order( $args['orderby'] ?? 'id', $args['order'] ?? 'ASC' );

		$limit_sql = '';
		if ( isset( $args['limit'] ) ) {
			$limit     = max( 0, (int) $args['limit'] );
			$offset    = max( 0, (int) ( $args['offset'] ?? 0 ) );
			$values[]  = $limit;
			$values[]  = $offset;
			$limit_sql = 'LIMIT %d OFFSET %d';
		}

		$sql = trim( "SELECT * FROM `{$table}` {$where_sql} {$order_sql} {$limit_sql}" );

		if ( array() !== $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $this->wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count rows matching equality conditions.
	 *
	 * @param array<string, scalar|null> $conditions      Column => value.
	 * @param bool                       $include_trashed Include soft-deleted rows.
	 * @return int
	 */
	public function count( array $conditions = array(), bool $include_trashed = false ): int {
		$table                      = $this->table();
		list( $where_sql, $values ) = $this->build_where( $conditions, $include_trashed );

		$sql = trim( "SELECT COUNT(*) FROM `{$table}` {$where_sql}" );
		if ( array() !== $values ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $this->wpdb->prepare( $sql, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create( array $data ): int {
		$data = $this->filter_columns( $data );
		$this->wpdb->insert( $this->table(), $data );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( int $id, array $data ): bool {
		$data   = $this->filter_columns( $data );
		$result = $this->wpdb->update( $this->table(), $data, array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( int $id ): bool {
		if ( ! $this->soft_deletes ) {
			return $this->force_delete( $id );
		}

		$result = $this->wpdb->update(
			$this->table(),
			array( 'deleted_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Permanently delete a row.
	 *
	 * @param int $id Primary key.
	 * @return bool
	 */
	public function force_delete( int $id ): bool {
		$result = $this->wpdb->delete( $this->table(), array( 'id' => $id ) );

		return false !== $result;
	}

	/**
	 * Restore a soft-deleted row.
	 *
	 * @param int $id Primary key.
	 * @return bool
	 */
	public function restore( int $id ): bool {
		if ( ! $this->soft_deletes ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table(),
			array( 'deleted_at' => null ),
			array( 'id' => $id )
		);

		return false !== $result;
	}

	/**
	 * Build a parameterised WHERE clause from equality conditions.
	 *
	 * @param array<string, scalar|null> $conditions      Column => value.
	 * @param bool                       $include_trashed Include soft-deleted rows.
	 * @return array{0: string, 1: array<int, scalar>}
	 */
	protected function build_where( array $conditions, bool $include_trashed ): array {
		$clauses = array();
		$values  = array();

		foreach ( $conditions as $column => $value ) {
			if ( ! $this->is_valid_identifier( $column ) ) {
				continue;
			}
			if ( null === $value ) {
				$clauses[] = "`{$column}` IS NULL";
			} else {
				$clauses[] = "`{$column}` = %s";
				$values[]  = $value;
			}
		}

		if ( $this->soft_deletes && ! $include_trashed ) {
			$clauses[] = 'deleted_at IS NULL';
		}

		$sql = array() === $clauses ? '' : 'WHERE ' . implode( ' AND ', $clauses );

		return array( $sql, $values );
	}

	/**
	 * Build a validated ORDER BY clause.
	 *
	 * @param string $orderby Column name.
	 * @param string $order   ASC|DESC.
	 * @return string
	 */
	protected function build_order( string $orderby, string $order ): string {
		if ( ! $this->is_valid_identifier( $orderby ) ) {
			$orderby = 'id';
		}
		$order = 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';

		return "ORDER BY `{$orderby}` {$order}";
	}

	/**
	 * Drop keys whose names are not valid SQL identifiers.
	 *
	 * @param array<string, mixed> $data Column => value.
	 * @return array<string, mixed>
	 */
	protected function filter_columns( array $data ): array {
		$clean = array();
		foreach ( $data as $column => $value ) {
			if ( $this->is_valid_identifier( $column ) ) {
				$clean[ $column ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Whether a string is a safe SQL identifier (letters, digits, underscore).
	 *
	 * @param string $identifier Candidate identifier.
	 * @return bool
	 */
	protected function is_valid_identifier( string $identifier ): bool {
		return 1 === preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier );
	}
}
