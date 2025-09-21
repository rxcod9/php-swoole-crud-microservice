<?php

namespace App\Tasks;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * LoggerTask handles logging of request payloads to a file.
 *
 * This class uses Monolog to write log entries to /app/logs/access.log.
 */
final class LoggerTask
{
    /**
     * Handles logging of the provided payload.
     *
     * @param array $payload The data to be logged.
     *
     * @return void
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
