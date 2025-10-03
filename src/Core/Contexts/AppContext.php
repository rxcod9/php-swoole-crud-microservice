<?php

/**
 * src/Core/Contexts/AppContext.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core\Contexts
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Contexts/AppContext.php
 */
declare(strict_types=1);

namespace App\Core\Contexts;

/**
 * Class AppContext
 * Application Context for managing global application state.
 * This class provides static methods to manage and query the readiness state
 * of the application worker. It is intended to be used as a singleton-like
 * context holder for application-wide flags and states.
 *
 * @category Core
 * @package  App\Core\Contexts
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class AppContext
{
    /**
     * Indicates if the worker is ready to handle requests.
     */
    private static bool $workerReady = false;

    /**
     * Get the current worker readiness state.
     *
     * @return bool True if the worker is ready, false otherwise.
     */
    public static function isWorkerReady(): bool
    {
        return self::$workerReady;
    }

    /**
     * Set the worker readiness state.
     *
     * @param bool $ready True if the worker is ready, false otherwise.
     */
    public static function setWorkerReady(bool $ready): void
    {
        self::$workerReady = $ready;
    }
}
