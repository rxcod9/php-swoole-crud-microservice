<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;
use App\Exceptions\DatabasePoolNotInitializedException;

final class PoolBinder
{
    public function __construct(
        private PDOPool &$mysql,
        private RedisPool &$redis
    ) {
        //
    }

    public function bind(Container $container): void
    {
        if (!isset($this->mysql) || !isset($this->redis)) {
            throw new DatabasePoolNotInitializedException('Database pools not initialized');
        }

        $container->bind(PDOPool::class, fn () => $this->mysql);
        $container->bind(RedisPool::class, fn () => $this->redis);
    }
}
