<?php

/**
 * src/Tasks/TaskMetricsTask.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/TaskMetricsTask.php
 */
declare(strict_types=1);

namespace App\Tasks;

use App\Core\Metrics;
use App\Core\Pools\RedisPool;

/**
 * TaskMetricsTask handles logging of request payloads to a file.
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
final class TaskMetricsTask implements TaskInterface
{
    public const TAG = 'TaskMetricsTask';

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
        [$class, $status, $dur] = $arguments;

        try {
            $redis = $this->redisPool->get();

            $collectorRegistry = $this->metrics
                ->getCollectorRegistry($redis);
            $counter   = $collectorRegistry->getOrRegisterCounter('task_requests_total', 'Tasks', 'Total Task requests', ['class', 'status']);
            $histogram = $collectorRegistry->getOrRegisterHistogram('task_request_seconds', 'Latency', 'Task request latency', ['class']);

            $counter->inc([$class, (string) $status]);
            $histogram->observe($dur, [$class]);
        } finally {
            if (isset($redis)) {
                $this->redisPool->put($redis);
            }
        }

        return true;
    }
}
