<?php
/**
 * Coupon application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Coupons;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, applies, and manages discount coupons.
 */
final class CouponManager {

	private const TYPES = array( 'percent', 'fixed' );

	private CouponRepository $coupons;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param CouponRepository $coupons Coupon repository.
	 * @param ActivityLogger   $audit   Audit logger.
	 */
	public function __construct( CouponRepository $coupons, ActivityLogger $audit ) {
		$this->coupons = $coupons;
		$this->audit   = $audit;
	}

	/**
	 * Validate a coupon against an amount and return the discount.
	 *
	 * @param string $code   Coupon code.
	 * @param float  $amount Order amount.
	 * @return array{coupon_id: int, code: string, discount: float, final: float}|WP_Error
	 */
	public function validate( string $code, float $amount ): array|WP_Error {
		$coupon = $this->coupons->find_by_code( $code );
		if ( null === $coupon || 'active' !== $coupon['status'] ) {
			return new WP_Error( 'bookora_coupon_invalid', __( 'This coupon is not valid.', 'bookora' ), array( 'status' => 422 ) );
		}

		$now = time();
		if ( ! empty( $coupon['starts_at'] ) && strtotime( (string) $coupon['starts_at'] ) > $now ) {
			return new WP_Error( 'bookora_coupon_not_started', __( 'This coupon is not active yet.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( ! empty( $coupon['expires_at'] ) && strtotime( (string) $coupon['expires_at'] ) < $now ) {
			return new WP_Error( 'bookora_coupon_expired', __( 'This coupon has expired.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( (int) $coupon['usage_limit'] > 0 && (int) $coupon['used'] >= (int) $coupon['usage_limit'] ) {
			return new WP_Error( 'bookora_coupon_used_up', __( 'This coupon has reached its limit.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( $amount < (float) $coupon['min_amount'] ) {
			return new WP_Error( 'bookora_coupon_min', __( 'Order total is below the coupon minimum.', 'bookora' ), array( 'status' => 422 ) );
		}

		$discount = 'percent' === $coupon['type']
			? round( $amount * (float) $coupon['value'] / 100, 2 )
			: (float) $coupon['value'];
		$discount = min( $discount, $amount );

		return array(
			'coupon_id' => (int) $coupon['id'],
			'code'      => (string) $coupon['code'],
			'discount'  => round( $discount, 2 ),
			'final'     => round( $amount - $discount, 2 ),
		);
	}

	/**
	 * Record a redemption (after a successful booking/payment).
	 *
	 * @param int $coupon_id Coupon id.
	 * @return void
	 */
	public function redeem( int $coupon_id ): void {
		$this->coupons->increment_used( $coupon_id );
		$this->audit->log(
			'coupon.redeemed',
			array(
				'entity_type' => 'coupon',
				'entity_id'   => $coupon_id,
			)
		);
	}

	/**
	 * Create a coupon.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $input ): array|WP_Error {
		$data = $this->validate_input( $input );
		if ( $data instanceof WP_Error ) {
			return $data;
		}
		if ( null !== $this->coupons->find_by_code( (string) $data['code'] ) ) {
			return new WP_Error( 'bookora_coupon_exists', __( 'A coupon with this code already exists.', 'bookora' ), array( 'status' => 422 ) );
		}
		$id = $this->coupons->create( $data );
		$this->audit->log(
			'coupon.created',
			array(
				'entity_type' => 'coupon',
				'entity_id'   => $id,
			)
		);

		return (array) $this->coupons->find( $id );
	}

	/**
	 * Update a coupon.
	 *
	 * @param int                  $id    Coupon id.
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $input ): array|WP_Error {
		if ( null === $this->coupons->find( $id ) ) {
			return new WP_Error( 'bookora_not_found', __( 'Coupon not found.', 'bookora' ), array( 'status' => 404 ) );
		}
		$data = $this->validate_input( $input, $id );
		if ( $data instanceof WP_Error ) {
			return $data;
		}
		$this->coupons->update( $id, $data );

		return (array) $this->coupons->find( $id );
	}

	/**
	 * Soft-delete a coupon.
	 *
	 * @param int $id Coupon id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->coupons->delete( $id );
	}

	/**
	 * Validate + sanitise coupon input.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @param int|null             $id    Existing id when updating.
	 * @return array<string, mixed>|WP_Error
	 */
	private function validate_input( array $input, ?int $id = null ): array|WP_Error {
		$data = array();

		if ( array_key_exists( 'code', $input ) ) {
			$code = strtoupper( sanitize_text_field( (string) $input['code'] ) );
			if ( '' === $code && null === $id ) {
				return new WP_Error( 'bookora_validation', __( 'A code is required.', 'bookora' ), array( 'status' => 422 ) );
			}
			if ( '' !== $code ) {
				$data['code'] = $code;
			}
		} elseif ( null === $id ) {
			return new WP_Error( 'bookora_validation', __( 'A code is required.', 'bookora' ), array( 'status' => 422 ) );
		}

		if ( array_key_exists( 'type', $input ) ) {
			$data['type'] = in_array( $input['type'], self::TYPES, true ) ? (string) $input['type'] : 'percent';
		}
		foreach ( array( 'value', 'min_amount' ) as $num ) {
			if ( array_key_exists( $num, $input ) ) {
				$data[ $num ] = max( 0, (float) $input[ $num ] );
			}
		}
		if ( array_key_exists( 'usage_limit', $input ) ) {
			$data['usage_limit'] = max( 0, (int) $input['usage_limit'] );
		}
		foreach ( array( 'starts_at', 'expires_at' ) as $date ) {
			if ( array_key_exists( $date, $input ) ) {
				$data[ $date ] = '' !== $input[ $date ] ? sanitize_text_field( (string) $input[ $date ] ) : null;
			}
		}
		if ( array_key_exists( 'status', $input ) ) {
			$data['status'] = in_array( $input['status'], array( 'active', 'inactive' ), true ) ? (string) $input['status'] : 'active';
		}

		return $data;
	}
}
