<?php
/**
 * Role → capability permission matrix.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Security;

defined( 'ABSPATH' ) || exit;

/**
 * The single source of truth for which role tier receives which capabilities.
 *
 * Role tiers (mandate): Administrator, Manager, Staff, Customer.
 * - Administrator: existing WP role, granted every Bookora capability.
 * - Manager (`bookora_manager`): runs the business day-to-day, no site/agency settings.
 * - Staff (`bookora_staff`): own schedule + own bookings only.
 * - Customer (`bookora_customer`): self-service portal + own bookings.
 */
final class PermissionMatrix {

	public const ROLE_MANAGER  = 'bookora_manager';
	public const ROLE_STAFF    = 'bookora_staff';
	public const ROLE_CUSTOMER = 'bookora_customer';

	/**
	 * Capabilities granted to the Administrator role (everything).
	 *
	 * @return array<int, string>
	 */
	public static function administrator(): array {
		return Capabilities::all();
	}

	/**
	 * Capabilities for the Bookora Manager role.
	 *
	 * @return array<int, string>
	 */
	public static function manager(): array {
		return array(
			Capabilities::MANAGE_SERVICES,
			Capabilities::MANAGE_STAFF,
			Capabilities::MANAGE_CUSTOMERS,
			Capabilities::MANAGE_BOOKINGS,
			Capabilities::MANAGE_PAYMENTS,
			Capabilities::VIEW_REPORTS,
			Capabilities::VIEW_AUDIT_LOG,
			Capabilities::VIEW_OWN_SCHEDULE,
			Capabilities::MANAGE_OWN_BOOKINGS,
		);
	}

	/**
	 * Capabilities for the Bookora Staff role.
	 *
	 * @return array<int, string>
	 */
	public static function staff(): array {
		return array(
			Capabilities::VIEW_OWN_SCHEDULE,
			Capabilities::MANAGE_OWN_BOOKINGS,
		);
	}

	/**
	 * Capabilities for the Bookora Customer role.
	 *
	 * @return array<int, string>
	 */
	public static function customer(): array {
		return array(
			Capabilities::ACCESS_PORTAL,
			Capabilities::MANAGE_OWN_BOOKINGS,
		);
	}

	/**
	 * The custom roles Bookora registers, keyed by role slug.
	 *
	 * @return array<string, array{label: string, caps: array<int, string>}>
	 */
	public static function custom_roles(): array {
		return array(
			self::ROLE_MANAGER  => array(
				'label' => 'Bookora Manager',
				'caps'  => self::manager(),
			),
			self::ROLE_STAFF    => array(
				'label' => 'Bookora Staff',
				'caps'  => self::staff(),
			),
			self::ROLE_CUSTOMER => array(
				'label' => 'Bookora Customer',
				'caps'  => self::customer(),
			),
		);
	}
}
