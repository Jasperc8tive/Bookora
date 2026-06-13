<?php
/**
 * Reporting / analytics aggregation service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Reports;

use Bookora\Appointments\Clock;
use Bookora\Database\Schema;
use Bookora\Staff\AvailabilityRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only aggregations over appointments for the analytics dashboard:
 * revenue, status/conversion, per-staff, per-service, and utilization.
 *
 * All grouping is done in SQL over the date range (bounded), so reports stay
 * fast even with large appointment volumes. Times are grouped in UTC.
 */
final class ReportService {

	/**
	 * Statuses excluded from revenue.
	 */
	private const NON_REVENUE = "'cancelled'";

	private \wpdb $wpdb;
	private Schema $schema;
	private Clock $clock;
	private AvailabilityRepository $availability;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null             $wpdb         Optional handle.
	 * @param Schema|null            $schema       Optional schema helper.
	 * @param Clock|null             $clock        Optional clock.
	 * @param AvailabilityRepository|null $availability Optional availability repo.
	 */
	public function __construct( ?\wpdb $wpdb = null, ?Schema $schema = null, ?Clock $clock = null, ?AvailabilityRepository $availability = null ) {
		$this->wpdb         = $wpdb ?? $GLOBALS['wpdb'];
		$this->schema       = $schema ?? new Schema( $this->wpdb );
		$this->clock        = $clock ?? new Clock();
		$this->availability = $availability ?? new AvailabilityRepository( $this->wpdb, $this->schema );
	}

	/**
	 * Headline KPIs + breakdowns for a UTC range [from, to).
	 *
	 * @param string $from_utc Range start (UTC).
	 * @param string $to_utc   Range end (UTC, exclusive).
	 * @return array<string, mixed>
	 */
	public function overview( string $from_utc, string $to_utc ): array {
		return array(
			'kpis'           => $this->kpis( $from_utc, $to_utc ),
			'revenue_by_day' => $this->revenue_by_day( $from_utc, $to_utc ),
			'by_status'      => $this->by_status( $from_utc, $to_utc ),
			'by_staff'       => $this->by_staff( $from_utc, $to_utc ),
			'by_service'     => $this->by_service( $from_utc, $to_utc ),
		);
	}

	/**
	 * Headline KPIs.
	 *
	 * @param string $from_utc Range start.
	 * @param string $to_utc   Range end.
	 * @return array<string, mixed>
	 */
	private function kpis( string $from_utc, string $to_utc ): array {
		$appts = $this->schema->table( 'appointments' );
		$sql   = "SELECT
				COUNT(*) AS total,
				SUM(status = 'completed') AS completed,
				SUM(status = 'cancelled') AS cancelled,
				SUM(status = 'no_show') AS no_show,
				SUM(CASE WHEN status <> " . self::NON_REVENUE . " THEN total ELSE 0 END) AS revenue,
				SUM(amount_paid) AS collected
			FROM `{$appts}`
			WHERE deleted_at IS NULL AND start_at >= %s AND start_at < %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $this->wpdb->get_row( $this->wpdb->prepare( $sql, $from_utc, $to_utc ), ARRAY_A );

		$total     = (int) ( $row['total'] ?? 0 );
		$cancelled = (int) ( $row['cancelled'] ?? 0 );
		$no_show   = (int) ( $row['no_show'] ?? 0 );
		$revenue   = (float) ( $row['revenue'] ?? 0 );

		return array(
			'total_bookings'    => $total,
			'completed'         => (int) ( $row['completed'] ?? 0 ),
			'cancelled'         => $cancelled,
			'no_show'           => $no_show,
			'revenue'           => round( $revenue, 2 ),
			'collected'         => round( (float) ( $row['collected'] ?? 0 ), 2 ),
			'cancellation_rate' => $total > 0 ? round( $cancelled / $total * 100, 1 ) : 0.0,
			'no_show_rate'      => $total > 0 ? round( $no_show / $total * 100, 1 ) : 0.0,
			'avg_value'         => $total - $cancelled > 0 ? round( $revenue / ( $total - $cancelled ), 2 ) : 0.0,
		);
	}

	/**
	 * Revenue + booking count grouped by UTC date.
	 *
	 * @param string $from_utc Range start.
	 * @param string $to_utc   Range end.
	 * @return array<int, array{date: string, revenue: float, bookings: int}>
	 */
	private function revenue_by_day( string $from_utc, string $to_utc ): array {
		$appts = $this->schema->table( 'appointments' );
		$sql   = "SELECT DATE(start_at) AS d, SUM(total) AS rev, COUNT(*) AS cnt
			FROM `{$appts}`
			WHERE deleted_at IS NULL AND status <> " . self::NON_REVENUE . ' AND start_at >= %s AND start_at < %s
			GROUP BY DATE(start_at) ORDER BY d ASC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $from_utc, $to_utc ), ARRAY_A );

		return array_map(
			static fn ( array $r ): array => array(
				'date'     => (string) $r['d'],
				'revenue'  => round( (float) $r['rev'], 2 ),
				'bookings' => (int) $r['cnt'],
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Counts grouped by status (conversion funnel).
	 *
	 * @param string $from_utc Range start.
	 * @param string $to_utc   Range end.
	 * @return array<string, int>
	 */
	private function by_status( string $from_utc, string $to_utc ): array {
		$appts = $this->schema->table( 'appointments' );
		$sql   = "SELECT status, COUNT(*) AS cnt FROM `{$appts}`
			WHERE deleted_at IS NULL AND start_at >= %s AND start_at < %s GROUP BY status";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $from_utc, $to_utc ), ARRAY_A );

		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$out[ (string) $r['status'] ] = (int) $r['cnt'];
		}

		return $out;
	}

	/**
	 * Per-staff bookings + revenue.
	 *
	 * @param string $from_utc Range start.
	 * @param string $to_utc   Range end.
	 * @return array<int, array<string, mixed>>
	 */
	private function by_staff( string $from_utc, string $to_utc ): array {
		$appts = $this->schema->table( 'appointments' );
		$staff = $this->schema->table( 'staff' );
		$sql   = 'SELECT a.staff_id, st.display_name AS name, COUNT(*) AS cnt,
				SUM(CASE WHEN a.status <> ' . self::NON_REVENUE . " THEN a.total ELSE 0 END) AS rev
			FROM `{$appts}` a LEFT JOIN `{$staff}` st ON a.staff_id = st.id
			WHERE a.deleted_at IS NULL AND a.start_at >= %s AND a.start_at < %s
			GROUP BY a.staff_id ORDER BY rev DESC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $from_utc, $to_utc ), ARRAY_A );

		return array_map(
			static fn ( array $r ): array => array(
				'staff_id' => (int) $r['staff_id'],
				'name'     => (string) ( $r['name'] ?? '' ),
				'bookings' => (int) $r['cnt'],
				'revenue'  => round( (float) $r['rev'], 2 ),
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Per-service bookings + revenue.
	 *
	 * @param string $from_utc Range start.
	 * @param string $to_utc   Range end.
	 * @return array<int, array<string, mixed>>
	 */
	private function by_service( string $from_utc, string $to_utc ): array {
		$appts    = $this->schema->table( 'appointments' );
		$services = $this->schema->table( 'services' );
		$sql      = 'SELECT a.service_id, s.name AS name, COUNT(*) AS cnt,
				SUM(CASE WHEN a.status <> ' . self::NON_REVENUE . " THEN a.total ELSE 0 END) AS rev
			FROM `{$appts}` a LEFT JOIN `{$services}` s ON a.service_id = s.id
			WHERE a.deleted_at IS NULL AND a.start_at >= %s AND a.start_at < %s
			GROUP BY a.service_id ORDER BY rev DESC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $from_utc, $to_utc ), ARRAY_A );

		return array_map(
			static fn ( array $r ): array => array(
				'service_id' => (int) $r['service_id'],
				'name'       => (string) ( $r['name'] ?? '' ),
				'bookings'   => (int) $r['cnt'],
				'revenue'    => round( (float) $r['rev'], 2 ),
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Per-staff utilisation: booked minutes vs available working minutes.
	 *
	 * @param string $from_date Local start date (Y-m-d).
	 * @param string $to_date   Local end date (Y-m-d, inclusive).
	 * @return array<int, array<string, mixed>>
	 */
	public function utilization( string $from_date, string $to_date ): array {
		list( $from_utc ) = $this->clock->day_bounds_utc( $from_date );
		list( , $to_utc ) = $this->clock->day_bounds_utc( $to_date );

		$appts = $this->schema->table( 'appointments' );
		$staff = $this->schema->table( 'staff' );
		$sql   = "SELECT a.staff_id, st.display_name AS name,
				SUM(TIMESTAMPDIFF(MINUTE, a.start_at, a.end_at)) AS booked
			FROM `{$appts}` a LEFT JOIN `{$staff}` st ON a.staff_id = st.id
			WHERE a.deleted_at IS NULL AND a.status <> " . self::NON_REVENUE . '
				AND a.start_at >= %s AND a.start_at < %s AND a.staff_id IS NOT NULL
			GROUP BY a.staff_id';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $from_utc, $to_utc ), ARRAY_A );

		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$staff_id  = (int) $r['staff_id'];
			$booked    = (int) $r['booked'];
			$available = $this->available_minutes( $staff_id, $from_date, $to_date );
			$out[]     = array(
				'staff_id'          => $staff_id,
				'name'              => (string) ( $r['name'] ?? '' ),
				'booked_minutes'    => $booked,
				'available_minutes' => $available,
				'utilization'       => $available > 0 ? round( min( 100, $booked / $available * 100 ), 1 ) : 0.0,
			);
		}

		return $out;
	}

	/**
	 * Available working minutes for a staff member across a date range.
	 *
	 * @param int    $staff_id  Staff id.
	 * @param string $from_date Local start date.
	 * @param string $to_date   Local end date (inclusive).
	 * @return int
	 */
	private function available_minutes( int $staff_id, string $from_date, string $to_date ): int {
		$entries  = $this->availability->for_staff( $staff_id );
		$by_day   = array();
		$time_off = array();
		foreach ( $entries as $e ) {
			if ( 'working_hours' === $e['type'] ) {
				$mins                          = ( strtotime( (string) $e['end_time'] ) - strtotime( (string) $e['start_time'] ) ) / 60;
				$by_day[ (int) $e['weekday'] ] = ( $by_day[ (int) $e['weekday'] ] ?? 0 ) + max( 0, (int) $mins );
			} elseif ( in_array( (string) $e['type'], array( 'time_off', 'holiday' ), true ) ) {
				$time_off[] = array( (string) $e['start_date'], (string) $e['end_date'] );
			}
		}

		$total  = 0;
		$cursor = strtotime( $from_date );
		$end    = strtotime( $to_date );
		$guard  = 0;
		while ( $cursor <= $end && $guard < 400 ) {
			$date    = gmdate( 'Y-m-d', $cursor );
			$weekday = (int) gmdate( 'w', $cursor );
			if ( ! $this->is_off( $date, $time_off ) ) {
				$total += (int) ( $by_day[ $weekday ] ?? 0 );
			}
			$cursor += DAY_IN_SECONDS;
			++$guard;
		}

		return $total;
	}

	/**
	 * Whether a date falls within any time-off/holiday range.
	 *
	 * @param string                       $date  Date (Y-m-d).
	 * @param array<int, array{0:string,1:string}> $ranges Off ranges.
	 * @return bool
	 */
	private function is_off( string $date, array $ranges ): bool {
		foreach ( $ranges as $range ) {
			if ( $date >= $range[0] && $date <= $range[1] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a CSV export for a report type.
	 *
	 * @param string $type     One of "revenue", "staff", "service".
	 * @param string $from_utc Range start.
	 * @param string $to_utc   Range end.
	 * @return string CSV content.
	 */
	public function export_csv( string $type, string $from_utc, string $to_utc ): string {
		$rows = match ( $type ) {
			'staff'   => array_map(
				static fn ( array $r ): array => array( $r['name'], $r['bookings'], $r['revenue'] ),
				$this->by_staff( $from_utc, $to_utc )
			),
			'service' => array_map(
				static fn ( array $r ): array => array( $r['name'], $r['bookings'], $r['revenue'] ),
				$this->by_service( $from_utc, $to_utc )
			),
			default   => array_map(
				static fn ( array $r ): array => array( $r['date'], $r['bookings'], $r['revenue'] ),
				$this->revenue_by_day( $from_utc, $to_utc )
			),
		};
		$header = 'staff' === $type || 'service' === $type ? array( 'Name', 'Bookings', 'Revenue' ) : array( 'Date', 'Bookings', 'Revenue' );

		$lines = array( implode( ',', $header ) );
		foreach ( $rows as $row ) {
			$lines[] = implode( ',', array_map( static fn ( $v ): string => '"' . str_replace( '"', '""', (string) $v ) . '"', $row ) );
		}

		return implode( "\n", $lines ) . "\n";
	}
}
