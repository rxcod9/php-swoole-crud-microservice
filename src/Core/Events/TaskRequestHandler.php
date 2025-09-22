<?php

namespace App\Core\Events;

use App\Core\Container;
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
        // $data = $task->data;
        // if (($data['type'] ?? '') === 'log') {
        //     LoggerTask::handle($data['data']);
        // }
        // return true;

        try {
            (new WorkerReadyChecker())->wait();

            $taskId = bin2hex(random_bytes(8));
            $start = microtime(true);

            (new PoolBinder())->bind($server, $this->container);

            // // Metrics collection
            // $reg = Metrics::reg();
            // $counter = $reg->getOrRegisterCounter(
            //     'task_requests_total',
            //     'Requests',
            //     'Total HTTP requests',
            //     ['method', 'path', 'status']
            // );
            // $hist = $reg->getOrRegisterHistogram(
            //     'task_request_seconds',
            //     'Latency',
            //     'HTTP request latency',
            //     ['method', 'path']
            // );

            $response = (new TaskRequestDispatcher($this->container))->dispatch($task);
            // echo __CLASS__ . "Payload: " . PHP_EOL;
            // var_dump($response);

            // Metrics and async logging
            $dur = microtime(true) - $start;

            // $counter->inc([$task->server['request_method'], $path, (string)$status]);
            // $hist->observe($dur, [$task->server['request_method'], $path]);

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

            return $response;
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
