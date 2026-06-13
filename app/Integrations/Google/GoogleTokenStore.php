<?php
/**
 * Per-staff Google OAuth token store.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations\Google;

use Bookora\Integrations\AbstractTokenStore;

defined( 'ABSPATH' ) || exit;

/**
 * Stores each staff member's encrypted Google OAuth tokens under the provider
 * key "google_{staff_id}".
 */
final class GoogleTokenStore extends AbstractTokenStore {

	/**
	 * {@inheritDoc}
	 */
	protected function prefix(): string {
		return 'google_';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function name(): string {
		return 'Google Calendar';
	}
}
