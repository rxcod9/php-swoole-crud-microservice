<?php

/**
 * src/Tables/TableWithLRUAndGC.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Tables
 * @package  App\Tables
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableWithLRUAndGC.php
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
 * @category Tables
 * @package  App\Tables
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @var      const TYPE_INT = 1;
 * @var      const TYPE_FLOAT = 2;
 * @var      const TYPE_STRING = 3;
 * @var      ?int  $size;
 * @var      ?int  $memorySize;
 * @method   bool  column(string $name, int $type, int $size = 0)
 * @method   bool  create()
 * @method   bool  destroy()
 * @method   bool  set(string $key, array<key, mixed> $value)
 * @method   mixed get(string $key, ?string $field = null)
 * @method   int   count()
 * @method   bool  del(string $key)
 * @method   bool  delete(string $key)
 * @method   bool  exists(string $key)
 * @method   bool  exist(string $key)
 * @method   float incr(string $key, string $column, int|float $incrby = 1): int|float
 * @method   float decr(string $key, string $column, int|float $incrby = 1): int|float
 * @method   int getSize()
 * @method   int getMemorySize()
 * @method   false stats(): arra
 * @method   void  rewind()
 * @method   bool  valid()
 * @method   void  next()
 * @method   mixed current()
 * @method   mixed key()
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
     */
    public function get(string $key): ?array
    {
        $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
        $row = $this->table->get($key);
        if (!$row) {
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
     */
    public function set(string $key, array $row, int $localTtl = 0): bool
    {
        $key    = strlen($key) > 32 ? substr($key, 0, 32) : $key;
        $exists = $this->table->exist($key);
        if (
            !$exists &&
            is_countable($this->table) &&
            count($this->table) >= ($this->maxSize - $this->bufferSize)
        ) {
            $this->evict();
        }

        // auto-update last_access
        // if (!isset($row['last_access'])) {
        //     $row['last_access'] = time();
        // }
        if (!isset($row['expires_at'])) {
            $row['expires_at'] = Carbon::now()->getTimestamp() + (($localTtl > 0 ? $localTtl : $this->ttl) * 1000);
        }

        if (!isset($row['created_at'])) {
            $row['created_at'] = Carbon::now()->getTimestamp();
        }

        $success = $this->table->set($key, $row);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        return $success;
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
            $oldestKey = strlen($oldestKey) > 32 ? substr($oldestKey, 0, 32) : $oldestKey;
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
                $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
                $this->table->del($key);
            }
        }
    }

    /**
     * Proxy unknown calls to Swoole\Table.
     */
    public function __call(mixed $name, mixed $arguments)
    {
        if (!method_exists($this->table, $name)) {
            throw new BadMethodCallException(sprintf('Method %s does not exist on Swoole\Table', $name));
        }

        return $this->table->$name(...$arguments);
    }

    /* -------------------------
     * Property access
     * ------------------------- */
    public function __get(string $name)
    {
        if (property_exists($this->table, $name)) {
            return $this->table->$name;
        }

        throw new OutOfBoundsException(sprintf('Property %s does not exist on Swoole\Table', $name));
    }

    public function __set(string $name, $value): void
    {
        if (property_exists($this->table, $name)) {
            $this->table->$name = $value;
            return;
        }

        throw new OutOfBoundsException(sprintf('Property %s does not exist on Swoole\Table', $name));
    }

    public function __isset(string $name): bool
    {
        return isset($this->table->$name);
    }

    public function __unset(string $name): void
    {
        if (isset($this->table->$name)) {
            unset($this->table->$name);
            return;
        }

        throw new OutOfBoundsException(sprintf('Property %s does not exist on Swoole\Table', $name));
    }

    /* -------------------------
     * ArrayAccess
     * ------------------------- */
    public function offsetExists($offset): bool
    {
        return isset($this->table[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->table[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->table[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->table[$offset]);
    }

    /* -------------------------
     * Iterator
     * ------------------------- */
    public function rewind(): void
    {
        $this->table->rewind();
    }

    public function current(): mixed
    {
        return $this->table->current();
    }

    public function key(): mixed
    {
        return $this->table->key();
    }

    public function next(): void
    {
        $this->table->next();
    }

    public function valid(): bool
    {
        return $this->table->valid();
    }

    /* -------------------------
     * Countable
     * ------------------------- */
    public function count(): int
    {
        return count($this->table);
    }
}
