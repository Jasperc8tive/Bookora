<?php
/**
 * Commercial hardening (licensing, feature flags, data portability) tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Commercial;

use Bookora\Core\Settings;
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

	private Schema $schema;
	private Settings $settings;
	private ActivityLogger $audit;

	public function set_up(): void {
		parent::set_up();
		$this->schema   = new Schema();
		$this->settings = new Settings();
		$this->audit    = new ActivityLogger( new AuditLogRepository( null, $this->schema ) );
	}

	public function tear_down(): void {
		delete_option( 'bookora_license' );
		// This class exercises DataPortability::import and BackupManager, which
		// COMMIT (atomic restore) and therefore END WP_UnitTestCase's isolation
		// transaction. Without an explicit purge, that committed data leaks into
		// later test classes. Clean up the rows we may have committed.
		$this->purge_bookora_tables();
		parent::tear_down();
	}

	/**
	 * Delete all rows from the Bookora data tables (FK-safe), excluding the
	 * migration ledger. Used to undo data committed by import/backup tests.
	 */
	private function purge_bookora_tables(): void {
		global $wpdb;
		$prefix = $wpdb->prefix . 'bkra_';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		foreach ( $tables as $table ) {
			if ( $prefix . 'migrations' === $table ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DELETE FROM `{$table}`" );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
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

	/**
	 * HOTFIX-004: with no license server configured the site MUST default-deny
	 * to the free tier (it must NOT silently grant pro). The key is still stored
	 * encrypted.
	 */
	public function test_activation_without_server_defaults_to_free_tier(): void {
		add_filter( 'bookora_license_api_url', '__return_empty_string' );
		$license = $this->license();

		$result = $license->activate( 'BKRA-TEST-KEY-1234' );
		$this->assertIsArray( $result );
		$this->assertSame( 'free', $license->tier(), 'No server must default-deny to free.' );

		// Stored key is encrypted, never plaintext, but decrypts back.
		$raw = get_option( 'bookora_license' );
		$this->assertIsArray( $raw );
		$this->assertNotSame( 'BKRA-TEST-KEY-1234', $raw['key'] );
		$this->assertSame( 'BKRA-TEST-KEY-1234', $license->get_key() );

		remove_filter( 'bookora_license_api_url', '__return_empty_string' );
	}

	/**
	 * HOTFIX-004: a configured server can grant pro; tiers are then enforced by
	 * FeatureFlags with overrides and the kill-switch filter.
	 */
	public function test_feature_flags_follow_tier_and_overrides(): void {
		$license  = $this->license();
		$features = new FeatureFlags( $license, $this->settings );

		// Free tier: pro/agency features off, core on.
		$this->assertTrue( $features->enabled( 'payments' ) );
		$this->assertFalse( $features->enabled( 'ai_scheduling' ) );
		$this->assertFalse( $features->enabled( 'white_label' ) );

		// Activate against a (mocked) server that returns the pro tier.
		add_filter( 'bookora_license_api_url', static fn (): string => 'https://lic.test/api' );
		$mock = $this->mock_license_server( array( 'status' => 'active', 'tier' => 'pro' ) );
		add_filter( 'pre_http_request', $mock, 10, 3 );
		$license->activate( 'KEY' );
		remove_filter( 'pre_http_request', $mock, 10 );

		$this->assertSame( 'pro', $license->tier() );
		$this->assertTrue( $features->enabled( 'ai_scheduling' ) );
		$this->assertFalse( $features->enabled( 'white_label' ), 'Agency-only stays locked on pro.' );

		// Per-site override forces a feature off regardless of tier.
		$this->settings->set( 'feature_overrides', array( 'ai_scheduling' => false ) );
		$this->assertFalse( $features->enabled( 'ai_scheduling' ) );

		// The kill-switch filter has the final say.
		add_filter( 'bookora_feature_enabled', '__return_false' );
		$this->assertFalse( $features->enabled( 'payments' ) );
		remove_filter( 'bookora_feature_enabled', '__return_false' );
	}

	/**
	 * HOTFIX-004: when a public key is configured, an unsigned server response is
	 * rejected (tamper resistance) and the site is not granted a paid tier.
	 */
	public function test_unsigned_response_is_rejected_when_public_key_set(): void {
		add_filter( 'bookora_license_api_url', static fn (): string => 'https://lic.test/api' );
		add_filter( 'bookora_license_public_key', static fn (): string => "-----BEGIN PUBLIC KEY-----\nMOCK\n-----END PUBLIC KEY-----" );
		$mock = $this->mock_license_server( array( 'status' => 'active', 'tier' => 'agency' ) ); // no signature
		add_filter( 'pre_http_request', $mock, 10, 3 );

		$license = $this->license();
		$result  = $license->activate( 'KEY' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'bookora_license_untrusted', $result->get_error_code() );
		$this->assertSame( 'free', $license->tier() );

		remove_filter( 'pre_http_request', $mock, 10 );
	}

	/**
	 * Build a `pre_http_request` short-circuit that emulates a license server
	 * returning the given data payload as JSON.
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return callable
	 */
	private function mock_license_server( array $data ): callable {
		return static function () use ( $data ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => (string) wp_json_encode( array( 'data' => $data ) ),
			);
		};
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

	/**
	 * HOTFIX-001 regression: a backup of FK-linked tables (service → appointment
	 * → payment) must restore atomically. The old TRUNCATE path failed on the
	 * FK-referenced parents and left partial data.
	 */
	public function test_import_restores_fk_linked_tables_atomically(): void {
		$services = new ServiceRepository( null, $this->schema );
		$service  = $services->create(
			array(
				'name'         => 'Cut',
				'duration_min' => 30,
				'price'        => 50,
				'currency'     => 'NGN',
				'status'       => 'active',
			)
		);
		$customers = new \Bookora\Customers\CustomerRepository( null, $this->schema );
		$customer  = $customers->create( array( 'name' => 'Joy' ) );
		$staff     = new \Bookora\Staff\StaffRepository( null, $this->schema );
		$member    = $staff->create( array( 'display_name' => 'Ada', 'status' => 'active' ) );

		$appointments = new \Bookora\Appointments\AppointmentRepository( null, $this->schema );
		$appt         = $appointments->create(
			array(
				'service_id' => $service,
				'staff_id'   => $member,
				'customer_id' => $customer,
				'start_at'   => '2030-01-01 09:00:00',
				'end_at'     => '2030-01-01 09:30:00',
				'status'     => 'confirmed',
				'total'      => 50,
				'currency'   => 'NGN',
			)
		);
		$this->assertGreaterThan( 0, $appt );

		$data     = new DataPortability( $this->schema, $this->settings, $this->audit );
		$document = $data->export();

		// Round-trip: importing the export over the live data must succeed and
		// preserve the FK-linked chain (no TRUNCATE-on-referenced-parent error).
		$result = $data->import( $document, false );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'appointments', $result );

		$this->assertIsArray( $services->find( $service ) );
		$this->assertIsArray( $customers->find( $customer ) );
		$this->assertIsArray( $appointments->find( $appt ) );
	}

	/**
	 * HOTFIX-001: unknown columns in an import payload are filtered out rather
	 * than reaching the SQL layer, and the row still imports.
	 */
	public function test_import_filters_unknown_columns(): void {
		$services = new ServiceRepository( null, $this->schema );
		$id       = $services->create(
			array(
				'name'         => 'Trim',
				'duration_min' => 20,
				'price'        => 30,
				'currency'     => 'NGN',
				'status'       => 'active',
			)
		);
		$data     = new DataPortability( $this->schema, $this->settings, $this->audit );
		$document = $data->export();
		// Inject a bogus column into the first service row.
		$document['tables']['services'][0]['evil_column`); DROP TABLE x;--'] = 'x';

		$result = $data->import( $document, false );
		$this->assertIsArray( $result );
		$restored = $services->find( $id );
		$this->assertIsArray( $restored );
		$this->assertSame( 'Trim', $restored['name'] );
	}

	/**
	 * HOTFIX-002: a backup is encrypted at rest (no plaintext PII on disk), its
	 * directory is hardened for Apache/IIS, and it restores cleanly.
	 */
	public function test_backup_is_encrypted_at_rest_and_restores(): void {
		$services = new ServiceRepository( null, $this->schema );
		$services->create(
			array(
				'name'         => 'SecretService',
				'duration_min' => 30,
				'price'        => 10,
				'currency'     => 'NGN',
				'status'       => 'active',
			)
		);

		$dir     = sys_get_temp_dir() . '/bkra-backup-' . wp_generate_password( 8, false );
		$data    = new DataPortability( $this->schema, $this->settings, $this->audit );
		$backups = new \Bookora\DataTransfer\BackupManager( $data, $this->audit, new Crypto(), $dir );

		$created = $backups->create();
		$this->assertIsArray( $created );
		$id   = (string) $created['id'];
		$file = $dir . '/' . $id . '.json';

		// On-disk contents must be ciphertext, not the plaintext service name.
		$raw = file_get_contents( $file );
		$this->assertStringNotContainsString( 'SecretService', (string) $raw );
		$this->assertStringStartsWith( 'enc:', (string) $raw );

		// Directory is hardened for Apache + IIS.
		$this->assertFileExists( $dir . '/.htaccess' );
		$this->assertFileExists( $dir . '/web.config' );
		$this->assertFileExists( $dir . '/index.php' );

		// And it restores without error.
		$restored = $backups->restore( $id );
		$this->assertIsArray( $restored );

		// Recursive cleanup that includes hidden guard files (.htaccess, web.config).
		foreach ( (array) glob( $dir . '/{,.}*', GLOB_BRACE ) as $entry ) {
			if ( is_file( $entry ) ) {
				unlink( $entry );
			}
		}
		rmdir( $dir );
	}

	/**
	 * HOTFIX-001 failure path: when an insert fails mid-import the whole
	 * transaction rolls back and pre-existing data is left intact.
	 */
	public function test_import_rolls_back_on_failure_preserving_data(): void {
		$services = new ServiceRepository( null, $this->schema );
		$keep     = $services->create(
			array(
				'name'         => 'KeepMe',
				'duration_min' => 30,
				'price'        => 10,
				'currency'     => 'NGN',
				'status'       => 'active',
			)
		);

		// A payload that wipes services then inserts two rows with the SAME id:
		// the second insert always fails (duplicate PRIMARY KEY), regardless of
		// sql_mode, forcing a rollback.
		$payload = array(
			'format' => 1,
			'tables' => array(
				'services' => array(
					array( 'id' => 999, 'name' => 'A', 'duration_min' => 30, 'price' => 5, 'currency' => 'NGN', 'status' => 'active' ),
					array( 'id' => 999, 'name' => 'B', 'duration_min' => 30, 'price' => 5, 'currency' => 'NGN', 'status' => 'active' ),
				),
			),
		);

		$data   = new DataPortability( $this->schema, $this->settings, $this->audit );
		$result = $data->import( $payload, false );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'bookora_import_failed', $result->get_error_code() );
		$this->assertIsArray( $services->find( $keep ), 'Pre-existing data must survive a rolled-back import.' );
	}
}
