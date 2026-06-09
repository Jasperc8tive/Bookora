<?php
/**
 * Bookora capability registry.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical list of Bookora capabilities.
 *
 * These are custom WordPress capabilities (prefixed `bookora_`) granted to
 * roles by {@see Roles} according to the {@see PermissionMatrix}. Feature code
 * checks them via {@see Guard} / current_user_can().
 */
final class Capabilities {

	public const MANAGE_SETTINGS     = 'bookora_manage_settings';
	public const MANAGE_SERVICES     = 'bookora_manage_services';
	public const MANAGE_STAFF        = 'bookora_manage_staff';
	public const MANAGE_CUSTOMERS    = 'bookora_manage_customers';
	public const MANAGE_BOOKINGS     = 'bookora_manage_bookings';
	public const MANAGE_PAYMENTS     = 'bookora_manage_payments';
	public const VIEW_REPORTS        = 'bookora_view_reports';
	public const VIEW_AUDIT_LOG      = 'bookora_view_audit_log';
	public const MANAGE_AGENCY       = 'bookora_manage_agency';
	public const MANAGE_AFFILIATES   = 'bookora_manage_affiliates';
	public const VIEW_OWN_SCHEDULE   = 'bookora_view_own_schedule';
	public const MANAGE_OWN_BOOKINGS = 'bookora_manage_own_bookings';
	public const ACCESS_PORTAL       = 'bookora_access_portal';

	/**
	 * Every Bookora capability.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::MANAGE_SETTINGS,
			self::MANAGE_SERVICES,
			self::MANAGE_STAFF,
			self::MANAGE_CUSTOMERS,
			self::MANAGE_BOOKINGS,
			self::MANAGE_PAYMENTS,
			self::VIEW_REPORTS,
			self::VIEW_AUDIT_LOG,
			self::MANAGE_AGENCY,
			self::MANAGE_AFFILIATES,
			self::VIEW_OWN_SCHEDULE,
			self::MANAGE_OWN_BOOKINGS,
			self::ACCESS_PORTAL,
		);
	}

	/**
	 * Whether a string is a known Bookora capability.
	 *
	 * @param string $capability Candidate capability.
	 * @return bool
	 */
	public static function is_known( string $capability ): bool {
		return in_array( $capability, self::all(), true );
	}
}
