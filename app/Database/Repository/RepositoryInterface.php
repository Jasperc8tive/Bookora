<?php
/**
 * Repository contract.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Generic data-access contract for a single table.
 */
interface RepositoryInterface {

	/**
	 * Find a single row by primary key.
	 *
	 * @param int  $id              Primary key.
	 * @param bool $include_trashed Include soft-deleted rows.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id, bool $include_trashed = false ): ?array;

	/**
	 * Insert a row and return its new ID.
	 *
	 * @param array<string, mixed> $data Column => value.
	 * @return int
	 */
	public function create( array $data ): int;

	/**
	 * Update a row by primary key.
	 *
	 * @param int                  $id   Primary key.
	 * @param array<string, mixed> $data Column => value.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool;

	/**
	 * Soft-delete (or hard-delete when soft deletes are disabled) a row.
	 *
	 * @param int $id Primary key.
	 * @return bool
	 */
	public function delete( int $id ): bool;
}
