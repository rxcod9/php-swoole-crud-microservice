<?php

/**
 * src/Core/Events/WorkerReadyChecker.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core\Events
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/WorkerReadyChecker.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Contexts\AppContext;
use App\Exceptions\WorkerNotReadyException;

/**
 * Class WorkerReadyChecker
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category Core
 * @package  App\Core\Events
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class WorkerReadyChecker
{
    public function wait(int $timeoutMs = 2000): void
    {
        $waited = 0;
        while (!AppContext::isWorkerReady() && $waited < $timeoutMs) {
            error_log('Waiting for worker to be ready...' . PHP_EOL);
            usleep(10000);
            $waited += 10;
        }

        if ($waited >= $timeoutMs) {
            throw new WorkerNotReadyException('Worker not ready');
        }
    }
}
