<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use PHPUnit\Framework\TestCase;
use Lukman\Container\ContextualBindingBuilder;
use Lukman\Container\Container;
use Lukman\Container\ContainerInterface;
use Lukman\Container\Binding;
use Lukman\Container\Exception\ContainerException;
use Lukman\Container\Exception\NotFoundException;
use Lukman\Container\ServiceProviderInterface;

class AutoloadTest extends TestCase
{
    public function testClassesAndInterfacesAreAutoloaded(): void
    {
        $this->assertTrue(class_exists(Container::class));
        $this->assertTrue(interface_exists(ContainerInterface::class));
        $this->assertTrue(class_exists(Binding::class));
        $this->assertTrue(class_exists(ContextualBindingBuilder::class));
        $this->assertTrue(interface_exists(ServiceProviderInterface::class));
        $this->assertTrue(class_exists(ContainerException::class));
        $this->assertTrue(class_exists(NotFoundException::class));
    }

    public function testContainerBasicBindingAndResolution(): void
    {
        $container = new Container();
        $container->bind('foo', 'bar');

        $this->assertTrue($container->has('foo'));
        $this->assertSame('bar', $container->get('foo'));
    }

    public function testContainerNotFoundException(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('non-existent'));

        $this->expectException(NotFoundException::class);
        $container->get('non-existent');
    }
}
