<?php
/**
 * Service repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Services;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `services` table, with search/filter/pagination.
 */
final class ServiceRepository extends AbstractRepository {

	/**
	 * Columns permitted in ORDER BY.
	 *
	 * @var array<int, string>
	 */
	private const SORTABLE = array( 'id', 'name', 'price', 'duration_min', 'status', 'created_at' );

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'services';
	}

	/**
	 * Search + filter + paginate services.
	 *
	 * @param array{
	 *     search?: string,
	 *     category_id?: int|null,
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

		list( $where, $values ) = $this->build_filters( $args );

		$orderby = in_array( $args['orderby'] ?? '', self::SORTABLE, true ) ? $args['orderby'] : 'name';
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

	/**
	 * Whether any non-deleted service references the given category.
	 *
	 * @param int $category_id Category id.
	 * @return int Count of services in the category.
	 */
	public function count_in_category( int $category_id ): int {
		return $this->count( array( 'category_id' => $category_id ) );
	}

	/**
	 * Build the WHERE clause and bound values for a filter set.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{0: string, 1: array<int, scalar>}
	 */
	private function build_filters( array $args ): array {
		$clauses = array();
		$values  = array();

		if ( empty( $args['include_trashed'] ) ) {
			$clauses[] = 'deleted_at IS NULL';
		}

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = '(name LIKE %s OR description LIKE %s)';
			$values[]  = $like;
			$values[]  = $like;
		}

		if ( ! empty( $args['category_id'] ) ) {
			$clauses[] = 'category_id = %d';
			$values[]  = (int) $args['category_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
			$values[]  = (string) $args['status'];
		}

		$where = array() === $clauses ? '' : 'WHERE ' . implode( ' AND ', $clauses );

		return array( $where, $values );
	}
}
