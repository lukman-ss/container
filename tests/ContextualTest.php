<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use Lukman\Container\Container;
use Lukman\Container\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

interface ContextLoggerInterface
{
}

class FileContextLogger implements ContextLoggerInterface
{
}

class DatabaseContextLogger implements ContextLoggerInterface
{
}

class WorkerA
{
    public function __construct(public ContextLoggerInterface $logger)
    {
    }
}

class WorkerB
{
    public function __construct(public ContextLoggerInterface $logger)
    {
    }
}

class NestedWorker
{
    public function __construct(public WorkerA $worker)
    {
    }
}

class BrokenContextDependency
{
    public function __construct(public string $value)
    {
    }
}

class BrokenContextWorker
{
    public function __construct(public BrokenContextDependency $dependency)
    {
    }
}

class ContextualTest extends TestCase
{
    public function testWhenNeedsGiveMenyimpanMappingBenar(): void
    {
        $container = new Container();

        $container->when(WorkerA::class)
            ->needs(ContextLoggerInterface::class)
            ->give(FileContextLogger::class);

        $bindings = $container->getContextualBindings();

        $this->assertSame(FileContextLogger::class, $bindings[WorkerA::class][ContextLoggerInterface::class]);
    }

    public function testContextualClassStringTidakBocorKeClassLain(): void
    {
        $container = new Container();
        $container->when(WorkerA::class)
            ->needs(ContextLoggerInterface::class)
            ->give(FileContextLogger::class);
        $container->bind(ContextLoggerInterface::class, DatabaseContextLogger::class);

        $workerA = $container->get(WorkerA::class);
        $workerB = $container->get(WorkerB::class);

        $this->assertInstanceOf(FileContextLogger::class, $workerA->logger);
        $this->assertInstanceOf(DatabaseContextLogger::class, $workerB->logger);
    }

    public function testContextualClosureMenerimaContainer(): void
    {
        $container = new Container();
        $container->when(WorkerA::class)
            ->needs(ContextLoggerInterface::class)
            ->give(function (Container $container): ContextLoggerInterface {
                $this->assertInstanceOf(Container::class, $container);

                return new FileContextLogger();
            });

        $worker = $container->get(WorkerA::class);

        $this->assertInstanceOf(FileContextLogger::class, $worker->logger);
    }

    public function testContextualObjectDipakaiLangsung(): void
    {
        $container = new Container();
        $logger = new FileContextLogger();

        $container->when(WorkerA::class)
            ->needs(ContextLoggerInterface::class)
            ->give($logger);

        $worker = $container->get(WorkerA::class);

        $this->assertSame($logger, $worker->logger);
    }

    public function testGlobalBindingMenjadiFallback(): void
    {
        $container = new Container();
        $container->bind(ContextLoggerInterface::class, DatabaseContextLogger::class);

        $worker = $container->get(WorkerA::class);

        $this->assertInstanceOf(DatabaseContextLogger::class, $worker->logger);
    }

    public function testNestedBuildContextBenar(): void
    {
        $container = new Container();
        $container->when(WorkerA::class)
            ->needs(ContextLoggerInterface::class)
            ->give(FileContextLogger::class);
        $container->bind(ContextLoggerInterface::class, DatabaseContextLogger::class);

        $nested = $container->get(NestedWorker::class);

        $this->assertInstanceOf(FileContextLogger::class, $nested->worker->logger);
    }

    public function testContextStackDibersihkanSetelahBuildGagal(): void
    {
        $container = new Container();

        try {
            $container->get(BrokenContextWorker::class);
            $this->fail('Expected ContainerException.');
        } catch (ContainerException) {
        }

        $container->bind(ContextLoggerInterface::class, DatabaseContextLogger::class);

        $worker = $container->get(WorkerA::class);

        $this->assertInstanceOf(DatabaseContextLogger::class, $worker->logger);
    }
}
