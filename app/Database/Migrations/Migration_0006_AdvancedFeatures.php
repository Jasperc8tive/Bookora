<?php
/**
 * Adds coupons, gift cards, memberships, and customer memberships.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Migrations;

use Bookora\Database\MigrationInterface;
use Bookora\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Stage 16 commerce tables: coupons, gift cards, membership plans, and the
 * customer↔membership join (which also backs subscriptions).
 */
final class Migration_0006_AdvancedFeatures implements MigrationInterface {

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
	 * Tables created by this migration, in drop order.
	 *
	 * @var array<int, string>
	 */
	private const TABLES = array( 'customer_memberships', 'memberships', 'gift_cards', 'coupons' );

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
		return '0006';
	}

	/**
	 * {@inheritDoc}
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $this->schema->charset_collate();

		$coupons = $this->schema->table( 'coupons' );
		dbDelta(
			"CREATE TABLE {$coupons} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				tenant_id bigint unsigned NOT NULL DEFAULT 0,
				code varchar(64) NOT NULL,
				type varchar(20) NOT NULL DEFAULT 'percent',
				value decimal(12,2) NOT NULL DEFAULT 0.00,
				min_amount decimal(12,2) NOT NULL DEFAULT 0.00,
				usage_limit int unsigned NOT NULL DEFAULT 0,
				used int unsigned NOT NULL DEFAULT 0,
				starts_at datetime DEFAULT NULL,
				expires_at datetime DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code (code),
				KEY status (status),
				KEY deleted (deleted_at)
			) {$collate};"
		);

		$gift_cards = $this->schema->table( 'gift_cards' );
		dbDelta(
			"CREATE TABLE {$gift_cards} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				tenant_id bigint unsigned NOT NULL DEFAULT 0,
				code varchar(64) NOT NULL,
				initial_amount decimal(12,2) NOT NULL DEFAULT 0.00,
				balance decimal(12,2) NOT NULL DEFAULT 0.00,
				currency char(3) NOT NULL DEFAULT 'NGN',
				customer_id bigint unsigned DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				expires_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code (code),
				KEY status (status),
				KEY deleted (deleted_at)
			) {$collate};"
		);

		$memberships = $this->schema->table( 'memberships' );
		dbDelta(
			"CREATE TABLE {$memberships} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				tenant_id bigint unsigned NOT NULL DEFAULT 0,
				name varchar(191) NOT NULL,
				price decimal(12,2) NOT NULL DEFAULT 0.00,
				billing_period varchar(20) NOT NULL DEFAULT 'month',
				benefit_type varchar(30) NOT NULL DEFAULT 'discount_percent',
				benefit_value decimal(12,2) NOT NULL DEFAULT 0.00,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY deleted (deleted_at)
			) {$collate};"
		);

		$customer_memberships = $this->schema->table( 'customer_memberships' );
		dbDelta(
			"CREATE TABLE {$customer_memberships} (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				tenant_id bigint unsigned NOT NULL DEFAULT 0,
				customer_id bigint unsigned NOT NULL,
				membership_id bigint unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				renews_at datetime DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY customer (customer_id),
				KEY membership (membership_id),
				KEY status (status),
				KEY deleted (deleted_at)
			) {$collate};"
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function down(): void {
		foreach ( self::TABLES as $table ) {
			$name = $this->schema->table( $table );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$this->wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		}
	}
}
