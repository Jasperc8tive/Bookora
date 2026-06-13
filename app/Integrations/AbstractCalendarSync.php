<?php
/**
 * Provider-agnostic calendar sync engine.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Customers\CustomerRepository;
use Bookora\Services\ServiceRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Pushes appointments to a connected calendar and exposes external busy
 * intervals to the availability engine. Concrete subclasses bind a token store,
 * a {@see CalendarClient}, and a provider key; all sync logic lives here.
 *
 * Availability reads a short-lived cache (warmed by sync/cron), never a live API
 * call, so slot computation stays fast and resilient to provider outages.
 */
abstract class AbstractCalendarSync {

	private const BUSY_TTL = 600;
	private const HORIZON  = 21;

	protected AbstractTokenStore $tokens;
	protected CalendarClient $client;
	protected AppointmentRepository $appointments;
	protected ServiceRepository $services;
	protected CustomerRepository $customers;

	/**
	 * Constructor.
	 *
	 * @param AbstractTokenStore    $tokens       Token store.
	 * @param CalendarClient        $client       Provider client.
	 * @param AppointmentRepository $appointments Appointment repository.
	 * @param ServiceRepository     $services     Service repository.
	 * @param CustomerRepository    $customers    Customer repository.
	 */
	public function __construct(
		AbstractTokenStore $tokens,
		CalendarClient $client,
		AppointmentRepository $appointments,
		ServiceRepository $services,
		CustomerRepository $customers
	) {
		$this->tokens       = $tokens;
		$this->client       = $client;
		$this->appointments = $appointments;
		$this->services     = $services;
		$this->customers    = $customers;
	}

	/**
	 * Provider key used for the external-id map (e.g. "google", "outlook").
	 *
	 * @return string
	 */
	abstract protected function provider(): string;

	/**
	 * Filter callback feeding cached external busy intervals to availability.
	 *
	 * @param array<int, array{start_utc: string, end_utc: string}> $busy     Existing busy.
	 * @param int                                                    $staff_id Staff id.
	 * @param string                                                 $from_utc Range start.
	 * @param string                                                 $to_utc   Range end.
	 * @return array<int, array{start_utc: string, end_utc: string}>
	 */
	public function filter_busy( array $busy, int $staff_id, string $from_utc, string $to_utc ): array {
		foreach ( $this->cached_busy( $staff_id ) as $interval ) {
			if ( $interval['end_utc'] > $from_utc && $interval['start_utc'] < $to_utc ) {
				$busy[] = $interval;
			}
		}

		return $busy;
	}

	/**
	 * Push (create or update) an appointment to the provider calendar.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return void
	 */
	public function push_for_appointment( int $appointment_id ): void {
		$appt = $this->appointments->find( $appointment_id );
		if ( null === $appt || empty( $appt['staff_id'] ) ) {
			return;
		}
		$staff_id = (int) $appt['staff_id'];
		$token    = $this->access_token( $staff_id );
		if ( null === $token ) {
			return;
		}

		$event    = $this->client->to_event(
			array(
				'summary'     => $this->summary( $appt ),
				'description' => __( 'Booked via Bookora', 'bookora' ),
				'start_utc'   => (string) $appt['start_at'],
				'end_utc'     => (string) $appt['end_at'],
			)
		);
		$calendar = $this->calendar_id( $staff_id );
		$existing = $this->appointments->external_id( $appointment_id, $this->provider() );

		if ( '' !== $existing ) {
			$this->client->update_event( $token, $calendar, $existing, $event );

			return;
		}

		$event_id = $this->client->insert_event( $token, $calendar, $event );
		if ( ! is_wp_error( $event_id ) && '' !== $event_id ) {
			$this->appointments->set_external_id( $appointment_id, $this->provider(), $event_id );
		}
	}

	/**
	 * Delete an appointment's provider event.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return void
	 */
	public function delete_for_appointment( int $appointment_id ): void {
		$appt     = $this->appointments->find( $appointment_id );
		$existing = $this->appointments->external_id( $appointment_id, $this->provider() );
		if ( null === $appt || empty( $appt['staff_id'] ) || '' === $existing ) {
			return;
		}
		$token = $this->access_token( (int) $appt['staff_id'] );
		if ( null === $token ) {
			return;
		}
		$this->client->delete_event( $token, $this->calendar_id( (int) $appt['staff_id'] ), $existing );
		$this->appointments->set_external_id( $appointment_id, $this->provider(), null );
	}

	/**
	 * Pull a staff member's free/busy from the provider and cache it.
	 *
	 * @param int $staff_id Staff id.
	 * @return void
	 */
	public function warm_busy_cache( int $staff_id ): void {
		$token = $this->access_token( $staff_id );
		if ( null === $token ) {
			return;
		}
		$from = gmdate( 'Y-m-d\TH:i:s\Z' );
		$to   = gmdate( 'Y-m-d\TH:i:s\Z', time() + self::HORIZON * DAY_IN_SECONDS );

		$busy = $this->client->free_busy( $token, $this->calendar_id( $staff_id ), $from, $to );
		if ( ! is_wp_error( $busy ) ) {
			set_transient( $this->cache_key( $staff_id ), $busy, self::BUSY_TTL );
		}
	}

	/**
	 * Cached busy intervals for a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return array<int, array{start_utc: string, end_utc: string}>
	 */
	private function cached_busy( int $staff_id ): array {
		$cached = get_transient( $this->cache_key( $staff_id ) );

		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * Transient cache key for this provider + staff.
	 *
	 * @param int $staff_id Staff id.
	 * @return string
	 */
	private function cache_key( int $staff_id ): string {
		return 'bookora_busy_' . $this->provider() . '_' . $staff_id;
	}

	/**
	 * Get a valid access token, refreshing if needed.
	 *
	 * @param int $staff_id Staff id.
	 * @return string|null
	 */
	private function access_token( int $staff_id ): ?string {
		$stored = $this->tokens->get( $staff_id );
		if ( null === $stored ) {
			return null;
		}

		if ( $this->tokens->is_expired( $staff_id ) && ! empty( $stored['refresh_token'] ) ) {
			$refreshed = $this->client->refresh( (string) $stored['refresh_token'] );
			if ( is_wp_error( $refreshed ) || empty( $refreshed['access_token'] ) ) {
				return null;
			}
			$this->tokens->update_access( $staff_id, (string) $refreshed['access_token'], (int) ( $refreshed['expires_in'] ?? 3600 ) );

			return (string) $refreshed['access_token'];
		}

		$access = (string) ( $stored['access_token'] ?? '' );

		return '' !== $access ? $access : null;
	}

	/**
	 * Resolve the calendar id for a staff member.
	 *
	 * @param int $staff_id Staff id.
	 * @return string
	 */
	private function calendar_id( int $staff_id ): string {
		$stored = $this->tokens->get( $staff_id );

		return (string) ( $stored['calendar_id'] ?? 'primary' );
	}

	/**
	 * Build the event summary from the appointment.
	 *
	 * @param array<string, mixed> $appt Appointment row.
	 * @return string
	 */
	private function summary( array $appt ): string {
		$service  = $this->services->find( (int) $appt['service_id'] );
		$customer = $this->customers->find( (int) $appt['customer_id'] );

		return trim( (string) ( $service['name'] ?? __( 'Booking', 'bookora' ) ) . ' — ' . (string) ( $customer['name'] ?? '' ) );
	}
}
