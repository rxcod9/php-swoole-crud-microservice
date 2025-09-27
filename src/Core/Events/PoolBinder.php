<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;
use RuntimeException;
use Swoole\Http\Server;

final class PoolBinder
{
    public function bind(Server $server, Container $container): void
    {
        if (!isset($server->mysql) || !isset($server->redis)) {
            throw new RuntimeException('Database pools not initialized');
        }

        $container->bind(PDOPool::class, fn () => $server->mysql);
        $container->bind(RedisPool::class, fn () => $server->redis);
    }
}
