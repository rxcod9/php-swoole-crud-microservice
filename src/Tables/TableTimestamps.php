<?php

/**
 * src/Tables/TableTimestamps.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tables/TableTimestamps.php
 */
declare(strict_types=1);

namespace App\Tables;

use Carbon\Carbon;

/**
 * TableTimestamps
 * Handles all timestamp and TTL related operations.
 *
 * @category  Tables
 * @package   App\Tables
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
class TableTimestamps
{
    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public function apply(array $row, int $ttl, int $localTtl = 0): array
    {
        $now = Carbon::now()->getTimestamp();
        $row['expires_at'] ??= $now + ($localTtl > 0 ? $localTtl : $ttl);
        $row['created_at'] ??= $now;
        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isExpired(array $row): bool
    {
        $now = Carbon::now()->getTimestamp();
        return isset($row['expires_at']) && $now > $row['expires_at'];
    }
}
