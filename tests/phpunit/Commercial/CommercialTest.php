<?php
/**
 * Commercial hardening (licensing, feature flags, data portability) tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Commercial;

use Bookora\Core\Settings;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\DataTransfer\DataPortability;
use Bookora\Licensing\FeatureFlags;
use Bookora\Licensing\LicenseManager;
use Bookora\Security\ActivityLogger;
use Bookora\Security\Crypto;
use Bookora\Services\ServiceRepository;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Licensing\LicenseManager
 * @covers \Bookora\Licensing\FeatureFlags
 * @covers \Bookora\DataTransfer\DataPortability
 */
class CommercialTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private Schema $schema;
	private Settings $settings;
	private ActivityLogger $audit;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();
		$this->schema   = new Schema();
		$this->settings = new Settings();
		$this->audit    = new ActivityLogger( new AuditLogRepository( null, $this->schema ) );
	}

	public function tear_down(): void {
		delete_option( 'bookora_license' );
		$this->runner->rollback();
		parent::tear_down();
	}

	private function license(): LicenseManager {
		return new LicenseManager( new Crypto(), $this->audit );
	}

	public function test_unlicensed_site_is_free_tier(): void {
		$license = $this->license();
		$this->assertFalse( $license->is_valid() );
		$this->assertSame( 'free', $license->tier() );
		$this->assertSame( '', $license->get_key() );
	}

	public function test_activation_without_server_grants_pro_and_encrypts_key(): void {
		add_filter( 'bookora_license_api_url', '__return_empty_string' );
		$license = $this->license();

		$result = $license->activate( 'BKRA-TEST-KEY-1234' );
		$this->assertIsArray( $result );
		$this->assertTrue( $license->is_valid() );
		$this->assertSame( 'pro', $license->tier() );

		// Stored key is encrypted, never plaintext.
		$raw = get_option( 'bookora_license' );
		$this->assertIsArray( $raw );
		$this->assertNotSame( 'BKRA-TEST-KEY-1234', $raw['key'] );
		// …but decrypts back to the original.
		$this->assertSame( 'BKRA-TEST-KEY-1234', $license->get_key() );

		remove_filter( 'bookora_license_api_url', '__return_empty_string' );
	}

	public function test_feature_flags_follow_tier_and_overrides(): void {
		$license  = $this->license();
		$features = new FeatureFlags( $license, $this->settings );

		// Free tier: pro/agency features off, core on.
		$this->assertTrue( $features->enabled( 'payments' ) );
		$this->assertFalse( $features->enabled( 'ai_scheduling' ) );
		$this->assertFalse( $features->enabled( 'white_label' ) );

		// Activate pro.
		add_filter( 'bookora_license_api_url', '__return_empty_string' );
		$license->activate( 'KEY' );
		$this->assertTrue( $features->enabled( 'ai_scheduling' ) );
		$this->assertFalse( $features->enabled( 'white_label' ), 'Agency-only stays locked on pro.' );
		remove_filter( 'bookora_license_api_url', '__return_empty_string' );

		// Per-site override forces a feature off regardless of tier.
		$this->settings->set( 'feature_overrides', array( 'ai_scheduling' => false ) );
		$this->assertFalse( $features->enabled( 'ai_scheduling' ) );

		// The kill-switch filter has the final say.
		add_filter( 'bookora_feature_enabled', '__return_false' );
		$this->assertFalse( $features->enabled( 'payments' ) );
		remove_filter( 'bookora_feature_enabled', '__return_false' );
	}

	public function test_export_then_import_round_trips_data(): void {
		$services = new ServiceRepository( null, $this->schema );
		$id       = $services->create(
			array(
				'name'         => 'Massage',
				'duration_min' => 60,
				'price'        => 100,
				'currency'     => 'NGN',
				'status'       => 'active',
			)
		);
		$this->assertGreaterThan( 0, $id );

		$data     = new DataPortability( $this->schema, $this->settings, $this->audit );
		$document = $data->export();
		$this->assertArrayHasKey( 'services', $document['tables'] );
		$this->assertCount( 1, $document['tables']['services'] );

		// Wipe, then restore from the export.
		$services->delete( $id );
		$services->force_delete( $id );
		$this->assertNull( $services->find( $id ) );

		$result = $data->import( $document, false );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'services', $result );

		$restored = $services->find( $id );
		$this->assertIsArray( $restored );
		$this->assertSame( 'Massage', $restored['name'] );
	}

	public function test_import_rejects_unknown_format(): void {
		$data   = new DataPortability( $this->schema, $this->settings, $this->audit );
		$result = $data->import( array( 'format' => 99, 'tables' => array() ) );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
