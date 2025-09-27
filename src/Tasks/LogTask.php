<?php

declare(strict_types=1);

namespace App\Tasks;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * LogTask handles logging of request payloads to a file.
 *
 * This class uses Monolog to write log entries to /app/logs/access.log.
 */
final class LogTask implements TaskInterface
{
    /**
     * Handles logging of the provided arguments.
     *
     * @param array $arguments The data to be logged.
     *
     * @return mixed
     */
    public function handle(...$arguments)
    {
        [$data] = $arguments;
        // echo __CLASS__ . " " . json_encode($arguments, JSON_PRETTY_PRINT) . PHP_EOL;
        static $log = null;
        if (!$log) {
            $log = new Logger('access');
            $log->pushHandler(new StreamHandler('/app/logs/access.log'));
        }
        $log->info(
            'request',
            $data
        );
    }
}
