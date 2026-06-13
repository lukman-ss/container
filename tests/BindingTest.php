<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Container\Container;
use Lukman\Container\Binding;
use stdClass;

class BindingTest extends TestCase
{
    public function testBindConcreteClass(): void
    {
        $container = new Container();
        $container->bind('some_id', stdClass::class);

        $bindings = $container->getBindings();
        $this->assertArrayHasKey('some_id', $bindings);
        
        $binding = $bindings['some_id'];
        $this->assertInstanceOf(Binding::class, $binding);
        $this->assertSame('some_id', $binding->abstract());
        $this->assertSame(stdClass::class, $binding->concrete());
        $this->assertFalse($binding->shared());
    }

    public function testBindConcreteNull(): void
    {
        $container = new Container();
        $container->bind('some_id'); // concrete is null

        $bindings = $container->getBindings();
        $this->assertArrayHasKey('some_id', $bindings);

        $binding = $bindings['some_id'];
        $this->assertSame('some_id', $binding->abstract());
        $this->assertSame('some_id', $binding->concrete()); // defaults to abstract
        $this->assertFalse($binding->shared());
    }

    public function testSingletonSharedTrue(): void
    {
        $container = new Container();
        $container->singleton('some_singleton', stdClass::class);

        $bindings = $container->getBindings();
        $this->assertArrayHasKey('some_singleton', $bindings);

        $binding = $bindings['some_singleton'];
        $this->assertTrue($binding->shared());
    }

    public function testSingletonConcreteNullDefaultKeAbstractDanShared(): void
    {
        $container = new Container();
        $container->singleton('some_singleton');

        $binding = $container->getBindings()['some_singleton'];

        $this->assertSame('some_singleton', $binding->abstract());
        $this->assertSame('some_singleton', $binding->concrete());
        $this->assertTrue($binding->shared());
    }

    public function testBindingImmutableSecaraPraktis(): void
    {
        $binding = new Binding('foo', 'bar', true);

        $this->assertSame('foo', $binding->abstract());
        $this->assertSame('bar', $binding->concrete());
        $this->assertTrue($binding->shared());
    }

    public function testInstanceTersimpan(): void
    {
        $container = new Container();
        $instance = new stdClass();
        $container->instance('my_instance', $instance);

        $instances = $container->getInstances();
        $this->assertArrayHasKey('my_instance', $instances);
        $this->assertSame($instance, $instances['my_instance']);
        $this->assertSame($instance, $container->get('my_instance'));
    }

    public function testHasBinding(): void
    {
        $container = new Container();
        $container->bind('some_binding', 'value');

        $this->assertTrue($container->has('some_binding'));
    }

    public function testHasInstance(): void
    {
        $container = new Container();
        $container->instance('some_instance', 'value');

        $this->assertTrue($container->has('some_instance'));
    }

    public function testHasFalseUntukIdTidakAda(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('non_existent'));
    }
}
