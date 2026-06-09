<?php
/**
 * Staff repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Staff;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `staff` table, with search/filter/pagination.
 */
final class StaffRepository extends AbstractRepository {

	/**
	 * Columns permitted in ORDER BY.
	 *
	 * @var array<int, string>
	 */
	private const SORTABLE = array( 'id', 'display_name', 'status', 'created_at' );

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'staff';
	}

	/**
	 * Search + filter + paginate staff.
	 *
	 * @param array{
	 *     search?: string,
	 *     status?: string,
	 *     orderby?: string,
	 *     order?: string,
	 *     page?: int,
	 *     per_page?: int,
	 *     include_trashed?: bool
	 * } $args Query arguments.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public function paginate( array $args = array() ): array {
		$table    = $this->table();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$clauses = array();
		$values  = array();

		if ( empty( $args['include_trashed'] ) ) {
			$clauses[] = 'deleted_at IS NULL';
		}
		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = '(display_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
		}
		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
			$values[]  = (string) $args['status'];
		}

		$where   = array() === $clauses ? '' : 'WHERE ' . implode( ' AND ', $clauses );
		$orderby = in_array( $args['orderby'] ?? '', self::SORTABLE, true ) ? $args['orderby'] : 'display_name';
		$order   = 'DESC' === strtoupper( (string) ( $args['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC';

		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) ( array() === $values ? $this->wpdb->get_var( $count_sql ) : $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, $values ) ) );

		$list_sql    = "SELECT * FROM `{$table}` {$where} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
		$list_values = array_merge( $values, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$items = $this->wpdb->get_results( $this->wpdb->prepare( $list_sql, $list_values ), ARRAY_A );

		return array(
			'items'       => is_array( $items ) ? $items : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}
}
