<?php

namespace App\Tasks;

/**
 * TaskInterface handles logging of request payloads to a file.
 *
 * This class uses Monolog to write log entries to /app/logs/access.log.
 */
interface TaskInterface
{
    /**
     * Handles logging of the provided payload.
     *
     * @param array $arguments The data to be logged.
     *
     * @return mixed
     */
    public function handle(...$arguments);
}
