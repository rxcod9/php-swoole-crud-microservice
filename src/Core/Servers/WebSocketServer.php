<?php

namespace App\Core\Servers;

use Swoole\WebSocket\Server;

/**
 * WebSocketServer
 *
 * A simple WebSocket server using Swoole.
 *
 * @package App\Core
 */
final class WebSocketServer
{
    /**
     * The host address to bind the WebSocket server.
     *
     * @var string
     */
    private string $host;

    /**
     * The port number to bind the WebSocket server.
     *
     * @var int
     */
    private int $port;

    /**
     * WebSocketServer constructor.
     *
     * @param string $host The host address to bind (default: 0.0.0.0)
     * @param int $port The port number to bind (default: 9502)
     */
    public function __construct(string $host = '0.0.0.0', int $port = 9502)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Start the WebSocket server.
     *
     * @return void
     */
    public function start(): void
    {
        $ws = new Server($this->host, $this->port);
        $ws->set([
            'worker_num' => 1,
            'task_worker_num' => 1,
            'task_enable_coroutine' => true
        ]);

        // Event: Server start
        $ws->on('start', function () {
            print("WS listening on {$this->host}:{$this->port}\n");
        });

        // Event: New connection opened
        $ws->on('open', function ($server, $request) {
            $server->push($request->fd, json_encode(['hello' => 'ws']));
        });

        // Event: Message received
        $ws->on('message', function ($server, $frame) {
            $server->push($frame->fd, strtoupper($frame->data));
        });

        // Event: Task received
        $ws->on('task', function ($server, $task) {
            return true;
        });

        $ws->start();
    }
}
