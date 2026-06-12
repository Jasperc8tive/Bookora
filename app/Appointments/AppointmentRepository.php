<?php
/**
 * Appointment repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `appointments` table.
 */
final class AppointmentRepository extends AbstractRepository {

	/**
	 * Statuses that do not occupy a slot.
	 */
	private const FREE_STATUSES = "'cancelled','no_show'";

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'appointments';
	}

	/**
	 * Occupying appointments for a staff member overlapping a UTC window,
	 * joined with each service's buffers.
	 *
	 * @param int    $staff_id Staff id.
	 * @param string $from_utc Window start (UTC, inclusive-ish).
	 * @param string $to_utc   Window end (UTC).
	 * @return array<int, array<string, mixed>>
	 */
	public function occupying_in_range( int $staff_id, string $from_utc, string $to_utc ): array {
		$appointments = $this->table();
		$services     = $this->schema->table( 'services' );

		$sql = "SELECT a.id, a.service_id, a.status, a.start_at, a.end_at,
				COALESCE(s.buffer_before_min, 0) AS buffer_before_min,
				COALESCE(s.buffer_after_min, 0) AS buffer_after_min
			FROM `{$appointments}` a
			LEFT JOIN `{$services}` s ON a.service_id = s.id
			WHERE a.staff_id = %d AND a.deleted_at IS NULL
				AND a.status NOT IN ( " . self::FREE_STATUSES . ' )
				AND a.start_at < %s AND a.end_at > %s
			ORDER BY a.start_at ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $staff_id, $to_utc, $from_utc ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Calendar feed: appointments overlapping a UTC range, joined with service,
	 * staff, and customer display data.
	 *
	 * @param string $from_utc Range start (UTC).
	 * @param string $to_utc   Range end (UTC).
	 * @param array{staff_id?: int, status?: string, service_id?: int} $filters Optional filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function calendar( string $from_utc, string $to_utc, array $filters = array() ): array {
		$appointments = $this->table();
		$services     = $this->schema->table( 'services' );
		$staff        = $this->schema->table( 'staff' );
		$customers    = $this->schema->table( 'customers' );

		$clauses = array( 'a.deleted_at IS NULL', 'a.start_at < %s', 'a.end_at > %s' );
		$values  = array( $to_utc, $from_utc );

		if ( ! empty( $filters['staff_id'] ) ) {
			$clauses[] = 'a.staff_id = %d';
			$values[]  = (int) $filters['staff_id'];
		}
		if ( ! empty( $filters['service_id'] ) ) {
			$clauses[] = 'a.service_id = %d';
			$values[]  = (int) $filters['service_id'];
		}
		if ( ! empty( $filters['status'] ) ) {
			$clauses[] = 'a.status = %s';
			$values[]  = (string) $filters['status'];
		}

		$where = implode( ' AND ', $clauses );
		$sql   = "SELECT a.id, a.start_at, a.end_at, a.status, a.staff_id, a.service_id, a.customer_id,
				a.total, a.currency,
				s.name AS service_name,
				st.display_name AS staff_name, st.color AS staff_color,
				c.name AS customer_name
			FROM `{$appointments}` a
			LEFT JOIN `{$services}` s ON a.service_id = s.id
			LEFT JOIN `{$staff}` st ON a.staff_id = st.id
			LEFT JOIN `{$customers}` c ON a.customer_id = c.id
			WHERE {$where}
			ORDER BY a.start_at ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $values ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Find an appointment by idempotency key.
	 *
	 * @param string $key Idempotency key.
	 * @return array<string, mixed>|null
	 */
	public function find_by_idempotency( string $key ): ?array {
		return $this->find_by( array( 'idempotency_key' => $key ) );
	}

	/**
	 * Search + filter + paginate appointments.
	 *
	 * @param array{
	 *     staff_id?: int,
	 *     customer_id?: int,
	 *     status?: string,
	 *     from?: string,
	 *     to?: string,
	 *     page?: int,
	 *     per_page?: int
	 * } $args Query arguments (from/to are UTC datetimes).
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public function paginate( array $args = array() ): array {
		$table    = $this->table();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$clauses = array( 'deleted_at IS NULL' );
		$values  = array();

		foreach ( array( 'staff_id', 'customer_id' ) as $col ) {
			if ( ! empty( $args[ $col ] ) ) {
				$clauses[] = "{$col} = %d";
				$values[]  = (int) $args[ $col ];
			}
		}
		if ( ! empty( $args['status'] ) ) {
			$clauses[] = 'status = %s';
			$values[]  = (string) $args['status'];
		}
		if ( ! empty( $args['from'] ) ) {
			$clauses[] = 'start_at >= %s';
			$values[]  = (string) $args['from'];
		}
		if ( ! empty( $args['to'] ) ) {
			$clauses[] = 'start_at < %s';
			$values[]  = (string) $args['to'];
		}

		$where = 'WHERE ' . implode( ' AND ', $clauses );

		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$total = (int) ( array() === $values ? $this->wpdb->get_var( $count_sql ) : $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, $values ) ) );

		$list_sql    = "SELECT * FROM `{$table}` {$where} ORDER BY start_at ASC LIMIT %d OFFSET %d";
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
