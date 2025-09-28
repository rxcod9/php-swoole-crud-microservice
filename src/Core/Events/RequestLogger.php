<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Tasks\LogTask;
use Swoole\Http\Request;
use Swoole\Http\Server;

final class RequestLogger
{
    public function log($level, Server $server, Request $req, array $data): void
    {
        $server->task([
            'class'     => LogTask::class,
            'arguments' => [$level, $data],
        ]);
    }
}
