<?php
/**
 * Notification dispatcher / template / reminder tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Notifications;

use Bookora\Appointments\AppointmentRepository;
use Bookora\Appointments\Clock;
use Bookora\Core\Settings;
use Bookora\Customers\CustomerRepository;
use Bookora\Database\Schema;
use Bookora\Notifications\ChannelRegistry;
use Bookora\Notifications\ContextBuilder;
use Bookora\Notifications\NotificationDispatcher;
use Bookora\Notifications\NotificationRepository;
use Bookora\Notifications\TemplateRenderer;
use Bookora\Payments\PaymentRepository;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Tests\Notifications\Fixtures\FakeChannel;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Notifications\NotificationDispatcher
 * @covers \Bookora\Notifications\TemplateRenderer
 * @covers \Bookora\Notifications\ContextBuilder
 */
class NotificationDispatcherTest extends WP_UnitTestCase {

	private NotificationDispatcher $dispatcher;
	private NotificationRepository $log;
	private FakeChannel $channel;
	private int $appointment_id;

	public function set_up(): void {
		parent::set_up();

		$schema    = new Schema();
		$settings  = new Settings();
		$services  = new ServiceRepository( null, $schema );
		$customers = new CustomerRepository( null, $schema );
		$appts     = new AppointmentRepository( null, $schema );
		$this->log = new NotificationRepository( null, $schema );

		$context = new ContextBuilder(
			$appts,
			$services,
			new StaffRepository( null, $schema ),
			$customers,
			new PaymentRepository( null, $schema ),
			$settings,
			new Clock()
		);

		$this->channel = new FakeChannel();
		$registry      = new ChannelRegistry();
		$registry->add( $this->channel );

		$this->dispatcher = new NotificationDispatcher( $registry, new TemplateRenderer( $settings ), $context, $this->log, $settings );

		$service_id           = $services->create( array( 'name' => 'Facial', 'price' => 50, 'currency' => 'NGN' ) );
		$customer_id          = $customers->create( array( 'name' => 'Ada', 'email' => 'ada@example.com' ) );
		$this->appointment_id = $appts->create(
			array(
				'customer_id' => $customer_id,
				'service_id'  => $service_id,
				'start_at'    => '2030-06-12 09:00:00',
				'end_at'      => '2030-06-12 09:30:00',
				'status'      => 'pending',
				'total'       => 50,
				'currency'    => 'NGN',
			)
		);
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_dispatch_renders_template_and_records_sent(): void {
		$this->dispatcher->for_event( 'booking_created', $this->appointment_id );

		$this->assertCount( 1, $this->channel->sent );
		$this->assertSame( 'ada@example.com', $this->channel->sent[0]['recipient'] );
		$this->assertStringContainsString( 'Ada', $this->channel->sent[0]['message']['body'] );
		$this->assertStringContainsString( 'Facial', $this->channel->sent[0]['message']['body'] );

		$rows = $this->log->recent();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'sent', $rows[0]['status'] );
		$this->assertSame( 'booking_created', $rows[0]['event'] );
	}

	public function test_failed_send_is_logged(): void {
		$this->channel->fail = true;
		$this->dispatcher->for_event( 'reminder', $this->appointment_id );

		$this->assertSame( 'failed', $this->log->recent()[0]['status'] );
	}

	public function test_missing_recipient_is_skipped(): void {
		// A customer with no email.
		$schema    = new Schema();
		$customers = new CustomerRepository( null, $schema );
		$appts     = new AppointmentRepository( null, $schema );
		$cid       = $customers->create( array( 'name' => 'NoEmail' ) );
		$aid       = $appts->create(
			array(
				'customer_id' => $cid,
				'service_id'  => 1,
				'start_at'    => '2030-06-12 10:00:00',
				'end_at'      => '2030-06-12 10:30:00',
				'status'      => 'pending',
				'total'       => 10,
				'currency'    => 'NGN',
			)
		);

		$this->dispatcher->for_event( 'booking_created', $aid );
		$this->assertSame( 'skipped', $this->log->recent()[0]['status'] );
		$this->assertCount( 0, $this->channel->sent );
	}

	public function test_custom_template_overrides_default(): void {
		$settings = new Settings();
		$settings->set(
			'notifications',
			array(
				'templates' => array(
					'booking_created' => array( 'fake' => array( 'subject' => '', 'body' => 'Custom for {{customer_name}}' ) ),
				),
			)
		);
		$renderer = new TemplateRenderer( $settings );
		$rendered = $renderer->render( 'booking_created', 'fake', array( 'customer_name' => 'Zed' ) );
		$this->assertSame( 'Custom for Zed', $rendered['body'] );

		$settings->set( 'notifications', array() );
	}

	public function test_schedule_reminders_books_cron_events(): void {
		// Move the appointment far into the future so offsets are schedulable.
		$schema = new Schema();
		( new AppointmentRepository( null, $schema ) )->update(
			$this->appointment_id,
			array( 'start_at' => gmdate( 'Y-m-d H:i:s', time() + 10 * DAY_IN_SECONDS ) )
		);

		$this->dispatcher->schedule_reminders( $this->appointment_id );

		$this->assertNotFalse( wp_next_scheduled( 'bookora_send_reminder', array( $this->appointment_id, 1440 ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'bookora_send_reminder', array( $this->appointment_id, 60 ) ) );
	}
}
