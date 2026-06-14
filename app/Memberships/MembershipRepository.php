<?php
/**
 * Membership plan repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Memberships;

use Bookora\Database\Repository\AbstractRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the `memberships` (plans) table.
 */
final class MembershipRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'memberships';
	}
}
