<?php
/**
 * Container "not found" exception.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Psr\Container\NotFoundExceptionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a requested identifier is not bound in the container.
 */
class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {
}
