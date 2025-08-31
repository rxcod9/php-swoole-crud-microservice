<?php

namespace App\Core;

final class Container
{
    private array $bindings = [];
    private array $instances = [];
    public function bind(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
    }
    public function singleton(string $id, \Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->instances[$id] = null;
    }
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || class_exists($id);
    }
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
    private function autowire(string $class)
    {
        $ref = new \ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException("Cannot instantiate $class");
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
