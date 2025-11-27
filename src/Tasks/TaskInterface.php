<?php

/**
 * src/Tasks/TaskInterface.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/TaskInterface.php
 */
declare(strict_types=1);

namespace App\Tasks;

/**
 * TaskInterface handles logging of request payloads to a file.
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
interface TaskInterface
{
    /**
     * Handles task.
     *
     * @param string $id id
     * @param mixed ...$arguments Arguments to process
     */
    public function handle(string $id, mixed ...$arguments): mixed;
}
