<?php

/**
 * src/Core/Pools/RetryContext.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Pools
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Pools/RetryContext.php
 */
declare(strict_types=1);

namespace App\Core\Pools;

/**
 * Class RetryContext
 * Represents retry attempt configuration and state.
 *
 * @category  Core
 * @package   App\Core\Pools
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class RetryContext
{
    public function __construct(
        public int $attempt = 0,
        public readonly int $maxRetry = 10,
        public readonly int $delayMs = 100
    ) {
        // Empty Constructor
    }

    /**
     * Increment attempt counter.
     */
    public function next(): self
    {
        $this->attempt++;
        return $this;
    }

    /**
     * Calculate exponential backoff in microseconds.
     */
    public function backoff(): int
    {
        return (1 << $this->attempt) * $this->delayMs * 1000;
    }

    /**
     * Check if retry is still allowed.
     */
    public function canRetry(): bool
    {
        return $this->maxRetry === -1 || $this->attempt <= $this->maxRetry;
    }
}
