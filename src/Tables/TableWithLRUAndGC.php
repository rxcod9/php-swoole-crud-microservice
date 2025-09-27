<?php

declare(strict_types=1);

namespace App\Tables;

use BadMethodCallException;

use function count;

use Swoole\Table;
use Swoole\Timer;

/**
 * TableWithLRUAndGC
 *
 * Extensible wrapper around Swoole\Table with user-defined schema.
 * Behaves like Table but allows overrides for eviction and GC.
 *
 * @package App\Tables
 */
class TableWithLRUAndGC
{
    protected Table $table;

    public function __construct(
        protected int $maxSize = 1024,
        protected int $ttl = 60,
        protected int $bufferSize = 10
    ) {
        $table = new Table($maxSize);
        $table->column('value', Table::TYPE_STRING, 256);
        $table->column('last_access', Table::TYPE_INT, 8);
        $table->column('expires_at', Table::TYPE_INT, 8);
        // $table->create();

        $this->table = $table;

        // if ($this->ttl > 0) {
        //     Timer::tick($ttl * 1000, fn() => $this->gc());
        // }
    }

    /**
     * Get row (and update last_access if exists).
     */
    public function get(string $key): ?array
    {
        $row = $this->table->get($key);
        if (!$row) {
            return null;
        }

        // check expiration
        $now = time();
        if (isset($row['expires_at']) && $now >= $row['expires_at']) {
            $this->table->del($key);
            return null;
        }

        if (isset($row['last_access'])) {
            $row = $this->touch($key, $row);
        }

        return $row;
    }

    /**
     * Set row with user-defined schema.
     */
    public function set(string $key, array $data, int $localTtl = 0): void
    {
        $exists = $this->table->exist($key);
        if (
            !$exists &&
            is_countable($this->table) &&
            count($this->table) >= ($this->maxSize - $this->bufferSize)
        ) {
            $this->evict();
        }

        // auto-update last_access
        if (!isset($data['last_access'])) {
            $data['last_access'] = time();
        }
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = time() + ($localTtl > 0 ? $localTtl : $this->ttl);
        }

        $this->table->set($key, $data);
    }

    /**
     * Eviction policy — default LRU (using last_access).
     */
    protected function evict(): void
    {
        $oldestKey = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($this->table as $key => $row) {
            $lastAccess = $row['last_access'] ?? 0;
            if ($lastAccess < $oldestTime) {
                $oldestTime = $lastAccess;
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
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
            // $lastAccess = $row['last_access'] ?? 0;
            $expiresAt = $row['expires_at'] ?? time();
            if ($now >= $expiresAt) {
                $this->table->del($key);
            }
        }
    }

    /**
     * Update last_access timestamp if column exists.
     */
    protected function touch(string $key, array $row): array
    {
        $row['last_access'] = time();
        $this->table->set($key, $row);
        return $row;
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
}
