<?php
/**
 * Roles / capabilities tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Security;

use Bookora\Security\Capabilities;
use Bookora\Security\PermissionMatrix;
use Bookora\Security\Roles;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Security\Roles
 * @covers \Bookora\Security\PermissionMatrix
 * @covers \Bookora\Security\Capabilities
 */
class RolesTest extends WP_UnitTestCase {

	private Roles $roles;

	public function set_up(): void {
		parent::set_up();
		$this->roles = new Roles();
		$this->roles->install();
	}

	public function tear_down(): void {
		$this->roles->remove();
		parent::tear_down();
	}

	public function test_custom_roles_are_registered(): void {
		$this->assertNotNull( get_role( PermissionMatrix::ROLE_MANAGER ) );
		$this->assertNotNull( get_role( PermissionMatrix::ROLE_STAFF ) );
		$this->assertNotNull( get_role( PermissionMatrix::ROLE_CUSTOMER ) );
	}

	public function test_administrator_gets_all_capabilities(): void {
		$admin = get_role( 'administrator' );
		foreach ( Capabilities::all() as $cap ) {
			$this->assertTrue( $admin->has_cap( $cap ), "administrator should have {$cap}" );
		}
	}

	public function test_manager_has_business_caps_but_not_settings(): void {
		$manager = get_role( PermissionMatrix::ROLE_MANAGER );
		$this->assertTrue( $manager->has_cap( Capabilities::MANAGE_BOOKINGS ) );
		$this->assertTrue( $manager->has_cap( Capabilities::VIEW_AUDIT_LOG ) );
		$this->assertFalse( $manager->has_cap( Capabilities::MANAGE_SETTINGS ) );
		$this->assertFalse( $manager->has_cap( Capabilities::MANAGE_AGENCY ) );
	}

	public function test_staff_is_limited_to_own_scope(): void {
		$staff = get_role( PermissionMatrix::ROLE_STAFF );
		$this->assertTrue( $staff->has_cap( Capabilities::VIEW_OWN_SCHEDULE ) );
		$this->assertFalse( $staff->has_cap( Capabilities::MANAGE_BOOKINGS ) );
		$this->assertFalse( $staff->has_cap( Capabilities::MANAGE_CUSTOMERS ) );
	}

	public function test_customer_can_only_access_portal_and_own_bookings(): void {
		$customer = get_role( PermissionMatrix::ROLE_CUSTOMER );
		$this->assertTrue( $customer->has_cap( Capabilities::ACCESS_PORTAL ) );
		$this->assertTrue( $customer->has_cap( Capabilities::MANAGE_OWN_BOOKINGS ) );
		$this->assertFalse( $customer->has_cap( Capabilities::VIEW_REPORTS ) );
	}

	public function test_remove_strips_roles_and_admin_caps(): void {
		$this->roles->remove();

		$this->assertNull( get_role( PermissionMatrix::ROLE_MANAGER ) );
		$admin = get_role( 'administrator' );
		$this->assertFalse( $admin->has_cap( Capabilities::MANAGE_SETTINGS ) );

		// Restore for tear_down symmetry.
		$this->roles->install();
	}

	public function test_capabilities_list_has_no_duplicates(): void {
		$all = Capabilities::all();
		$this->assertSame( array_values( array_unique( $all ) ), $all );
	}
}
