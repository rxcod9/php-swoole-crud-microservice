<?php

/**
 * src/Core/Events/ChannelTaskRequestDispatcher.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/ChannelTaskRequestDispatcher.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Channels\ChannelManager;
use App\Core\ChannelTaskDispatcher;
use App\Tasks\HttpMetricsTask;
use App\Tasks\TaskMetricsTask;

/**
 * Class ChannelTaskRequestDispatcher
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class ChannelTaskRequestDispatcher
{
    public const TAG = 'TaskRequestHandler';

    public function __construct(
        private ChannelManager $channelManager,
        private ChannelTaskDispatcher $channelTaskDispatcher
    ) {
        // Empty Constructor
    }

    /**
     * @SuppressWarnings("PHPMD.LongVariable")
     * @param array<string, mixed> $task Task
     */
    public function dispatch(array $task): mixed
    {
        $start     = microtime(true);
        $class     = $task['class'] ?? null;
        $id        = $task['id'] ?? bin2hex(random_bytes(8));
        $arguments = $task['arguments'] ?? null;

        $status = $this->channelTaskDispatcher->dispatch(
            $class,
            $id,
            $arguments
        );

        $this->saveMetrics($start, $task, $status);

        return $status;
    }

    /**
     * Save Metrics
     *
     * @param array<string, mixed> $data
     */
    public function saveMetrics(float $start, array $data, bool $status): void
    {
        // Metrics and async logging
        $dur   = microtime(true) - $start;
        $class = $data['class'] ?? null;
        $id    = $data['id'] ?? bin2hex(random_bytes(8));

        logDebug(
            self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__,
            'id #' . $id . ' ' . var_export($class, true) . ' === ' . var_export(TaskMetricsTask::class, true) . ' || ' . var_export($class, true) . ' === ' . var_export(HttpMetricsTask::class, true) . ''
        );
        if ($class === TaskMetricsTask::class || $class === HttpMetricsTask::class) {
            // Skip recursion
            return;
        }

        $start = microtime(true);
        // Dispatch async user creation task
        $result = $this->channelManager->push([
            'class'     => TaskMetricsTask::class,
            'id'        => $id,
            'arguments' => [$class, $status, $dur],
        ]);

        $timeMs = round((microtime(true) - $start) * 1000, 3);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push called'));

        // check if unable to push
        if ($result === false) {
            // Log warning
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => Time: %f ms %s', __FUNCTION__, $timeMs, 'channelManager->push failed'));
        }
    }
}
