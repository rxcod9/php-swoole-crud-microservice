<?php

/**
 * src/Tables/TableWithLRUAndGC.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: Composite Table implementation with LRU eviction, TTL expiration,
 * and Garbage Collection (GC) capabilities on top of Swoole\Table.
 * PHP version 8.5
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c)
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableWithLRUAndGC.php
 */
declare(strict_types=1);

namespace App\Tables;

use App\Exceptions\CacheSetException;
use Countable;
use Iterator;
use Swoole\Table;

/**
 * Class TableWithLRUAndGC
 * Composite abstraction on top of {@see Swoole\Table} adding:
 * - **LRU Eviction**: Automatically removes least-recently-used entries when capacity is reached.
 * - **TTL Expiry**: Supports per-item and global TTL-based expiry for cache-like semantics.
 * - **Garbage Collection (GC)**: Periodic cleanup of expired entries without blocking main flow.
 * - **Iteration & Countable Interfaces**: Allow iterating and counting table rows directly.
 * This class is useful for building high-performance in-memory caches or shared data stores
 * inside Swoole-based microservices, avoiding memory bloat or stale data.
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
 * @method     false stats(): array
 * @method     void  rewind()
 * @method     bool  valid()
 * @method     void  next()
 * @method     mixed current()
 * @method     mixed key()
 * @template   TKey of array-key
 * @template   TValue of array<string, mixed>
 * @implements Iterator<TKey, TValue>
 */
class TableWithLRUAndGC extends BaseTableProxy implements Iterator, Countable
{
    /**
     * Timestamp manager that handles TTL calculation and expiry validation.
     *
     */
    private readonly TableTimestamps $timestamps;

    /**
     * LRU Evictor that ensures table does not exceed capacity.
     *
     */
    private readonly TableEvictor $evictor;

    /**
     * Garbage collector responsible for removing expired records.
     *
     */
    private readonly TableGarbageCollector $gc;

    /**
     * Constructor
     *
     * @param int $maxSize    Maximum number of rows in the table.
     * @param int $ttl        Default TTL (seconds) for cache entries.
     * @param int $bufferSize Additional buffer size for LRU eviction smoothing.
     *
     * @throws \RuntimeException If Swoole\Table cannot be created.
     */
    public function __construct(
        int $maxSize = 1024,
        private readonly int $ttl = 2 * 60 * 10,
        int $bufferSize = 10
    ) {
        // Initialize Swoole Table with typed columns
        $table = new Table($maxSize);

        // Data payload (up to 30 KB per row)
        $table->column('value', Table::TYPE_STRING, 30000);

        // Metadata columns for lifecycle management
        $table->column('last_access', Table::TYPE_INT, 10); // UNIX timestamp of last access
        $table->column('usage', Table::TYPE_INT, 10);       // Access counter (for LRU scoring)
        $table->column('expires_at', Table::TYPE_INT, 10);  // Expiry timestamp
        $table->column('created_at', Table::TYPE_INT, 10);  // Creation timestamp

        // IMPORTANT: Actual table creation is deferred to BaseTableProxy or explicitly in factory.
        // Uncomment when running standalone:
        // $table->create();

        parent::__construct($table);

        // Composition of lifecycle managers
        $this->timestamps = new TableTimestamps();
        $this->evictor    = new TableEvictor($this->table, $maxSize, $bufferSize);
        $this->gc         = new TableGarbageCollector($this->table);
    }

    /**
     * Retrieve a row by key, with TTL enforcement.
     *
     * @param string $key Unique cache key.
     *
     * @return array<string, mixed>|null Returns the row array if present and valid, null if missing or expired.
     */
    public function get(string $key): ?array
    {
        $key = $this->normalizeKey($key);

        // Attempt to fetch entry
        $row = $this->table->get($key);

        if ($row === false || $row === null) {
            // Key not found in table
            return null;
        }

        // Check for expiration
        if ($this->timestamps->isExpired($row)) {
            // Remove expired entry and return null
            $this->table->del($key);
            return null;
        }

        $this->evictor->onAccess($key);

        // TODO: Optionally update usage counter for true LRU tracking
        // $this->timestamps->touch($this->table, $key);

        return $row;
    }

    /**
     * Insert or update an entry with LRU enforcement and TTL management.
     *
     * @param string $key       Cache key (normalized internally).
     * @param array<string, mixed>  $row       Row data. Should include 'value' at minimum.
     * @param int    $localTtl  Optional per-entry TTL (overrides global).
     *
     * @return bool True if entry was set successfully.
     *
     * @throws CacheSetException When unable to persist to the table.
     */
    public function set(string $key, array $row, int $localTtl = 0): bool
    {
        $key = $this->normalizeKey($key);

        // Ensure there is capacity using LRU eviction if necessary
        $this->evictor->ensureCapacity();

        // Add timestamp metadata (created_at, expires_at)
        $row = $this->timestamps->apply($row, $this->ttl, $localTtl);

        // Write entry to Swoole table
        if (!$this->table->set($key, $row)) {
            throw new CacheSetException('Unable to set cache entry for key: ' . $key);
        }

        return true;
    }

    /**
     * Trigger garbage collection to remove expired items.
     * Typically called periodically by a coroutine scheduler or maintenance task.
     *
     */
    public function gc(): void
    {
        $this->gc->run();
        $this->evictor->removeExpired();
    }

    // --------------------------------------------------------------------------
    // Iterator & Countable Implementations
    // --------------------------------------------------------------------------

    /**
     * Rewind the internal table iterator.
     *
     */
    public function rewind(): void
    {
        $this->table->rewind();
    }

    /**
     * Return the current row in iteration.
     *
     * @return array<string, mixed> Current table row
     */
    public function current(): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->table->current();
        return $row;
    }

    /**
     * Return the current iterator key.
     *
     * @return int|string Key of the current element
     */
    public function key(): int|string
    {
        /** @var int|string $key */
        $key = $this->table->key();
        return $key;
    }

    /**
     * Advance the iterator to the next element.
     */
    public function next(): void
    {
        $this->table->next();
    }

    /**
     * Check if the current iterator position is valid.
     *
     * @return bool True if valid; false if end of table
     */
    public function valid(): bool
    {
        return $this->table->valid();
    }

    /**
     * Count number of elements in the table.
     *
     * @return int<0, max> Total number of active rows (non-negative)
     */
    public function count(): int
    {
        return count($this->table);
    }
}
