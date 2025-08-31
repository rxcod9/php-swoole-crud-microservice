<?php

namespace App\Core;

use Swoole\WebSocket\Server;

final class WebSocketServer
{
  public function __construct(private string $host = '0.0.0.0', private int $port = 9502) {}
  public function start(): void
  {
    $ws = new Server($this->host, $this->port);
    $ws->set(['worker_num' => 1, 'task_worker_num' => 1, 'task_enable_coroutine' => true]);
    $ws->on('start', fn() => print("WS listening on {$this->host}:{$this->port}\n"));
    $ws->on('open', fn($s, $req) => $s->push($req->fd, json_encode(['hello' => 'ws'])));
    $ws->on('message', fn($s, $frame) => $s->push($frame->fd, strtoupper($frame->data)));
    $ws->on('task', fn($s, $task) => true);
    $ws->start();
  }
}
