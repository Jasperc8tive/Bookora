<?php
/**
 * Staff-availability application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Staff;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and replaces a staff member's availability (working hours, breaks,
 * time off, holidays) as a single validated set.
 */
final class AvailabilityManager {

	/**
	 * Availability repository.
	 *
	 * @var AvailabilityRepository
	 */
	private AvailabilityRepository $repository;

	/**
	 * Audit logger.
	 *
	 * @var ActivityLogger
	 */
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AvailabilityRepository $repository Availability repository.
	 * @param ActivityLogger         $audit      Audit logger.
	 */
	public function __construct( AvailabilityRepository $repository, ActivityLogger $audit ) {
		$this->repository = $repository;
		$this->audit      = $audit;
	}

	/**
	 * Get a staff member's availability grouped by type.
	 *
	 * @param int $staff_id Staff id.
	 * @return array{working_hours: array<int, array<string, mixed>>, breaks: array<int, array<string, mixed>>, time_off: array<int, array<string, mixed>>, holidays: array<int, array<string, mixed>>}
	 */
	public function get( int $staff_id ): array {
		$grouped = array(
			'working_hours' => array(),
			'breaks'        => array(),
			'time_off'      => array(),
			'holidays'      => array(),
		);

		foreach ( $this->repository->for_staff( $staff_id ) as $row ) {
			match ( (string) $row['type'] ) {
				'working_hours' => $grouped['working_hours'][] = $row,
				'break'         => $grouped['breaks'][]        = $row,
				'time_off'      => $grouped['time_off'][]      = $row,
				'holiday'       => $grouped['holidays'][]      = $row,
				default         => null,
			};
		}

		return $grouped;
	}

	/**
	 * Replace a staff member's availability with a validated set.
	 *
	 * @param int                  $staff_id Staff id.
	 * @param array<string, mixed> $input    Input groups (working_hours, breaks, time_off, holidays).
	 * @return array<string, mixed>|WP_Error Grouped availability, or a validation error.
	 */
	public function set( int $staff_id, array $input ): array|WP_Error {
		$rows   = array();
		$errors = array();

		foreach ( array( 'working_hours', 'breaks' ) as $group ) {
			$type = 'breaks' === $group ? 'break' : 'working_hours';
			foreach ( (array) ( $input[ $group ] ?? array() ) as $i => $entry ) {
				$row = $this->validate_time_entry( (array) $entry, $type );
				if ( $row instanceof WP_Error ) {
					$errors[ "{$group}.{$i}" ] = $row->get_error_message();
					continue;
				}
				$row['staff_id'] = $staff_id;
				$rows[]          = $row;
			}
		}

		foreach ( array(
			'time_off' => 'time_off',
			'holidays' => 'holiday',
		) as $group => $type ) {
			foreach ( (array) ( $input[ $group ] ?? array() ) as $i => $entry ) {
				$row = $this->validate_date_entry( (array) $entry, $type );
				if ( $row instanceof WP_Error ) {
					$errors[ "{$group}.{$i}" ] = $row->get_error_message();
					continue;
				}
				$row['staff_id'] = $staff_id;
				$rows[]          = $row;
			}
		}

		if ( array() !== $errors ) {
			return new WP_Error(
				'bookora_validation',
				__( 'Please correct the availability entries.', 'bookora' ),
				array(
					'status' => 422,
					'fields' => $errors,
				)
			);
		}

		$this->repository->clear_for_staff( $staff_id );
		foreach ( $rows as $row ) {
			$this->repository->create( $row );
		}

		$this->audit->log(
			'staff.availability_set',
			array(
				'entity_type' => 'staff',
				'entity_id'   => $staff_id,
			)
		);

		return $this->get( $staff_id );
	}

	/**
	 * Validate a weekday time entry.
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @param string               $type  Entry type.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate_time_entry( array $entry, string $type ): array|WP_Error {
		$weekday = (int) ( $entry['weekday'] ?? -1 );
		if ( $weekday < 0 || $weekday > 6 ) {
			return new WP_Error( 'invalid', __( 'Weekday must be 0–6.', 'bookora' ) );
		}
		$start = $this->normalize_time( (string) ( $entry['start_time'] ?? '' ) );
		$end   = $this->normalize_time( (string) ( $entry['end_time'] ?? '' ) );
		if ( null === $start || null === $end ) {
			return new WP_Error( 'invalid', __( 'Times must be in HH:MM format.', 'bookora' ) );
		}
		if ( strcmp( $start, $end ) >= 0 ) {
			return new WP_Error( 'invalid', __( 'End time must be after start time.', 'bookora' ) );
		}

		return array(
			'type'       => $type,
			'weekday'    => $weekday,
			'start_time' => $start,
			'end_time'   => $end,
		);
	}

	/**
	 * Validate a date-range entry.
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @param string               $type  Entry type.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate_date_entry( array $entry, string $type ): array|WP_Error {
		$start = $this->normalize_date( (string) ( $entry['start_date'] ?? '' ) );
		$end   = $this->normalize_date( (string) ( $entry['end_date'] ?? ( $entry['start_date'] ?? '' ) ) );
		if ( null === $start || null === $end ) {
			return new WP_Error( 'invalid', __( 'Dates must be in YYYY-MM-DD format.', 'bookora' ) );
		}
		if ( strcmp( $start, $end ) > 0 ) {
			return new WP_Error( 'invalid', __( 'End date cannot be before start date.', 'bookora' ) );
		}

		return array(
			'type'       => $type,
			'start_date' => $start,
			'end_date'   => $end,
			'note'       => isset( $entry['note'] ) ? sanitize_text_field( (string) $entry['note'] ) : null,
		);
	}

	/**
	 * Normalise an HH:MM(:SS) string to HH:MM:00, or null if invalid.
	 *
	 * @param string $time Time string.
	 * @return string|null
	 */
	private function normalize_time( string $time ): ?string {
		if ( 1 !== preg_match( '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time ) ) {
			return null;
		}

		return substr( $time, 0, 5 ) . ':00';
	}

	/**
	 * Validate a YYYY-MM-DD date, returning it normalised or null.
	 *
	 * @param string $date Date string.
	 * @return string|null
	 */
	private function normalize_date( string $date ): ?string {
		$dt = \DateTime::createFromFormat( 'Y-m-d', $date );

		return ( $dt && $dt->format( 'Y-m-d' ) === $date ) ? $date : null;
	}
}
