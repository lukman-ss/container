<?php

declare(strict_types=1);

namespace Lukman\Container;

final readonly class Binding
{
    public function __construct(
        private string $abstract,
        private mixed $concrete,
        private bool $shared = false
    ) {
    }

    public function abstract(): string
    {
        return $this->abstract;
    }

    public function concrete(): mixed
    {
        return $this->concrete;
    }

    public function shared(): bool
    {
        return $this->shared;
    }
}
