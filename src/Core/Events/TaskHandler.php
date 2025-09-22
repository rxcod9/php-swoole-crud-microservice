<?php

namespace App\Core\Events;

use App\Tasks\LoggerTask;
use Swoole\Http\Server;
use Swoole\Server\Task;

final class TaskHandler
{
    public function __invoke(Server $server, Task $task)
    {
        $data = $task->data;
        if (($data['type'] ?? '') === 'log') {
            LoggerTask::handle($data['data']);
        }
        return true;
    }
}
