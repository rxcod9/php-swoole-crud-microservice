<?php

/**
 * src/Core/Events/TaskFinishHandler.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/TaskFinishHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use Swoole\Http\Server;

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
final class TaskFinishHandler
{
    public const TAG = 'TaskFinishHandler';

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function __invoke(Server $server, int $taskId, mixed $data): bool
    {
        $class = $data['class'] ?? 'unknown';
        $error = $data['error'] ?? null;

        if ($error !== null) {
            logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('[Task Failed] %s -> %s%s', $class, $error, PHP_EOL));
            return false;
        }

        // logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Task %d finished:  %s -> {', $taskId, $class) . json_encode($data) . '}' . PHP_EOL);
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Task %d finished:  %s -> {', $taskId, $class) . '}' . PHP_EOL);

        // @TODO call TaskListener for chaining
        return true;
    }
}
