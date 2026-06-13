<?php
/**
 * Calendar provider client contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Integrations;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Common surface implemented by the Google and Microsoft Graph clients so a
 * single sync engine can drive either provider.
 */
interface CalendarClient {

	/**
	 * Whether the provider app credentials are configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Refresh an access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array<string, mixed>|WP_Error
	 */
	public function refresh( string $refresh_token ): array|WP_Error;

	/**
	 * Insert an event; returns the created event id.
	 *
	 * @param string               $access_token Access token.
	 * @param string               $calendar_id  Calendar id.
	 * @param array<string, mixed> $event        Event body.
	 * @return string|WP_Error
	 */
	public function insert_event( string $access_token, string $calendar_id, array $event ): string|WP_Error;

	/**
	 * Update an event.
	 *
	 * @param string               $access_token Access token.
	 * @param string               $calendar_id  Calendar id.
	 * @param string               $event_id     Event id.
	 * @param array<string, mixed> $event        Event body.
	 * @return bool|WP_Error
	 */
	public function update_event( string $access_token, string $calendar_id, string $event_id, array $event ): bool|WP_Error;

	/**
	 * Delete an event.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id  Calendar id.
	 * @param string $event_id     Event id.
	 * @return bool|WP_Error
	 */
	public function delete_event( string $access_token, string $calendar_id, string $event_id ): bool|WP_Error;

	/**
	 * Free/busy intervals for a calendar in an ISO range.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id  Calendar id.
	 * @param string $from_iso     ISO8601 start.
	 * @param string $to_iso       ISO8601 end.
	 * @return array<int, array{start_utc: string, end_utc: string}>|WP_Error
	 */
	public function free_busy( string $access_token, string $calendar_id, string $from_iso, string $to_iso ): array|WP_Error;

	/**
	 * Map appointment context to a provider event body.
	 *
	 * @param array{summary: string, start_utc: string, end_utc: string, description?: string} $context Context.
	 * @return array<string, mixed>
	 */
	public function to_event( array $context ): array;
}
