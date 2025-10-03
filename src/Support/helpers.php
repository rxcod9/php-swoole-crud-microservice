<?php

/**
 * src/Support/helpers.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category General
 * @package  Global
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/helpers.php
 */
declare(strict_types=1);

use App\Core\Constants;

if (!\function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}

if (!\function_exists('secondsReadable')) {
    function secondsReadable(int $seconds): string
    {
        $negative = $seconds < 0;
        $seconds  = abs($seconds);

        $units = [
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ];

        $parts = [];
        foreach ($units as $label => $divisor) {
            if ($seconds >= $divisor) {
                $quot = intdiv($seconds, $divisor);
                $seconds %= $divisor;
                $parts[] = $quot . $label;
            }
        }

        if ($parts === []) {
            $parts[] = '0s';
        }

        $result = implode(' ', $parts);

        return $negative ? '-' . $result : $result;
    }
}

if (!\function_exists('bytesReadable')) {
    /**
     * Convert bytes to human-readable format.
     *
     * @param int $precision Number of decimal points
     */
    function bytesReadable(int|float $bytes, int $precision = 2): string
    {
        $negative = $bytes < 0;
        $bytes    = abs($bytes);

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $i     = 0;

        while (
            $bytes >= 1024
            && $i < \count($units) - 1
        ) {
            $bytes /= 1024;
            ++$i;
        }

        $result = round($bytes, $precision) . ' ' . $units[$i];

        return $negative ? '-' . $result : $result;
    }
}

if (!\function_exists('shouldPDORetry')) {
    /**
     * Is PDO Connection Refused
     */
    function shouldPDORetry(PDOException $pdoException): bool
    {
        $errorInfo  = $pdoException->errorInfo ?? [];
        $sqlState   = $errorInfo[0] ?? null;  // SQLSTATE
        $driverCode = $errorInfo[1] ?? null; // MySQL driver code
        $driverMsg  = $errorInfo[2] ?? null; // MySQL message

        // Retry only if "Connection refused" (MySQL error 2002)
        return (
            $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
            && $driverCode === Constants::PDO_CONNECTION_REFUSED_ERROR_CODE
            && (
                str_contains($driverMsg, Constants::PDO_CONNECTION_REFUSED_MESSAGE) ||
                str_contains($driverMsg, Constants::PDO_CONNECTION_TIMED_OUT_IN)
            )
        ) || (
            $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
            && $driverCode === Constants::PDO_SERVER_GONE_AWAY_ERROR_CODE
            && str_contains($driverMsg, Constants::PDO_SERVER_GONE_AWAY_MESSAGE)
        );
    }
}

if (!\function_exists('shouldRedisRetry')) {
    /**
     * Determine if a Redis exception is retryable.
     *
     * Typical cases:
     *  - Connection refused
     *  - Connection lost / server went away
     */
    function shouldRedisRetry(Throwable $throwable): bool
    {
        $message = $throwable->getMessage();

        // Normalize to lowercase for easier checks
        $normalized = strtolower($message);

        return
            // Redis connection refused
            (str_contains($normalized, 'connection refused')) ||

            // Redis server closed the connection
            (str_contains($normalized, 'connection lost')) ||

            // Common Redis disconnects
            (
                str_contains($normalized, 'went away') ||
                str_contains($normalized, 'read error on connection') ||
                str_contains($normalized, 'failed to connect') ||
                str_contains($normalized, 'Connection timed out in')
            );
    }
}

if (!\function_exists('maybeDecodeJson')) {
    /**
     * Decode value if it looks like JSON, otherwise return as-is.
     * Supports null, false, and strings.
     */
    function maybeDecodeJson(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value; // return #1
        }

        $value = trim($value);
        if ($value === '') {
            return null; // return #2
        }

        $decoded   = null;
        $firstChar = $value[0];
        if ($firstChar === '{' || $firstChar === '[') {
            $decoded = json_decode($value, true);
        }

        // single fallback return for both decoded and plain string
        return (json_last_error() === JSON_ERROR_NONE && $decoded !== null)
            ? $decoded
            : $value; // return #3
    }
}

if (!\function_exists('maybeEncodeJson')) {
    /**
     * Encode value to JSON if it is array or object, otherwise return as-is.
     */
    function maybeEncodeJson(mixed $value, int $flags = 0, int $depth = 512): mixed
    {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, $flags, $depth);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $encoded;
            }
        }

        return $value;
    }
}
