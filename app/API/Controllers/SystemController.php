<?php
/**
 * System/health REST controller.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\API\Controllers;

use Bookora\API\AbstractController;
use Bookora\Database\MigrationRunner;
use Bookora\Database\Schema;
use Bookora\Security\Capabilities;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes GET /bookora/v1/system/health — proves the REST stack end to end and
 * powers the admin dashboard's System Status panel.
 */
final class SystemController extends AbstractController {

	/**
	 * Schema helper.
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Migration runner.
	 *
	 * @var MigrationRunner
	 */
	private MigrationRunner $migrations;

	/**
	 * Core tables expected to exist once migrations have run.
	 *
	 * @var array<int, string>
	 */
	private const CORE_TABLES = array(
		'services',
		'staff',
		'staff_availability',
		'customers',
		'appointments',
		'payments',
		'notes',
		'notifications',
		'waitlist',
		'resources',
		'integrations',
		'activity_logs',
	);

	/**
	 * Constructor.
	 *
	 * @param Schema          $schema     Schema helper.
	 * @param MigrationRunner $migrations Migration runner.
	 */
	public function __construct( Schema $schema, MigrationRunner $migrations ) {
		$this->schema     = $schema;
		$this->migrations = $migrations;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/system/health',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'health' ),
					'permission_callback' => $this->require_capability( Capabilities::MANAGE_SETTINGS ),
				),
			)
		);
	}

	/**
	 * Return system health.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function health( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );

		$tables = array();
		$all_ok = true;
		foreach ( self::CORE_TABLES as $table ) {
			$exists           = $this->schema->table_exists( $table );
			$tables[ $table ] = $exists;
			$all_ok           = $all_ok && $exists;
		}

		return $this->success(
			array(
				'plugin'    => array(
					'name'       => 'Bookora',
					'version'    => BOOKORA_VERSION,
					'db_version' => BOOKORA_DB_VERSION,
				),
				'env'       => array(
					'php'    => PHP_VERSION,
					'wp'     => get_bloginfo( 'version' ),
					'prefix' => $this->schema->prefix(),
				),
				'database'  => array(
					'migrated' => $all_ok,
					'applied'  => $this->migrations->applied_versions(),
					'tables'   => $tables,
				),
				'healthy'   => $all_ok,
				'timestamp' => gmdate( 'c' ),
			)
		);
	}
}
