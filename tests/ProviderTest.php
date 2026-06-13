<?php

declare(strict_types=1);

namespace Lukman\Container\Tests;

use Lukman\Container\Container;
use Lukman\Container\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;

class TestServiceProvider implements ServiceProviderInterface
{
    public int $registerCalls = 0;
    public int $bootCalls = 0;

    public function register(Container $container): void
    {
        $this->registerCalls++;
        $container->bind('provider_service', 'provider_value');
    }

    public function boot(Container $container): void
    {
        $this->bootCalls++;
    }
}

class StringServiceProvider implements ServiceProviderInterface
{
    public static int $registerCalls = 0;
    public static int $bootCalls = 0;

    public function register(Container $container): void
    {
        self::$registerCalls++;
        $container->bind('string_provider_service', 'string_provider_value');
    }

    public function boot(Container $container): void
    {
        self::$bootCalls++;
    }
}

class ProviderTest extends TestCase
{
    protected function setUp(): void
    {
        StringServiceProvider::$registerCalls = 0;
        StringServiceProvider::$bootCalls = 0;
    }

    public function testRegisterProviderObjectMenjalankanRegisterMethod(): void
    {
        $container = new Container();
        $provider = new TestServiceProvider();

        $container->register($provider);

        $this->assertSame(1, $provider->registerCalls);
        $this->assertSame('provider_value', $container->get('provider_service'));
    }

    public function testRegisterProviderClassStringDiInstantiate(): void
    {
        $container = new Container();

        $container->register(StringServiceProvider::class);

        $providers = $container->getServiceProviders();

        $this->assertCount(1, $providers);
        $this->assertInstanceOf(StringServiceProvider::class, $providers[0]);
        $this->assertSame(1, StringServiceProvider::$registerCalls);
        $this->assertSame('string_provider_value', $container->get('string_provider_service'));
    }

    public function testDuplicateProviderTidakRegisterUlang(): void
    {
        $container = new Container();
        $providerA = new TestServiceProvider();
        $providerB = new TestServiceProvider();

        $container->register($providerA);
        $container->register($providerB);

        $providers = $container->getServiceProviders();

        $this->assertCount(1, $providers);
        $this->assertSame($providerA, $providers[0]);
        $this->assertSame(1, $providerA->registerCalls);
        $this->assertSame(0, $providerB->registerCalls);
    }

    public function testBootMenjalankanProviderBootMethodSekali(): void
    {
        $container = new Container();
        $provider = new TestServiceProvider();

        $container->register($provider);
        $container->boot();
        $container->boot();

        $this->assertSame(1, $provider->bootCalls);
    }

    public function testRegisterSetelahBootLangsungBootProviderBaru(): void
    {
        $container = new Container();
        $provider = new TestServiceProvider();

        $container->boot();
        $container->register($provider);

        $this->assertSame(1, $provider->registerCalls);
        $this->assertSame(1, $provider->bootCalls);
    }
}
