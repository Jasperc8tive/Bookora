<?php
/**
 * Gift card application service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\GiftCards;

use Bookora\Security\ActivityLogger;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Issues gift cards and applies them against amounts (balance debit).
 */
final class GiftCardManager {

	private GiftCardRepository $cards;
	private ActivityLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param GiftCardRepository $cards Gift card repository.
	 * @param ActivityLogger     $audit Audit logger.
	 */
	public function __construct( GiftCardRepository $cards, ActivityLogger $audit ) {
		$this->cards = $cards;
		$this->audit = $audit;
	}

	/**
	 * Issue a new gift card.
	 *
	 * @param array<string, mixed> $input Raw input (amount, currency, customer_id, expires_at).
	 * @return array<string, mixed>|WP_Error
	 */
	public function issue( array $input ): array|WP_Error {
		$amount = round( max( 0, (float) ( $input['amount'] ?? 0 ) ), 2 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'bookora_validation', __( 'A positive amount is required.', 'bookora' ), array( 'status' => 422 ) );
		}

		$code = $this->unique_code();
		$id   = $this->cards->create(
			array(
				'code'           => $code,
				'initial_amount' => $amount,
				'balance'        => $amount,
				'currency'       => strtoupper( substr( (string) ( $input['currency'] ?? 'NGN' ), 0, 3 ) ),
				'customer_id'    => (int) ( $input['customer_id'] ?? 0 ) > 0 ? (int) $input['customer_id'] : null,
				'expires_at'     => '' !== ( $input['expires_at'] ?? '' ) ? sanitize_text_field( (string) $input['expires_at'] ) : null,
				'status'         => 'active',
			)
		);
		$this->audit->log(
			'gift_card.issued',
			array(
				'entity_type' => 'gift_card',
				'entity_id'   => $id,
			)
		);

		return (array) $this->cards->find( $id );
	}

	/**
	 * Look up a card's spendable balance by code.
	 *
	 * @param string $code Card code.
	 * @return array{code: string, balance: float, currency: string}|WP_Error
	 */
	public function balance( string $code ): array|WP_Error {
		$card = $this->active_card( $code );
		if ( $card instanceof WP_Error ) {
			return $card;
		}

		return array(
			'code'     => (string) $card['code'],
			'balance'  => round( (float) $card['balance'], 2 ),
			'currency' => (string) $card['currency'],
		);
	}

	/**
	 * Redeem (debit) a card toward an amount.
	 *
	 * @param string $code   Card code.
	 * @param float  $amount Amount to apply.
	 * @return array{code: string, applied: float, balance_after: float}|WP_Error
	 */
	public function redeem( string $code, float $amount ): array|WP_Error {
		$card = $this->active_card( $code );
		if ( $card instanceof WP_Error ) {
			return $card;
		}
		$applied = round( min( max( 0, $amount ), (float) $card['balance'] ), 2 );
		if ( $applied <= 0 ) {
			return new WP_Error( 'bookora_gift_card_empty', __( 'This gift card has no balance.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( ! $this->cards->debit( (int) $card['id'], $applied ) ) {
			return new WP_Error( 'bookora_gift_card_conflict', __( 'Could not apply the gift card. Please try again.', 'bookora' ), array( 'status' => 409 ) );
		}
		$this->audit->log(
			'gift_card.redeemed',
			array(
				'entity_type' => 'gift_card',
				'entity_id'   => (int) $card['id'],
			)
		);

		return array(
			'code'          => (string) $card['code'],
			'applied'       => $applied,
			'balance_after' => round( (float) $card['balance'] - $applied, 2 ),
		);
	}

	/**
	 * Soft-delete (deactivate) a card.
	 *
	 * @param int $id Card id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		return $this->cards->delete( $id );
	}

	/**
	 * Resolve an active, non-expired card by code.
	 *
	 * @param string $code Card code.
	 * @return array<string, mixed>|WP_Error
	 */
	private function active_card( string $code ): array|WP_Error {
		$card = $this->cards->find_by_code( $code );
		if ( null === $card || 'active' !== $card['status'] ) {
			return new WP_Error( 'bookora_gift_card_invalid', __( 'This gift card is not valid.', 'bookora' ), array( 'status' => 422 ) );
		}
		if ( ! empty( $card['expires_at'] ) && strtotime( (string) $card['expires_at'] ) < time() ) {
			return new WP_Error( 'bookora_gift_card_expired', __( 'This gift card has expired.', 'bookora' ), array( 'status' => 422 ) );
		}

		return $card;
	}

	/**
	 * Generate a unique uppercase card code.
	 *
	 * @return string
	 */
	private function unique_code(): string {
		do {
			$code = strtoupper( wp_generate_password( 12, false, false ) );
		} while ( null !== $this->cards->find_by_code( $code ) );

		return $code;
	}
}
