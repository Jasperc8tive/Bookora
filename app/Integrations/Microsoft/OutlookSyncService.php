<?php
/**
 * Outlook (Microsoft) calendar sync service.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations\Microsoft;

use Bookora\Integrations\AbstractCalendarSync;

defined( 'ABSPATH' ) || exit;

/**
 * Microsoft binding of the shared calendar sync engine.
 */
final class OutlookSyncService extends AbstractCalendarSync {

	/**
	 * {@inheritDoc}
	 */
	protected function provider(): string {
		return 'outlook';
	}
}
