<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use Lukman\Container\Container;
use Lukman\Container\Exception\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;

class CallableDependency
{
}

class CallableTarget
{
    public function handle(CallableDependency $dependency, string $name, string $greeting = 'Hello'): string
    {
        return sprintf('%s %s', $greeting, $name);
    }
}

class HelperTest extends TestCase
{
    public function testBoundHanyaCekBindings(): void
    {
        $container = new Container();
        $container->instance('foo', 'bar');

        $this->assertFalse($container->bound('foo'));

        $container->bind('foo', 'bar');

        $this->assertTrue($container->bound('foo'));
    }

    public function testResolvedCekInstances(): void
    {
        $container = new Container();
        $container->singleton('foo', fn() => new stdClass());

        $this->assertFalse($container->resolved('foo'));

        $container->get('foo');

        $this->assertTrue($container->resolved('foo'));
    }

    public function testFactoryResolveSetiapDipanggil(): void
    {
        $container = new Container();
        $factory = $container->factory('foo', fn() => new stdClass());

        $this->assertNotSame($factory(), $factory());
    }

    public function testCallInjectDependency(): void
    {
        $container = new Container();

        $result = $container->call(function (CallableDependency $dependency): CallableDependency {
            return $dependency;
        });

        $this->assertInstanceOf(CallableDependency::class, $result);
    }

    public function testExplicitParameterMenangAtasAutoResolve(): void
    {
        $container = new Container();
        $custom = new CallableDependency();

        $result = $container->call(function (CallableDependency $dependency): CallableDependency {
            return $dependency;
        }, [
            'dependency' => $custom,
        ]);

        $this->assertSame($custom, $result);
    }

    public function testExplicitParameterByTypeMenangAtasAutoResolve(): void
    {
        $container = new Container();
        $custom = new CallableDependency();

        $result = $container->call(function (CallableDependency $dependency): CallableDependency {
            return $dependency;
        }, [
            CallableDependency::class => $custom,
        ]);

        $this->assertSame($custom, $result);
    }

    public function testArrayCallableClassStringDiInstantiate(): void
    {
        $container = new Container();

        $result = $container->call([CallableTarget::class, 'handle'], [
            'name' => 'Lukman',
        ]);

        $this->assertSame('Hello Lukman', $result);
    }

    public function testStringClassAtMethodDidukung(): void
    {
        $container = new Container();

        $result = $container->call(CallableTarget::class . '@handle', [
            'name' => 'Lukman',
            'greeting' => 'Hi',
        ]);

        $this->assertSame('Hi Lukman', $result);
    }

    public function testInvalidCallableThrowContainerException(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);

        $container->call('missing_function');
    }
}
