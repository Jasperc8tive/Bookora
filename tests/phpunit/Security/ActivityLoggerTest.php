<?php
/**
 * Activity logger / audit chain tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Security;

use Bookora\Database\MigrationRunner;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Security\ActivityLogger;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Security\ActivityLogger
 * @covers \Bookora\Database\Repository\AuditLogRepository
 */
class ActivityLoggerTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private AuditLogRepository $repo;
	private ActivityLogger $logger;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$this->repo   = new AuditLogRepository( null, new Schema() );
		$this->logger = new ActivityLogger( $this->repo );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	public function test_log_writes_a_row(): void {
		$id  = $this->logger->log( 'settings.changed', array( 'entity_type' => 'settings' ) );
		$row = $this->repo->find( $id );

		$this->assertNotNull( $row );
		$this->assertSame( 'settings.changed', $row['action'] );
		$this->assertSame( 'settings', $row['entity_type'] );
		$this->assertNotEmpty( $row['after_hash'] );
	}

	public function test_hash_chain_links_consecutive_entries(): void {
		$id1 = $this->logger->log( 'a.one' );
		$id2 = $this->logger->log( 'a.two' );

		$first  = $this->repo->find( $id1 );
		$second = $this->repo->find( $id2 );

		$this->assertSame( '', $first['prev_hash'] );
		$this->assertSame( $first['after_hash'], $second['prev_hash'] );
		$this->assertTrue( $this->repo->verify_chain() );
	}

	public function test_ip_is_not_stored_in_clear(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$id                     = $this->logger->log( 'auth.login' );
		$row                    = $this->repo->find( $id );

		$this->assertNotEmpty( $row['ip_hash'] );
		$this->assertStringNotContainsString( '203.0.113.7', (string) $row['ip_hash'] );
		$this->assertStringNotContainsString( '203.0.113.7', (string) $row['context'] );
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_tampering_breaks_the_chain(): void {
		$this->logger->log( 'a.one' );
		$id2 = $this->logger->log( 'a.two' );

		// Tamper with a stored record.
		global $wpdb;
		$table = ( new Schema() )->table( 'activity_logs' );
		$wpdb->update( $table, array( 'action' => 'a.two.TAMPERED', 'context' => '{"x":1}' ), array( 'id' => $id2 ) );

		$this->assertFalse( $this->repo->verify_chain() );
	}
}
