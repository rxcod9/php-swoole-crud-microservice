<?php

/**
 * Retryable.php
 * src/Traits/Retryable.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Traits
 * @package   App\Traits
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Traits/Retryable.php
 */
declare(strict_types=1);

namespace App\Traits;

use Swoole\Coroutine;
use Throwable;

/**
 * Trait Retryable
 *
 * @category  Traits
 * @package   App\Traits
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
trait Retryable
{
    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempt Number of attempts
     * @param int $maxRetry Max Number of attempts
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     * @see shouldRetry()
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function retry(callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if (shouldRetry($throwable) && ($attempt <= $maxRetry || $maxRetry === -1)) {
                $backoff = (1 << $attempt) * $delayMs * 1000;
                // microseconds
                logDebug((defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class) . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[RETRY] Retrying #%d in %.2f seconds...', $backoff / 1000000, $attempt + 1));
                Coroutine::sleep($backoff / 1000000);
                ++$attempt;
                $result = $this->retry($callback, $attempt, $maxRetry, $delayMs);
                logDebug((defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class) . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[RETRY] Retry #%d succeeded', $attempt));
                return $result;
            }

            logDebug((defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class) . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[EXCEPTION] Retry #%d failed error: %s', $attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }

    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempt Number of attempts
     * @param int $maxRetry Max Number of attempts
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function forceRetry(callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            // Retry only if "Connection refused" (MySQL error 2002)
            if ($attempt <= $maxRetry || $maxRetry === -1) {
                $backoff = (1 << $attempt) * $delayMs * 1000; // microseconds
                logDebug((defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class) . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[RETRY] Force Retrying #%d in %.2f seconds...', $backoff / 1000000, $attempt + 1));
                Coroutine::sleep($backoff / 1000000);
                ++$attempt;
                $result = $this->forceRetry($callback, $attempt, $maxRetry, $delayMs);
                logDebug((defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class) . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[RETRY] Force Retry #%d succeeded', $attempt));
                return $result;
            }

            logDebug((defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class) . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', sprintf('Force Retry #%d failed error: %s', $attempt, $throwable->getMessage()));
            throw $throwable;
        }
    }
}
