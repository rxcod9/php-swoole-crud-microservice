<?php

/**
 * src/Core/Servers/WebSocketServer.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/WebSocketServer.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use Swoole\WebSocket\Server;

/**
 * WebSocketServer
 * A simple WebSocket server using Swoole.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class WebSocketServer
{
    /**
     * WebSocketServer constructor.
     *
     * @param string $host The host address to bind (default: 0.0.0.0)
     * @param int    $port The port number to bind (default: 9502)
     */
    public function __construct(private string $host = '0.0.0.0', private int $port = 9502)
    {
        // Empty constructor
    }

    /**
     * Start the WebSocket server.
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function start(): void
    {
        $ws = new Server($this->host, $this->port);
        $ws->set([
            'worker_num'            => 1,
            'task_worker_num'       => 1,
            'task_enable_coroutine' => true,
        ]);

        // Event: Server start
        $ws->on('start', function (): void {
            print(sprintf('WS listening on %s:%d%s', $this->host, $this->port, PHP_EOL));
        });

        // Event: New connection opened
        $ws->on('open', function ($server, $request): void {
            $server->push($request->fd, json_encode(['hello' => 'ws']));
        });

        // Event: Message received
        $ws->on('message', function ($server, $frame): void {
            $server->push($frame->fd, strtoupper($frame->data));
        });

        // Event: Task received
        /**
         * @SuppressWarnings("PHPMD.UnusedFormalParameter")
         */
        $ws->on('task', function ($server, $task): true {
            return true;
        });

        $ws->start();
    }
}
