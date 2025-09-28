<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Contexts\AppContext;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;
use App\Exceptions\CacheSetException;
use App\Services\Cache\CacheService;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\Timer;
use Throwable;

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
        private Table $table,
        private CacheService $cacheService,
        private PDOPool &$mysql,
        private RedisPool &$redis
    ) {
        //
    }

    public function __invoke(Server $server, int $workerId)
    {
        $pid = posix_getpid();
        echo "Worker {$workerId} started with {$pid}\n";

        // Write initial health
        $success = $this->table->set((string) $workerId, [
            'pid'             => $pid,
            'first_heartbeat' => time(),
            'last_heartbeat'  => time(),
        ]);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        AppContext::setWorkerReady(true);
        echo "Worker {$workerId} started with {$pid} ready\n";

        $this->startTimers($server, $workerId, $pid);
    }

    private function startTimers(Server $server, int $workerId, int $pid): void
    {
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
        if (!isset($this->workerTimers[$workerId])) {
            return;
        }

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
        error_log("Timer {$timerId} heartbeat from Worker {$workerId} (PID {$pid})\n");

        $mysqlStats     = $this->mysql->stats();
        $mysqlCapacity  = $mysqlStats['capacity'];
        $mysqlAvailable = $mysqlStats['available'];
        $mysqlCreated   = $mysqlStats['created'];
        $mysqlInUse     = $mysqlStats['in_use'];

        $redisStats     = $this->redis->stats();
        $redisCapacity  = $redisStats['capacity'];
        $redisAvailable = $redisStats['available'];
        $redisCreated   = $redisStats['created'];
        $redisInUse     = $redisStats['in_use'];

        $row     = $this->table->get((string) $workerId) ?? [];
        $success = $this->table->set((string) $workerId, [
            'pid'             => $pid,
            'timer_id'        => $timerId,
            'first_heartbeat' => $row['first_heartbeat'] ?? time(),
            'last_heartbeat'  => time(),
            'mysql_capacity'  => $mysqlCapacity,
            'mysql_available' => $mysqlAvailable,
            'mysql_created'   => $mysqlCreated,
            'mysql_in_use'    => $mysqlInUse,
            'redis_capacity'  => $redisCapacity,
            'redis_available' => $redisAvailable,
            'redis_created'   => $redisCreated,
            'redis_in_use'    => $redisInUse,
        ]);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        try {
            $this->mysql->autoScale();
        } catch (Throwable $e) {
            error_log("[Worker {$workerId}] MySQL autoScale error: " . $e->getMessage() . "\n");
        }

        try {
            $this->redis->autoScale();
        } catch (Throwable $e) {
            error_log("[Worker {$workerId}] Redis autoScale error: " . $e->getMessage() . "\n");
        }

        $this->cacheService->gc(); // run garbage collection on cache table
    }
}
