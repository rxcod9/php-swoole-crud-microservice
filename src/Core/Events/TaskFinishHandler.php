<?php

namespace App\Core\Events;

use Swoole\Http\Server;

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
final class TaskFinishHandler
{
    public function __invoke(Server $server, int $taskId, $data): bool
    {
        $class = $data['class'] ?? 'unknown';
        $arguments  = $data['arguments'] ?? [];
        $result  = $data['result'] ?? null;
        $error   = $data['error'] ?? null;

        if ($error) {
            echo "[Task Failed] {$class} -> {$error}\n";
            return false;
        }

        echo "Task {$taskId} finished:  {$class} -> {" . json_encode($data) .  "}" . PHP_EOL;

        // @TODO call TaskListener for chaining
        return true;
    }
}
