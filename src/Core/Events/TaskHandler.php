<?php

/**
 * src/Core/Events/TaskHandler.php
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
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/TaskHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Tasks\LoggerTask;
use Swoole\Http\Server;
use Swoole\Server\Task;

/**
 * Class TaskHandler
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
final class TaskHandler
{
    public function __invoke(Server $server, Task $task): bool
    {
        $data = $task->data;
        if (($data['type'] ?? '') === 'log') {
            LoggerTask::handle($data['data']);
        }

        return true;
    }
}
