<?php
/**
 * Advanced features (coupons, gift cards, memberships, waitlist, resources) tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Advanced;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Coupons\CouponManager;
use Bookora\Coupons\CouponRepository;
use Bookora\Customers\CustomerManager;
use Bookora\Customers\CustomerRepository;
use Bookora\Customers\NoteRepository;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\GiftCards\GiftCardManager;
use Bookora\GiftCards\GiftCardRepository;
use Bookora\Memberships\CustomerMembershipRepository;
use Bookora\Memberships\MembershipManager;
use Bookora\Memberships\MembershipRepository;
use Bookora\Resources\ResourceManager;
use Bookora\Resources\ResourceRepository;
use Bookora\Security\ActivityLogger;
use Bookora\Waitlist\WaitlistManager;
use Bookora\Waitlist\WaitlistRepository;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Coupons\CouponManager
 * @covers \Bookora\GiftCards\GiftCardManager
 * @covers \Bookora\Memberships\MembershipManager
 * @covers \Bookora\Waitlist\WaitlistManager
 * @covers \Bookora\Resources\ResourceManager
 */
class AdvancedFeaturesTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private Schema $schema;
	private ActivityLogger $audit;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();
		$this->schema = new Schema();
		$this->audit  = new ActivityLogger( new AuditLogRepository( null, $this->schema ) );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	public function test_coupon_percent_and_limits(): void {
		$manager = new CouponManager( new CouponRepository( null, $this->schema ), $this->audit );
		$manager->create( array( 'code' => 'save10', 'type' => 'percent', 'value' => 10, 'usage_limit' => 1, 'min_amount' => 50 ) );

		$valid = $manager->validate( 'SAVE10', 100 );
		$this->assertIsArray( $valid );
		$this->assertEqualsWithDelta( 10.0, $valid['discount'], 0.001 );
		$this->assertEqualsWithDelta( 90.0, $valid['final'], 0.001 );

		// Below minimum is rejected.
		$this->assertInstanceOf( WP_Error::class, $manager->validate( 'SAVE10', 10 ) );

		// After redemption hits the limit.
		$manager->redeem( (int) $valid['coupon_id'] );
		$this->assertInstanceOf( WP_Error::class, $manager->validate( 'SAVE10', 100 ) );
	}

	public function test_gift_card_redeem_debits_balance(): void {
		$manager = new GiftCardManager( new GiftCardRepository( null, $this->schema ), $this->audit );
		$card    = $manager->issue( array( 'amount' => 100 ) );
		$code    = (string) $card['code'];

		$first = $manager->redeem( $code, 30 );
		$this->assertEqualsWithDelta( 30.0, $first['applied'], 0.001 );
		$this->assertEqualsWithDelta( 70.0, $first['balance_after'], 0.001 );

		// Redeeming more than the balance applies only the remaining balance.
		$second = $manager->redeem( $code, 1000 );
		$this->assertEqualsWithDelta( 70.0, $second['applied'], 0.001 );
		$this->assertEqualsWithDelta( 0.0, $manager->balance( $code )['balance'], 0.001 );
	}

	public function test_membership_discount(): void {
		$plans   = new MembershipRepository( null, $this->schema );
		$enrol   = new CustomerMembershipRepository( null, $this->schema );
		$manager = new MembershipManager( $plans, $enrol, $this->audit );

		$plan = $manager->create_plan( array( 'name' => 'VIP', 'price' => 20, 'billing_period' => 'month', 'benefit_type' => 'discount_percent', 'benefit_value' => 15 ) );
		$customer = ( new CustomerRepository( null, $this->schema ) )->create( array( 'name' => 'Ada' ) );

		$this->assertEqualsWithDelta( 0.0, $manager->discount_for( $customer, 100 ), 0.001 );
		$manager->enroll( $customer, (int) $plan['id'] );
		$this->assertEqualsWithDelta( 15.0, $manager->discount_for( $customer, 100 ), 0.001 );
	}

	public function test_resource_is_free_respects_capacity(): void {
		$repo    = new ResourceRepository( null, $this->schema );
		$manager = new ResourceManager( $repo, $this->audit );
		$res     = $manager->create( array( 'name' => 'Room A', 'capacity' => 1 ) );
		$rid     = (int) $res['id'];

		$this->assertTrue( $manager->is_free( $rid, '2030-06-12 09:00:00', '2030-06-12 10:00:00' ) );

		// Book the room for that window.
		( new AppointmentRepository( null, $this->schema ) )->create(
			array(
				'customer_id' => 1,
				'service_id'  => 1,
				'resource_id' => $rid,
				'start_at'    => '2030-06-12 09:00:00',
				'end_at'      => '2030-06-12 10:00:00',
				'status'      => 'confirmed',
				'total'       => 0,
				'currency'    => 'NGN',
			)
		);

		$this->assertFalse( $manager->is_free( $rid, '2030-06-12 09:30:00', '2030-06-12 10:30:00' ) );
		$this->assertTrue( $manager->is_free( $rid, '2030-06-12 11:00:00', '2030-06-12 12:00:00' ) );
	}

	public function test_waitlist_join_and_promote_on_cancel(): void {
		$customers = new CustomerRepository( null, $this->schema );
		$cm        = new CustomerManager( $customers, new NoteRepository( null, $this->schema ), new AuditLogRepository( null, $this->schema ), $this->audit );
		$appts     = new AppointmentRepository( null, $this->schema );
		$wl_repo   = new WaitlistRepository( null, $this->schema );
		$manager   = new WaitlistManager( $wl_repo, $appts, $cm, $this->audit );

		$entry = $manager->join( array( 'service_id' => 7, 'customer' => array( 'name' => 'Joy', 'email' => 'joy@example.com' ) ) );
		$this->assertIsArray( $entry );
		$this->assertSame( 'active', $entry['status'] );

		// A cancelled appointment for service 7 promotes the waitlisted customer.
		$appt_id = $appts->create(
			array( 'customer_id' => 1, 'service_id' => 7, 'start_at' => '2030-06-12 09:00:00', 'end_at' => '2030-06-12 09:30:00', 'status' => 'cancelled', 'total' => 0, 'currency' => 'NGN' )
		);
		$this->assertSame( 1, $manager->promote_for_appointment( $appt_id ) );
		$this->assertSame( 'notified', $wl_repo->find( (int) $entry['id'] )['status'] );
	}
}
