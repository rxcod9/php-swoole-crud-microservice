<?php

/**
 * src/Tables/TableGarbageCollector.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableGarbageCollector.php
 */
declare(strict_types=1);

namespace App\Tables;

use Carbon\Carbon;
use Swoole\Table;

/**
 * TableGarbageCollector
 * Performs TTL-based cleanup on Swoole\Table.
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
class TableGarbageCollector
{
    public function __construct(private readonly Table $table)
    {
        // Empty constructor
    }

    public function run(): void
    {
        $now = Carbon::now()->getTimestamp();
        foreach ($this->table as $key => $row) {
            $expiresAt = $row['expires_at'] ?? 0;
            if ($expiresAt > 0 && $now > $expiresAt) {
                $this->table->del($key);
            }
        }
    }
}
