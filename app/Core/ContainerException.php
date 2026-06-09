<?php
/**
 * Generic container resolution exception.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Psr\Container\ContainerExceptionInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a binding cannot be resolved (e.g. unresolvable constructor dependency).
 */
class ContainerException extends \RuntimeException implements ContainerExceptionInterface {
}
