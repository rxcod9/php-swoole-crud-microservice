<?php

/**
 * src/Support/helpers.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  General
 * @package   Global
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/helpers.php
 */
declare(strict_types=1);

/**
 * src/Support/helpers.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: Global helper function mappings to static Helper classes.
 */

use App\Support\{
    DuplicateHelper,
    EnvHelper,
    FormatHelper,
    JsonHelper,
    LogHelper,
    PdoHelper,
    RedisHelper,
    RetryHelper
};

// -----------------------------------------------------------------------------
// LOGGING
// -----------------------------------------------------------------------------

if (!function_exists('logDebug')) {
    /**
     * Centralized debug logger.
     *
     * Provides a single entry point for debug logging across the project.
     *
     * @param string               $tag     Tag or category for the log
     * @param string               $message Log message
     * @param array<string,mixed>  $context Optional context for structured logging
     *
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function logDebug(string $tag, string $message, array $context = []): void
    {
        LogHelper::debug($tag, $message, $context);
    }
}

// -----------------------------------------------------------------------------
// ENVIRONMENT VARIABLES
// -----------------------------------------------------------------------------

if (!function_exists('env')) {
    /**
     * Retrieve environment variable with fallback.
     *
     * Looks in $_ENV, $_SERVER, then getenv() with optional default value.
     *
     * @param string $key     Name of the environment variable
     * @param mixed  $default Default value if env key is not set
     *
     * @return mixed The value of the environment variable or $default
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function env(string $key, mixed $default = null): mixed
    {
        return EnvHelper::get($key, $default);
    }
}

// -----------------------------------------------------------------------------
// FORMATTERS
// -----------------------------------------------------------------------------

if (!function_exists('secondsReadable')) {
    /**
     * Convert seconds into a human-readable string (e.g., 1h 3m 10s).
     *
     * @param int $seconds Number of seconds
     *
     * @return string Human-readable formatted string
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function secondsReadable(int $seconds): string
    {
        return FormatHelper::secondsReadable($seconds);
    }
}

if (!function_exists('bytesReadable')) {
    /**
     * Convert bytes to human-readable format (B, KB, MB, GB, etc.).
     *
     * @param int|float $bytes     Number of bytes
     * @param int       $precision Number of decimal places
     *
     * @return string Human-readable string
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function bytesReadable(int|float $bytes, int $precision = 2): string
    {
        return FormatHelper::bytesReadable($bytes, $precision);
    }
}

// -----------------------------------------------------------------------------
// RETRY / ERROR LOGIC
// -----------------------------------------------------------------------------

if (!function_exists('shouldPDORetry')) {
    /**
     * Determine whether a PDOException should trigger a retry.
     *
     * @param PDOException $pdoException Exception thrown during DB operation
     *
     * @return bool True if retry is recommended, false otherwise
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function shouldPDORetry(PDOException $pdoException): bool
    {
        return PdoHelper::shouldRetry($pdoException);
    }
}

if (!function_exists('shouldRetry')) {
    /**
     * Determine if a Throwable should trigger a retry.
     *
     * Supports PDO exceptions, CreateFailedException, and generic retryable patterns.
     *
     * @param Throwable $throwable Exception or error thrown
     *
     * @return bool True if retry is recommended, false otherwise
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function shouldRetry(Throwable $throwable): bool
    {
        return RetryHelper::shouldRetry($throwable);
    }
}

if (!function_exists('shouldForceRetry')) {
    /**
     * Determine if a Throwable should trigger a force retry.
     *
     * Supports PDO exceptions, CreateFailedException, and generic retryable patterns.
     *
     * @param Throwable $throwable Exception or error thrown
     *
     * @return bool True if retry is recommended, false otherwise
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function shouldForceRetry(Throwable $throwable): bool
    {
        return RetryHelper::shouldForceRetry($throwable);
    }
}

if (!function_exists('shouldRedisRetry')) {
    /**
     * Determine if a Redis-related exception should trigger a retry.
     *
     * Checks common transient Redis/connection issues.
     *
     * @param Throwable $throwable Exception thrown during Redis operation
     *
     * @return bool True if retry is recommended, false otherwise
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function shouldRedisRetry(Throwable $throwable): bool
    {
        return RedisHelper::shouldRetry($throwable);
    }
}

// -----------------------------------------------------------------------------
// DUPLICATE EXCEPTION
// -----------------------------------------------------------------------------

if (!function_exists('isDuplicateException')) {
    /**
     * Detect if a Throwable represents a duplicate record exception.
     *
     * Supports MySQL, Postgres, SQLite, SQL Server duplicate detection.
     *
     * @param Throwable $throwable Exception thrown during DB operation
     *
     * @return bool True if it is a duplicate record exception, false otherwise
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function isDuplicateException(Throwable $throwable): bool
    {
        return DuplicateHelper::isDuplicate($throwable);
    }
}

// -----------------------------------------------------------------------------
// JSON HELPERS
// -----------------------------------------------------------------------------

if (!function_exists('maybeDecodeJson')) {
    /**
     * Safely decode a JSON string if possible, otherwise return original value.
     *
     * @param mixed $value JSON string or any value
     *
     * @return mixed Decoded array/object if JSON, otherwise original value
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function maybeDecodeJson(mixed $value): mixed
    {
        return JsonHelper::maybeDecode($value);
    }
}

if (!function_exists('maybeEncodeJson')) {
    /**
     * Safely encode an array or object to JSON string.
     *
     * Returns original value if encoding fails.
     *
     * @param mixed $value JSON-serializable value
     * @param int   $flags JSON encode flags
     * @param int   $depth Maximum depth
     *
     * @return mixed JSON string or original value if encoding fails
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function maybeEncodeJson(mixed $value, int $flags = 0, int $depth = 512): mixed
    {
        return JsonHelper::maybeEncode($value, $flags, $depth);
    }
}
