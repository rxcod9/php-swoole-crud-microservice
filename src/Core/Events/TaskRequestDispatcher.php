<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\TaskDispatcher;
use Swoole\Server\Task;

final class TaskRequestDispatcher
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
