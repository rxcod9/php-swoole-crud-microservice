<?php

/**
 * src/Tables/TableEvictor.php
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
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableEvictor.php
 */
declare(strict_types=1);

namespace App\Tables;

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
    public function __construct(
        private readonly Table $table,
        private readonly int $maxSize,
        private readonly int $bufferSize
    ) {
        // Empty Constructor
    }

    public function ensureCapacity(): void
    {
        if (count($this->table) >= ($this->maxSize - $this->bufferSize)) {
            $this->evict();
        }
    }

    private function evict(): void
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
            $this->table->del($oldestKey);
        }
    }
}
