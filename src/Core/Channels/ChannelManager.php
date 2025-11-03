<?php

/**
 * src/Core/Channels/ChannelManager.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Channels
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-11-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Channels/ChannelManager.php
 */
declare(strict_types=1);

namespace App\Core\Channels;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Http\Server;

/**
 * Manages a single in-memory channel per worker.
 * Lifecycle:
 * - Created in WorkerStartHandler
 * - Consumed in coroutine
 * - Cleaned up in WorkerStopHandler
 *
 * @category  Core
 * @package   App\Core\Channels
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-11-02
 */
final class ChannelManager
{
    public const TAG = 'ChannelManager';

    private readonly Channel $channel;

    private bool $running = false;

    public function __construct(
        private readonly Server $server,
        private readonly int $workerId,
        int $capacity = 1024
    ) {
        $this->channel = new Channel($capacity);
    }

    /**
     * Start consuming tasks in coroutine context.
     *
     * @param callable $handler  Function to process each task
     */
    public function startConsumer(callable $handler): void
    {
        if ($this->running) {
            logDebug(self::TAG, sprintf(
                "[Worker %d] skip: %s\n",
                $this->workerId,
                'Already runninng'
            ));
            return;
        }

        $this->running = true;

        go(function () use ($handler): void {
            // Assign random sleep range per worker to avoid synchronization
            $baseSleep   = 0.1; // minimum base sleep in seconds
            $randRange   = 0.3; // add up to +0.3s jitter
            $workerSleep = $baseSleep + random_int(0, (int) ($randRange * 1000)) / 1000;

            logDebug(self::TAG, sprintf(
                "[Worker %d] Random sleep interval: %.3fs\n",
                $this->workerId,
                $workerSleep
            ));

            while ($this->running) {
                $task = $this->channel->pop(1.0); // wait max 1 sec

                if ($this->channel->length() > 0) {
                    logDebug(self::TAG, sprintf(
                        "[Worker %d] TaskChannel length: %s\n",
                        $this->workerId,
                        $this->channel->length()
                    ));
                }

                if ($task === false) {
                    // Add jitter here too for idle workers
                    $workerSleep = min($workerSleep * 1.5, 2.0); // exponential backoff up to 2s
                    Coroutine::sleep($workerSleep);
                    continue;
                }

                try {
                    $handler($this->workerId, $task);
                } catch (\Throwable $e) {
                    logDebug(self::TAG, sprintf(
                        "[Worker %d] Task error: %s\n",
                        $this->workerId,
                        $e->getMessage()
                    ));
                }

                // Add jitter here too for idle workers
                $workerSleep = $baseSleep + random_int(0, (int) ($randRange * 1000)) / 1000;
                Coroutine::sleep($workerSleep);
            }
        });
    }

    /**
     * Push task into channel
     * @param array<string, mixed> $task Task
     */
    public function push(array $task): int|bool
    {
        if (!$this->running) {
            logDebug(self::TAG, sprintf("[Worker %d] Channel not running\n", $this->workerId));
            return false;
        }

        if ($this->channel->isFull() === true) {
            logDebug(self::TAG, sprintf("[Worker %d] Channel is full\n", $this->workerId));
            // Offload the task to Task worker
            return $this->server->task($task, -1); // Push and forget
        }

        return $this->channel->push($task);
    }

    /**
     * Stop consumer and close channel.
     */
    public function stop(): void
    {
        $this->running = false;
        $this->channel->close();

        logDebug(self::TAG, sprintf("[Worker %d] Channel stopped and closed\n", $this->workerId));
    }
}
