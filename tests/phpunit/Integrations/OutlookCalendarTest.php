<?php
/**
 * Outlook / Microsoft Graph integration tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Integrations;

use Bookora\Core\Settings;
use Bookora\Database\Schema;
use Bookora\Integrations\IntegrationRepository;
use Bookora\Integrations\Microsoft\GraphClient;
use Bookora\Integrations\Microsoft\MicrosoftTokenStore;
use Bookora\Integrations\OAuthState;
use Bookora\Security\Crypto;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Integrations\Microsoft\MicrosoftTokenStore
 * @covers \Bookora\Integrations\Microsoft\GraphClient
 * @covers \Bookora\Integrations\OAuthState
 * @covers \Bookora\Integrations\AbstractTokenStore
 */
class OutlookCalendarTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_microsoft_token_store_round_trip_and_isolation(): void {
		$store = new MicrosoftTokenStore( new IntegrationRepository( null, new Schema() ), new Crypto() );
		$store->store( 3, array( 'access_token' => 'ms_at', 'refresh_token' => 'ms_rt', 'expires_in' => 3600 ) );

		$this->assertTrue( $store->is_connected( 3 ) );
		$this->assertSame( 'ms_at', $store->get( 3 )['access_token'] );
		$this->assertSame( array( 3 ), $store->connected_staff_ids() );

		// Provider key is namespaced under outlook_, distinct from google_.
		$repo = new IntegrationRepository( null, new Schema() );
		$this->assertNotNull( $repo->for_provider( 'outlook_3' ) );
		$this->assertNull( $repo->for_provider( 'google_3' ) );
		$this->assertStringNotContainsString( 'ms_at', (string) $repo->for_provider( 'outlook_3' )['credentials'] );
	}

	public function test_graph_to_event_shape(): void {
		$event = ( new GraphClient( new Settings() ) )->to_event(
			array( 'summary' => 'Cut — Ada', 'start_utc' => '2030-06-12 09:00:00', 'end_utc' => '2030-06-12 09:30:00' )
		);

		$this->assertSame( 'Cut — Ada', $event['subject'] );
		$this->assertSame( '2030-06-12T09:00:00', $event['start']['dateTime'] );
		$this->assertSame( 'UTC', $event['start']['timeZone'] );
		$this->assertSame( '2030-06-12T09:30:00', $event['end']['dateTime'] );
	}

	public function test_oauth_state_round_trip_and_rejection(): void {
		$state = OAuthState::sign( 42 );
		$this->assertSame( 42, OAuthState::verify( $state ) );

		// Tampered state is rejected.
		$this->assertNull( OAuthState::verify( $state . 'x' ) );
		$this->assertNull( OAuthState::verify( 'garbage' ) );
	}
}
