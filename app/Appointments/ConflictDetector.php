<?php
/**
 * Buffer-aware conflict detection.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether a candidate interval clashes with existing appointments
 * (respecting each side's buffers) or active holds, working in epoch seconds.
 */
final class ConflictDetector {

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
	 * @param AppointmentRepository $appointments Appointment repository.
	 * @param BookingHoldRepository $holds        Hold repository.
	 * @param Clock                 $clock        Clock.
	 */
	public function __construct( AppointmentRepository $appointments, BookingHoldRepository $holds, Clock $clock ) {
		$this->appointments = $appointments;
		$this->holds        = $holds;
		$this->clock        = $clock;
	}

	/**
	 * Whether two half-open intervals [a1,a2) and [b1,b2) overlap.
	 *
	 * @param int $a1 Start of A.
	 * @param int $a2 End of A.
	 * @param int $b1 Start of B.
	 * @param int $b2 End of B.
	 * @return bool
	 */
	public static function overlaps( int $a1, int $a2, int $b1, int $b2 ): bool {
		return $a1 < $b2 && $a2 > $b1;
	}

	/**
	 * Whether a candidate [start,end) epoch interval conflicts for a staff member.
	 *
	 * @param int      $staff_id       Staff id.
	 * @param int      $start          Candidate start epoch.
	 * @param int      $end            Candidate end epoch.
	 * @param int      $buffer_before  Candidate service before-buffer, minutes.
	 * @param int      $buffer_after   Candidate service after-buffer, minutes.
	 * @param int|null $ignore_id      Appointment id to ignore (when rescheduling).
	 * @param string   $session_token  Hold session token to ignore (its own hold).
	 * @return bool
	 */
	public function has_conflict( int $staff_id, int $start, int $end, int $buffer_before = 0, int $buffer_after = 0, ?int $ignore_id = null, string $session_token = '' ): bool {
		$cand_start = $start - ( $buffer_before * MINUTE_IN_SECONDS );
		$cand_end   = $end + ( $buffer_after * MINUTE_IN_SECONDS );

		$from = $this->clock->utc_string( $cand_start );
		$to   = $this->clock->utc_string( $cand_end );

		foreach ( $this->appointments->occupying_in_range( $staff_id, $from, $to ) as $appt ) {
			if ( null !== $ignore_id && (int) $appt['id'] === $ignore_id ) {
				continue;
			}
			$a_start = $this->clock->utc_to_epoch( (string) $appt['start_at'] ) - ( (int) $appt['buffer_before_min'] * MINUTE_IN_SECONDS );
			$a_end   = $this->clock->utc_to_epoch( (string) $appt['end_at'] ) + ( (int) $appt['buffer_after_min'] * MINUTE_IN_SECONDS );
			if ( self::overlaps( $cand_start, $cand_end, $a_start, $a_end ) ) {
				return true;
			}
		}

		$now = $this->clock->utc_string( time() );
		foreach ( $this->holds->active_in_range( $staff_id, $from, $to, $now, $session_token ) as $hold ) {
			$h_start = $this->clock->utc_to_epoch( (string) $hold['start_at'] );
			$h_end   = $this->clock->utc_to_epoch( (string) $hold['end_at'] );
			if ( self::overlaps( $start, $end, $h_start, $h_end ) ) {
				return true;
			}
		}

		return false;
	}
}
