<?php
/**
 * Container tests.
 *
 * @package Bookora
 */

declare(strict_types=1);

namespace Bookora\Tests;

use Bookora\Core\Container;
use Bookora\Core\NotFoundException;
use WP_UnitTestCase;

/**
 * @covers \Bookora\Core\Container
 */
class ContainerTest extends WP_UnitTestCase {

	public function test_bind_and_make_returns_new_instances(): void {
		$container = new Container();
		$container->bind( Fixtures\Alpha::class );

		$a = $container->make( Fixtures\Alpha::class );
		$b = $container->make( Fixtures\Alpha::class );

		$this->assertInstanceOf( Fixtures\Alpha::class, $a );
		$this->assertNotSame( $a, $b, 'Non-shared bindings should produce distinct instances.' );
	}

	public function test_singleton_returns_same_instance(): void {
		$container = new Container();
		$container->singleton( Fixtures\Alpha::class );

		$this->assertSame(
			$container->get( Fixtures\Alpha::class ),
			$container->get( Fixtures\Alpha::class )
		);
	}

	public function test_autowires_constructor_dependencies(): void {
		$container = new Container();

		$beta = $container->make( Fixtures\Beta::class );

		$this->assertInstanceOf( Fixtures\Beta::class, $beta );
		$this->assertInstanceOf( Fixtures\Alpha::class, $beta->alpha );
	}

	public function test_closure_binding_receives_container(): void {
		$container = new Container();
		$container->bind( 'greeting', static fn (): string => 'hello' );

		$this->assertSame( 'hello', $container->get( 'greeting' ) );
	}

	public function test_instance_binding_is_returned_as_is(): void {
		$container = new Container();
		$object    = new Fixtures\Alpha();
		$container->instance( 'alpha', $object );

		$this->assertSame( $object, $container->get( 'alpha' ) );
	}

	public function test_unknown_identifier_throws_not_found(): void {
		$container = new Container();

		$this->expectException( NotFoundException::class );
		$container->get( 'does_not_exist' );
	}
}
