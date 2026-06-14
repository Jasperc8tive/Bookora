<?php
/**
 * AI / heuristic scheduling intelligence.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Scheduling;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\Clock;
use Bookora\Database\Schema;
use Bookora\Staff\StaffServiceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Smart scheduling over the availability engine + appointment history:
 *  - suggest()       — ranked "best" slots for a service across a date range,
 *  - auto_assign()   — pick the least-loaded eligible staff for a slot,
 *  - forecast()      — demand per weekday + a next-N-day forecast,
 *  - workload()      — per-staff upcoming load.
 *
 * Slot scoring is delegated to a {@see SlotScorer} and is also filterable via
 * `bookora_slot_score`, so a Claude-API/ML scorer can be layered on later.
 */
final class SchedulingIntelligence {

	private const MAX_RANGE_DAYS = 60;

	private \wpdb $wpdb;
	private Schema $schema;
	private AvailabilityEngine $availability;
	private StaffServiceRepository $assignments;
	private AppointmentRepository $appointments;
	private Clock $clock;
	private SlotScorer $scorer;

	/**
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $occupy_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Schema                 $schema       Schema helper.
	 * @param AvailabilityEngine     $availability Availability engine.
	 * @param StaffServiceRepository $assignments  Assignment repository.
	 * @param AppointmentRepository  $appointments Appointment repository.
	 * @param Clock                  $clock        Clock.
	 * @param SlotScorer             $scorer       Slot scorer.
	 * @param \wpdb|null             $wpdb         Database handle (defaults to global).
	 */
	public function __construct(
		Schema $schema,
		AvailabilityEngine $availability,
		StaffServiceRepository $assignments,
		AppointmentRepository $appointments,
		Clock $clock,
		SlotScorer $scorer,
		?\wpdb $wpdb = null
	) {
		$this->wpdb         = $wpdb ?? $GLOBALS['wpdb'];
		$this->schema       = $schema;
		$this->availability = $availability;
		$this->assignments  = $assignments;
		$this->appointments = $appointments;
		$this->clock        = $clock;
		$this->scorer       = $scorer;
	}

	/**
	 * Ranked slot suggestions for a service across a date range.
	 *
	 * @param int    $service_id Service id.
	 * @param string $from_date  Range start (Y-m-d).
	 * @param string $to_date    Range end (Y-m-d, inclusive).
	 * @param string $prefer     Preferred band (morning|afternoon|evening|'').
	 * @param int    $limit      Max suggestions.
	 * @return array<int, array{start_local: string, staff_id: int, score: float}>
	 */
	public function suggest( int $service_id, string $from_date, string $to_date, string $prefer = '', int $limit = 5 ): array {
		$today   = (int) strtotime( gmdate( 'Y-m-d' ) );
		$results = array();
		$cursor  = (int) strtotime( $from_date );
		$end     = (int) strtotime( $to_date );
		$guard   = 0;

		while ( $cursor <= $end && $guard < self::MAX_RANGE_DAYS ) {
			++$guard;
			$date = gmdate( 'Y-m-d', $cursor );
			foreach ( $this->availability->for_date( $service_id, null, $date )['slots'] as $slot ) {
				$context = array(
					'prefer'        => $prefer,
					'adjacent'      => $this->is_adjacent( (int) $slot['staff_id'], $date, (string) $slot['start_utc'], (string) $slot['end_utc'] ),
					'days_from_now' => (int) floor( ( $cursor - $today ) / DAY_IN_SECONDS ),
				);
				$score   = $this->scorer->score( $slot, $context );

				/**
				 * Filter a slot's suggestion score.
				 *
				 * @param float                $score   Heuristic score.
				 * @param array<string, mixed> $slot    The slot.
				 * @param array<string, mixed> $context Scoring context.
				 */
				$score = (float) apply_filters( 'bookora_slot_score', $score, $slot, $context );

				$results[] = array(
					'start_local' => (string) $slot['start_local'],
					'staff_id'    => (int) $slot['staff_id'],
					'score'       => round( $score, 1 ),
				);
			}

			$cursor += DAY_IN_SECONDS;
		}

		usort( $results, static fn ( array $a, array $b ): int => $b['score'] <=> $a['score'] );

		return array_slice( $results, 0, max( 1, $limit ) );
	}

	/**
	 * Choose the least-loaded eligible staff for a slot (load balancing).
	 *
	 * @param int    $service_id Service id.
	 * @param string $start      Local start datetime.
	 * @return int|null Suggested staff id, or null if none free.
	 */
	public function auto_assign( int $service_id, string $start ): ?int {
		$date  = substr( $start, 0, 10 );
		$best  = null;
		$least = PHP_INT_MAX;

		foreach ( $this->assignments->staff_ids_for_service( $service_id ) as $staff_id ) {
			$free = false;
			foreach ( $this->availability->for_date( $service_id, $staff_id, $date )['slots'] as $slot ) {
				if ( (string) $slot['start_local'] === $start ) {
					$free = true;
					break;
				}
			}
			if ( ! $free ) {
				continue;
			}
			$load = $this->upcoming_load( $staff_id );
			if ( $load < $least ) {
				$least = $load;
				$best  = $staff_id;
			}
		}

		return $best;
	}

	/**
	 * Per-staff upcoming workload (non-cancelled future appointments).
	 *
	 * @return array<int, array{staff_id: int, name: string, upcoming: int}>
	 */
	public function workload(): array {
		$appts = $this->schema->table( 'appointments' );
		$staff = $this->schema->table( 'staff' );
		$now   = $this->clock->utc_string( time() );
		$sql   = "SELECT st.id AS staff_id, st.display_name AS name,
				COALESCE(SUM(a.id IS NOT NULL), 0) AS upcoming
			FROM `{$staff}` st
			LEFT JOIN `{$appts}` a ON a.staff_id = st.id AND a.deleted_at IS NULL
				AND a.status NOT IN ('cancelled', 'no_show') AND a.start_at >= %s
			WHERE st.status = 'active' AND st.deleted_at IS NULL
			GROUP BY st.id ORDER BY upcoming DESC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $now ), ARRAY_A );

		return array_map(
			static fn ( array $r ): array => array(
				'staff_id' => (int) $r['staff_id'],
				'name'     => (string) $r['name'],
				'upcoming' => (int) $r['upcoming'],
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Demand forecast: average bookings per weekday from history, projected
	 * over the next $days days.
	 *
	 * @param int $days     Days to forecast.
	 * @param int $lookback Lookback window (days) for the average.
	 * @return array{by_weekday: array<int, float>, next_days: array<int, array{date: string, weekday: int, predicted: float}>}
	 */
	public function forecast( int $days = 14, int $lookback = 90 ): array {
		$days     = max( 1, min( 60, $days ) );
		$lookback = max( 7, min( 365, $lookback ) );

		$appts = $this->schema->table( 'appointments' );
		$since = $this->clock->utc_string( time() - $lookback * DAY_IN_SECONDS );
		$sql   = "SELECT (DAYOFWEEK(start_at) - 1) AS dow, COUNT(*) AS cnt
			FROM `{$appts}`
			WHERE deleted_at IS NULL AND status NOT IN ('cancelled', 'no_show') AND start_at >= %s
			GROUP BY dow";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $since ), ARRAY_A );

		$weeks      = max( 1.0, $lookback / 7 );
		$by_weekday = array_fill( 0, 7, 0.0 );
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$by_weekday[ (int) $r['dow'] ] = round( (int) $r['cnt'] / $weeks, 1 );
		}

		$next = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$ts      = time() + $i * DAY_IN_SECONDS;
			$weekday = (int) gmdate( 'w', $ts );
			$next[]  = array(
				'date'      => gmdate( 'Y-m-d', $ts ),
				'weekday'   => $weekday,
				'predicted' => $by_weekday[ $weekday ],
			);
		}

		return array(
			'by_weekday' => $by_weekday,
			'next_days'  => $next,
		);
	}

	/**
	 * Whether a slot is adjacent to an existing appointment (tight packing).
	 *
	 * @param int    $staff_id  Staff id.
	 * @param string $date      Local date.
	 * @param string $start_utc Slot start (UTC).
	 * @param string $end_utc   Slot end (UTC).
	 * @return bool
	 */
	private function is_adjacent( int $staff_id, string $date, string $start_utc, string $end_utc ): bool {
		foreach ( $this->occupying( $staff_id, $date ) as $appt ) {
			if ( (string) $appt['end_at'] === $start_utc || (string) $appt['start_at'] === $end_utc ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cached day appointments for a staff member.
	 *
	 * @param int    $staff_id Staff id.
	 * @param string $date     Local date.
	 * @return array<int, array<string, mixed>>
	 */
	private function occupying( int $staff_id, string $date ): array {
		$key = $staff_id . '|' . $date;
		if ( ! isset( $this->occupy_cache[ $key ] ) ) {
			list( $from, $to )          = $this->clock->day_bounds_utc( $date );
			$this->occupy_cache[ $key ] = $this->appointments->occupying_in_range( $staff_id, $from, $to );
		}

		return $this->occupy_cache[ $key ];
	}

	/**
	 * Count of a staff member's upcoming non-cancelled appointments.
	 *
	 * @param int $staff_id Staff id.
	 * @return int
	 */
	private function upcoming_load( int $staff_id ): int {
		$appts = $this->schema->table( 'appointments' );
		$sql   = "SELECT COUNT(*) FROM `{$appts}`
			WHERE staff_id = %d AND deleted_at IS NULL
				AND status NOT IN ('cancelled', 'no_show') AND start_at >= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $this->wpdb->get_var( $this->wpdb->prepare( $sql, $staff_id, $this->clock->utc_string( time() ) ) );
	}
}
