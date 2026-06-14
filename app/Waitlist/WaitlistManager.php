<?php
/**
 * Waitlist application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Waitlist;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Customers\CustomerManager;
use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Lets customers join a waitlist and promotes them when a slot frees.
 */
final class WaitlistManager {

	private WaitlistRepository $waitlist;
	private AppointmentRepository $appointments;
	private CustomerManager $customers;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param WaitlistRepository    $waitlist     Waitlist repository.
	 * @param AppointmentRepository $appointments Appointment repository.
	 * @param CustomerManager       $customers    Customer manager.
	 * @param ActivityLogger        $audit        Audit logger.
	 */
	public function __construct( WaitlistRepository $waitlist, AppointmentRepository $appointments, CustomerManager $customers, ActivityLogger $audit ) {
		$this->waitlist     = $waitlist;
		$this->appointments = $appointments;
		$this->customers    = $customers;
		$this->audit        = $audit;
	}

	/**
	 * Join the waitlist for a service (resolving/creating the customer).
	 *
	 * @param array<string, mixed> $input Service, contact, desired window.
	 * @return array<string, mixed>|WP_Error
	 */
	public function join( array $input ): array|WP_Error {
		$service_id = (int) ( $input['service_id'] ?? 0 );
		if ( $service_id <= 0 ) {
			return new WP_Error( 'bookora_validation', __( 'A service is required.', 'bookora' ), array( 'status' => 422 ) );
		}
		$customer_id = $this->customers->resolve_or_create( (array) ( $input['customer'] ?? array() ) );
		if ( $customer_id instanceof WP_Error ) {
			return $customer_id;
		}

		$id = $this->waitlist->create(
			array(
				'service_id'   => $service_id,
				'staff_id'     => (int) ( $input['staff_id'] ?? 0 ) > 0 ? (int) $input['staff_id'] : null,
				'customer_id'  => $customer_id,
				'desired_from' => '' !== ( $input['desired_from'] ?? '' ) ? sanitize_text_field( (string) $input['desired_from'] ) : null,
				'desired_to'   => '' !== ( $input['desired_to'] ?? '' ) ? sanitize_text_field( (string) $input['desired_to'] ) : null,
				'status'       => 'active',
			)
		);
		$this->audit->log(
			'waitlist.joined',
			array(
				'entity_type' => 'waitlist',
				'entity_id'   => $id,
			)
		);

		return (array) $this->waitlist->find( $id );
	}

	/**
	 * Admin listing.
	 *
	 * @param int $service_id Filter by service (0 = all).
	 * @return array<int, array<string, mixed>>
	 */
	public function listing( int $service_id = 0 ): array {
		return $this->waitlist->listing( $service_id );
	}

	/**
	 * Remove a waitlist entry.
	 *
	 * @param int $id Entry id.
	 * @return bool
	 */
	public function remove( int $id ): bool {
		return $this->waitlist->delete( $id );
	}

	/**
	 * When an appointment is cancelled, notify waitlisted customers for that
	 * service (mark them notified and fire an action for delivery/extension).
	 *
	 * @param int $appointment_id Cancelled appointment id.
	 * @param int $limit          Max entries to promote.
	 * @return int Number promoted.
	 */
	public function promote_for_appointment( int $appointment_id, int $limit = 3 ): int {
		$appt = $this->appointments->find( $appointment_id );
		if ( null === $appt ) {
			return 0;
		}

		$promoted = 0;
		foreach ( $this->waitlist->active_for_service( (int) $appt['service_id'] ) as $entry ) {
			if ( $promoted >= $limit ) {
				break;
			}
			$this->waitlist->update( (int) $entry['id'], array( 'status' => 'notified' ) );

			/**
			 * Fires when a waitlisted customer is promoted by a freed slot.
			 *
			 * @param int $waitlist_id    Waitlist entry id.
			 * @param int $appointment_id The freed appointment id.
			 */
			do_action( 'bookora_waitlist_opening', (int) $entry['id'], $appointment_id );
			$this->audit->log(
				'waitlist.promoted',
				array(
					'entity_type' => 'waitlist',
					'entity_id'   => (int) $entry['id'],
				)
			);
			++$promoted;
		}

		return $promoted;
	}
}
