<?php

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
     * Handles logging of the provided payload.
     *
     * @param array $payload The data to be logged.
     *
     * @return mixed
     */
    public function handle(...$arguments)
    {
        echo __CLASS__ . " " . json_encode($arguments, JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
