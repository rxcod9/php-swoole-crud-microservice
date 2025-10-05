<?php

/**
 * src/Core/Events/WorkerStartHandler.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/WorkerStartHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Contexts\AppContext;
use App\Core\Pools\PDOPool;
use App\Core\Pools\RedisPool;
use App\Exceptions\CacheSetException;
use App\Services\Cache\CacheService;
use Carbon\Carbon;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\Timer;
use Throwable;

/**
 * Handles WorkerStart event
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class WorkerStartHandler
{
    /** @var array<int, array<int>> Keeps track of timers per worker */
    private array $workerTimers = [];

    public function __construct(
        private readonly Table $table,
        private readonly CacheService $cacheService,
        private PDOPool &$pdoPool,
        private RedisPool &$redisPool
    ) {
        //
    }

    public function __invoke(Server $server, int $workerId): void
    {
        $pid = posix_getpid();
        error_log(sprintf('Worker %d started with %d%s', $workerId, $pid, PHP_EOL));

        // Write initial health
        $success = $this->table->set((string) $workerId, [
            'pid'             => $pid,
            'first_heartbeat' => Carbon::now()->getTimestamp(),
            'last_heartbeat'  => Carbon::now()->getTimestamp(),
        ]);
        if (!$success) {
            throw new CacheSetException('Unable to set Cache');
        }

        AppContext::setWorkerReady(true);
        error_log("Worker {$workerId} started with {$pid} ready\n");

        $this->startTimers($server, $workerId, $pid);
    }

    private function startTimers(Server $server, int $workerId, int $pid): void
    {
        // Heartbeat every 5s
        $timerId = Timer::tick(5000, function ($timerId) use ($server, $workerId, $pid): void {
            $this->tick($timerId, $workerId, $pid);
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
        mixed $timerId,
        int $workerId,
        int $pid
    ): void {
        error_log("Timer {$timerId} heartbeat from Worker {$workerId} (PID {$pid})\n");

        $mysqlStats     = $this->pdoPool->stats();
        $mysqlCapacity  = $mysqlStats['capacity'];
        $mysqlAvailable = $mysqlStats['available'];
        $mysqlCreated   = $mysqlStats['created'];
        $mysqlInUse     = $mysqlStats['in_use'];

        $redisStats     = $this->redisPool->stats();
        $redisCapacity  = $redisStats['capacity'];
        $redisAvailable = $redisStats['available'];
        $redisCreated   = $redisStats['created'];
        $redisInUse     = $redisStats['in_use'];

        $row     = $this->table->get((string) $workerId) ?? [];
        $success = $this->table->set((string) $workerId, [
            'pid'             => $pid,
            'timer_id'        => $timerId,
            'first_heartbeat' => $row['first_heartbeat'] ?? Carbon::now()->getTimestamp(),
            'last_heartbeat'  => Carbon::now()->getTimestamp(),
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
            $this->pdoPool->autoScale();
        } catch (Throwable $throwable) {
            error_log(sprintf('[Worker %d] MySQL autoScale error: ', $workerId) . $throwable->getMessage() . "\n");
        }

        try {
            $this->redisPool->autoScale();
        } catch (Throwable $throwable) {
            error_log(sprintf('[Worker %d] Redis autoScale error: ', $workerId) . $throwable->getMessage() . "\n");
        }

        $this->cacheService->gc(); // run garbage collection on cache table
    }
}
