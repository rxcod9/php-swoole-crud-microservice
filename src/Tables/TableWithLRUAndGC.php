<?php

/**
 * src/Tables/TableWithLRUAndGC.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableWithLRUAndGC.php
 */
declare(strict_types=1);

namespace App\Tables;

use App\Exceptions\CacheSetException;
use BadMethodCallException;
use Carbon\Carbon;
use Countable;
use Iterator;
use OutOfBoundsException;
use Swoole\Table;

/**
 * TableWithLRUAndGC
 * Extensible wrapper around Swoole\Table with user-defined schema.
 * Behaves like Table but allows overrides for eviction and GC.
 *
 * @category   Tables
 * @package    App\Tables
 * @author     Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright  Copyright (c) 2025
 * @license    MIT
 * @version    1.0.0
 * @since      2025-10-02
 * @method     bool  column(string $name, int $type, int $size = 0)
 * @method     bool  create()
 * @method     bool  destroy()
 * @method     bool  set(string $key, array<key, mixed> $value)
 * @method     mixed get(string $key, ?string $field = null)
 * @method     int   count()
 * @method     bool  del(string $key)
 * @method     bool  delete(string $key)
 * @method     bool  exists(string $key)
 * @method     bool  exist(string $key)
 * @method     int|float incr(string $key, string $column, int|float $incrby = 1)
 * @method     int|float decr(string $key, string $column, int|float $incrby = 1)
 * @method     int getSize()
 * @method     int getMemorySize()
 * @method     false stats(): arra
 * @method     void  rewind()
 * @method     bool  valid()
 * @method     void  next()
 * @method     mixed current()
 * @method     mixed key()
 * @template   TKey of array-key
 * @template   TValue of array<string, mixed>
 * @implements Iterator<TKey, TValue>
 */
class TableWithLRUAndGC implements Iterator, Countable
{
    protected Table $table;

    public function __construct(
        protected int $maxSize = 1024,
        protected int $ttl = 120,
        protected int $bufferSize = 10
    ) {
        $table = new Table($maxSize);
        $table->column('value', Table::TYPE_STRING, 30000);
        $table->column('last_access', Table::TYPE_INT, 10);
        $table->column('usage', Table::TYPE_INT, 10);
        $table->column('expires_at', Table::TYPE_INT, 10);
        $table->column('created_at', Table::TYPE_INT, 10);

        $this->table = $table;
    }

    /**
     * Get a row from the cache table.
     * Only updates last_access if a threshold time has passed to avoid write storms.
     *
     * @param string $key The key to retrieve.
     * @return array<string, mixed>|null The row data or null if not found or expired.
     */
    public function get(string $key): ?array
    {
        $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;
        $row = $this->table->get($key);
        if ($row === false || $row === null) {
            return null;
        }

        // check expiration
        $now = Carbon::now()->getTimestamp();
        if (isset($row['expires_at']) && $now > $row['expires_at']) {
            $this->table->del($key);
            return null;
        }

        return $row;
    }

    /**
     * Set row with user-defined schema.
     *
     * @param string $key The key to set.
     * @param array<string, mixed> $row The row data to set.
     * @param int $localTtl Optional TTL for this specific entry (overrides default ttl).
     * @throws CacheSetException
     */
    public function set(string $key, array $row, int $localTtl = 0): bool
    {
        $key = $this->normalizeKey($key);

        $this->ensureCapacity();

        $row = $this->applyTimestamps($row, $localTtl);

        $success = $this->table->set($key, $row);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        return true;
    }

    /**
     * Normalize key length to max 56 chars.
     */
    private function normalizeKey(string $key): string
    {
        return strlen($key) > 56 ? substr($key, 0, 56) : $key;
    }

    /**
     * Enforces LRU eviction if table is near capacity.
     */
    private function ensureCapacity(): void
    {
        if (count($this->table) >= ($this->maxSize - $this->bufferSize)) {
            $this->evict();
        }
    }

    /**
     * Apply expiration and creation timestamps.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function applyTimestamps(array $row, int $localTtl): array
    {
        $now = Carbon::now()->getTimestamp();

        $row['expires_at'] ??= $now + (($localTtl > 0 ? $localTtl : $this->ttl) * 1000);
        $row['created_at'] ??= $now;

        return $row;
    }

    /**
     * Eviction policy — default LRU (using last_access).
     */
    protected function evict(): void
    {
        $oldestKey  = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($this->table as $key => $row) {
            $lastAccess = $row['last_access'] ?? 0;
            if ($lastAccess < $oldestTime) {
                $oldestTime = $lastAccess;
                $oldestKey  = $key;
            }
        }

        if ($oldestKey !== null) {
            $oldestKey = strlen($oldestKey) > 56 ? substr($oldestKey, 0, 56) : $oldestKey;
            $this->table->del($oldestKey);
        }
    }

    /**
     * Garbage collection (default: based on expires_at).
     */
    public function gc(): void
    {
        $now = Carbon::now()->getTimestamp();
        foreach ($this->table as $key => $row) {
            $expiresAt = $row['expires_at'] ?? Carbon::now()->getTimestamp();
            if ($now > $expiresAt) {
                $key = strlen($key) > 56 ? substr($key, 0, 56) : $key;
                $this->table->del($key);
            }
        }
    }

    /**
     * Proxy unknown calls to Swoole\Table.
     *
     * @param mixed $name The method name.
     * @param mixed $arguments The method arguments.
     * @return mixed The result of the method call.
     * @throws BadMethodCallException If the method does not exist on Swoole\Table
     */
    public function __call(mixed $name, mixed $arguments): mixed
    {
        if (!method_exists($this->table, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist on Swoole\Table', $name));
        }

        return call_user_func_array([$this->table, $name], $arguments);
    }

    /**
     * Proxy property access to Swoole\Table.
     *
     * @param string $name The property name.
     * @return mixed The property value.
     * @throws OutOfBoundsException If the property does not exist on Swoole\Table
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this->table, $name)) {
            /** @phpstan-ignore-next-line */
            return $this->table->$name;
        }

        throw new OutOfBoundsException(sprintf('Property %s does not exist on Swoole\Table', $name));
    }

    /**
     * Proxy property setting to Swoole\Table.
     *
     * @param string $name The property name.
     * @param mixed $value The property value.
     * @throws OutOfBoundsException If the property does not exist on Swoole\Table
     */
    public function __set(string $name, mixed $value): void
    {
        if (property_exists($this->table, $name)) {
            /** @phpstan-ignore-next-line */
            $this->table->$name = $value;
            return;
        }

        throw new OutOfBoundsException(sprintf('Property %s does not exist on Swoole\Table', $name));
    }

    /**
     * Proxy isset to Swoole\Table.
     *
     * @param string $name The property name.
     * @return bool True if the property is set, false otherwise.
     */
    public function __isset(string $name): bool
    {
        /** @phpstan-ignore-next-line */
        return isset($this->table->$name);
    }

    /**
     * Proxy unset to Swoole\Table.
     *
     * @param string $name The property name.
     * @throws OutOfBoundsException If the property does not exist on Swoole\Table
     */
    public function __unset(string $name): void
    {
        /** @phpstan-ignore-next-line */
        if (isset($this->table->$name)) {
            /** @phpstan-ignore-next-line */
            unset($this->table->$name);
            return;
        }

        throw new OutOfBoundsException(sprintf('Property %s does not exist on Swoole\Table', $name));
    }

    /**
     * OffsetExists for ArrayAccess
     * @param mixed $offset The offset to check.
     * @return bool True if the offset exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->table[$offset]);
    }

    /**
     * OffsetGet for ArrayAccess
     * @param mixed $offset The offset to get.
     * @return mixed The value at the offset or null if not found.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->table[$offset] ?? null;
    }

    /**
     * OffsetSet for ArrayAccess
     * @param mixed $offset The offset to set.
     * @param mixed $value The value to set.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->table[$offset] = $value;
    }

    /**
     * OffsetUnset for ArrayAccess
     * @param mixed $offset The offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->table[$offset]);
    }

    /**
     * Rewind the iterator (Iterator interface).
     */
    public function rewind(): void
    {
        $this->table->rewind();
    }

    /**
     * Get current element (Iterator interface).
     * @return array<string, mixed> The current element.
     */
    public function current(): mixed
    {
        return $this->table->current();
    }

    /**
     * Get current key (Iterator interface).
     * @return int|string The current key.
     */
    public function key(): mixed
    {
        return $this->table->key();
    }

    /**
     * Move to next element (Iterator interface).
     */
    public function next(): void
    {
        $this->table->next();
    }

    /**
     * Check if current position is valid (Iterator interface).
     * @return bool True if valid, false otherwise.
     */
    public function valid(): bool
    {
        return $this->table->valid();
    }

    /**
     * Count elements (Countable interface).
     * @return int<0, max> The number of elements in the table.
     */
    public function count(): int
    {
        return count($this->table);
    }
}
