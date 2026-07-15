<?php

declare(strict_types=1);

namespace Core;

use Exceptions\ContainerException;
use Exceptions\ContainerNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    /**
     * Service factories indexed by identifier.
     */
    private array $factories = [];

    /**
     * Resolved shared service instances.
     */
    private array $instances = [];

    /**
     * Cached class reflection metadata.
     */
    private array $reflections = [];

    /**
     * Service identifiers currently being resolved.
     */
    private array $resolving = [];

    /**
     * Register a shared service factory.
     */
    public function set(string $id, callable $factory): self
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);

        return $this;
    }

    /**
     * Register a pre-built shared service instance.
     */
    public function instance(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;

        return $this;
    }

    /**
     * Check whether a service or auto-wirable class is available.
     */
    public function has(string $id): bool
    {
        if (isset($this->instances[$id]) || isset($this->factories[$id])) {
            return true;
        }

        return class_exists($id) && $this->reflection($id)->isInstantiable();
    }

    /**
     * Resolve a shared service or auto-wire a concrete class.
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            if (!class_exists($id)) {
                throw new ContainerNotFoundException('Service does not exist: ' . $id);
            }

            return $this->make($id);
        }

        $this->beginResolving($id);

        try {
            $service = ($this->factories[$id])($this);
        } catch (ContainerExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ContainerException(
                'Unable to resolve service ' . $id . ': ' . $exception->getMessage(),
                0,
                $exception
            );
        } finally {
            $this->endResolving();
        }

        if (!is_object($service)) {
            throw new ContainerException('Service factory must return an object: ' . $id);
        }

        $this->instances[$id] = $service;

        return $service;
    }

    /**
     * Create an object and resolve constructor dependencies.
     */
    public function make(string $className, array $parameters = []): object
    {
        if (!class_exists($className)) {
            throw new ContainerNotFoundException('Class does not exist: ' . $className);
        }

        $reflection = $this->reflection($className);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException('Class is not instantiable: ' . $className);
        }

        $this->beginResolving($className);

        try {
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $arguments = [];

            foreach ($constructor->getParameters() as $parameter) {
                $arguments[] = $this->resolveParameter($className, $parameter, $parameters);
            }

            return $reflection->newInstanceArgs($arguments);
        } finally {
            $this->endResolving();
        }
    }

    /**
     * Resolve one constructor parameter.
     */
    private function resolveParameter(
        string $className,
        \ReflectionParameter $parameter,
        array $parameters
    ): mixed {
        $name = $parameter->getName();

        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        $type = $parameter->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $this->resolveNamedType($className, $parameter, $type, $parameters);
        }

        if ($type instanceof \ReflectionUnionType) {
            return $this->resolveUnionType($className, $parameter, $type, $parameters);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return $this->resolveIntersectionType($className, $parameter, $type);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw $this->unresolvedParameter($className, $parameter);
    }

    /**
     * Resolve a named constructor parameter type.
     */
    private function resolveNamedType(
        string $className,
        \ReflectionParameter $parameter,
        \ReflectionNamedType $type,
        array $parameters
    ): mixed {
        $typeName = $type->getName();

        if (array_key_exists($typeName, $parameters)) {
            return $parameters[$typeName];
        }

        if (!$type->isBuiltin()) {
            try {
                return $this->get($typeName);
            } catch (ContainerNotFoundException $exception) {
                if ($parameter->allowsNull()) {
                    return null;
                }

                throw $exception;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw $this->unresolvedParameter($className, $parameter);
    }

    /**
     * Resolve a union type when one candidate has an explicit binding.
     */
    private function resolveUnionType(
        string $className,
        \ReflectionParameter $parameter,
        \ReflectionUnionType $type,
        array $parameters
    ): mixed {
        $candidates = [];

        foreach ($type->getTypes() as $candidate) {
            if ($candidate->isBuiltin()) {
                continue;
            }

            $candidateName = $candidate->getName();

            if (array_key_exists($candidateName, $parameters)) {
                return $parameters[$candidateName];
            }

            if (isset($this->instances[$candidateName]) || isset($this->factories[$candidateName])) {
                $candidates[] = $candidateName;
            }
        }

        $candidates = array_values(array_unique($candidates));

        if (count($candidates) === 1) {
            return $this->get($candidates[0]);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new ContainerException(
            sprintf(
                'Unable to resolve union parameter %s for %s without one explicit binding.',
                $parameter->getName(),
                $className
            )
        );
    }

    /**
     * Resolve an intersection type from a registered instance.
     */
    private function resolveIntersectionType(
        string $className,
        \ReflectionParameter $parameter,
        \ReflectionIntersectionType $type
    ): object {
        $matches = [];

        foreach ($this->instances as $instance) {
            $valid = true;

            foreach ($type->getTypes() as $candidate) {
                $candidateName = $candidate->getName();

                if (!($instance instanceof $candidateName)) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                $matches[] = $instance;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        throw new ContainerException(
            sprintf(
                'Unable to resolve intersection parameter %s for %s.',
                $parameter->getName(),
                $className
            )
        );
    }

    /**
     * Return cached reflection metadata for a class.
     */
    private function reflection(string $className): \ReflectionClass
    {
        return $this->reflections[$className] ??= new \ReflectionClass($className);
    }

    /**
     * Mark a service as being resolved and detect circular dependencies.
     */
    private function beginResolving(string $id): void
    {
        if (in_array($id, $this->resolving, true)) {
            $chain = implode(' -> ', [...$this->resolving, $id]);

            throw new ContainerException('Circular dependency detected: ' . $chain);
        }

        $this->resolving[] = $id;
    }

    /**
     * Remove the last service from the resolution stack.
     */
    private function endResolving(): void
    {
        array_pop($this->resolving);
    }

    /**
     * Create a consistent unresolved parameter exception.
     */
    private function unresolvedParameter(
        string $className,
        \ReflectionParameter $parameter
    ): ContainerException {
        return new ContainerException(
            sprintf(
                'Unable to resolve constructor parameter %s for %s.',
                $parameter->getName(),
                $className
            )
        );
    }
}
