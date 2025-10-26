<?php

/**
 * src/Support/LogHelper.php
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
 * @since     2025-10-13
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/LogHelper.php
 */
declare(strict_types=1);

namespace App\Support;

use Swoole\Coroutine;

/**
 * Class LogHelper
 * Handles all log helper operations.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-13
 */
class LogHelper
{
    /**
     * Get the current coroutine ID or fallback to N/A
     *
     * @return string CoroutineId
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public static function getCoroutineId(): string|int
    {
        return class_exists(Coroutine::class)
            ? Coroutine::getCid()
            : 'N/A';
    }

    /**
     * Centralized debug logger
     *
     * @param  array<string, mixed> $context
     */
    public static function debug(string $tag, string $message, array $context = []): void
    {
        // no env helper use, avoding dependency
        $appDebug = env('APP_DEBUG', false);

        if ($appDebug === false) {
            // redirect to /dev/null in tests
            return;
        }

        $cid = self::getCoroutineId();

        $line = sprintf('[%s][cid:%s] %s', $tag, $cid, $message);
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        error_log($line);
    }
}
