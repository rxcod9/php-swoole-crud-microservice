<?php

/**
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
 * @since     2025-10-13
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Traits/Retryable.php
 */
declare(strict_types=1);

namespace App\Traits;

use Swoole\Coroutine;
use Throwable;

/**
 * Trait Retryable
 * Handles retry logic with exponential backoff for coroutine-safe operations.
 *
 * @category  Traits
 * @package   App\Traits
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-13
 */
trait Retryable
{
    /**
     * Retry a callback a few times with optional delay.
     *
     * @param callable(): mixed $callback
     * @param int $attempt Number of attempts
     * @param int $maxRetry Max Number of attempts (-1 for infinite)
     * @param int $delayMs Delay between retries in milliseconds
     *
     * @throws Throwable
     */
    public function retry(callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            if (!$this->canRetry($throwable, $attempt, $maxRetry)) {
                $this->logRetryFailure($throwable, $attempt);
                throw $throwable;
            }

            $this->sleepWithBackoff($attempt, $delayMs, __FUNCTION__);
            return $this->retry($callback, ++$attempt, $maxRetry, $delayMs);
        }
    }

    /**
     * Retry unconditionally (ignores shouldRetry()).
     *
     * @param callable(): mixed $callback
     *
     * @throws Throwable
     */
    public function forceRetry(callable $callback, int $attempt = 0, int $maxRetry = 5, int $delayMs = 100): mixed
    {
        try {
            return $callback();
        } catch (Throwable $throwable) {
            if (!$this->withinRetryLimit($attempt, $maxRetry)) {
                $this->logRetryFailure($throwable, $attempt, true);
                throw $throwable;
            }

            $this->sleepWithBackoff($attempt, $delayMs, __FUNCTION__, true);
            return $this->forceRetry($callback, ++$attempt, $maxRetry, $delayMs);
        }
    }

    /**
     * Check if retry is allowed.
     */
    private function canRetry(Throwable $throwable, int $attempt, int $maxRetry): bool
    {
        return (function_exists('shouldRetry') && shouldRetry($throwable))
            && $this->withinRetryLimit($attempt, $maxRetry);
    }

    /**
     * Check retry limit.
     */
    private function withinRetryLimit(int $attempt, int $maxRetry): bool
    {
        return ($attempt <= $maxRetry || $maxRetry === -1);
    }

    /**
     * Handle coroutine backoff sleep.
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function sleepWithBackoff(int $attempt, int $delayMs, string $method, bool $force = false): void
    {
        $backoffUs = (1 << $attempt) * $delayMs * 1000;
        $label     = $force ? 'Force Retry' : 'Retry';
        $tag       = defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class;

        logDebug($tag . ':' . __LINE__ . ('][' . $method), sprintf('[%s] #%d in %.2f seconds...', $label, $attempt + 1, $backoffUs / 1_000_000));
        Coroutine::sleep($backoffUs / 1_000_000);
    }

    /**
     * Log final failure before throwing.
     */
    private function logRetryFailure(Throwable $throwable, int $attempt, bool $force = false): void
    {
        $label = $force ? 'Force Retry' : 'Retry';
        $tag   = defined(static::class . '::TAG') ? constant(static::class . '::TAG') : static::class;

        logDebug($tag . ':' . __LINE__ . '][__FUNCTION__', sprintf('[%s] #%d failed error: %s', $label, $attempt, $throwable->getMessage()));
    }
}
