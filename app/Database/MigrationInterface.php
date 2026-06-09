<?php
/**
 * Migration contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A single, ordered, reversible schema change.
 */
interface MigrationInterface {

	/**
	 * Monotonic version string (e.g. "0001"). Determines apply order.
	 *
	 * @return string
	 */
	public function version(): string;

	/**
	 * Apply the migration.
	 *
	 * @return void
	 */
	public function up(): void;

	/**
	 * Reverse the migration.
	 *
	 * @return void
	 */
	public function down(): void;
}
