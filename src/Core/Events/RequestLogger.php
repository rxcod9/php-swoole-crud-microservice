<?php

namespace App\Core\Events;

use App\Tasks\LogTask;
use Swoole\Http\Request;
use Swoole\Http\Server;

final class RequestLogger
{
    public function log(Server $server, Request $req, array $data): void
    {
        // $server->task([
        //     'type' => 'log',
        //     'data' => $data
        // ]);
        $server->task([
            'class' => LogTask::class,
            'arguments' => $data
        ]);
    }
}
