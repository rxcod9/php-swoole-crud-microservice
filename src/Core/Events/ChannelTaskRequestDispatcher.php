<?php

/**
 * src/Core/Events/ChannelTaskRequestDispatcher.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/ChannelTaskRequestDispatcher.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\ChannelTaskDispatcher;
use App\Core\Container;

/**
 * Class ChannelTaskRequestDispatcher
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class ChannelTaskRequestDispatcher
{
    public function __construct(
        private Container $container
    ) {
        // Empty Constructor
    }

    /**
     * @SuppressWarnings("PHPMD.LongVariable")
     * @param array<string, mixed> $task Task
     */
    public function dispatch(array $task): mixed
    {
        $class                 = $task['class'] ?? null;
        $id                    = $task['id'] ?? bin2hex(random_bytes(8));
        $arguments             = $task['arguments'] ?? null;
        $channelTaskDispatcher = new ChannelTaskDispatcher($this->container);
        return $channelTaskDispatcher->dispatch(
            $class,
            $id,
            $arguments
        );
    }
}
