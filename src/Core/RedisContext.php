<?php

namespace App\Core;

use Swoole\Coroutine\Redis;

final class RedisContext
{
    private Redis $conn;

    public function __construct(Redis $conn)
    {
        $this->conn = $conn;
    }

    public function conn(): Redis
    {
        return $this->conn;
    }

    /**
     * Begin a transaction (MULTI)
     */
    public function begin(): bool
    {
        return $this->conn->multi();
    }

    /**
     * Commit a transaction (EXEC)
     */
    public function commit(): array|false
    {
        return $this->conn->exec();
    }

    /**
     * Rollback a transaction (DISCARD)
     */
    public function rollback(): bool
    {
        return $this->conn->discard();
    }

    /**
     * Execute a Redis command
     *
     * Example:
     *   $redis->command('set', ['foo', 'bar']);
     *   $val = $redis->command('get', ['foo']);
     */
    public function command(string $cmd, array $args = []): mixed
    {
        // Normalize command name
        $cmd = strtolower($cmd);

        // Dynamically call Redis method
        if (!method_exists($this->conn, $cmd)) {
            throw new \RuntimeException("Unsupported Redis command: {$cmd}");
        }

        return $this->conn->{$cmd}(...$args);
    }

    public function get(string $key): mixed
    {
        return $this->command('get', [$key]);
    }

    public function set(string $key, $val): mixed
    {
        return $this->command('set', [$key, $val]);
    }

    public function del(string $key): mixed
    {
        return $this->command('del', [$key]);
    }

    public function exists(string $key): mixed
    {
        return $this->command('exists', [$key]);
    }
}
