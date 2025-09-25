<?php

namespace App\Core;

use RuntimeException;

/**
 * Class Container
 *
 * A simple Dependency Injection Container for managing object creation and dependency resolution.
 *
 * @package App\Core
 */
final class Container
{
    /**
     * @var array<string, \Closure> Factory bindings for services.
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed> Singleton instances.
     */
    private array $instances = [];

    /**
     * Bind a factory closure to an identifier.
     *
     * @param string $id Identifier for the service.
     * @param \Closure $factory Factory function that returns the service instance.
     * @return void
     */
    public function bind(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    /**
     * Bind a singleton factory closure to an identifier.
     * The service will be instantiated only once.
     *
     * @param string $id Identifier for the service.
     * @param \Closure $factory Factory function that returns the service instance.
     * @return void
     */
    public function singleton(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->instances[$id] = null;
    }

    /**
     * Check if an identifier is bound or can be autowired.
     *
     * @param string $id Identifier for the service.
     * @return bool True if the service is bound or the class exists, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    /**
     * Get an instance by identifier.
     *
     * @param string $id Identifier for the service.
     * @return mixed The resolved service instance.
     * @throws \RuntimeException If the identifier cannot be resolved.
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            if ($this->instances[$id] === null) {
                $this->instances[$id] = ($this->bindings[$id])($this);
            }
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            return ($this->bindings[$id])($this);
        }
        return $this->autowire($id);
    }

    /**
     * Autowire a class by resolving its dependencies recursively.
     *
     * @param string $class Class name to instantiate.
     * @return mixed The instantiated class.
     * @throws \RuntimeException If the class cannot be instantiated.
     */
    private function autowire(string $class)
    {
        $ref = new \ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Cannot instantiate $class");
        }
        $ctor = $ref->getConstructor();
        if (!$ctor) {
            return new $class();
        }
        $args = [];
        foreach ($ctor->getParameters() as $p) {
            $t = $p->getType();
            if ($t && !$t->isBuiltin()) {
                $args[] = $this->get($t->getName());
            } else {
                $args[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : null;
            }
        }
        return $ref->newInstanceArgs($args);
    }
}
