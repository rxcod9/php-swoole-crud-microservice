<?php

/**
 * src/Tasks/Task.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Tasks/Task.php
 */
declare(strict_types=1);

namespace App\Tasks;

use BadMethodCallException;
use InvalidArgumentException;

/**
 * Task handles create user operation.
 *
 * @category  Tasks
 * @package   App\Tasks
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
abstract class Task implements TaskInterface
{
    public const TAG = 'Task';

    /**
     * Handles Task.
     *
     * @param string $id Id
     * @param mixed ...$arguments Arguments, expected to be [method, params, data]
     *
     * @return mixed Always returns true on successful logging
     */
    public function handle(string $id, mixed ...$arguments): mixed
    {
        if ($arguments === []) {
            throw new InvalidArgumentException('No method name provided.');
        }

        // ✅ PHP-compatible destructuring
        $method = array_shift($arguments); // first argument = method name
        $rest   = $arguments;                // remaining args

        if (!method_exists($this, $method)) {
            throw new BadMethodCallException(sprintf('Method %s not found on target class.', $method));
        }

        return call_user_func_array([$this, $method], $rest);
    }
}
