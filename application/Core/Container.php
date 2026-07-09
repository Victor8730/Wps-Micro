<?php

declare(strict_types=1);

namespace Core;

class Container
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
     * Resolve a shared service.
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            return $this->make($id);
        }

        $service = ($this->factories[$id])($this);

        if (!is_object($service)) {
            throw new \RuntimeException('Service factory must return an object: ' . $id);
        }

        $this->instances[$id] = $service;

        return $service;
    }

    /**
     * Create an object and resolve class dependencies from the container.
     */
    public function make(string $className, array $parameters = []): object
    {
        if (!class_exists($className)) {
            throw new \RuntimeException('Class does not exist: ' . $className);
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(
                'Unable to resolve constructor parameter ' . $name . ' for ' . $className
            );
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
