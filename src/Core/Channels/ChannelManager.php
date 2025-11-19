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
 * @since     2025-11-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Channels/ChannelManager.php
 */
declare(strict_types=1);

namespace App\Core\Channels;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Swoole\Http\Server;

/**
 * Batch consumer channel manager with:
 * - Exponential backoff on idle
 * - Reset backoff when tasks arrive
 * - Batch processing for lower context overhead
 * - Clean shutdown with channel close unblocking pop()
 *
 * @category  Core
 * @package   App\Core\Channels
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-11-05
 */
final class ChannelManager
{
    public const TAG = 'ChannelManager';

    /**  */
    private readonly Channel $channel;

    private bool $running = false;

    private readonly int $consumerCount;

    public function __construct(
        private readonly Server $server,
        private readonly int $workerId,
        int $capacity = 5000,
        ?int $consumerCount = null,
        private readonly int $batchSize = 10
    ) {
        $this->channel       = new Channel($capacity);
        $this->consumerCount = $consumerCount ?? max(2, swoole_cpu_num());
    }

    /**
     * Start batch consumers
     *
     * @param callable $handler function(int $workerId, array $tasks, int $consumerIndex): void
     */
    public function startConsumer(callable $handler): void
    {
        if ($this->running) {
            logDebug(self::TAG, sprintf('[W%d] Already running', $this->workerId));
            return;
        }

        $this->running = true;

        for ($i = 0; $i < $this->consumerCount; $i++) {
            go(fn () => $this->consumeLoop($handler, $i));
        }

        logDebug(self::TAG, sprintf('[W%d] Started %d consumers', $this->workerId, $this->consumerCount));
    }

    /**
     * Batch consume loop with exponential backoff.
     * Reduced cognitive complexity by extracting helper methods.
     *
     */
    private function consumeLoop(callable $handler, int $index): void
    {
        $sleep    = 0.005;   // 5ms
        $maxSleep = 0.2;  // 200ms cap

        while (true) {
            $batch = $this->drainBatch();

            if ($batch === null) {
                // Channel closed → stop consumer
                break;
            }

            if ($batch !== []) {
                $sleep = 0.005; // reset backoff
                $this->processBatch($handler, $batch, $index);
                continue;
            }

            if (!$this->running) {
                break;
            }

            $sleep = $this->applyBackoff($sleep, $maxSleep);
        }

        logDebug(self::TAG, sprintf('[W%d|C%d] Stopped', $this->workerId, $index));
    }

    /**
     * Drain up to batchSize tasks from channel.
     * Returns:
     * - array of tasks if available
     * - [] when empty but open
     * - null if channel closed
     *
     * @return array<mixed>|null
     */
    private function drainBatch(): ?array
    {
        $batch = [];

        for ($i = 0; $i < $this->batchSize; $i++) {
            $task = $this->channel->pop(0.0);

            if ($task === false) {
                // Channel closed
                if ($this->channel->errCode === SWOOLE_CHANNEL_CLOSED) {
                    return null;
                }

                // Channel empty
                break;
            }

            $batch[] = $task;
        }

        return $batch;
    }

    /**
     * Process tasks and catch exceptions.
     *
     * @param array<mixed> $batch
     */
    private function processBatch(callable $handler, array $batch, int $index): void
    {
        try {
            $handler($this->workerId, $batch, $index);
        } catch (\Throwable $throwable) {
            logDebug(self::TAG, sprintf('[W%d|C%d] Error: %s', $this->workerId, $index, $throwable->getMessage()));
        }
    }

    /**
     * Apply exponential backoff sleep and return next sleep value.
     *
     *
     */
    private function applyBackoff(float $sleep, float $maxSleep): float
    {
        Coroutine::sleep($sleep);
        return min($sleep * 2, $maxSleep);
    }

    /**
     * Push task into channel
     * @param array<string,mixed> $task
     */
    public function push(array $task): bool
    {
        if (!$this->running) {
            logDebug(self::TAG, sprintf('[W%d] Reject push: not running', $this->workerId));
            return false;
        }

        // Try quick non-blocking push with small timeout
        if ($this->channel->push($task, 0.002) === false) {
            if ($this->channel->isFull() === true) {
                logDebug(self::TAG, sprintf('[W%d] Channel full → offloading to task worker', $this->workerId));
            }

            go(fn (): mixed => $this->server->task($task)); // fire & forget
        }

        return true;
    }

    /**
     * Stop consumer and close channel
     */
    public function stop(): void
    {
        if (!$this->running) {
            return;
        }

        $this->running = false;

        // Unblocks all consumer pop() loops
        $this->channel->close();

        logDebug(self::TAG, sprintf('[W%d] Channel stopped', $this->workerId));
    }
}
