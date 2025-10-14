<?php

/**
 * src/Support/helpers.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice Helpers with Detailed Logging and Cleaner Functions
 * PHP version 8.4
 *
 * @category  General
 * @package   Global
 * @author    Ramakant Gangwar
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.2.0
 * @since     2025-10-12
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/helpers.php
 */
declare(strict_types=1);

use App\Core\Constants;
use App\Support\LogHelper;

// -----------------------------------------------------------------------------
// LOGGING
// -----------------------------------------------------------------------------

if (!function_exists('logDebug')) {
    /**
     * Centralized debug logger
     *
     * @param array<string, mixed> $context
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function logDebug(string $tag, string $message, array $context = []): void
    {
        LogHelper::debug($tag, $message, $context);
    }
}

// -----------------------------------------------------------------------------
// ENV HELPER
// -----------------------------------------------------------------------------

if (!function_exists('env')) {
    /**
     * Retrieve environment variable with fallback
     */
    function env(string $key, mixed $default = null): mixed
    {
        // logDebug(__FUNCTION__, 'called', ['key' => $key, 'default' => $default]);

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? (getenv($key) !== false ? getenv($key) : $default);

        // logDebug(__FUNCTION__, 'resolved', ['key' => $key, 'value' => $value]);
        return $value;
    }
}

// -----------------------------------------------------------------------------
// TIME AND SIZE FORMATTING
// -----------------------------------------------------------------------------

if (!function_exists('secondsReadable')) {
    function secondsReadable(int $seconds): string
    {
        $negative = $seconds < 0;
        $seconds  = abs($seconds);
        $units    = ['h' => 3600, 'm' => 60, 's' => 1];
        $parts    = [];

        foreach ($units as $label => $divisor) {
            $quot = intdiv($seconds, $divisor);
            if ($quot > 0) {
                $parts[] = $quot . $label;
                $seconds %= $divisor;
            }
        }

        if ($parts === []) {
            $parts[] = '0s';
        }

        $result = implode(' ', $parts);
        return $negative ? '-' . $result : $result;
    }
}

if (!function_exists('bytesReadable')) {
    function bytesReadable(int|float $bytes, int $precision = 2): string
    {
        $negative = $bytes < 0;
        $bytes    = abs($bytes);
        $units    = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $i        = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            ++$i;
        }

        $result = round($bytes, $precision) . ' ' . $units[$i];
        return $negative ? '-' . $result : $result;
    }
}

// -----------------------------------------------------------------------------
// PDO RETRY LOGIC
// -----------------------------------------------------------------------------

if (!function_exists('shouldPDORetry')) {
    function shouldPDORetry(PDOException $pdoException): bool
    {
        logDebug(__FUNCTION__, 'called', ['message' => $pdoException->getMessage()]);
        return isConnectionRefused($pdoException) || isServerGoneAway($pdoException);
    }
}

function isConnectionRefused(PDOException $pdoException): bool
{
    $info       = $pdoException->errorInfo ?? [];
    $sqlState   = $info[0] ?? null;
    $driverCode = $info[1] ?? null;
    $driverMsg  = $info[2] ?? '';

    return $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
        && $driverCode === Constants::PDO_CONNECTION_REFUSED_ERROR_CODE
        && (str_contains($driverMsg, Constants::PDO_CONNECTION_REFUSED_MESSAGE)
            || str_contains($driverMsg, Constants::PDO_CONNECTION_TIMED_OUT_IN)
            || str_contains($driverMsg, Constants::PDO_DNS_LOOKUP_RESOLVE_FAILED));
}

function isServerGoneAway(PDOException $pdoException): bool
{
    $info       = $pdoException->errorInfo ?? [];
    $sqlState   = $info[0] ?? null;
    $driverCode = $info[1] ?? null;
    $driverMsg  = $info[2] ?? '';

    return $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
        && $driverCode === Constants::PDO_SERVER_GONE_AWAY_ERROR_CODE
        && str_contains($driverMsg, Constants::PDO_SERVER_GONE_AWAY_MESSAGE);
}

// -----------------------------------------------------------------------------
// GENERAL RETRY LOGIC
// -----------------------------------------------------------------------------

if (!function_exists('shouldRetry')) {
    function shouldRetry(Throwable $throwable): bool
    {
        logDebug(__FUNCTION__, 'called', ['class' => get_class($throwable), 'msg' => $throwable->getMessage()]);

        if ($throwable instanceof PDOException) {
            return shouldPDORetry($throwable);
        }

        if ($throwable instanceof \App\Exceptions\CreateFailedException) {
            logDebug(__FUNCTION__, 'matched retryable class');
            return true;
        }

        return matchesRetryPattern($throwable->getMessage());
    }
}

function matchesRetryPattern(string $msg): bool
{
    $patterns = [
        '/deadlock/i',
        '/timeout/i',
        '/connection.*refused/i',
        '/temporarily.*unavailable/i',
        '/lost.*connection/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $msg)) {
            logDebug(__FUNCTION__, 'matched retryable message', ['pattern' => $pattern]);
            return true;
        }
    }

    return false;
}

// -----------------------------------------------------------------------------
// REDIS RETRY LOGIC
// -----------------------------------------------------------------------------

if (!function_exists('shouldRedisRetry')) {
    function shouldRedisRetry(Throwable $throwable): bool
    {
        $msg = strtolower($throwable->getMessage());
        logDebug(__FUNCTION__, 'called', ['message' => $msg]);

        $retryable = str_contains($msg, 'connection refused')
            || str_contains($msg, 'connection lost')
            || str_contains($msg, 'went away')
            || str_contains($msg, 'read error on connection')
            || str_contains($msg, 'failed to connect')
            || str_contains($msg, 'connection timed out in');

        logDebug(__FUNCTION__, 'result', ['retryable' => $retryable]);
        return $retryable;
    }
}

// -----------------------------------------------------------------------------
// DUPLICATE EXCEPTION CHECK
// -----------------------------------------------------------------------------

if (!function_exists('isDuplicateException')) {
    function isDuplicateException(Throwable $throwable): bool
    {
        if (!($throwable instanceof PDOException)) {
            logDebug(__FUNCTION__, 'not a PDOException');
            return false;
        }

        $info       = $throwable->errorInfo ?? [];
        $sqlState   = $info[0] ?? null;
        $driverCode = $info[1] ?? null;
        $driverMsg  = $info[2] ?? '';

        return isMySqlDuplicate($sqlState, $driverCode)
            || isPostgresDuplicate($sqlState)
            || isSqliteDuplicate($driverMsg)
            || isSqlServerDuplicate($driverMsg);
    }
}

function isMySqlDuplicate(?string $sqlState, ?int $code): bool
{
    return $sqlState === '23000' && in_array($code, [1062, 1022], true);
}

function isPostgresDuplicate(?string $sqlState): bool
{
    return $sqlState === '23505';
}

function isSqliteDuplicate(string $msg): bool
{
    $patterns = ['UNIQUE constraint failed', 'column is not unique'];
    return array_any($patterns, fn ($pattern): bool => stripos($msg, $pattern) !== false);
}

function isSqlServerDuplicate(string $msg): bool
{
    $patterns = ['Cannot insert duplicate key', 'duplicate key row'];
    return array_any($patterns, fn ($pattern): bool => stripos($msg, $pattern) !== false);
}

// -----------------------------------------------------------------------------
// JSON HELPERS
// -----------------------------------------------------------------------------

if (!function_exists('maybeDecodeJson')) {
    function maybeDecodeJson(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        $trimmed = trim($value);
        $decoded = (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '['))
            ? json_decode($trimmed, true)
            : null;

        return (json_last_error() === JSON_ERROR_NONE && $decoded !== null) ? $decoded : $value;
    }
}

if (!function_exists('maybeEncodeJson')) {
    function maybeEncodeJson(mixed $value, int $flags = 0, int $depth = 512): mixed
    {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, $flags, $depth);
            return (json_last_error() === JSON_ERROR_NONE) ? $encoded : $value;
        }

        return $value;
    }
}
