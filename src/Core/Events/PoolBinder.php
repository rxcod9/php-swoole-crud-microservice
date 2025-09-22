<?php

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Pools\MySQLPool;
use App\Core\Pools\RedisPool;
use Swoole\Http\Server;

final class PoolBinder
{
    public function bind(Server $server, Container $container): void
    {
        if (!isset($server->mysql) || !isset($server->redis)) {
            throw new \RuntimeException("Database pools not initialized");
        }

        $container->bind(MySQLPool::class, fn() => $server->mysql);
        $container->bind(RedisPool::class, fn() => $server->redis);
    }
}
