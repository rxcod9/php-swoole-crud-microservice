<?php

/**
 * src/Support/DuplicateHelper.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/DuplicateHelper.php
 */
declare(strict_types=1);

namespace App\Support;

use PDOException;
use Throwable;

/**
 * Class DuplicateHelper
 * Provides detection logic for duplicate record exceptions.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 */
class DuplicateHelper
{
    /**
     * Determine if the given Throwable represents a duplicate record.
     */
    public static function isDuplicate(Throwable $throwable): bool
    {
        if (!($throwable instanceof PDOException)) {
            logDebug(__METHOD__, 'not a PDOException');
            return false;
        }

        $info       = $throwable->errorInfo ?? [];
        $sqlState   = $info[0] ?? null;
        $driverCode = $info[1] ?? null;
        $driverMsg  = $info[2] ?? '';
        if (self::isMySqlDuplicate($sqlState, $driverCode)) {
            return true;
        }

        if (self::isPostgresDuplicate($sqlState)) {
            return true;
        }

        if (self::isSqliteDuplicate($driverMsg)) {
            return true;
        }

        return self::isSqlServerDuplicate($driverMsg);
    }

    private static function isMySqlDuplicate(?string $sqlState, ?int $code): bool
    {
        return $sqlState === '23000' && in_array($code, [1062, 1022], true);
    }

    private static function isPostgresDuplicate(?string $sqlState): bool
    {
        return $sqlState === '23505';
    }

    private static function isSqliteDuplicate(string $msg): bool
    {
        $patterns = ['UNIQUE constraint failed', 'column is not unique'];
        return array_any($patterns, fn ($p): bool => stripos($msg, $p) !== false);
    }

    private static function isSqlServerDuplicate(string $msg): bool
    {
        $patterns = ['Cannot insert duplicate key', 'duplicate key row'];
        return array_any($patterns, fn ($p): bool => stripos($msg, $p) !== false);
    }
}
