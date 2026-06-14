<?php
/**
 * CustomerManager tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Customers;

use Bookora\Customers\CustomerManager;
use Bookora\Customers\CustomerRepository;
use Bookora\Customers\NoteRepository;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Security\ActivityLogger;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Customers\CustomerManager
 * @covers \Bookora\Customers\CustomerRepository
 * @covers \Bookora\Customers\NoteRepository
 */
class CustomerManagerTest extends WP_UnitTestCase {

	private CustomerManager $manager;

	public function set_up(): void {
		parent::set_up();

		$schema        = new Schema();
		$audit_repo    = new AuditLogRepository( null, $schema );
		$this->manager = new CustomerManager(
			new CustomerRepository( null, $schema ),
			new NoteRepository( null, $schema ),
			$audit_repo,
			new ActivityLogger( $audit_repo )
		);
	}

	public function tear_down(): void {
		parent::tear_down();
	}

	public function test_create_requires_a_name(): void {
		$result = $this->manager->create( array( 'email' => 'x@example.com' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'name', $result->get_error_data()['fields'] );
	}

	public function test_name_is_derived_from_first_and_last(): void {
		$result = $this->manager->create( array( 'first_name' => 'Ada', 'last_name' => 'Lovelace' ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'Ada Lovelace', $result['name'] );
	}

	public function test_duplicate_email_is_rejected(): void {
		$this->manager->create( array( 'name' => 'Ada', 'email' => 'ada@example.com' ) );
		$dup = $this->manager->create( array( 'name' => 'Ada 2', 'email' => 'ada@example.com' ) );
		$this->assertInstanceOf( WP_Error::class, $dup );
	}

	public function test_tags_round_trip(): void {
		$created = $this->manager->create( array( 'name' => 'Ada', 'tags' => 'VIP, Regular , VIP' ) );
		$this->assertSame( array( 'VIP', 'Regular' ), $created['tags'] );

		$tags = $this->manager->tags();
		$this->assertContains( 'VIP', $tags );
		$this->assertContains( 'Regular', $tags );
	}

	public function test_notes_add_list_delete_with_ownership(): void {
		$a = $this->manager->create( array( 'name' => 'A' ) );
		$b = $this->manager->create( array( 'name' => 'B' ) );

		$note = $this->manager->add_note( (int) $a['id'], 'Allergic to latex' );
		$this->assertIsArray( $note );
		$this->assertCount( 1, $this->manager->notes( (int) $a['id'] ) );

		// Cannot delete A's note via B.
		$this->assertFalse( $this->manager->delete_note( (int) $b['id'], (int) $note['id'] ) );
		$this->assertTrue( $this->manager->delete_note( (int) $a['id'], (int) $note['id'] ) );
		$this->assertCount( 0, $this->manager->notes( (int) $a['id'] ) );
	}

	public function test_empty_note_is_rejected(): void {
		$a      = $this->manager->create( array( 'name' => 'A' ) );
		$result = $this->manager->add_note( (int) $a['id'], '   ' );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_timeline_merges_notes_and_activity(): void {
		$a = $this->manager->create( array( 'name' => 'A' ) ); // emits customer.created
		$this->manager->add_note( (int) $a['id'], 'First note' );

		$timeline = $this->manager->timeline( (int) $a['id'] );
		$types    = array_column( $timeline, 'type' );

		$this->assertContains( 'note', $types );
		$this->assertContains( 'activity', $types );
	}

	public function test_booking_stats_default_to_zero(): void {
		$a     = $this->manager->create( array( 'name' => 'A' ) );
		$stats = $a['stats'];
		$this->assertSame( 0, $stats['count'] );
		$this->assertSame( 0.0, $stats['total_spent'] );
	}
}
