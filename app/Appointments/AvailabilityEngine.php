<?php
/**
 * Availability calculation engine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

use Bookora\Services\ServiceRepository;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffServiceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Computes bookable slots for a service on a date by composing staff working
 * hours minus breaks, time off / holidays, existing appointments (with
 * buffers), and active holds — honouring notice windows, buffers, and group
 * capacity. All math is done in epoch seconds; results carry UTC + local times.
 */
final class AvailabilityEngine {

	/**
	 * Service repository.
	 *
	 * @var ServiceRepository
	 */
	private ServiceRepository $services;

	/**
	 * Staff availability repository.
	 *
	 * @var AvailabilityRepository
	 */
	private AvailabilityRepository $availability;

	/**
	 * Staff↔service assignments.
	 *
	 * @var StaffServiceRepository
	 */
	private StaffServiceRepository $assignments;

	/**
	 * Appointment repository.
	 *
	 * @var AppointmentRepository
	 */
	private AppointmentRepository $appointments;

	/**
	 * Hold repository.
	 *
	 * @var BookingHoldRepository
	 */
	private BookingHoldRepository $holds;

	/**
	 * Clock.
	 *
	 * @var Clock
	 */
	private Clock $clock;

	/**
	 * Constructor.
	 *
	 * @param ServiceRepository      $services     Service repository.
	 * @param AvailabilityRepository $availability Staff availability repository.
	 * @param StaffServiceRepository $assignments  Assignment repository.
	 * @param AppointmentRepository  $appointments Appointment repository.
	 * @param BookingHoldRepository  $holds        Hold repository.
	 * @param Clock                  $clock        Clock.
	 */
	public function __construct(
		ServiceRepository $services,
		AvailabilityRepository $availability,
		StaffServiceRepository $assignments,
		AppointmentRepository $appointments,
		BookingHoldRepository $holds,
		Clock $clock
	) {
		$this->services     = $services;
		$this->availability = $availability;
		$this->assignments  = $assignments;
		$this->appointments = $appointments;
		$this->holds        = $holds;
		$this->clock        = $clock;
	}

	/**
	 * Compute slots for a service on a local date.
	 *
	 * @param int      $service_id Service id.
	 * @param int|null $staff_id   Specific staff, or null for "any assigned".
	 * @param string   $date       Local date (Y-m-d).
	 * @return array{date: string, service_id: int, slots: array<int, array<string, mixed>>}
	 */
	public function for_date( int $service_id, ?int $staff_id, string $date ): array {
		$service = $this->services->find( $service_id );
		$result  = array(
			'date'       => $date,
			'service_id' => $service_id,
			'slots'      => array(),
		);

		if ( null === $service || 'active' !== ( $service['status'] ?? '' ) ) {
			return $result;
		}

		$staff_ids = null !== $staff_id
			? array( $staff_id )
			: $this->assignments->staff_ids_for_service( $service_id );

		$slots = array();
		foreach ( $staff_ids as $sid ) {
			foreach ( $this->staff_slots( (int) $sid, $service, $date ) as $slot ) {
				$slots[] = $slot;
			}
		}

		usort(
			$slots,
			static function ( array $a, array $b ): int {
				$by_time = $a['start_utc'] <=> $b['start_utc'];

				return 0 !== $by_time ? $by_time : $a['staff_id'] <=> $b['staff_id'];
			}
		);

		$result['slots'] = $slots;

		return $result;
	}

	/**
	 * Compute one staff member's slots for a date.
	 *
	 * @param int                  $staff_id Staff id.
	 * @param array<string, mixed> $service  Service row.
	 * @param string               $date     Local date (Y-m-d).
	 * @return array<int, array<string, mixed>>
	 */
	private function staff_slots( int $staff_id, array $service, string $date ): array {
		$entries = $this->availability->for_staff( $staff_id );

		if ( $this->is_day_off( $entries, $date ) ) {
			return array();
		}

		$weekday = $this->clock->weekday( $date );
		$blocks  = $this->working_intervals( $entries, $weekday, $date );
		if ( array() === $blocks ) {
			return array();
		}

		$duration = max( 1, (int) $service['duration_min'] ) * MINUTE_IN_SECONDS;
		$before   = (int) $service['buffer_before_min'];
		$after    = (int) $service['buffer_after_min'];
		$capacity = max( 1, (int) $service['capacity'] );

		// Notice window.
		$now        = time();
		$min_notice = max( (int) $service['min_notice_min'], (int) $service['lead_time_min'] ) * MINUTE_IN_SECONDS;
		$earliest   = $now + $min_notice;
		$latest     = null !== $service['max_notice_min'] ? $now + ( (int) $service['max_notice_min'] * MINUTE_IN_SECONDS ) : null;

		// Existing appointments + holds for the day.
		list( $day_from, $day_to ) = $this->clock->day_bounds_utc( $date );
		$day_appts                 = $this->appointments->occupying_in_range( $staff_id, $day_from, $day_to );
		$now_utc                   = $this->clock->utc_string( $now );
		$day_holds                 = $this->holds->active_in_range( $staff_id, $day_from, $day_to, $now_utc );

		$slots = array();
		foreach ( $blocks as $block ) {
			for ( $start = $block[0]; $start + $duration <= $block[1]; $start += $duration ) {
				$end = $start + $duration;

				if ( $start < $earliest || ( null !== $latest && $start > $latest ) ) {
					continue;
				}
				if ( $this->blocked( $start, $end, $before, $after, (int) $service['id'], $capacity, $day_appts, $day_holds ) ) {
					continue;
				}

				$slots[] = array(
					'staff_id'    => $staff_id,
					'start_utc'   => $this->clock->utc_string( $start ),
					'end_utc'     => $this->clock->utc_string( $end ),
					'start_local' => $this->clock->local_string( $start ),
				);
			}
		}

		return $slots;
	}

	/**
	 * Whether a candidate is blocked by appointments, capacity, or holds.
	 *
	 * @param int                              $start      Candidate start epoch.
	 * @param int                              $end        Candidate end epoch.
	 * @param int                              $before     Service before-buffer minutes.
	 * @param int                              $after      Service after-buffer minutes.
	 * @param int                              $service_id Candidate service id.
	 * @param int                              $capacity   Service capacity.
	 * @param array<int, array<string, mixed>> $appts      Day appointments.
	 * @param array<int, array<string, mixed>> $holds      Day holds.
	 * @return bool
	 */
	private function blocked( int $start, int $end, int $before, int $after, int $service_id, int $capacity, array $appts, array $holds ): bool {
		$cand_start   = $start - ( $before * MINUTE_IN_SECONDS );
		$cand_end     = $end + ( $after * MINUTE_IN_SECONDS );
		$same_overlap = 0;

		foreach ( $appts as $appt ) {
			$raw_start = $this->clock->utc_to_epoch( (string) $appt['start_at'] );
			$raw_end   = $this->clock->utc_to_epoch( (string) $appt['end_at'] );

			if ( $capacity > 1 && (int) $appt['service_id'] === $service_id ) {
				if ( ConflictDetector::overlaps( $start, $end, $raw_start, $raw_end ) ) {
					++$same_overlap;
				}
				continue;
			}

			$a_start = $raw_start - ( (int) $appt['buffer_before_min'] * MINUTE_IN_SECONDS );
			$a_end   = $raw_end + ( (int) $appt['buffer_after_min'] * MINUTE_IN_SECONDS );
			if ( ConflictDetector::overlaps( $cand_start, $cand_end, $a_start, $a_end ) ) {
				return true;
			}
		}

		if ( $capacity > 1 && $same_overlap >= $capacity ) {
			return true;
		}

		foreach ( $holds as $hold ) {
			$h_start = $this->clock->utc_to_epoch( (string) $hold['start_at'] );
			$h_end   = $this->clock->utc_to_epoch( (string) $hold['end_at'] );
			if ( ConflictDetector::overlaps( $start, $end, $h_start, $h_end ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the date falls within a time-off or holiday entry.
	 *
	 * @param array<int, array<string, mixed>> $entries Availability entries.
	 * @param string                           $date    Local date (Y-m-d).
	 * @return bool
	 */
	private function is_day_off( array $entries, string $date ): bool {
		foreach ( $entries as $entry ) {
			if ( ! in_array( $entry['type'], array( 'time_off', 'holiday' ), true ) ) {
				continue;
			}
			$from = (string) ( $entry['start_date'] ?? '' );
			$to   = (string) ( $entry['end_date'] ?? $from );
			if ( '' !== $from && $date >= $from && $date <= $to ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Working intervals (epoch) for a weekday, minus breaks.
	 *
	 * @param array<int, array<string, mixed>> $entries Availability entries.
	 * @param int                              $weekday Weekday 0–6.
	 * @param string                           $date    Local date (Y-m-d).
	 * @return array<int, array{0: int, 1: int}>
	 */
	private function working_intervals( array $entries, int $weekday, string $date ): array {
		$blocks = array();
		$breaks = array();

		foreach ( $entries as $entry ) {
			if ( (int) ( $entry['weekday'] ?? -1 ) !== $weekday ) {
				continue;
			}
			$start = $this->clock->epoch( $date . ' ' . (string) $entry['start_time'] );
			$end   = $this->clock->epoch( $date . ' ' . (string) $entry['end_time'] );
			if ( $end <= $start ) {
				continue;
			}
			if ( 'working_hours' === $entry['type'] ) {
				$blocks[] = array( $start, $end );
			} elseif ( 'break' === $entry['type'] ) {
				$breaks[] = array( $start, $end );
			}
		}

		$result = array();
		foreach ( $blocks as $block ) {
			foreach ( $this->subtract_breaks( $block, $breaks ) as $piece ) {
				$result[] = $piece;
			}
		}

		return $result;
	}

	/**
	 * Subtract break intervals from a working block, returning the remaining pieces.
	 *
	 * @param array{0: int, 1: int}             $block  Working block.
	 * @param array<int, array{0: int, 1: int}> $breaks Break intervals.
	 * @return array<int, array{0: int, 1: int}>
	 */
	private function subtract_breaks( array $block, array $breaks ): array {
		$pieces = array( $block );

		foreach ( $breaks as $break ) {
			$next = array();
			foreach ( $pieces as $piece ) {
				if ( ! ConflictDetector::overlaps( $piece[0], $piece[1], $break[0], $break[1] ) ) {
					$next[] = $piece;
					continue;
				}
				if ( $break[0] > $piece[0] ) {
					$next[] = array( $piece[0], $break[0] );
				}
				if ( $break[1] < $piece[1] ) {
					$next[] = array( $break[1], $piece[1] );
				}
			}
			$pieces = $next;
		}

		return $pieces;
	}
}
