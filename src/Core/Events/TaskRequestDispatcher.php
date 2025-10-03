<?php

/**
 * src/Core/Events/TaskRequestDispatcher.php
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
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/TaskRequestDispatcher.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\TaskDispatcher;
use Swoole\Server\Task;

/**
 * Class TaskRequestDispatcher
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
final readonly class TaskRequestDispatcher
{
    public function __construct(
        private Container $container
    ) {
        //
    }

    public function dispatch(Task $task): bool
    {
        $data      = $task->data;
        $class     = $data['class'] ?? null;
        $arguments = $data['arguments'] ?? null;
        return new TaskDispatcher($this->container)->dispatch(
            $class,
            $arguments,
            $task
        );
    }
}
