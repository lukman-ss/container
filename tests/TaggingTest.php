<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use Lukman\Container\Container;
use Lukman\Container\Exception\NotFoundException;
use PHPUnit\Framework\TestCase;
use stdClass;

class TaggingTest extends TestCase
{
    public function testTagTidakLangsungResolveService(): void
    {
        $container = new Container();
        $resolved = false;

        $container->bind('lazy', function () use (&$resolved): stdClass {
            $resolved = true;

            return new stdClass();
        });

        $container->tag('lazy', 'services');

        $this->assertFalse($resolved);
    }

    public function testTaggedResolveService(): void
    {
        $container = new Container();
        $container->bind('service', fn() => new stdClass());
        $container->tag('service', 'services');

        $resolved = $container->tagged('services');

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(stdClass::class, $resolved[0]);
    }

    public function testUrutanServiceTetapStabil(): void
    {
        $container = new Container();
        $container->bind('first', 'a');
        $container->bind('second', 'b');
        $container->bind('third', 'c');

        $container->tag(['first', 'second', 'third'], 'ordered');

        $this->assertSame(['a', 'b', 'c'], $container->tagged('ordered'));
    }

    public function testMultipleAbstractTersimpan(): void
    {
        $container = new Container();

        $container->tag(['first', 'second'], 'services');

        $this->assertSame(['first', 'second'], $container->getTags()['services']);
    }

    public function testTagKosongReturnEmptyArray(): void
    {
        $container = new Container();

        $this->assertSame([], $container->tagged('missing'));
    }

    public function testDuplicateServiceDedupePerTag(): void
    {
        $container = new Container();
        $container->bind('service', 'value');

        $container->tag('service', 'services');
        $container->tag('service', 'services');

        $this->assertSame(['service'], $container->getTags()['services']);
        $this->assertSame(['value'], $container->tagged('services'));
    }

    public function testTaggedTidakMenelanExceptionResolve(): void
    {
        $container = new Container();
        $container->tag('missing_service', 'services');

        $this->expectException(NotFoundException::class);

        $container->tagged('services');
    }
}
