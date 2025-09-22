<?php

namespace App\Core\Contexts;

/**
 * Class AppContext
 *
 * Application Context for managing global application state.
 * 
 * This class provides static methods to manage and query the readiness state
 * of the application worker. It is intended to be used as a singleton-like
 * context holder for application-wide flags and states.
 *
 * @package App\Core
 */
final class AppContext
{
    /**
     * Indicates if the worker is ready to handle requests.
     *
     * @var bool
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
     * @return void
     */
    public static function setWorkerReady(bool $ready): void
    {
        self::$workerReady = $ready;
    }
}
