<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\MySQLPool;
use App\Core\RedisPool;

final class HealthController extends Controller
{
    private MySQLPool $mysql;
    private RedisPool $redis;

    public function __construct(
        MySQLPool $mysql,
        RedisPool $redis
    ) {
        $this->mysql = $mysql;
        $this->redis = $redis;
    }

    public function check(): array
    {
        // $redis = new \Swoole\Coroutine\Redis();
        // $redis->connect('redis', 6379);

        // $pong = $this->redis->command('PING');

        return $this->json([
            'ok' => true,
            'mysql'  => $this->mysql->stats(),
            'redis'  => $this->redis->stats(),
            'uptime' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
            'ts' => time(),
            // 'ping' => var_export($pong, true),
        ]);
    }
}
