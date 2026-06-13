<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use Lukman\Container\Container;
use Lukman\Container\Exception\ContainerException;
use Lukman\Container\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

class AliasTest extends TestCase
{
    public function testAliasDipakaiOlehGet(): void
    {
        $container = new Container();
        $container->bind('foo', 'bar');
        $container->alias('foo', 'foo_alias');

        $this->assertSame('bar', $container->get('foo_alias'));
    }

    public function testAliasDipakaiOlehHas(): void
    {
        $container = new Container();
        $container->bind('foo', 'bar');
        $container->alias('foo', 'foo_alias');

        $this->assertTrue($container->has('foo_alias'));
    }

    public function testNestedAlias(): void
    {
        $container = new Container();
        $container->instance('foo', new stdClass());
        $container->alias('foo', 'foo_alias');
        $container->alias('foo_alias', 'foo_nested_alias');

        $this->assertSame($container->get('foo'), $container->get('foo_nested_alias'));
    }

    public function testCircularAliasThrowContainerException(): void
    {
        $container = new Container();
        $container->alias('foo', 'bar');

        $this->expectException(ContainerException::class);
        $container->alias('bar', 'foo');
    }

    public function testRemoveMenghapusBindingInstanceDanAliasTerkait(): void
    {
        $container = new Container();
        $container->bind('foo', 'bar');
        $container->instance('baz', new stdClass());
        $container->alias('foo', 'foo_alias');
        $container->alias('baz', 'baz_alias');

        $container->remove('foo_alias');
        $container->remove('baz');

        $this->assertFalse($container->has('foo'));
        $this->assertFalse($container->has('baz'));
        $this->assertSame([], $container->getAliases());

        $this->expectException(NotFoundException::class);
        $container->get('foo_alias');
    }

    public function testFlushMembersihkanSemuaState(): void
    {
        $container = new Container();
        $container->bind('foo', 'bar');
        $container->instance('baz', new stdClass());
        $container->alias('foo', 'foo_alias');

        $container->flush();

        $this->assertSame([], $container->getBindings());
        $this->assertSame([], $container->getInstances());
        $this->assertSame([], $container->getAliases());
    }
}
