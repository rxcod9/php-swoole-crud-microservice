<?php

/**
 * src/Core/Pools/PoolFacade.php
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
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Pools/PoolFacade.php
 */
declare(strict_types=1);

namespace App\Core\Pools;

use App\Services\Cache\CacheService;
use Throwable;

/**
 * Facade for managing all pool-related operations.
 * Aggregates PDO pool, Redis pool, and cache service.
 *
 * @category  Core
 * @package   App\Core\Pools
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class PoolFacade
{
    public function __construct(
        private readonly PDOPool $pdoPool,
        private readonly RedisPool $redisPool,
        private readonly CacheService $cacheService
    ) {
        //
    }

    /**
     * Returns stats for both PDO and Redis pools.
     *
     * @return array<string, array<string, int>>
     */
    public function getStats(): array
    {
        return [
            'pdo'   => $this->pdoPool->stats(),
            'redis' => $this->redisPool->stats(),
        ];
    }

    /**
     * Runs autoscale on both pools with error logging.
     */
    public function autoScale(int $workerId): void
    {
        try {
            $this->pdoPool->autoScale();
        } catch (Throwable $throwable) {
            error_log(sprintf('[Worker %d] PDO autoScale error: %s', $workerId, $throwable->getMessage()));
        }

        try {
            $this->redisPool->autoScale();
        } catch (Throwable $throwable) {
            error_log(sprintf('[Worker %d] Redis autoScale error: %s', $workerId, $throwable->getMessage()));
        }
    }

    /**
     * Runs cache service garbage collection
     */
    public function runGC(): void
    {
        $this->cacheService->gc();
    }
}
