<?php

/**
 * src/Tasks/HttpMetricsTask.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/HttpMetricsTask.php
 */
declare(strict_types=1);

namespace App\Tasks;

use App\Core\Metrics;
use App\Core\Pools\RedisPool;

/**
 * HttpMetricsTask handles logging of request payloads to a file.
 * This class uses Monolog to write log entries to /app/logs/access.log.
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class HttpMetricsTask implements TaskInterface
{
    public const TAG = 'HttpMetricsTask';

    /**
     * Inject UserService for business logic operations.
     */
    public function __construct(
        private readonly RedisPool $redisPool,
        private readonly Metrics $metrics,
    ) {
        // Empty Constructor
    }

    /**
     * Handles Metrics.
     *
     * @param mixed ...$arguments Arguments for metrics, expected to be [level, data]
     *
     * @return mixed Always returns true on successful metrics
     */
    public function handle(string $id, mixed ...$arguments): mixed
    {
        [$method, $path, $status, $dur] = $arguments;

        try {
            $redis = $this->redisPool->get();

            $collectorRegistry = $this->metrics
                ->getCollectorRegistry($redis);
            $counter   = $collectorRegistry->getOrRegisterCounter('http_requests_total', 'Requests', 'Total HTTP requests', ['method', 'path', 'status']);
            $histogram = $collectorRegistry->getOrRegisterHistogram('http_request_seconds', 'Latency', 'HTTP request latency', ['method', 'path']);

            $counter->inc([$method, $path, (string) $status]);
            $histogram->observe($dur, [$method, $path]);
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }

        return true;
    }
}
