<?php
/**
 * Google Calendar integration tests (crypto, token store, mapping, busy filter).
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Integrations;

use Bookora\Appointments\AvailabilityEngine;
use Bookora\Appointments\Clock;
use Bookora\Core\Settings;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Integrations\Google\GoogleClient;
use Bookora\Integrations\Google\GoogleTokenStore;
use Bookora\Integrations\IntegrationRepository;
use Bookora\Security\Crypto;
use Bookora\Services\ServiceRepository;
use Bookora\Staff\AvailabilityRepository;
use Bookora\Staff\StaffRepository;
use Bookora\Staff\StaffServiceRepository;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Security\Crypto
 * @covers \Bookora\Integrations\Google\GoogleTokenStore
 * @covers \Bookora\Integrations\Google\GoogleClient
 * @covers \Bookora\Appointments\AvailabilityEngine
 */
class GoogleCalendarTest extends WP_UnitTestCase {

	private MigrationRunner $runner;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	public function test_crypto_round_trip(): void {
		$crypto = new Crypto();
		$enc    = $crypto->encrypt( 'super-secret-token' );

		$this->assertStringNotContainsString( 'super-secret-token', $enc );
		$this->assertSame( 'super-secret-token', $crypto->decrypt( $enc ) );
	}

	public function test_token_store_encrypts_and_round_trips(): void {
		$store = new GoogleTokenStore( new IntegrationRepository( null, new Schema() ), new Crypto() );
		$store->store( 7, array( 'access_token' => 'at_123', 'refresh_token' => 'rt_456', 'expires_in' => 3600, 'calendar_id' => 'primary' ) );

		$this->assertTrue( $store->is_connected( 7 ) );
		$this->assertSame( 'at_123', $store->get( 7 )['access_token'] );
		$this->assertSame( array( 7 ), $store->connected_staff_ids() );

		// Stored credentials are not in clear text.
		$row = ( new IntegrationRepository( null, new Schema() ) )->for_provider( 'google_7' );
		$this->assertStringNotContainsString( 'at_123', (string) $row['credentials'] );

		$store->disconnect( 7 );
		$this->assertFalse( $store->is_connected( 7 ) );
	}

	public function test_to_event_maps_utc_to_rfc3339(): void {
		$event = ( new GoogleClient( new Settings() ) )->to_event(
			array( 'summary' => 'Facial — Ada', 'start_utc' => '2030-06-12 09:00:00', 'end_utc' => '2030-06-12 09:30:00' )
		);

		$this->assertSame( 'Facial — Ada', $event['summary'] );
		$this->assertSame( '2030-06-12T09:00:00Z', $event['start']['dateTime'] );
		$this->assertSame( '2030-06-12T09:30:00Z', $event['end']['dateTime'] );
	}

	public function test_external_busy_filter_blocks_a_slot(): void {
		$schema      = new Schema();
		$services    = new ServiceRepository( null, $schema );
		$staff       = new StaffRepository( null, $schema );
		$assignments = new StaffServiceRepository( null, $schema );
		$avail       = new AvailabilityRepository( null, $schema );

		$service_id = $services->create( array( 'name' => 'Cut', 'duration_min' => 60, 'price' => 10, 'currency' => 'NGN', 'status' => 'active' ) );
		$staff_id   = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );
		$assignments->sync_for_staff( $staff_id, array( $service_id ) );

		$date = '2030-06-12';
		$avail->create(
			array(
				'staff_id'   => $staff_id,
				'type'       => 'working_hours',
				'weekday'    => (int) gmdate( 'w', (int) strtotime( $date ) ),
				'start_time' => '09:00:00',
				'end_time'   => '12:00:00',
			)
		);

		$engine = new AvailabilityEngine(
			$services,
			$avail,
			$assignments,
			new \Bookora\Appointments\AppointmentRepository( null, $schema ),
			new \Bookora\Appointments\BookingHoldRepository( null, $schema ),
			new Clock()
		);

		$this->assertCount( 3, $engine->for_date( $service_id, $staff_id, $date )['slots'] );

		// Block 10:00–11:00 via the external-busy filter.
		$cb = static function ( array $busy ): array {
			$busy[] = array( 'start_utc' => '2030-06-12 10:00:00', 'end_utc' => '2030-06-12 11:00:00' );

			return $busy;
		};
		add_filter( 'bookora_external_busy', $cb );
		$slots = $engine->for_date( $service_id, $staff_id, $date )['slots'];
		remove_filter( 'bookora_external_busy', $cb );

		$this->assertCount( 2, $slots );
		foreach ( $slots as $slot ) {
			$this->assertNotSame( '2030-06-12 10:00:00', $slot['start_local'] );
		}
	}
}
