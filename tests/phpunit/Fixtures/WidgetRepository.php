<?php
/**
 * Test fixture repository.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests\Fixtures;

use Bookora\Database\Repository\AbstractRepository;

/**
 * Concrete repository over a throwaway "test_widgets" table.
 */
class WidgetRepository extends AbstractRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function table_name(): string {
		return 'test_widgets';
	}
}
