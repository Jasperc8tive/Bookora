<?php
/**
 * Customer repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Customers;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `customers` table, plus booking-history reads.
 */
final class CustomerRepository extends AbstractRepository {

	/**
	 * Columns permitted in ORDER BY.
	 *
	 * @var array<int, string>
	 */
	private const SORTABLE = array( 'id', 'name', 'email', 'created_at' );

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'customers';
	}

	/**
	 * Search + tag filter + paginate customers.
	 *
	 * @param array{
	 *     search?: string,
	 *     tag?: string,
	 *     orderby?: string,
	 *     order?: string,
	 *     page?: int,
	 *     per_page?: int
	 * } $args Query arguments.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public function paginate( array $args = array() ): array {
		$table    = $this->table();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$clauses = array( 'deleted_at IS NULL' );
		$values  = array();

		if ( ! empty( $args['search'] ) ) {
			$like      = '%' . $this->wpdb->esc_like( (string) $args['search'] ) . '%';
			$clauses[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
		}
		if ( ! empty( $args['tag'] ) ) {
			$clauses[] = 'tags LIKE %s';
			$values[]  = '%' . $this->wpdb->esc_like( (string) $args['tag'] ) . '%';
		}

		$where   = 'WHERE ' . implode( ' AND ', $clauses );
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
	 * Distinct tags across all customers (for filter dropdowns).
	 *
	 * @return array<int, string>
	 */
	public function distinct_tags(): array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$values = $this->wpdb->get_col( "SELECT tags FROM `{$table}` WHERE tags IS NOT NULL AND tags <> '' AND deleted_at IS NULL" );

		$tags = array();
		foreach ( (array) $values as $value ) {
			foreach ( explode( ',', (string) $value ) as $tag ) {
				$tag = trim( $tag );
				if ( '' !== $tag ) {
					$tags[ $tag ] = true;
				}
			}
		}
		$list = array_keys( $tags );
		sort( $list );

		return $list;
	}

	/**
	 * Booking history for a customer (joined with service & staff names).
	 *
	 * @param int $customer_id Customer id.
	 * @param int $limit       Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function bookings_for_customer( int $customer_id, int $limit = 50 ): array {
		$appointments = $this->schema->table( 'appointments' );
		$services     = $this->schema->table( 'services' );
		$staff        = $this->schema->table( 'staff' );

		$sql = "SELECT a.id, a.start_at, a.end_at, a.status, a.total, a.currency,
				s.name AS service_name, st.display_name AS staff_name
			FROM `{$appointments}` a
			LEFT JOIN `{$services}` s ON a.service_id = s.id
			LEFT JOIN `{$staff}` st ON a.staff_id = st.id
			WHERE a.customer_id = %d AND a.deleted_at IS NULL
			ORDER BY a.start_at DESC LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $customer_id, max( 1, $limit ) ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Aggregate booking statistics for a customer.
	 *
	 * @param int $customer_id Customer id.
	 * @return array{count: int, last_at: string|null, total_spent: float}
	 */
	public function booking_stats( int $customer_id ): array {
		$appointments = $this->schema->table( 'appointments' );
		$sql          = "SELECT COUNT(*) AS cnt, MAX(start_at) AS last_at, COALESCE(SUM(total), 0) AS spent
			FROM `{$appointments}` WHERE customer_id = %d AND deleted_at IS NULL";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $customer_id ), ARRAY_A );

		return array(
			'count'       => (int) ( $row['cnt'] ?? 0 ),
			'last_at'     => $row['last_at'] ?? null,
			'total_spent' => (float) ( $row['spent'] ?? 0 ),
		);
	}
}
