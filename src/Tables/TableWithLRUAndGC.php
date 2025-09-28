<?php

declare(strict_types=1);

namespace App\Tables;

use App\Exceptions\CacheSetException;
use BadMethodCallException;

use function count;

use Countable;
use Iterator;
use OutOfBoundsException;

use function strlen;

use Swoole\Table;

/**
 * TableWithLRUAndGC
 *
 * Extensible wrapper around Swoole\Table with user-defined schema.
 * Behaves like Table but allows overrides for eviction and GC.
 *
 * @var const TYPE_INT = 1;
 * @var const TYPE_FLOAT = 2;
 * @var const TYPE_STRING = 3;
 * @var ?int $size;
 * @var ?int $memorySize;
 * @method bool column(string $name, int $type, int $size = 0)
 * @method bool create()
 * @method bool destroy()
 * @method bool set(string $key, array $value)
 * @method mixed get(string $key, ?string $field = null)
 * @method int count()
 * @method bool del(string $key)
 * @method bool delete(string $key)
 * @method bool exists(string $key)
 * @method bool exist(string $key)
 * @method float incr(string $key, string $column, int|float $incrby = 1): in
 * @method float decr(string $key, string $column, int|float $incrby = 1): in
 * @method int getSize()
 * @method int getMemorySize()
 * @method false stats(): arra
 * @method void rewind()
 * @method bool valid()
 * @method void next()
 * @method mixed current()
 * @method mixed key()
 *
 * @package App\Tables
 */
class TableWithLRUAndGC implements Iterator, Countable
{
    protected Table $table;

    public function __construct(
        protected int $maxSize = 1024,
        protected int $ttl = 60,
        protected int $bufferSize = 10
    ) {
        $table = new Table($maxSize, 128);
        $table->column('value', Table::TYPE_STRING, 30000);
        $table->column('last_access', Table::TYPE_INT, 10);
        $table->column('expires_at', Table::TYPE_INT, 10);

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
        $now = time();
        if (isset($row['expires_at']) && $now > $row['expires_at']) {
            $this->table->del($key);
            return null;
        }

        return $row;
    }

    /**
     * Set row with user-defined schema.
     */
    public function set(string $key, array $data, int $localTtl = 0): bool
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
        // if (!isset($data['last_access'])) {
        //     $data['last_access'] = time();
        // }
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = time() + (($localTtl > 0 ? $localTtl : $this->ttl) * 1000);
        }

        $success = $this->table->set($key, $data);
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
        $now = time();
        foreach ($this->table as $key => $row) {
            $expiresAt = $row['expires_at'] ?? time();
            if ($now >= $expiresAt) {
                $key = strlen($key) > 32 ? substr($key, 0, 32) : $key;
                $this->table->del($key);
            }
        }
    }

    /**
     * Proxy unknown calls to Swoole\Table.
     */
    public function __call($name, $arguments)
    {
        if (!method_exists($this->table, $name)) {
            throw new BadMethodCallException("Method {$name} does not exist on Swoole\\Table");
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
        throw new OutOfBoundsException("Property {$name} does not exist on Swoole\\Table");
    }

    public function __set(string $name, $value): void
    {
        if (property_exists($this->table, $name)) {
            $this->table->$name = $value;
            return;
        }
        throw new OutOfBoundsException("Property {$name} does not exist on Swoole\\Table");
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
        throw new OutOfBoundsException("Property {$name} does not exist on Swoole\\Table");
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
