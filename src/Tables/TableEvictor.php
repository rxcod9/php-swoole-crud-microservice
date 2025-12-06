<?php

/**
 * src/Tables/TableEvictor.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableEvictor.php
 */
declare(strict_types=1);

namespace App\Tables;

use Carbon\Carbon;
use SplDoublyLinkedList;
use Swoole\Table;

/**
 * TableEvictor
 * Implements Least Recently Used (LRU) eviction.
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
class TableEvictor
{
    /**
     * @var SplDoublyLinkedList<string>
     * LRU queue of keys for eviction ordering (head = oldest, tail = most recent)
     */
    private readonly SplDoublyLinkedList $lruQueue;

    public function __construct(
        private readonly Table $table,
        private readonly int $maxSize,
        private readonly int $bufferSize
    ) {
        $this->lruQueue = new SplDoublyLinkedList();
        $this->lruQueue->setIteratorMode(SplDoublyLinkedList::IT_MODE_FIFO);
    }

    public function onAccess(string $key): void
    {
        // Move accessed key to tail
        $index = array_search($key, iterator_to_array($this->lruQueue), true);
        if ($index !== false) {
            $this->lruQueue->offsetUnset($index);
        }

        $this->lruQueue->push($key);
    }

    public function ensureCapacity(): void
    {
        if (count($this->table) >= ($this->maxSize - $this->bufferSize)) {
            $this->evict();
        }
    }

    private function evict(): void
    {
        $oldestKey = null;

        $currentSize = count($this->table);

        // Evict bufferSize oldest keys from head
        for ($i = 0; $i < min($this->bufferSize, $currentSize); $i++) {
            if ($this->lruQueue->isEmpty()) {
                break;
            }

            $oldestKey = $this->lruQueue->shift();
            $this->table->del($oldestKey);
        }
    }

    /**
     * Garbage collect expired entries.
     *
     * Only checks oldest N items in LRU queue to avoid full table scan.
     */
    public function removeExpired(int $checkCount = 50): void
    {
        $currentTime = Carbon::now()->getTimestamp();
        $iterations  = min($checkCount, $this->lruQueue->count());

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->lruQueue->isEmpty()) {
                break;
            }

            $oldestKey = $this->lruQueue->bottom(); // peek oldest
            $row       = $this->table->get($oldestKey);

            // If row missing or expired, remove from table & queue
            if ($row !== null && (($row['expire_at'] ?? PHP_INT_MAX) <= $currentTime)) {
                $this->lruQueue->shift();
                $this->table->del($oldestKey);
            }
        }
    }
}
