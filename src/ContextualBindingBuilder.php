<?php

declare(strict_types=1);

namespace Lukman\Container;

final class ContextualBindingBuilder
{
    private string $needs;

    public function __construct(
        private readonly Container $container,
        private readonly string $concrete
    ) {
    }

    public function needs(string $abstract): self
    {
        $this->needs = $abstract;

        return $this;
    }

    public function give(mixed $implementation): void
    {
        $this->container->addContextualBinding($this->concrete, $this->needs, $implementation);
    }
}
