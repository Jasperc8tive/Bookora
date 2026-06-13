<?php
/**
 * Per-staff Microsoft OAuth token store.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations\Microsoft;

use Bookora\Integrations\AbstractTokenStore;

defined( 'ABSPATH' ) || exit;

/**
 * Stores each staff member's encrypted Microsoft OAuth tokens under the
 * provider key "outlook_{staff_id}".
 */
final class MicrosoftTokenStore extends AbstractTokenStore {

	/**
	 * {@inheritDoc}
	 */
	protected function prefix(): string {
		return 'outlook_';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'Outlook Calendar';
	}
}
