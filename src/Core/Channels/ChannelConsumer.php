<?php

/**
 * src/Core/Channels/ChannelConsumer.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Channels
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-11-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Channels/ChannelConsumer.php
 */
declare(strict_types=1);

namespace App\Core\Channels;

use App\Core\Events\ChannelTaskRequestDispatcher;
use Throwable;

/**
 * Defines how to handle tasks from the worker’s channel.
 *
 * @category  Core
 * @package   App\Core\Channels
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-11-02
 */
final class ChannelConsumer
{
    /**
     * @SuppressWarnings("PHPMD.LongVariable")
     */
    public function __construct(
        private readonly ChannelManager $channelManager,
        private readonly ChannelTaskRequestDispatcher $channelTaskRequestDispatcher
    ) {
        //
    }

    /**
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    public function start(): void
    {
        $this->channelManager->startConsumer(function (int $workerId, mixed $task): void {
            echo sprintf(
                "[Worker %d] Task %s\n",
                $workerId,
                json_encode($task, JSON_PRETTY_PRINT)
            );
            try {
                $this->channelTaskRequestDispatcher->dispatch($task);
            } catch (Throwable) {
                // Handle Error
            }
        });
    }
}
