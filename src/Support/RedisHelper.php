<?php

/**
 * src/Support/RedisHelper.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/RedisHelper.php
 */
declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Class RedisHelper
 * Handles Redis retry logic based on transient network conditions.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 */
class RedisHelper
{
    /**
     * Determine if Redis-related exception is retryable.
     */
    public static function shouldRetry(Throwable $throwable): bool
    {
        $msg = strtolower($throwable->getMessage());
        logDebug(__METHOD__, 'called', ['message' => $msg]);

        $retryable = str_contains($msg, 'connection refused')
            || str_contains($msg, 'connection lost')
            || str_contains($msg, 'went away')
            || str_contains($msg, 'read error on connection')
            || str_contains($msg, 'failed to connect')
            || str_contains($msg, 'connection timed out in');

        logDebug(__METHOD__, 'result', ['retryable' => $retryable]);
        return $retryable;
    }
}
