<?php

declare(strict_types=1);

namespace Lukman\Container;

interface ServiceProviderInterface
{
    public function register(Container $container): void;

    public function boot(Container $container): void;
}
