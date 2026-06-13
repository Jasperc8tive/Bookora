<?php
/**
 * Notification log repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Notifications;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `notifications` delivery log.
 */
final class NotificationRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'notifications';
	}

	/**
	 * Recent delivery records.
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

	/**
	 * Records for an appointment.
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
}
