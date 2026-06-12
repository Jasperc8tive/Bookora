<?php
/**
 * Payment repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Payments;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `payments` table.
 */
final class PaymentRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'payments';
	}

	/**
	 * Find a payment by gateway + our reference.
	 *
	 * @param string $gateway   Gateway key.
	 * @param string $reference Our reference (stored in gateway_ref).
	 * @return array<string, mixed>|null
	 */
	public function find_by_reference( string $gateway, string $reference ): ?array {
		return $this->find_by(
			array(
				'gateway'     => $gateway,
				'gateway_ref' => $reference,
			)
		);
	}

	/**
	 * Find any payment by our reference (gateway-agnostic).
	 *
	 * @param string $reference Our reference.
	 * @return array<string, mixed>|null
	 */
	public function find_by_any_reference( string $reference ): ?array {
		return $this->find_by( array( 'gateway_ref' => $reference ) );
	}

	/**
	 * Payments for an appointment, newest first.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return array<int, array<string, mixed>>
	 */
	public function for_appointment( int $appointment_id ): array {
		return $this->all(
			array(
				'where'   => array( 'appointment_id' => $appointment_id ),
				'orderby' => 'id',
				'order'   => 'DESC',
			)
		);
	}

	/**
	 * Recent payments across the system.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		return $this->all(
			array(
				'orderby' => 'id',
				'order'   => 'DESC',
				'limit'   => $limit,
			)
		);
	}
}
