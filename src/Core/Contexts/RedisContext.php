<?php

declare(strict_types=1);

namespace App\Core\Contexts;

use App\Exceptions\UnsupportedRedisCommandException;
use Redis;
use RuntimeException;

/**
 * Class RedisContext
 *
 * A wrapper for Swoole Coroutine Redis client providing
 * convenient methods for common Redis operations and transaction handling.
 *
 * @package App\Core
 */
final class RedisContext
{
    /**
     * The Swoole Coroutine Redis connection instance.
     *
     */
    private Redis $conn;

    /**
     * RedisContext constructor.
     *
     * @param Redis $conn The Swoole Coroutine Redis connection.
     */
    public function __construct(Redis $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Get the underlying Redis connection.
     *
     */
    public function conn(): Redis
    {
        return $this->conn;
    }

    /**
     * Begin a Redis transaction (MULTI).
     *
     * @return bool True on success, false on failure.
     */
    public function begin(): bool
    {
        return $this->conn->multi();
    }

    /**
     * Commit a Redis transaction (EXEC).
     *
     * @return array|false Array of replies for each command in the transaction, or false on failure.
     */
    public function commit(): array|false
    {
        return $this->conn->exec();
    }

    /**
     * Rollback a Redis transaction (DISCARD).
     *
     * @return bool True on success, false on failure.
     */
    public function rollback(): bool
    {
        return $this->conn->discard();
    }

    /**
     * Execute a Redis command dynamically.
     *
     * Example:
     *   $redis->command('set', ['foo', 'bar']);
     *   $val = $redis->command('get', ['foo']);
     *
     * @param string $cmd The Redis command name.
     * @param array $args Arguments for the command.
     * @return mixed The result of the Redis command.
     * @throws RuntimeException If the command is not supported.
     */
    public function command(string $cmd, array $args = []): mixed
    {
        // Normalize command name
        $cmd = strtolower($cmd);

        // Dynamically call Redis method
        if (!method_exists($this->conn, $cmd)) {
            throw new UnsupportedRedisCommandException("Unsupported Redis command: {$cmd}");
        }

        return $this->conn->{$cmd}(...$args);
    }

    /**
     * Get the value of a key.
     *
     * @param string $key The key name.
     * @return mixed The value, or false if the key does not exist.
     */
    public function get(string $key): mixed
    {
        return $this->command('get', [$key]);
    }

    /**
     * Set the value of a key.
     *
     * @param string $key The key name.
     * @param mixed $val The value to set.
     * @return mixed True if successful, false otherwise.
     */
    public function set(string $key, $val): mixed
    {
        return $this->command('set', [$key, $val]);
    }

    /**
     * Delete a key.
     *
     * @param string $key The key name.
     * @return mixed The number of keys deleted.
     */
    public function del(string $key): mixed
    {
        return $this->command('del', [$key]);
    }

    /**
     * Scan the Redis database for keys matching a pattern.
     *
     * @param mixed $it The iterator (pass null for the first call).
     * @param string $pattern The pattern to match.
     * @param int $count The number of keys to return per iteration.
     * @return mixed Array of keys, or false when iteration is complete.
     */
    public function scan($it, string $pattern, int $count = 100): mixed
    {
        return $this->command('scan', [$it, $pattern, $count]);
    }

    /**
     * Delete all keys matching a given pattern.
     *
     * @param string $pattern The pattern to match.
     */
    public function deleteByPattern(string $pattern): void
    {
        $it = null;
        while ($keys = $this->scan($it, $pattern, 100)) {
            print_r($keys);
            foreach ($keys as $key) {
                $this->del($key);
            }
        }
    }

    /**
     * Check if a key exists.
     *
     * @param string $key The key name.
     * @return mixed 1 if the key exists, 0 if not.
     */
    public function exists(string $key): mixed
    {
        return $this->command('exists', [$key]);
    }
}
