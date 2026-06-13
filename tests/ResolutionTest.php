<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Container\Container;
use Lukman\Container\Exception\ContainerException;
use Lukman\Container\Exception\NotFoundException;
use stdClass;

abstract class AbstractConcrete
{
}

interface LoggerInterface
{
}

class FileLogger implements LoggerInterface
{
}

class UserRepository
{
    public function __construct(public FileLogger $logger)
    {
    }
}

class UserService
{
    public function __construct(public UserRepository $repository)
    {
    }
}

class RequiredDependencyClass
{
    public function __construct(string $value)
    {
    }
}

class DefaultValueClass
{
    public function __construct(
        public string $name = 'default',
        public ?FileLogger $logger = null
    ) {
    }
}

class InterfaceDependencyClass
{
    public function __construct(public LoggerInterface $logger)
    {
    }
}

class UnionDependencyClass
{
    public function __construct(public FileLogger|stdClass $logger)
    {
    }
}

class UnionDefaultDependencyClass
{
    public function __construct(public FileLogger|stdClass|null $logger = null)
    {
    }
}

class ResolutionTest extends TestCase
{
    public function testResolveInstance(): void
    {
        $container = new Container();
        $instance = new stdClass();
        $container->instance('foo', $instance);

        $this->assertSame($instance, $container->get('foo'));
        $this->assertSame($instance, $container->make('foo'));
    }

    public function testResolveClosure(): void
    {
        $container = new Container();
        $container->bind('foo', function () {
            return 'resolved_from_closure';
        });

        $this->assertSame('resolved_from_closure', $container->get('foo'));
    }

    public function testClosureReceivesContainer(): void
    {
        $container = new Container();
        $container->bind('dependency', 'dep_value');
        $container->bind('target', function (Container $c) {
            return $c->get('dependency') . '_suffix';
        });

        $this->assertSame('dep_value_suffix', $container->get('target'));
    }

    public function testResolveConcreteString(): void
    {
        $container = new Container();
        $container->bind('my_class', stdClass::class);

        $this->assertInstanceOf(stdClass::class, $container->get('my_class'));
    }

    public function testBindClassStringReturnsNewObjectEachResolve(): void
    {
        $container = new Container();
        $container->bind('transient_class', stdClass::class);

        $first = $container->get('transient_class');
        $second = $container->get('transient_class');

        $this->assertInstanceOf(stdClass::class, $first);
        $this->assertInstanceOf(stdClass::class, $second);
        $this->assertNotSame($first, $second);
    }

    public function testSingletonClassStringReturnsSameObjectEachResolve(): void
    {
        $container = new Container();
        $container->singleton('singleton_class_string', stdClass::class);

        $first = $container->get('singleton_class_string');
        $second = $container->get('singleton_class_string');

        $this->assertInstanceOf(stdClass::class, $first);
        $this->assertSame($first, $second);
    }

    public function testSingletonReturnObjectYangSama(): void
    {
        $container = new Container();
        $container->singleton('singleton_class', function () {
            return new stdClass();
        });

        $first = $container->get('singleton_class');
        $second = $container->get('singleton_class');

        $this->assertSame($first, $second);
    }

    public function testBindNonSharedReturnObjectBerbeda(): void
    {
        $container = new Container();
        $container->bind('transient_class', function () {
            return new stdClass();
        });

        $first = $container->get('transient_class');
        $second = $container->get('transient_class');

        $this->assertNotSame($first, $second);
    }

    public function testUnknownIdThrowNotFoundException(): void
    {
        $container = new Container();

        $this->expectException(NotFoundException::class);
        $container->get('unknown_key');
    }

    public function testInvalidConcreteThrowsContainerException(): void
    {
        $container = new Container();
        $container->bind('invalid', AbstractConcrete::class);

        $this->expectException(ContainerException::class);
        $container->get('invalid');
    }

    public function testClassWithRequiredConstructorDependencyThrowsContainerException(): void
    {
        $container = new Container();
        $container->bind('invalid', RequiredDependencyClass::class);

        $this->expectException(ContainerException::class);
        $container->get('invalid');
    }

    public function testResolveConstructorDependency(): void
    {
        $container = new Container();
        $container->bind(UserRepository::class);

        $repository = $container->get(UserRepository::class);

        $this->assertInstanceOf(UserRepository::class, $repository);
        $this->assertInstanceOf(FileLogger::class, $repository->logger);
    }

    public function testResolveNestedDependency(): void
    {
        $container = new Container();
        $container->bind(UserService::class);

        $service = $container->get(UserService::class);

        $this->assertInstanceOf(UserService::class, $service);
        $this->assertInstanceOf(UserRepository::class, $service->repository);
        $this->assertInstanceOf(FileLogger::class, $service->repository->logger);
    }

    public function testDefaultValueDipakai(): void
    {
        $container = new Container();
        $container->bind(DefaultValueClass::class);

        $resolved = $container->get(DefaultValueClass::class);

        $this->assertSame('default', $resolved->name);
        $this->assertNull($resolved->logger);
    }

    public function testInterfaceWithoutBindingThrowsClearContainerException(): void
    {
        $container = new Container();
        $container->bind(InterfaceDependencyClass::class);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('interface');

        $container->get(InterfaceDependencyClass::class);
    }

    public function testUnionTypeWithoutDefaultThrowsContainerException(): void
    {
        $container = new Container();
        $container->bind(UnionDependencyClass::class);

        $this->expectException(ContainerException::class);
        $container->get(UnionDependencyClass::class);
    }

    public function testUnionTypeWithDefaultUsesDefaultValue(): void
    {
        $container = new Container();
        $container->bind(UnionDefaultDependencyClass::class);

        $resolved = $container->get(UnionDefaultDependencyClass::class);

        $this->assertNull($resolved->logger);
    }
}
