<?php

/**
 * src/Core/Events/WorkerStartHandler.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/WorkerStartHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Channels\ChannelConsumer;
use App\Core\Channels\ChannelManager;
use App\Core\ChannelTaskDispatcher;
use App\Core\Container;
use App\Core\Contexts\AppContext;
use App\Core\Pools\PoolFacade;
use App\Exceptions\CacheSetException;
use Carbon\Carbon;
use Swoole\Http\Server;
use Swoole\Table;
use Swoole\Timer;

/**
 * Handles WorkerStart event
 * Responsibilities:
 * - Initialize worker heartbeat in shared memory
 * - Start periodic timers for heartbeat and pool autoscaling
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class WorkerStartHandler
{
    public const TAG = 'WorkerStartHandler';

    /** @var array<int, array<int>> Keeps track of timers per worker */
    private array $workerTimers = [];

    /** @var array<int, ChannelManager> Keeps track of channel managers per worker */
    private array $channels = [];

    public function __construct(
        private readonly Container $container,
        private readonly Table $table,
        private readonly PoolFacade $poolFacade
    ) {
        // Empty Constructor
    }

    /**
     * Entry point for worker start event
     *
     *
     * @throws CacheSetException
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function __invoke(Server $server, int $workerId): void
    {
        $pid = posix_getpid();
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf('Worker %d started with %d%s', $workerId, $pid, PHP_EOL));

        $this->initializeWorkerRow($workerId, $pid);
        AppContext::setWorkerReady(true);

        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, "Worker {$workerId} with PID {$pid} ready\n");

        $this->start($server, $workerId, $pid);
    }

    private function initializeWorkerRow(int $workerId, int $pid): void
    {
        $success = $this->table->set((string) $workerId, [
            'pid'             => $pid,
            'first_heartbeat' => Carbon::now()->getTimestamp(),
            'last_heartbeat'  => Carbon::now()->getTimestamp(),
        ]);

        if (!$success) {
            throw new CacheSetException('Unable to set initial worker cache');
        }
    }

    private function start(Server $server, int $workerId, int $pid): void
    {
        $this->startTimers($workerId, $pid);
        $this->startChannels($server, $workerId);
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function startTimers(int $workerId, int $pid): void
    {
        $timerId                       = Timer::tick(5000, fn ($tid) => $this->handleTick($tid, $workerId, $pid));
        $this->workerTimers[$workerId] = [$timerId];
    }

    private function startChannels(Server $server, int $workerId): void
    {
        // initialize per-worker channel
        $channelManager            = new ChannelManager($server, $workerId);
        $this->channels[$workerId] = $channelManager;

        // start consumer
        $channelConsumer = new ChannelConsumer($channelManager, new ChannelTaskRequestDispatcher(
            $channelManager,
            $this->container->get(ChannelTaskDispatcher::class)
        ));
        $channelConsumer->start();

        // Register per-worker channel manager and consumer in container
        $this->container->singleton(ChannelManager::class, fn (): \App\Core\Channels\ChannelManager => $channelManager);
        $this->container->singleton(ChannelConsumer::class, fn (): \App\Core\Channels\ChannelConsumer => $channelConsumer);
    }

    public function clear(int $workerId): void
    {
        $this->clearTimers($workerId);
        $this->clearChannels($workerId);
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    private function clearTimers(int $workerId): void
    {
        if (!isset($this->workerTimers[$workerId])) {
            return;
        }

        foreach ($this->workerTimers[$workerId] as $timerId) {
            Timer::clear($timerId);
        }

        unset($this->workerTimers[$workerId]);
    }

    private function clearChannels(int $workerId): void
    {
        if (isset($this->channels[$workerId])) {
            $this->channels[$workerId]->stop();
            unset($this->channels[$workerId]);
        }
    }

    private function handleTick(mixed $timerId, int $workerId, int $pid): void
    {
        // logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, "Timer {$timerId} heartbeat from Worker {$workerId} (PID {$pid})\n");

        $this->updateWorkerStats($workerId, $pid, $timerId);
        $this->poolFacade->autoScale($workerId);
        $this->poolFacade->runGC();
    }

    private function updateWorkerStats(int $workerId, int $pid, mixed $timerId): void
    {
        $stats = $this->poolFacade->getStats();
        $pdo   = $stats['pdo'];
        $redis = $stats['redis'];

        $row = $this->table->get((string) $workerId) ?? [];

        $success = $this->table->set((string) $workerId, [
            'pid'             => $pid,
            'timer_id'        => $timerId,
            'first_heartbeat' => $row['first_heartbeat'] ?? Carbon::now()->getTimestamp(),
            'last_heartbeat'  => Carbon::now()->getTimestamp(),
            'mysql_capacity'  => $pdo['capacity'],
            'mysql_available' => $pdo['available'],
            'mysql_created'   => $pdo['created'],
            'mysql_in_use'    => $pdo['in_use'],
            'redis_capacity'  => $redis['capacity'],
            'redis_available' => $redis['available'],
            'redis_created'   => $redis['created'],
            'redis_in_use'    => $redis['in_use'],
        ]);

        if (!$success) {
            throw new CacheSetException('Unable to update worker cache');
        }
    }
}
