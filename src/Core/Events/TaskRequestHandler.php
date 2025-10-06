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

use App\Core\Container;
use App\Core\Events\TaskRequestDispatcher as Dispatcher;
use App\Core\Metrics;
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
    public function __construct(
        private Container $container
    ) {
        //
    }

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Server $server, Task $task): bool
    {
        try {
            $workerReadyChecker = new WorkerReadyChecker();
            $workerReadyChecker->wait();

            $taskId = bin2hex(random_bytes(8));
            $start  = microtime(true);

            // Metrics collection
            $reg     = Metrics::reg();
            $counter = $reg->getOrRegisterCounter(
                'task_requests_total',
                'Tasks',
                'Total Task requests',
                ['class', 'status']
            );
            $hist = $reg->getOrRegisterHistogram(
                'task_request_seconds',
                'Latency',
                'HTTP request latency',
                ['class']
            );

            $taskRequestDispatcher = new Dispatcher($this->container);
            $status                = $taskRequestDispatcher->dispatch($task);
            // Metrics and async logging
            $dur = microtime(true) - $start;

            $data  = $task->data;
            $class = $data['class'] ?? null;
            $counter->inc([$class, (string)$status]);
            $hist->observe($dur, [$class]);

            return $status;
        } catch (Throwable $throwable) {
            error_log('Exception: ' . $throwable->getMessage()); // logged internally
        }

        return false;
    }
}
