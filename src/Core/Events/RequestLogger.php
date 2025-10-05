<?php

/**
 * src/Core/Events/RequestLogger.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestLogger.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Tasks\LogTask;
use Swoole\Http\Request;
use Swoole\Http\Server;

/**
 * Class RequestLogger
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
final class RequestLogger
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param mixed $level
     */
    public function log($level, Server $server, Request $request, array $data): void
    {
        $server->task([
            'class'     => LogTask::class,
            'arguments' => [$level, $data],
        ]);
    }
}
