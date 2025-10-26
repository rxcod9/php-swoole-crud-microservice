<?php

/**
 * src/Core/Servers/ServerFactory.php
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
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/ServerFactory.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use Swoole\Http\Server;

/**
 * Class ServerFactory
 * Handles all server factory operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class ServerFactory
{
    /**
     * @param array<string, mixed> $config Config
     */
    public function create(array $config): Server
    {
        $ssl  = isset($config['server']['ssl']['enable']) && (bool) $config['server']['ssl']['enable'];
        $host = $config['server']['host'] ?? null;
        $port = $config['server']['http_port'] ?? null;

        $server = new Server(
            $host,
            $port,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP | ($ssl ? SWOOLE_SSL : 0)
        );

        if ($ssl) {
            $server->set([
                'ssl_cert_file'       => $config['server']['ssl']['cert_file'],
                'ssl_key_file'        => $config['server']['ssl']['key_file'],
                'open_http2_protocol' => true,
                'ssl_alpn_protocols'  => 'h2,http/1.1',  // critical for HTTP/2
            ]);
        }

        $server->set($config['server']['settings'] ?? []);
        $server->on('Start', fn (): int => printf("HTTP listening on %s:%s\n", $host, $port));

        return $server;
    }
}
