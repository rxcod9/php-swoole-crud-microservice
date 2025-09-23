<?php

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Metrics;
use Swoole\Http\Server;
use Swoole\Server\Task;
use Swoole\Table;

/**
 * Handles incoming HTTP requests, including routing, middleware, and response generation.
 * Also manages CORS headers and preflight requests.
 * Binds request-scoped dependencies like DbContext and connection pools.
 * Logs request details asynchronously.
 * Provides health check endpoints.
 * Ensures worker readiness before processing requests.
 * 
 * @package App\Core\Events
 * @version 1.0.0
 * @since 1.0.0
 * @author Your Name
 * @license MIT
 * @link https://your-repo-link
 */
final class TaskRequestHandler
{
    public function __construct(
        private Table $table,
        private Container $container
    ) {
        //
    }

    public function __invoke(Server $server, Task $task): bool
    {
        try {
            (new WorkerReadyChecker())->wait();

            $taskId = bin2hex(random_bytes(8));
            $start = microtime(true);

            (new PoolBinder())->bind($server, $this->container);

            // Metrics collection
            $reg = Metrics::reg();
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

            $status = (new TaskRequestDispatcher($this->container))->dispatch($task);
            // echo __CLASS__ . "Payload: " . PHP_EOL;
            // var_dump($status);

            // Metrics and async logging
            $dur = microtime(true) - $start;

            $data = $task->data;
            $class = $data['class'] ?? null;
            $counter->inc([$class, (string)$status]);
            $hist->observe($dur, [$class]);

            // (new TaskRequestLogger())->log(
            //     $server,
            //     $task,
            //     [
            //         'id' => $taskId,
            //         'path' => $path,
            //         'status' => $status,
            //         'dur_ms' => (int)round($dur * 1000)
            //     ]
            // );

            return $status;
        } catch (\Throwable $e) {
            echo $e->getMessage();
            // (new TaskRequestLogger())->log(
            //     $server,
            //     $task,
            //     [
            //         'error' => $e->getMessage()
            //     ]
            // );
        }

        return false;
    }
}
