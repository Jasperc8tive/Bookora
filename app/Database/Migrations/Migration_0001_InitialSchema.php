<?php
/**
 * Initial schema migration — creates all core Bookora tables.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Migrations;

use Bookora\Database\MigrationInterface;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the 12 core tables (services, staff, availability, customers,
 * appointments, payments, notes, notifications, waitlist, resources,
 * integrations, activity_logs).
 *
 * Conventions: BIGINT UNSIGNED surrogate keys, UTC DATETIME columns,
 * `tenant_id` scoping, `created_at`/`updated_at`, and a nullable `deleted_at`
 * for soft deletes (except the append-only activity_logs).
 */
final class Migration_0001_InitialSchema implements MigrationInterface {

	/**
	 * Schema helper.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param Schema|null $schema Optional schema helper.
	 */
	public function __construct( ?Schema $schema = null ) {
		$this->wpdb   = $GLOBALS['wpdb'];
		$this->schema = $schema ?? new Schema( $this->wpdb );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return '0001';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $this->table_definitions() as $sql ) {
			dbDelta( $sql );
		}

		$this->add_foreign_keys();
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		$tables = array(
			'activity_logs',
			'integrations',
			'resources',
			'waitlist',
			'notifications',
			'notes',
			'payments',
			'appointments',
			'customers',
			'staff_availability',
			'staff',
			'services',
		);

		// Disable FK checks so drop order cannot fail on constraints.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		foreach ( $tables as $table ) {
			$name = $this->schema->table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$this->wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
	}

	/**
	 * All CREATE TABLE statements, keyed by unprefixed table name.
	 *
	 * @return array<string, string>
	 */
	private function table_definitions(): array {
		$collate = $this->schema->charset_collate();
		$p       = $this->schema->prefix();

		$tables = array();

		$tables['services'] = "CREATE TABLE {$p}services (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			category_id bigint unsigned DEFAULT NULL,
			name varchar(191) NOT NULL,
			slug varchar(191) DEFAULT NULL,
			description longtext DEFAULT NULL,
			duration_min int unsigned NOT NULL DEFAULT 30,
			buffer_before_min int unsigned NOT NULL DEFAULT 0,
			buffer_after_min int unsigned NOT NULL DEFAULT 0,
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT 'NGN',
			deposit_type varchar(20) NOT NULL DEFAULT 'none',
			deposit_value decimal(12,2) NOT NULL DEFAULT 0.00,
			capacity int unsigned NOT NULL DEFAULT 1,
			min_notice_min int unsigned NOT NULL DEFAULT 0,
			max_notice_min int unsigned DEFAULT NULL,
			lead_time_min int unsigned NOT NULL DEFAULT 0,
			image_url varchar(255) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY tenant (tenant_id),
			KEY category (category_id),
			KEY status (status),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['staff'] = "CREATE TABLE {$p}staff (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			wp_user_id bigint unsigned DEFAULT NULL,
			display_name varchar(191) NOT NULL,
			email varchar(191) DEFAULT NULL,
			phone varchar(32) DEFAULT NULL,
			bio longtext DEFAULT NULL,
			avatar_url varchar(255) DEFAULT NULL,
			timezone varchar(64) DEFAULT NULL,
			color char(7) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY tenant (tenant_id),
			KEY wp_user (wp_user_id),
			KEY status (status),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['staff_availability'] = "CREATE TABLE {$p}staff_availability (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			staff_id bigint unsigned NOT NULL,
			location_id bigint unsigned DEFAULT NULL,
			type varchar(20) NOT NULL DEFAULT 'working_hours',
			weekday tinyint DEFAULT NULL,
			start_time time DEFAULT NULL,
			end_time time DEFAULT NULL,
			start_date date DEFAULT NULL,
			end_date date DEFAULT NULL,
			valid_from date DEFAULT NULL,
			valid_to date DEFAULT NULL,
			note varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY staff (staff_id),
			KEY type (type),
			KEY weekday (weekday),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['customers'] = "CREATE TABLE {$p}customers (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			wp_user_id bigint unsigned DEFAULT NULL,
			first_name varchar(100) DEFAULT NULL,
			last_name varchar(100) DEFAULT NULL,
			name varchar(191) DEFAULT NULL,
			email varchar(191) DEFAULT NULL,
			phone varchar(32) DEFAULT NULL,
			timezone varchar(64) DEFAULT NULL,
			locale varchar(10) DEFAULT NULL,
			notes longtext DEFAULT NULL,
			consent_json longtext DEFAULT NULL,
			tags varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY tenant (tenant_id),
			KEY email (email),
			KEY phone (phone),
			KEY wp_user (wp_user_id),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['appointments'] = "CREATE TABLE {$p}appointments (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			customer_id bigint unsigned NOT NULL,
			service_id bigint unsigned NOT NULL,
			staff_id bigint unsigned DEFAULT NULL,
			location_id bigint unsigned DEFAULT NULL,
			resource_id bigint unsigned DEFAULT NULL,
			start_at datetime NOT NULL,
			end_at datetime NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			price decimal(12,2) NOT NULL DEFAULT 0.00,
			tax decimal(12,2) NOT NULL DEFAULT 0.00,
			total decimal(12,2) NOT NULL DEFAULT 0.00,
			amount_paid decimal(12,2) NOT NULL DEFAULT 0.00,
			balance_due decimal(12,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT 'NGN',
			source varchar(50) DEFAULT NULL,
			notes_internal longtext DEFAULT NULL,
			external_event_id varchar(191) DEFAULT NULL,
			parent_recurring_id bigint unsigned DEFAULT NULL,
			idempotency_key varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency (idempotency_key),
			KEY tenant_staff_start (tenant_id, staff_id, start_at),
			KEY customer (customer_id),
			KEY service (service_id),
			KEY status_start (status, start_at),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['payments'] = "CREATE TABLE {$p}payments (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			appointment_id bigint unsigned DEFAULT NULL,
			customer_id bigint unsigned DEFAULT NULL,
			gateway varchar(30) NOT NULL,
			gateway_ref varchar(191) DEFAULT NULL,
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT 'NGN',
			status varchar(20) NOT NULL DEFAULT 'pending',
			type varchar(20) NOT NULL DEFAULT 'full',
			webhook_verified tinyint(1) NOT NULL DEFAULT 0,
			idempotency_key varchar(64) DEFAULT NULL,
			meta longtext DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY appointment (appointment_id),
			KEY gateway_ref (gateway, gateway_ref),
			KEY status (status),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['notes'] = "CREATE TABLE {$p}notes (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			entity_type varchar(30) NOT NULL,
			entity_id bigint unsigned NOT NULL,
			author_id bigint unsigned DEFAULT NULL,
			body longtext NOT NULL,
			is_private tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['notifications'] = "CREATE TABLE {$p}notifications (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			appointment_id bigint unsigned DEFAULT NULL,
			customer_id bigint unsigned DEFAULT NULL,
			channel varchar(20) NOT NULL,
			event varchar(50) NOT NULL,
			template_key varchar(100) DEFAULT NULL,
			recipient varchar(191) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			provider_ref varchar(191) DEFAULT NULL,
			attempts int unsigned NOT NULL DEFAULT 0,
			error varchar(255) DEFAULT NULL,
			scheduled_at datetime DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status_scheduled (status, scheduled_at),
			KEY appointment (appointment_id),
			KEY channel (channel),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['waitlist'] = "CREATE TABLE {$p}waitlist (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			service_id bigint unsigned NOT NULL,
			staff_id bigint unsigned DEFAULT NULL,
			customer_id bigint unsigned NOT NULL,
			desired_from datetime DEFAULT NULL,
			desired_to datetime DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY service (service_id),
			KEY customer (customer_id),
			KEY status (status),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['resources'] = "CREATE TABLE {$p}resources (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			name varchar(191) NOT NULL,
			type varchar(30) DEFAULT NULL,
			capacity int unsigned NOT NULL DEFAULT 1,
			location_id bigint unsigned DEFAULT NULL,
			description longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY tenant (tenant_id),
			KEY type (type),
			KEY status (status),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['integrations'] = "CREATE TABLE {$p}integrations (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			provider varchar(50) NOT NULL,
			name varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'disconnected',
			config longtext DEFAULT NULL,
			credentials longtext DEFAULT NULL,
			scope varchar(100) DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tenant_provider (tenant_id, provider),
			KEY provider (provider),
			KEY status (status),
			KEY deleted (deleted_at)
		) {$collate};";

		$tables['activity_logs'] = "CREATE TABLE {$p}activity_logs (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			tenant_id bigint unsigned NOT NULL DEFAULT 0,
			actor_type varchar(20) DEFAULT NULL,
			actor_id bigint unsigned DEFAULT NULL,
			action varchar(100) NOT NULL,
			entity_type varchar(50) DEFAULT NULL,
			entity_id bigint unsigned DEFAULT NULL,
			before_hash char(64) DEFAULT NULL,
			after_hash char(64) DEFAULT NULL,
			prev_hash char(64) DEFAULT NULL,
			ip_hash char(64) DEFAULT NULL,
			ua_hash char(64) DEFAULT NULL,
			context longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY tenant (tenant_id),
			KEY entity (entity_type, entity_id),
			KEY action (action),
			KEY actor (actor_type, actor_id),
			KEY created (created_at)
		) {$collate};";

		return $tables;
	}

	/**
	 * Add foreign-key constraints where appropriate.
	 *
	 * Best-effort: indexes already enforce lookup performance and the app layer
	 * enforces integrity, so a host that rejects FKs never blocks activation.
	 *
	 * @return void
	 */
	private function add_foreign_keys(): void {
		$t = fn ( string $name ): string => $this->schema->table( $name );

		$constraints = array(
			array( 'appointments', 'fk_appt_customer', "FOREIGN KEY (customer_id) REFERENCES {$t( 'customers' )} (id) ON DELETE CASCADE ON UPDATE CASCADE" ),
			array( 'appointments', 'fk_appt_service', "FOREIGN KEY (service_id) REFERENCES {$t( 'services' )} (id) ON DELETE RESTRICT ON UPDATE CASCADE" ),
			array( 'appointments', 'fk_appt_staff', "FOREIGN KEY (staff_id) REFERENCES {$t( 'staff' )} (id) ON DELETE SET NULL ON UPDATE CASCADE" ),
			array( 'staff_availability', 'fk_avail_staff', "FOREIGN KEY (staff_id) REFERENCES {$t( 'staff' )} (id) ON DELETE CASCADE ON UPDATE CASCADE" ),
			array( 'payments', 'fk_pay_appt', "FOREIGN KEY (appointment_id) REFERENCES {$t( 'appointments' )} (id) ON DELETE SET NULL ON UPDATE CASCADE" ),
			array( 'waitlist', 'fk_wait_service', "FOREIGN KEY (service_id) REFERENCES {$t( 'services' )} (id) ON DELETE CASCADE ON UPDATE CASCADE" ),
			array( 'waitlist', 'fk_wait_customer', "FOREIGN KEY (customer_id) REFERENCES {$t( 'customers' )} (id) ON DELETE CASCADE ON UPDATE CASCADE" ),
		);

		$suppress = $this->wpdb->suppress_errors( true );
		foreach ( $constraints as list( $table, $name, $definition ) ) {
			$full = $t( $table );
			if ( $this->constraint_exists( $table, $name ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query( "ALTER TABLE `{$full}` ADD CONSTRAINT `{$name}` {$definition}" );
		}
		$this->wpdb->suppress_errors( $suppress );
	}

	/**
	 * Whether a named constraint already exists on a table.
	 *
	 * @param string $table Unprefixed table name.
	 * @param string $name  Constraint name.
	 * @return bool
	 */
	private function constraint_exists( string $table, string $name ): bool {
		$full = $this->schema->table( $table );
		$sql  = $this->wpdb->prepare(
			'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
			 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s',
			$full,
			$name
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$found = $this->wpdb->get_var( $sql );

		return null !== $found;
	}
}
