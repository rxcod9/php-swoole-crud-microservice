<?php

namespace App\Core\Events;

use App\Core\Contexts\AppContext;
use App\Core\Pools\MySQLPool;
use App\Core\Pools\RedisPool;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\Timer;

/**
 * Handles WorkerStart event
 */
final class WorkerStartHandler
{
    /**
     * @var array<int, array<int>> Keeps track of timers per worker
     */
    private array $workerTimers = [];

    public function __construct(
        private array $config,
        private Table $table
    ) {
        //
    }

    public function __invoke(Server $server, int $workerId)
    {
        $pid = posix_getpid();
        echo "Worker {$workerId} started with {$pid}\n";

        // Write initial health
        $this->table->set($workerId, [
            "pid" => $pid,
            "first_heartbeat" => time(),
            "last_heartbeat" => time()
        ]);

        // Initialize pools per worker
        $dbConf = $this->config['db']['mysql'];
        $server->mysql = new MySQLPool($dbConf, $dbConf['pool']['min'] ?? 5, $dbConf['pool']['max'] ?? 200);
        $redisConf = $this->config['redis'];
        $server->redis = new RedisPool($redisConf, $redisConf['pool']['min'], $redisConf['pool']['max'] ?? 200);
        AppContext::setWorkerReady(true);
        echo "Worker {$workerId} started with {$pid} ready\n";

        // Heartbeat every 5s
        $timerId = Timer::tick(5000, function ($timerId) use ($server, $workerId, $pid) {
            $this->tick($timerId, $server, $workerId, $pid);
        });

        $this->workerTimers[$workerId] = [$timerId]; // store timers per worker
    }

    /**
     * Clear all timers for a given worker
     */
    public function clearTimers(int $workerId): void
    {
        if (!isset($this->workerTimers[$workerId])) return;

        foreach ($this->workerTimers[$workerId] as $t) {
            Timer::clear($t);
        }

        unset($this->workerTimers[$workerId]);
    }

    /**
     * Handle periodic timer event (heartbeat, autoscale, etc.)
     * Updates the shared memory table with current stats.
     */
    private function tick(
        $timerId,
        $server,
        $workerId,
        $pid
    ) {
        // echo "Timer {$timerId} heartbeat from Worker {$workerId} (PID {$pid})\n";
        try {
            $server->mysql->autoScale();
        } catch (\Throwable $e) {
            echo "[Worker {$workerId}] MySQL autoScale error: " . $e->getMessage() . "\n";
        }

        try {
            $server->redis->autoScale();
        } catch (\Throwable $e) {
            echo "[Worker {$workerId}] Redis autoScale error: " . $e->getMessage() . "\n";
        }

        $mysqlStats     = $server->mysql->stats();
        $mysqlCapacity  = $mysqlStats['capacity'];
        $mysqlAvailable = $mysqlStats['available'];
        $mysqlCreated   = $mysqlStats['created'];
        $mysqlInUse     = $mysqlStats['in_use'];

        $redisStats     = $server->redis->stats();
        $redisCapacity  = $redisStats['capacity'];
        $redisAvailable = $redisStats['available'];
        $redisCreated   = $redisStats['created'];
        $redisInUse     = $redisStats['in_use'];

        $row = $this->table->get($workerId) ?? [];
        $this->table->set($workerId, [
            "pid"               => $pid,
            "first_heartbeat"   => $row['first_heartbeat'] ?? time(),
            "last_heartbeat"    => time(),
            "mysql_capacity"    => $mysqlCapacity,
            "mysql_available"   => $mysqlAvailable,
            "mysql_created"     => $mysqlCreated,
            "mysql_in_use"      => $mysqlInUse,
            "redis_capacity"    => $redisCapacity,
            "redis_available"   => $redisAvailable,
            "redis_created"     => $redisCreated,
            "redis_in_use"      => $redisInUse,
        ]);
    }
}
