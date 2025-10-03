<?php

/**
 * src/Core/Container.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Container.php
 */
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\InstantiableException;
use Closure;
use ReflectionClass;

/**
 * Class Container
 * A simple Dependency Injection Container for managing object creation and dependency resolution.
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class Container
{
    /** @var array<string, Closure> Factory bindings for services. */
    private array $bindings = [];

    /** @var array<string, mixed> Singleton instances. */
    private array $instances = [];

    /**
     * Bind a factory closure to an identifier.
     *
     * @param string  $id      Identifier for the service.
     * @param Closure $factory Factory function that returns the service instance.
     */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    /**
     * Bind a singleton factory closure to an identifier.
     * The service will be instantiated only once.
     *
     * @param string  $id      Identifier for the service.
     * @param Closure $factory Factory function that returns the service instance.
     */
    public function singleton(string $id, Closure $factory): void
    {
        $this->bindings[$id]  = $factory;
        $this->instances[$id] = null;
    }

    /**
     * Check if an identifier is bound or can be autowired.
     *
     * @param string $id Identifier for the service.
     *
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
     *
     * @throws RuntimeException If the identifier cannot be resolved.
     *
     * @return mixed The resolved service instance.
     */
    public function get(string $id): mixed
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
     *
     * @throws InstantiableException If the class cannot be instantiated.
     *
     * @return mixed The instantiated class.
     */
    private function autowire(string $class): mixed
    {
        $reflectionClass = new ReflectionClass($class);
        if (!$reflectionClass->isInstantiable()) {
            throw new InstantiableException('Cannot instantiate ' . $class);
        }

        $ctor = $reflectionClass->getConstructor();
        if (!$ctor) {
            return new $class();
        }

        $args = [];
        foreach ($ctor->getParameters() as $parameter) {
            $t = $parameter->getType();
            if ($t && !$t->isBuiltin()) {
                $args[] = $this->get($t->getName());
            } else {
                $args[] = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
            }
        }

        return $reflectionClass->newInstanceArgs($args);
    }
}
