<?php
/**
 * ServiceManager + repository tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Services;

use Bookora\Database\MigrationRunner;
use Bookora\Database\Repository\AuditLogRepository;
use Bookora\Database\Schema;
use Bookora\Core\Settings;
use Bookora\Security\ActivityLogger;
use Bookora\Services\CategoryManager;
use Bookora\Services\CategoryRepository;
use Bookora\Services\ServiceManager;
use Bookora\Services\ServiceRepository;
use WP_Error;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Services\ServiceManager
 * @covers \Bookora\Services\ServiceRepository
 * @covers \Bookora\Services\CategoryManager
 * @covers \Bookora\Services\CategoryRepository
 */
class ServiceManagerTest extends WP_UnitTestCase {

	private MigrationRunner $runner;
	private ServiceManager $services;
	private CategoryManager $categories;
	private ServiceRepository $serviceRepo;
	private CategoryRepository $categoryRepo;

	public function set_up(): void {
		parent::set_up();
		$this->runner = new MigrationRunner();
		$this->runner->migrate();

		$schema             = new Schema();
		$this->serviceRepo  = new ServiceRepository( null, $schema );
		$this->categoryRepo = new CategoryRepository( null, $schema );
		$audit              = new ActivityLogger( new AuditLogRepository( null, $schema ) );
		$settings           = new Settings();

		$this->services   = new ServiceManager( $this->serviceRepo, $this->categoryRepo, $audit, $settings );
		$this->categories = new CategoryManager( $this->categoryRepo, $this->serviceRepo, $audit );
	}

	public function tear_down(): void {
		$this->runner->rollback();
		parent::tear_down();
	}

	public function test_create_requires_a_name(): void {
		$result = $this->services->create( array( 'duration_min' => 30 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertArrayHasKey( 'name', $result->get_error_data()['fields'] );
	}

	public function test_create_persists_and_generates_slug(): void {
		$result = $this->services->create( array( 'name' => 'Deep Tissue Massage', 'price' => 50, 'duration_min' => 60 ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'Deep Tissue Massage', $result['name'] );
		$this->assertSame( 'deep-tissue-massage', $result['slug'] );
		$this->assertSame( '60', (string) $result['duration_min'] );
	}

	public function test_invalid_duration_is_rejected(): void {
		$result = $this->services->create( array( 'name' => 'X', 'duration_min' => 0 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_percent_deposit_cannot_exceed_100(): void {
		$result = $this->services->create(
			array( 'name' => 'Facial', 'deposit_type' => 'percent', 'deposit_value' => 150 )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_unknown_category_is_rejected(): void {
		$result = $this->services->create( array( 'name' => 'Facial', 'category_id' => 9999 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_update_changes_fields(): void {
		$created = $this->services->create( array( 'name' => 'Trim', 'price' => 10 ) );
		$updated = $this->services->update( (int) $created['id'], array( 'price' => 25, 'status' => 'inactive' ) );

		$this->assertIsArray( $updated );
		$this->assertSame( '25.00', (string) $updated['price'] );
		$this->assertSame( 'inactive', $updated['status'] );
	}

	public function test_soft_delete_hides_service(): void {
		$created = $this->services->create( array( 'name' => 'Temp' ) );
		$this->assertTrue( $this->services->delete( (int) $created['id'] ) );
		$this->assertNull( $this->serviceRepo->find( (int) $created['id'] ) );
	}

	public function test_search_and_filter_paginate(): void {
		$cat = $this->categories->create( array( 'name' => 'Hair' ) );
		$this->services->create( array( 'name' => 'Haircut', 'category_id' => (int) $cat['id'], 'status' => 'active' ) );
		$this->services->create( array( 'name' => 'Beard Trim', 'status' => 'inactive' ) );
		$this->services->create( array( 'name' => 'Hair Colour', 'category_id' => (int) $cat['id'] ) );

		$search = $this->serviceRepo->paginate( array( 'search' => 'Hair' ) );
		$this->assertSame( 2, $search['total'] );

		$byCat = $this->serviceRepo->paginate( array( 'category_id' => (int) $cat['id'] ) );
		$this->assertSame( 2, $byCat['total'] );

		$active = $this->serviceRepo->paginate( array( 'status' => 'active' ) );
		$this->assertSame( 1, $active['total'] );

		$paged = $this->serviceRepo->paginate( array( 'per_page' => 2, 'page' => 1 ) );
		$this->assertCount( 2, $paged['items'] );
		$this->assertSame( 2, $paged['total_pages'] );
	}

	public function test_bulk_activate_and_delete(): void {
		$a = $this->services->create( array( 'name' => 'A', 'status' => 'inactive' ) );
		$b = $this->services->create( array( 'name' => 'B', 'status' => 'inactive' ) );

		$affected = $this->services->bulk( 'activate', array( (int) $a['id'], (int) $b['id'] ) );
		$this->assertSame( 2, $affected );
		$this->assertSame( 'active', $this->serviceRepo->find( (int) $a['id'] )['status'] );

		$this->services->bulk( 'delete', array( (int) $a['id'] ) );
		$this->assertNull( $this->serviceRepo->find( (int) $a['id'] ) );
	}

	public function test_bulk_rejects_unknown_action(): void {
		$a = $this->services->create( array( 'name' => 'A' ) );
		$this->assertInstanceOf( WP_Error::class, $this->services->bulk( 'explode', array( (int) $a['id'] ) ) );
	}

	public function test_category_delete_reports_affected_services(): void {
		$cat = $this->categories->create( array( 'name' => 'Spa' ) );
		$this->services->create( array( 'name' => 'Massage', 'category_id' => (int) $cat['id'] ) );

		$result = $this->categories->delete( (int) $cat['id'] );
		$this->assertTrue( $result['deleted'] );
		$this->assertSame( 1, $result['services_affected'] );
	}
}
