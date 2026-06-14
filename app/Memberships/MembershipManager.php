<?php
/**
 * Membership / subscription application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Memberships;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Manages membership plans, customer enrolments (subscriptions), member
 * discounts, and renewals.
 */
final class MembershipManager {

	private const PERIODS  = array( 'month', 'year', 'none' );
	private const BENEFITS = array( 'discount_percent', 'none' );

	private MembershipRepository $plans;
	private CustomerMembershipRepository $enrolments;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param MembershipRepository         $plans      Plan repository.
	 * @param CustomerMembershipRepository $enrolments Enrolment repository.
	 * @param ActivityLogger               $audit      Audit logger.
	 */
	public function __construct( MembershipRepository $plans, CustomerMembershipRepository $enrolments, ActivityLogger $audit ) {
		$this->plans      = $plans;
		$this->enrolments = $enrolments;
		$this->audit      = $audit;
	}

	/**
	 * Create a membership plan.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_plan( array $input ): array|WP_Error {
		$data = $this->validate_plan( $input, true );
		if ( $data instanceof WP_Error ) {
			return $data;
		}
		$id = $this->plans->create( $data );
		$this->audit->log(
			'membership.plan_created',
			array(
				'entity_type' => 'membership',
				'entity_id'   => $id,
			)
		);

		return (array) $this->plans->find( $id );
	}

	/**
	 * Update a membership plan.
	 *
	 * @param int                  $id    Plan id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update_plan( int $id, array $input ): array|WP_Error {
		if ( null === $this->plans->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Plan not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		$data = $this->validate_plan( $input, false );
		if ( $data instanceof WP_Error ) {
			return $data;
		}
		$this->plans->update( $id, $data );

		return (array) $this->plans->find( $id );
	}

	/**
	 * Soft-delete a plan.
	 *
	 * @param int $id Plan id.
	 * @return bool
	 */
	public function delete_plan( int $id ): bool {
		return $this->plans->delete( $id );
	}

	/**
	 * Enrol a customer on a plan (start a subscription).
	 *
	 * @param int $customer_id   Customer id.
	 * @param int $membership_id Plan id.
	 * @return array<string, mixed>|WP_Error
	 */
	public function enroll( int $customer_id, int $membership_id ): array|WP_Error {
		$plan = $this->plans->find( $membership_id );
		if ( null === $plan ) {
			return new WP_Error( 'bookora_not_found', __( 'Plan not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		$id = $this->enrolments->create(
			array(
				'customer_id'   => $customer_id,
				'membership_id' => $membership_id,
				'status'        => 'active',
				'started_at'    => current_time( 'mysql', true ),
				'renews_at'     => $this->next_renewal( (string) $plan['billing_period'] ),
			)
		);
		$this->audit->log(
			'membership.enrolled',
			array(
				'entity_type' => 'customer_membership',
				'entity_id'   => $id,
			)
		);

		return (array) $this->enrolments->find( $id );
	}

	/**
	 * Cancel a customer's membership.
	 *
	 * @param int $enrolment_id Enrolment id.
	 * @return bool
	 */
	public function cancel( int $enrolment_id ): bool {
		return $this->enrolments->update( $enrolment_id, array( 'status' => 'cancelled' ) );
	}

	/**
	 * The member discount on an amount for a customer (0 if none).
	 *
	 * @param int   $customer_id Customer id.
	 * @param float $amount      Order amount.
	 * @return float
	 */
	public function discount_for( int $customer_id, float $amount ): float {
		$active = $this->enrolments->active_for_customer( $customer_id );
		if ( null === $active || 'discount_percent' !== $active['benefit_type'] ) {
			return 0.0;
		}

		return round( min( $amount, $amount * (float) $active['benefit_value'] / 100 ), 2 );
	}

	/**
	 * Renew memberships due at/before now (cron entry point).
	 *
	 * @return int Number renewed.
	 */
	public function process_renewals(): int {
		$count = 0;
		foreach ( $this->enrolments->due_for_renewal( current_time( 'mysql', true ) ) as $row ) {
			$plan = $this->plans->find( (int) $row['membership_id'] );
			if ( null === $plan || 'none' === $plan['billing_period'] ) {
				continue;
			}
			$this->enrolments->update( (int) $row['id'], array( 'renews_at' => $this->next_renewal( (string) $plan['billing_period'] ) ) );
			++$count;
		}

		return $count;
	}

	/**
	 * Compute the next renewal datetime for a billing period.
	 *
	 * @param string $period Billing period.
	 * @return string|null
	 */
	private function next_renewal( string $period ): ?string {
		return match ( $period ) {
			'month' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			'year'  => gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			default => null,
		};
	}

	/**
	 * Validate + sanitise plan input.
	 *
	 * @param array<string, mixed> $input    Raw input.
	 * @param bool                 $creating Whether creating.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate_plan( array $input, bool $creating ): array|WP_Error {
		$data = array();
		if ( array_key_exists( 'name', $input ) ) {
			$data['name'] = sanitize_text_field( (string) $input['name'] );
		}
		if ( $creating && empty( $data['name'] ) ) {
			return new WP_Error( 'bookora_validation', __( 'A plan name is required.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( array_key_exists( 'price', $input ) ) {
			$data['price'] = max( 0, (float) $input['price'] );
		}
		if ( array_key_exists( 'billing_period', $input ) ) {
			$data['billing_period'] = in_array( $input['billing_period'], self::PERIODS, true ) ? (string) $input['billing_period'] : 'month';
		}
		if ( array_key_exists( 'benefit_type', $input ) ) {
			$data['benefit_type'] = in_array( $input['benefit_type'], self::BENEFITS, true ) ? (string) $input['benefit_type'] : 'discount_percent';
		}
		if ( array_key_exists( 'benefit_value', $input ) ) {
			$data['benefit_value'] = max( 0, (float) $input['benefit_value'] );
		}
		if ( array_key_exists( 'status', $input ) ) {
			$data['status'] = in_array( $input['status'], array( 'active', 'inactive' ), true ) ? (string) $input['status'] : 'active';
		}

		return $data;
	}
}
