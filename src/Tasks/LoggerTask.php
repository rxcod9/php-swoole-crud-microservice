<?php

/**
 * src/Tasks/LoggerTask.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/LoggerTask.php
 */
declare(strict_types=1);

namespace App\Tasks;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * LoggerTask handles logging of request payloads to a file.
 * This class uses Monolog to write log entries to /app/logs/access.log.
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class LoggerTask
{
    /**
     * Handles logging of the provided payload.
     *
     * @param array<int, mixed> $payload The data to be logged.
     */
    public static function handle(array $payload): void
    {
        static $log = null;
        if (!$log) {
            $log = new Logger('access');
            $log->pushHandler(new StreamHandler('/app/logs/access.log'));
        }

        $log->info('request', $payload);
    }
}
