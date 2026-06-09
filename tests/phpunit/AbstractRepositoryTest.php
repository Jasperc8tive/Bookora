<?php
/**
 * AbstractRepository tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests;

use Bookora\Database\Schema;
use Bookora\Tests\Fixtures\WidgetRepository;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Database\Repository\AbstractRepository
 */
class AbstractRepositoryTest extends WP_UnitTestCase {

	private Schema $schema;
	private WidgetRepository $repo;
	private string $table;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->schema = new Schema();
		$this->repo   = new WidgetRepository( $wpdb, $this->schema );
		$this->table  = $this->schema->table( 'test_widgets' );
		$collate      = $this->schema->charset_collate();

		$wpdb->query( "DROP TABLE IF EXISTS `{$this->table}`" ); // phpcs:ignore
		$wpdb->query(
			"CREATE TABLE `{$this->table}` (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				qty int NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY (id)
			) {$collate}"
		); // phpcs:ignore
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS `{$this->table}`" ); // phpcs:ignore
		parent::tear_down();
	}

	public function test_create_and_find(): void {
		$id = $this->repo->create( array( 'name' => 'Alpha', 'qty' => 3 ) );
		$this->assertGreaterThan( 0, $id );

		$row = $this->repo->find( $id );
		$this->assertNotNull( $row );
		$this->assertSame( 'Alpha', $row['name'] );
		$this->assertSame( '3', (string) $row['qty'] );
	}

	public function test_update(): void {
		$id = $this->repo->create( array( 'name' => 'Alpha', 'qty' => 1 ) );
		$this->assertTrue( $this->repo->update( $id, array( 'name' => 'Beta' ) ) );

		$row = $this->repo->find( $id );
		$this->assertSame( 'Beta', $row['name'] );
	}

	public function test_soft_delete_hides_row_but_keeps_it(): void {
		$id = $this->repo->create( array( 'name' => 'Gamma' ) );

		$this->assertTrue( $this->repo->delete( $id ) );
		$this->assertNull( $this->repo->find( $id ), 'Soft-deleted row should be hidden by default.' );
		$this->assertNotNull( $this->repo->find( $id, true ), 'Row should still exist when trashed are included.' );
		$this->assertSame( 0, $this->repo->count() );
	}

	public function test_restore(): void {
		$id = $this->repo->create( array( 'name' => 'Delta' ) );
		$this->repo->delete( $id );

		$this->assertTrue( $this->repo->restore( $id ) );
		$this->assertNotNull( $this->repo->find( $id ) );
	}

	public function test_force_delete_removes_permanently(): void {
		$id = $this->repo->create( array( 'name' => 'Epsilon' ) );

		$this->assertTrue( $this->repo->force_delete( $id ) );
		$this->assertNull( $this->repo->find( $id, true ) );
	}

	public function test_all_and_find_by(): void {
		$this->repo->create( array( 'name' => 'One', 'qty' => 1 ) );
		$this->repo->create( array( 'name' => 'Two', 'qty' => 2 ) );

		$all = $this->repo->all( array( 'orderby' => 'qty', 'order' => 'DESC' ) );
		$this->assertCount( 2, $all );
		$this->assertSame( 'Two', $all[0]['name'] );

		$found = $this->repo->find_by( array( 'name' => 'One' ) );
		$this->assertNotNull( $found );
		$this->assertSame( '1', (string) $found['qty'] );
	}

	public function test_create_ignores_invalid_column_names(): void {
		$id  = $this->repo->create( array( 'name' => 'Safe', '0; DROP TABLE x' => 'evil' ) );
		$row = $this->repo->find( $id );
		$this->assertSame( 'Safe', $row['name'] );
	}
}
