<?php
/**
 * Lightweight PSR-11 dependency-injection container with constructor autowiring.
 *
 * Intentionally dependency-free (beyond psr/container) to keep the commercial
 * plugin footprint small and predictable.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Core;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

defined( 'ABSPATH' ) || exit;

/**
 * Service container.
 */
class Container implements ContainerInterface {

	/**
	 * Registered bindings, keyed by identifier.
	 *
	 * @var array<string, array{concrete: Closure|string, shared: bool}>
	 */
	private array $bindings = array();

	/**
	 * Resolved shared instances, keyed by identifier.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a binding.
	 *
	 * @param string               $id       Identifier (usually a class/interface name).
	 * @param Closure|string|null  $concrete Concrete factory or class name. Defaults to $id.
	 * @param bool                 $shared   Whether to resolve once and cache.
	 * @return void
	 */
	public function bind( string $id, Closure|string|null $concrete = null, bool $shared = false ): void {
		$this->bindings[ $id ] = array(
			'concrete' => $concrete ?? $id,
			'shared'   => $shared,
		);
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register a shared (singleton) binding.
	 *
	 * @param string              $id       Identifier.
	 * @param Closure|string|null $concrete Concrete factory or class name.
	 * @return void
	 */
	public function singleton( string $id, Closure|string|null $concrete = null ): void {
		$this->bind( $id, $concrete, true );
	}

	/**
	 * Store an already-built instance as a shared binding.
	 *
	 * @param string $id       Identifier.
	 * @param mixed  $instance Concrete instance.
	 * @return void
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->bindings[ $id ]  = array(
			'concrete' => static fn () => $instance,
			'shared'   => true,
		);
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether the container can resolve the given identifier.
	 *
	 * @param string $id Identifier.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) || class_exists( $id );
	}

	/**
	 * Resolve an identifier from the container.
	 *
	 * @param string $id Identifier.
	 * @return mixed
	 *
	 * @throws NotFoundException When the identifier cannot be found.
	 * @throws ContainerException When the identifier cannot be built.
	 */
	public function get( string $id ): mixed {
		return $this->make( $id );
	}

	/**
	 * Resolve an identifier, optionally overriding constructor parameters by name.
	 *
	 * @param string              $id         Identifier.
	 * @param array<string,mixed> $parameters Named constructor overrides.
	 * @return mixed
	 *
	 * @throws NotFoundException When the identifier cannot be found.
	 * @throws ContainerException When the identifier cannot be built.
	 */
	public function make( string $id, array $parameters = array() ): mixed {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		$binding  = $this->bindings[ $id ] ?? null;
		$concrete = $binding['concrete'] ?? $id;

		if ( $concrete instanceof Closure ) {
			$object = $concrete( $this, $parameters );
		} else {
			$object = $this->build( $concrete, $parameters );
		}

		if ( $binding && $binding['shared'] ) {
			$this->instances[ $id ] = $object;
		}

		return $object;
	}

	/**
	 * Instantiate a concrete class, autowiring its constructor dependencies.
	 *
	 * @param string              $concrete   Class name.
	 * @param array<string,mixed> $parameters Named constructor overrides.
	 * @return object
	 *
	 * @throws NotFoundException When the class does not exist.
	 * @throws ContainerException When a dependency cannot be resolved.
	 */
	private function build( string $concrete, array $parameters = array() ): object {
		if ( ! class_exists( $concrete ) ) {
			throw new NotFoundException( esc_html( sprintf( 'Container: "%s" is not bound and is not an existing class.', $concrete ) ) );
		}

		$reflector = new ReflectionClass( $concrete );

		if ( ! $reflector->isInstantiable() ) {
			throw new ContainerException( esc_html( sprintf( 'Container: "%s" is not instantiable.', $concrete ) ) );
		}

		$constructor = $reflector->getConstructor();
		if ( null === $constructor ) {
			return new $concrete();
		}

		$arguments = array();
		foreach ( $constructor->getParameters() as $parameter ) {
			$arguments[] = $this->resolveParameter( $parameter, $parameters, $concrete );
		}

		return $reflector->newInstanceArgs( $arguments );
	}

	/**
	 * Resolve a single constructor parameter.
	 *
	 * @param ReflectionParameter $parameter  The parameter.
	 * @param array<string,mixed> $overrides  Named overrides.
	 * @param string              $concrete   Owning class (for error messages).
	 * @return mixed
	 *
	 * @throws ContainerException When the parameter cannot be resolved.
	 */
	private function resolveParameter( ReflectionParameter $parameter, array $overrides, string $concrete ): mixed {
		$name = $parameter->getName();

		if ( array_key_exists( $name, $overrides ) ) {
			return $overrides[ $name ];
		}

		$type = $parameter->getType();
		if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
			return $this->make( $type->getName() );
		}

		if ( $parameter->isDefaultValueAvailable() ) {
			return $parameter->getDefaultValue();
		}

		if ( $parameter->allowsNull() ) {
			return null;
		}

		throw new ContainerException(
			esc_html( sprintf( 'Container: unable to resolve parameter "$%s" of "%s".', $name, $concrete ) )
		);
	}
}
