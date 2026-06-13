<?php

declare(strict_types=1);

namespace Lukman\Container;

use Closure;
use Lukman\Container\Exception\ContainerException;
use Lukman\Container\Exception\NotFoundException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

class Container implements ContainerInterface
{
    /**
     * @var array<string, Binding>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $contextual = [];

    /**
     * @var array<int, string>
     */
    private array $buildStack = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $tags = [];

    /**
     * @var array<string, ServiceProviderInterface>
     */
    private array $serviceProviders = [];

    private bool $booted = false;

    /**
     * Register a binding with the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function bind(string $abstract, mixed $concrete = null): void
    {
        $abstract = $this->getAbstract($abstract);
        $this->bindings[$abstract] = new Binding($abstract, $concrete ?? $abstract, false);
        unset($this->instances[$abstract]);
    }

    /**
     * Register a shared binding (singleton) with the container.
     *
     * @param string $abstract
     * @param mixed $concrete
     * @return void
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $abstract = $this->getAbstract($abstract);
        $this->bindings[$abstract] = new Binding($abstract, $concrete ?? $abstract, true);
        unset($this->instances[$abstract]);
    }

    /**
     * Register an existing instance in the container.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $abstract = $this->getAbstract($abstract);
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias for an abstract id.
     *
     * @throws ContainerException
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($abstract === $alias) {
            throw new ContainerException(sprintf('Cannot alias [%s] to itself.', $abstract));
        }

        $this->aliases[$alias] = $abstract;
        $this->getAbstract($alias);
    }

    /**
     * Resolve an alias chain to its root abstract id.
     *
     * @throws ContainerException
     */
    public function getAbstract(string $abstract): string
    {
        $visited = [];
        $current = $abstract;

        while (isset($this->aliases[$current])) {
            if (isset($visited[$current])) {
                throw new ContainerException(sprintf(
                    'Circular alias detected for [%s].',
                    $abstract
                ));
            }

            $visited[$current] = true;
            $current = $this->aliases[$current];
        }

        return $current;
    }

    /**
     * Remove a binding or instance and aliases pointing to it.
     *
     * @throws ContainerException
     */
    public function remove(string $abstract): void
    {
        $resolvedAbstract = $this->getAbstract($abstract);

        unset($this->bindings[$resolvedAbstract], $this->instances[$resolvedAbstract]);

        foreach (array_keys($this->aliases) as $alias) {
            if ($alias === $abstract || $this->getAbstract($alias) === $resolvedAbstract) {
                unset($this->aliases[$alias]);
            }
        }
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->contextual = [];
        $this->buildStack = [];
        $this->tags = [];
        $this->serviceProviders = [];
        $this->booted = false;
    }

    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $this->getAbstract($concrete));
    }

    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextual[$this->getAbstract($concrete)][$this->getAbstract($abstract)] = $implementation;
    }

    /**
     * @param string|array<int, string> $abstracts
     * @throws ContainerException
     */
    public function tag(string|array $abstracts, string $tag): void
    {
        foreach ((array) $abstracts as $abstract) {
            $abstract = $this->getAbstract($abstract);

            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            if (!in_array($abstract, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * @return array<int, mixed>
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function tagged(string $tag): array
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        $resolved = [];

        foreach ($this->tags[$tag] as $abstract) {
            $resolved[] = $this->make($abstract);
        }

        return $resolved;
    }

    /**
     * @param ServiceProviderInterface|class-string<ServiceProviderInterface> $provider
     * @throws ContainerException
     */
    public function register(ServiceProviderInterface|string $provider): void
    {
        if (is_string($provider)) {
            if (!class_exists($provider)) {
                throw new ContainerException(sprintf('Provider class [%s] does not exist.', $provider));
            }

            $provider = new $provider();
        }

        if (!$provider instanceof ServiceProviderInterface) {
            throw new ContainerException('Provider must implement ServiceProviderInterface.');
        }

        $providerClass = $provider::class;

        if (isset($this->serviceProviders[$providerClass])) {
            return;
        }

        $this->serviceProviders[$providerClass] = $provider;
        $provider->register($this);

        if ($this->booted) {
            $provider->boot($this);
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot($this);
        }

        $this->booted = true;
    }

    /**
     * Determine if the given abstract type has been bound or has an instance.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        $id = $this->getAbstract($id);

        return isset($this->bindings[$id]) || array_key_exists($id, $this->instances);
    }

    public function bound(string $abstract): bool
    {
        $abstract = $this->getAbstract($abstract);

        return isset($this->bindings[$abstract]);
    }

    public function resolved(string $abstract): bool
    {
        $abstract = $this->getAbstract($abstract);

        return array_key_exists($abstract, $this->instances);
    }

    public function factory(string $abstract, mixed $concrete = null): callable
    {
        if ($concrete !== null) {
            $this->bind($abstract, $concrete);
        }

        return fn(): mixed => $this->make($abstract);
    }

    /**
     * @param callable|array<int|string, mixed>|string $callback
     * @param array<string|int, mixed> $parameters
     * @throws ContainerException
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        $callback = $this->normalizeCallable($callback);
        $reflector = $this->getCallableReflector($callback);
        $dependencies = $this->resolveCallableDependencies($reflector, $parameters);

        try {
            return $callback(...$dependencies);
        } catch (\Throwable $e) {
            throw new ContainerException(sprintf('Error calling target: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $id
     * @return mixed
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @return mixed
     * @throws NotFoundException
     * @throws ContainerException
     */
    public function make(string $abstract): mixed
    {
        $abstract = $this->getAbstract($abstract);

        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding->concrete();
        } elseif (class_exists($abstract)) {
            $binding = new Binding($abstract, $abstract, false);
            $concrete = $abstract;
        } else {
            throw new NotFoundException(sprintf('Identifier "%s" was not found in the container.', $abstract));
        }

        try {
            if ($concrete instanceof Closure) {
                $resolved = $concrete($this);
            } elseif (is_string($concrete) && (class_exists($concrete) || interface_exists($concrete))) {
                $resolved = $this->build($concrete);
            } else {
                $resolved = $concrete;
            }
        } catch (ContainerException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ContainerException(
                sprintf('Error resolving identifier "%s": %s', $abstract, $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }

        if ($binding->shared()) {
            $this->instances[$abstract] = $resolved;
        }

        return $resolved;
    }

    /**
     * @param class-string $concrete
     * @throws ContainerException
     */
    private function build(string $concrete): object
    {
        try {
            $reflector = new ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new ContainerException(sprintf('Target class [%s] does not exist.', $concrete), 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new ContainerException(sprintf('Target class [%s] is not instantiable.', $concrete));
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return $reflector->newInstance();
        }

        $this->buildStack[] = $concrete;

        try {
            return $reflector->newInstanceArgs($this->resolveDependencies($constructor));
        } finally {
            array_pop($this->buildStack);
        }
    }

    /**
     * @return array<int, mixed>
     * @throws ContainerException
     */
    private function resolveDependencies(ReflectionMethod $constructor): array
    {
        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $dependencies;
    }

    /**
     * @param callable|array<int|string, mixed>|string $callback
     * @return callable
     * @throws ContainerException
     */
    private function normalizeCallable(callable|array|string $callback): callable
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $callback = [$class, $method];
        }

        if (is_array($callback) && isset($callback[0], $callback[1]) && is_string($callback[0])) {
            $callback[0] = $this->make($callback[0]);
        }

        if (!is_callable($callback)) {
            throw new ContainerException('Target is not a valid callable.');
        }

        return $callback;
    }

    /**
     * @throws ContainerException
     */
    private function getCallableReflector(callable $callback): ReflectionFunctionAbstract
    {
        try {
            if (is_array($callback)) {
                return new ReflectionMethod($callback[0], $callback[1]);
            }

            if ($callback instanceof Closure) {
                return new ReflectionFunction($callback);
            }

            if (is_string($callback)) {
                return new ReflectionFunction($callback);
            }

            return new ReflectionMethod($callback, '__invoke');
        } catch (\ReflectionException $e) {
            throw new ContainerException('Target is not a valid callable.', 0, $e);
        }
    }

    /**
     * @param array<string|int, mixed> $parameters
     * @return array<int, mixed>
     * @throws ContainerException
     */
    private function resolveCallableDependencies(ReflectionFunctionAbstract $reflector, array $parameters): array
    {
        $dependencies = [];
        $numericParameters = [];

        foreach ($parameters as $key => $value) {
            if (is_int($key)) {
                $numericParameters[] = $value;
            }
        }

        $numericIndex = 0;

        foreach ($reflector->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            if ($typeName !== null && array_key_exists($typeName, $parameters)) {
                $dependencies[] = $parameters[$typeName];
                continue;
            }

            if (array_key_exists($numericIndex, $numericParameters)) {
                $dependencies[] = $numericParameters[$numericIndex];
                $numericIndex++;
                continue;
            }

            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $dependencies;
    }

    /**
     * @throws ContainerException
     */
    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(sprintf(
                'Unable to resolve parameter [%s]: union and intersection types are not supported.',
                $parameter->getName()
            ));
        }

        if (!$type instanceof ReflectionNamedType) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(sprintf(
                'Unable to resolve parameter [%s]: missing type declaration.',
                $parameter->getName()
            ));
        }

        if ($type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(sprintf(
                'Unable to resolve parameter [%s]: built-in type [%s] has no default value.',
                $parameter->getName(),
                $type->getName()
            ));
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        $className = $type->getName();
        $currentConcrete = end($this->buildStack);

        if ($currentConcrete !== false && isset($this->contextual[$currentConcrete][$this->getAbstract($className)])) {
            return $this->resolveContextual($this->contextual[$currentConcrete][$this->getAbstract($className)]);
        }

        if (interface_exists($className) && !$this->has($className)) {
            throw new ContainerException(sprintf(
                'Unable to resolve parameter [%s]: interface [%s] has no binding.',
                $parameter->getName(),
                $className
            ));
        }

        try {
            return $this->make($className);
        } catch (NotFoundException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new ContainerException(sprintf(
                'Unable to resolve parameter [%s]: class [%s] was not found.',
                $parameter->getName(),
                $className
            ), 0, $e);
        }
    }

    /**
     * @throws ContainerException
     */
    private function resolveContextual(mixed $implementation): mixed
    {
        if ($implementation instanceof Closure) {
            return $implementation($this);
        }

        if (is_string($implementation) && (class_exists($implementation) || interface_exists($implementation))) {
            return $this->make($implementation);
        }

        return $implementation;
    }

    /**
     * Get all bindings.
     *
     * @return array<string, Binding>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get all resolved instances.
     *
     * @return array<string, mixed>
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /**
     * Get all aliases.
     *
     * @return array<string, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get all contextual bindings.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getContextualBindings(): array
    {
        return $this->contextual;
    }

    /**
     * Get all tags.
     *
     * @return array<string, array<int, string>>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Get all service providers.
     *
     * @return array<int, ServiceProviderInterface>
     */
    public function getServiceProviders(): array
    {
        return array_values($this->serviceProviders);
    }
}
