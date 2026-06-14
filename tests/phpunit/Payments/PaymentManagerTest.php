<?php
/**
 * PaymentManager tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Payments;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Payments\GatewayRegistry;
use Bookora\Payments\Gateways\PaystackDriver;
use Bookora\Payments\PaymentManager;
use Bookora\Payments\PaymentRepository;
use Bookora\Security\ActivityLogger;
use Bookora\Services\ServiceRepository;
use Bookora\Tests\Payments\Fixtures\FakeGateway;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Payments\PaymentManager
 * @covers \Bookora\Payments\Gateways\PaystackDriver
 */
class PaymentManagerTest extends WP_UnitTestCase {

	private PaymentManager $manager;
	private PaymentRepository $payments;
	private AppointmentRepository $appointments;
	private FakeGateway $gateway;
	private int $appointment_id;

	public function set_up(): void {
		parent::set_up();

		$schema             = new Schema();
		$this->payments     = new PaymentRepository( null, $schema );
		$this->appointments = new AppointmentRepository( null, $schema );
		$services           = new ServiceRepository( null, $schema );
		$customers          = new CustomerRepository( null, $schema );
		$audit              = new ActivityLogger( new AuditLogRepository( null, $schema ) );

		$this->gateway = new FakeGateway();
		$registry      = new GatewayRegistry();
		$registry->add( $this->gateway );

		$this->manager = new PaymentManager( $this->payments, $this->appointments, $services, $customers, $registry, new Settings(), $audit );

		$service_id  = $services->create( array( 'name' => 'Cut', 'price' => 100, 'currency' => 'NGN', 'deposit_type' => 'percent', 'deposit_value' => 25 ) );
		$customer_id = $customers->create( array( 'name' => 'Joy', 'email' => 'joy@example.com' ) );
		$this->appointment_id = $this->appointments->create(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'staff_id'    => 1,
				'start_at'    => '2030-06-12 09:00:00',
				'end_at'      => '2030-06-12 09:30:00',
				'status'      => 'pending',
				'price'       => 100,
				'total'       => 100,
				'balance_due' => 100,
				'currency'    => 'NGN',
			)
		);
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	private function webhook( string $reference, array $overrides = array() ): array {
		return wp_parse_args( $overrides, array( 'reference' => $reference, 'status' => 'success', 'amount' => 100.0, 'currency' => 'NGN' ) );
	}

	public function test_initialize_creates_pending_payment_full(): void {
		$result = $this->manager->initialize( $this->appointment_id, 'fake', 'full', 'https://site.test/return' );
		$this->assertIsArray( $result );

		$payment = $this->payments->find( $result['payment_id'] );
		$this->assertSame( 'pending', $payment['status'] );
		$this->assertSame( '100.00', $payment['amount'] );
		$this->assertStringStartsWith( 'https://pay.example.test/', $result['redirect_url'] );
	}

	public function test_initialize_deposit_uses_percentage(): void {
		$result  = $this->manager->initialize( $this->appointment_id, 'fake', 'deposit', 'https://site.test/return' );
		$payment = $this->payments->find( $result['payment_id'] );
		$this->assertSame( '25.00', $payment['amount'] );
		$this->assertSame( 'deposit', $payment['type'] );
	}

	public function test_webhook_marks_paid_and_confirms_appointment(): void {
		$init = $this->manager->initialize( $this->appointment_id, 'fake', 'full', 'https://site.test/return' );
		$ref  = $init['reference'];

		$result = $this->manager->handle_webhook( 'fake', (string) wp_json_encode( $this->webhook( $ref ) ), 'sig' );
		$this->assertTrue( $result['handled'] );

		$this->assertSame( 'paid', $this->payments->find( $init['payment_id'] )['status'] );
		$appt = $this->appointments->find( $this->appointment_id );
		$this->assertSame( '100.00', $appt['amount_paid'] );
		$this->assertSame( 'confirmed', $appt['status'] );
	}

	public function test_webhook_rejects_bad_signature(): void {
		$init                  = $this->manager->initialize( $this->appointment_id, 'fake', 'full', 'https://site.test/return' );
		$this->gateway->signature_ok = false;

		$result = $this->manager->handle_webhook( 'fake', (string) wp_json_encode( $this->webhook( $init['reference'] ) ), 'bad' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_webhook_rejects_amount_mismatch(): void {
		$init   = $this->manager->initialize( $this->appointment_id, 'fake', 'full', 'https://site.test/return' );
		$result = $this->manager->handle_webhook( 'fake', (string) wp_json_encode( $this->webhook( $init['reference'], array( 'amount' => 5.0 ) ) ), 'sig' );

		$this->assertFalse( $result['handled'] );
		$this->assertSame( 'flagged', $this->payments->find( $init['payment_id'] )['status'] );
	}

	public function test_webhook_is_idempotent(): void {
		$init = $this->manager->initialize( $this->appointment_id, 'fake', 'full', 'https://site.test/return' );
		$body = (string) wp_json_encode( $this->webhook( $init['reference'] ) );

		$this->manager->handle_webhook( 'fake', $body, 'sig' );
		$this->manager->handle_webhook( 'fake', $body, 'sig' );

		// Only one paid record; appointment not over-credited.
		$this->assertSame( '100.00', $this->appointments->find( $this->appointment_id )['amount_paid'] );
	}

	public function test_manual_payment_and_refund(): void {
		$this->manager->record_manual( $this->appointment_id, 40.0, 'cash' );
		$this->assertSame( '40.00', $this->appointments->find( $this->appointment_id )['amount_paid'] );

		$init = $this->manager->initialize( $this->appointment_id, 'fake', 'full', 'https://site.test/return' );
		$this->manager->handle_webhook( 'fake', (string) wp_json_encode( $this->webhook( $init['reference'], array( 'amount' => 60.0 ) ) ), 'sig' );
		// 40 manual + 60 online = 100 paid.
		$this->assertSame( '100.00', $this->appointments->find( $this->appointment_id )['amount_paid'] );

		$refund = $this->manager->refund( $init['payment_id'], 60.0 );
		$this->assertSame( 'refunded', $refund['status'] );
		$this->assertTrue( $this->gateway->refunded );
		$this->assertSame( '40.00', $this->appointments->find( $this->appointment_id )['amount_paid'] );
	}

	public function test_paystack_signature_verification(): void {
		$settings = new Settings();
		$settings->set( 'payments', array( 'paystack' => array( 'enabled' => true, 'secret_key' => 'sk_test_secret' ) ) );
		$driver = new PaystackDriver( $settings );

		$payload = (string) wp_json_encode( array( 'event' => 'charge.success', 'data' => array( 'reference' => 'r1' ) ) );
		$valid   = hash_hmac( 'sha512', $payload, 'sk_test_secret' );

		$this->assertTrue( $driver->verify_signature( $payload, $valid ) );
		$this->assertFalse( $driver->verify_signature( $payload, 'deadbeef' ) );

		$settings->set( 'payments', array() );
	}
}
