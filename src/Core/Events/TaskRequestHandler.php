<?php

/**
 * src/Core/Events/TaskRequestHandler.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/TaskRequestHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Channels\ChannelManager;
use App\Core\Container;
use App\Core\Events\TaskRequestDispatcher as Dispatcher;
use App\Core\Metrics;
use App\Tasks\HttpMetricsTask;
use App\Tasks\TaskMetricsTask;
use Swoole\Http\Server;
use Swoole\Server\Task;
use Throwable;

/**
 * Handles incoming HTTP requests, including routing, middleware, and response generation.
 * Also manages CORS headers and preflight requests.
 * Binds request-scoped dependencies like connection pools.
 * Logs request details asynchronously.
 * Provides health check endpoints.
 * Ensures worker readiness before processing requests.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://your-repo-link
 */
final readonly class TaskRequestHandler
{
    public const TAG = 'TaskRequestHandler';

    public function __construct(
        private Container $container
    ) {
        // Empty Constructor
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @SuppressWarnings("PHPMD.UnusedLocalVariable")
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     * @SuppressWarnings("PHPMD.LongVariable")
     */
    public function __invoke(Server $server, Task $task): bool
    {
        try {
            $workerReadyChecker = new WorkerReadyChecker();
            $workerReadyChecker->wait();

            $start = microtime(true);

            $taskRequestDispatcher = new Dispatcher($this->container);
            $status                = $taskRequestDispatcher->dispatch($task);

            $this->saveMetrics($start, $task->data, $status);

            return $status;
        } catch (Throwable $throwable) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__ . '][Exception', $throwable->getMessage()); // logged internally
        }

        return false;
    }

    /**
     * Save Metrics
     * @param mixed $status
     */
    public function saveMetrics(float $start, array $data, bool $status): void
    {
        // Metrics and async logging
        $dur   = microtime(true) - $start;
        $class = $data['class'] ?? null;
        $id    = $data['id'] ?? bin2hex(random_bytes(8));

        if ($class === TaskMetricsTask::class || $class === HttpMetricsTask::class) {
            // Skip recursion
            return;
        }

        $channelManager = $this->container->get(ChannelManager::class);
        if (!$channelManager) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[%s] => %s', __FUNCTION__, 'channelManager not present.'));
            return;
        }

        $start = microtime(true);
        // Dispatch async user creation task
        $result = $channelManager->push([
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
