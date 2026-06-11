<?php
/**
 * Booking engine — the scheduling brain.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Appointments;

use Bookora\Customers\CustomerRepository;
use Bookora\Security\ActivityLogger;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;
use DateTimeImmutable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, reschedules, and cancels appointments with buffer-aware conflict
 * detection, timezone handling, group capacity, recurring series, idempotency,
 * and per-staff serialization to keep concurrent bookings race-safe.
 */
final class BookingEngine {

	/**
	 * Maximum occurrences generated for a recurring series.
	 */
	private const MAX_OCCURRENCES = 60;

	/**
	 * Bookable statuses accepted on create.
	 *
	 * @var array<int, string>
	 */
	private const STATUSES = array( 'pending', 'confirmed' );

	private ServiceRepository $services;
	private StaffRepository $staff;
	private CustomerRepository $customers;
	private AppointmentRepository $appointments;
	private BookingHoldRepository $holds;
	private ConflictDetector $conflicts;
	private Clock $clock;
	private ActivityLogger $audit;
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param ServiceRepository     $services     Service repository.
	 * @param StaffRepository       $staff        Staff repository.
	 * @param CustomerRepository    $customers    Customer repository.
	 * @param AppointmentRepository $appointments Appointment repository.
	 * @param BookingHoldRepository $holds        Hold repository.
	 * @param ConflictDetector      $conflicts    Conflict detector.
	 * @param Clock                 $clock        Clock.
	 * @param ActivityLogger        $audit        Audit logger.
	 */
	public function __construct(
		ServiceRepository $services,
		StaffRepository $staff,
		CustomerRepository $customers,
		AppointmentRepository $appointments,
		BookingHoldRepository $holds,
		ConflictDetector $conflicts,
		Clock $clock,
		ActivityLogger $audit
	) {
		$this->services     = $services;
		$this->staff        = $staff;
		$this->customers    = $customers;
		$this->appointments = $appointments;
		$this->holds        = $holds;
		$this->conflicts    = $conflicts;
		$this->clock        = $clock;
		$this->audit        = $audit;
		$this->wpdb         = $GLOBALS['wpdb'];
	}

	/**
	 * Create one appointment or a recurring series.
	 *
	 * @param array<string, mixed> $input Booking input.
	 * @return array{created: array<int, array<string, mixed>>, skipped: array<int, array<string, string>>}|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		$service = $this->services->find( (int) ( $input['service_id'] ?? 0 ) );
		if ( null === $service || 'active' !== ( $service['status'] ?? '' ) ) {
			return new WP_Error( 'bookora_invalid_service', __( 'Service not found or inactive.', 'bookora' ), array( 'status' => 422 ) );
		}

		$staff_id    = (int) ( $input['staff_id'] ?? 0 );
		$customer_id = (int) ( $input['customer_id'] ?? 0 );
		if ( null === $this->staff->find( $staff_id ) ) {
			return new WP_Error( 'bookora_invalid_staff', __( 'Staff member not found.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( null === $this->customers->find( $customer_id ) ) {
			return new WP_Error( 'bookora_invalid_customer', __( 'Customer not found.', 'bookora' ), array( 'status' => 422 ) );
		}

		$base = $this->parse_local( (string) ( $input['start'] ?? '' ) );
		if ( null === $base ) {
			return new WP_Error( 'bookora_invalid_start', __( 'Invalid start date/time.', 'bookora' ), array( 'status' => 422 ) );
		}

		$status = in_array( $input['status'] ?? '', self::STATUSES, true ) ? (string) $input['status'] : 'confirmed';
		$starts = $this->occurrences( $base, $input['recurrence'] ?? null );

		$created = array();
		$skipped = array();

		$this->with_staff_lock(
			$staff_id,
			function () use ( $service, $staff_id, $customer_id, $starts, $status, $input, &$created, &$skipped ): void {
				$parent_id = null;
				$single    = count( $starts ) === 1;

				foreach ( $starts as $start ) {
					$id = $this->book_one(
						$service,
						$staff_id,
						$customer_id,
						$start,
						$status,
						$single ? (string) ( $input['idempotency_key'] ?? '' ) : '',
						(string) ( $input['session_token'] ?? '' ),
						$parent_id,
						(int) ( $input['location_id'] ?? 0 )
					);

					if ( null === $id ) {
						$skipped[] = array(
							'start'  => $this->clock->local_string( $start ),
							'reason' => 'conflict',
						);
						continue;
					}
					$row       = (array) $this->appointments->find( $id );
					$created[] = $row;
					if ( null === $parent_id ) {
						$parent_id = $id;
					}
				}
			}
		);

		if ( '' !== (string) ( $input['session_token'] ?? '' ) ) {
			$this->holds->delete_by_token( (string) $input['session_token'] );
		}

		if ( array() === $created ) {
			return new WP_Error( 'bookora_conflict', __( 'That time is no longer available.', 'bookora' ), array( 'status' => 409 ) );
		}

		$this->audit->log(
			'booking.created',
			array(
				'entity_type' => 'appointment',
				'entity_id'   => (int) $created[0]['id'],
				'context'     => array( 'count' => count( $created ) ),
			)
		);

		return array(
			'created' => $created,
			'skipped' => $skipped,
		);
	}

	/**
	 * Reschedule an appointment to a new time and/or staff member.
	 *
	 * @param int                  $id    Appointment id.
	 * @param array<string, mixed> $input New start and/or staff_id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function reschedule( int $id, array $input ): array|WP_Error {
		$appt = $this->appointments->find( $id );
		if ( null === $appt ) {
			return new WP_Error( 'bookora_not_found', __( 'Appointment not found.', 'bookora' ), array( 'status' => 404 ) );
		}

		$service = $this->services->find( (int) $appt['service_id'] );
		if ( null === $service ) {
			return new WP_Error( 'bookora_invalid_service', __( 'Service no longer exists.', 'bookora' ), array( 'status' => 422 ) );
		}

		$staff_id = (int) ( $input['staff_id'] ?? $appt['staff_id'] );
		$start    = isset( $input['start'] ) ? $this->parse_local( (string) $input['start'] ) : $this->clock->utc_to_epoch( (string) $appt['start_at'] );
		if ( null === $start ) {
			return new WP_Error( 'bookora_invalid_start', __( 'Invalid start date/time.', 'bookora' ), array( 'status' => 422 ) );
		}

		$end = $start + ( max( 1, (int) $service['duration_min'] ) * MINUTE_IN_SECONDS );

		$taken = $this->with_staff_lock(
			$staff_id,
			fn (): bool => $this->slot_taken( $service, $staff_id, $start, $end, $id, '' )
				? true
				: ! $this->appointments->update(
					$id,
					array(
						'staff_id' => $staff_id,
						'start_at' => $this->clock->utc_string( $start ),
						'end_at'   => $this->clock->utc_string( $end ),
					)
				)
		);

		if ( $taken ) {
			return new WP_Error( 'bookora_conflict', __( 'That time is not available.', 'bookora' ), array( 'status' => 409 ) );
		}

		$this->audit->log(
			'booking.rescheduled',
			array(
				'entity_type' => 'appointment',
				'entity_id'   => $id,
			)
		);

		return (array) $this->appointments->find( $id );
	}

	/**
	 * Cancel an appointment.
	 *
	 * @param int    $id     Appointment id.
	 * @param string $reason Optional reason.
	 * @return bool
	 */
	public function cancel( int $id, string $reason = '' ): bool {
		$appt = $this->appointments->find( $id );
		if ( null === $appt ) {
			return false;
		}

		$this->appointments->update( $id, array( 'status' => 'cancelled' ) );
		$this->audit->log(
			'booking.cancelled',
			array(
				'entity_type' => 'appointment',
				'entity_id'   => $id,
				'context'     => array( 'reason' => sanitize_text_field( $reason ) ),
			)
		);

		return true;
	}

	/**
	 * Place a short-lived hold on a slot (used by the checkout wizard).
	 *
	 * @param int    $service_id Service id.
	 * @param int    $staff_id   Staff id.
	 * @param string $start      Local start datetime.
	 * @param int    $ttl        Time-to-live in seconds.
	 * @return array{token: string, expires_at: string}|WP_Error
	 */
	public function hold( int $service_id, int $staff_id, string $start, int $ttl = 600 ): array|WP_Error {
		$service = $this->services->find( $service_id );
		if ( null === $service ) {
			return new WP_Error( 'bookora_invalid_service', __( 'Service not found.', 'bookora' ), array( 'status' => 422 ) );
		}
		$epoch = $this->parse_local( $start );
		if ( null === $epoch ) {
			return new WP_Error( 'bookora_invalid_start', __( 'Invalid start.', 'bookora' ), array( 'status' => 422 ) );
		}
		$end = $epoch + ( max( 1, (int) $service['duration_min'] ) * MINUTE_IN_SECONDS );

		if ( $this->slot_taken( $service, $staff_id, $epoch, $end, null, '' ) ) {
			return new WP_Error( 'bookora_conflict', __( 'That time is not available.', 'bookora' ), array( 'status' => 409 ) );
		}

		$token   = wp_generate_password( 32, false, false );
		$expires = time() + max( 60, $ttl );
		$this->holds->create(
			array(
				'service_id'    => $service_id,
				'staff_id'      => $staff_id,
				'start_at'      => $this->clock->utc_string( $epoch ),
				'end_at'        => $this->clock->utc_string( $end ),
				'session_token' => $token,
				'expires_at'    => $this->clock->utc_string( $expires ),
			)
		);

		return array(
			'token'      => $token,
			'expires_at' => $this->clock->utc_string( $expires ),
		);
	}

	/**
	 * Book a single occurrence; returns the new id or null on conflict.
	 *
	 * @param array<string, mixed> $service     Service row.
	 * @param int                  $staff_id    Staff id.
	 * @param int                  $customer_id Customer id.
	 * @param int                  $start       Start epoch.
	 * @param string               $status      Status.
	 * @param string               $idempotency Idempotency key (single bookings only).
	 * @param string               $token       Hold session token to ignore.
	 * @param int|null             $parent_id   Parent recurring id.
	 * @param int                  $location_id Location id.
	 * @return int|null
	 */
	private function book_one( array $service, int $staff_id, int $customer_id, int $start, string $status, string $idempotency, string $token, ?int $parent_id, int $location_id ): ?int {
		if ( '' !== $idempotency ) {
			$existing = $this->appointments->find_by_idempotency( $idempotency );
			if ( null !== $existing ) {
				return (int) $existing['id'];
			}
		}

		$end = $start + ( max( 1, (int) $service['duration_min'] ) * MINUTE_IN_SECONDS );
		if ( $this->slot_taken( $service, $staff_id, $start, $end, null, $token ) ) {
			return null;
		}

		$price = (float) $service['price'];

		return $this->appointments->create(
			array(
				'customer_id'         => $customer_id,
				'service_id'          => (int) $service['id'],
				'staff_id'            => $staff_id,
				'location_id'         => $location_id > 0 ? $location_id : null,
				'start_at'            => $this->clock->utc_string( $start ),
				'end_at'              => $this->clock->utc_string( $end ),
				'status'              => $status,
				'price'               => $price,
				'total'               => $price,
				'balance_due'         => $price,
				'currency'            => (string) $service['currency'],
				'source'              => 'admin',
				'idempotency_key'     => '' !== $idempotency ? $idempotency : null,
				'parent_recurring_id' => $parent_id,
			)
		);
	}

	/**
	 * Whether a slot is taken, respecting buffers (1:1) or capacity (group).
	 *
	 * @param array<string, mixed> $service   Service row.
	 * @param int                  $staff_id  Staff id.
	 * @param int                  $start     Start epoch.
	 * @param int                  $end       End epoch.
	 * @param int|null             $ignore_id Appointment id to ignore.
	 * @param string               $token     Hold token to ignore.
	 * @return bool
	 */
	private function slot_taken( array $service, int $staff_id, int $start, int $end, ?int $ignore_id, string $token ): bool {
		$before   = (int) $service['buffer_before_min'];
		$after    = (int) $service['buffer_after_min'];
		$capacity = max( 1, (int) $service['capacity'] );

		if ( 1 === $capacity ) {
			return $this->conflicts->has_conflict( $staff_id, $start, $end, $before, $after, $ignore_id, $token );
		}

		// Group service: count same-service overlaps, block on other-service clashes.
		$from = $this->clock->utc_string( $start - ( $before * MINUTE_IN_SECONDS ) );
		$to   = $this->clock->utc_string( $end + ( $after * MINUTE_IN_SECONDS ) );
		$same = 0;

		foreach ( $this->appointments->occupying_in_range( $staff_id, $from, $to ) as $appt ) {
			if ( null !== $ignore_id && (int) $appt['id'] === $ignore_id ) {
				continue;
			}
			$raw_start = $this->clock->utc_to_epoch( (string) $appt['start_at'] );
			$raw_end   = $this->clock->utc_to_epoch( (string) $appt['end_at'] );

			if ( (int) $appt['service_id'] === (int) $service['id'] ) {
				if ( ConflictDetector::overlaps( $start, $end, $raw_start, $raw_end ) ) {
					++$same;
				}
				continue;
			}
			$a_start = $raw_start - ( (int) $appt['buffer_before_min'] * MINUTE_IN_SECONDS );
			$a_end   = $raw_end + ( (int) $appt['buffer_after_min'] * MINUTE_IN_SECONDS );
			if ( ConflictDetector::overlaps( $start - ( $before * MINUTE_IN_SECONDS ), $end + ( $after * MINUTE_IN_SECONDS ), $a_start, $a_end ) ) {
				return true;
			}
		}

		if ( $same >= $capacity ) {
			return true;
		}

		$now = $this->clock->utc_string( time() );
		foreach ( $this->holds->active_in_range( $staff_id, $from, $to, $now, $token ) as $hold ) {
			if ( ConflictDetector::overlaps( $start, $end, $this->clock->utc_to_epoch( (string) $hold['start_at'] ), $this->clock->utc_to_epoch( (string) $hold['end_at'] ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the list of start epochs for a (possibly recurring) booking.
	 *
	 * @param int                       $base       Base start epoch.
	 * @param array<string, mixed>|null $recurrence Recurrence config.
	 * @return array<int, int>
	 */
	private function occurrences( int $base, ?array $recurrence ): array {
		if ( ! is_array( $recurrence ) || empty( $recurrence['frequency'] ) ) {
			return array( $base );
		}

		$frequency = (string) $recurrence['frequency'];
		$interval  = max( 1, (int) ( $recurrence['interval'] ?? 1 ) );
		$count     = min( self::MAX_OCCURRENCES, max( 1, (int) ( $recurrence['count'] ?? 1 ) ) );
		$unit      = 'weekly' === $frequency ? 'weeks' : 'days';

		$local  = new DateTimeImmutable( $this->clock->local_string( $base ), $this->clock->zone() );
		$starts = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$starts[] = $local->modify( '+' . ( $i * $interval ) . ' ' . $unit )->getTimestamp();
		}

		return $starts;
	}

	/**
	 * Parse a local datetime string to an epoch, or null if invalid.
	 *
	 * @param string $value Local datetime (Y-m-d H:i[:s]).
	 * @return int|null
	 */
	private function parse_local( string $value ): ?int {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value, $this->clock->zone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Run a callback while holding a per-staff advisory lock (best effort).
	 *
	 * @template T
	 * @param int               $staff_id Staff id.
	 * @param callable(): T     $callback Critical section.
	 * @return mixed
	 */
	private function with_staff_lock( int $staff_id, callable $callback ): mixed {
		$key = 'bkra_book_' . $staff_id;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->get_var( $this->wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, 5 ) );
		try {
			return $callback();
		} finally {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->wpdb->get_var( $this->wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}
}
