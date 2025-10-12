<?php

/**
 * src/Support/helpers.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice Helpers with Detailed Step Logging
 * PHP version 8.4
 *
 * @category  General
 * @package   Global
 * @author    Ramakant Gangwar
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.1.0
 * @since     2025-10-12
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/helpers.php
 */
declare(strict_types=1);

use App\Core\Constants;

// -----------------------------------------------------------------------------
// Log
// -----------------------------------------------------------------------------
if (!\function_exists('logDebug')) {
    /**
     * Write a consistent log line using error_log().
     * Automatically prefixes coroutine ID and helper name.
     *
     * @param string $tag Tag or source of the log message
     * @param string $message The log message
     * @param array<string, mixed> $context Additional context data (optional)
     *
     * @SuppressWarnings("PHPMD.StaticAccess")
     */
    function logDebug(string $tag, string $message, array $context = []): void
    {
        $cid = \class_exists(\Swoole\Coroutine::class)
            ? \Swoole\Coroutine::getCid()
            : 'N/A';

        $line = sprintf('[%s][cid:%s] %s', $tag, $cid, $message);

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        error_log($line);
    }
}

// -----------------------------------------------------------------------------
// env()
// -----------------------------------------------------------------------------
if (!\function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        logDebug(__FUNCTION__, 'called', ['key' => $key, 'default' => $default]);

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? (getenv($key) !== false ? getenv($key) : $default);

        logDebug(__FUNCTION__, 'resolved', ['key' => $key, 'value' => $value]);
        return $value;
    }
}

// -----------------------------------------------------------------------------
// secondsReadable()
// -----------------------------------------------------------------------------
if (!\function_exists('secondsReadable')) {
    function secondsReadable(int $seconds): string
    {
        // logDebug(__FUNCTION__, 'called', ['seconds' => $seconds]);

        $negative = $seconds < 0;
        $seconds  = abs($seconds);

        $units = ['h' => 3600, 'm' => 60, 's' => 1];
        $parts = [];

        foreach ($units as $label => $divisor) {
            if ($seconds >= $divisor) {
                $quot = intdiv($seconds, $divisor);
                $seconds %= $divisor;
                $parts[] = $quot . $label;
                // logDebug(__FUNCTION__, 'unit processed', ['unit' => $label, 'quot' => $quot, 'remaining' => $seconds]);
            }
        }

        if ($parts === []) {
            $parts[] = '0s';
        }

        $result = implode(' ', $parts);

        // logDebug(__FUNCTION__, 'final result', ['result' => $final]);
        return $negative ? '-' . $result : $result;
    }
}

// -----------------------------------------------------------------------------
// bytesReadable()
// -----------------------------------------------------------------------------
if (!\function_exists('bytesReadable')) {
    function bytesReadable(int|float $bytes, int $precision = 2): string
    {
        // logDebug(__FUNCTION__, 'called', ['bytes' => $bytes, 'precision' => $precision]);

        $negative = $bytes < 0;
        $bytes    = abs($bytes);

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $i     = 0;

        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            ++$i;
            // logDebug(__FUNCTION__, 'scaled', ['bytes' => $bytes, 'unit' => $units[$i]]);
        }

        $result = round($bytes, $precision) . ' ' . $units[$i];

        // logDebug(__FUNCTION__, 'final result', ['result' => $final]);
        return $negative ? '-' . $result : $result;
    }
}

// -----------------------------------------------------------------------------
// shouldPDORetry()
// -----------------------------------------------------------------------------
if (!\function_exists('shouldPDORetry')) {
    function shouldPDORetry(PDOException $pdoException): bool
    {
        $errorInfo  = $pdoException->errorInfo ?? [];
        $sqlState   = $errorInfo[0] ?? null;
        $driverCode = $errorInfo[1] ?? null;
        $driverMsg  = $errorInfo[2] ?? null;

        logDebug(__FUNCTION__, 'called', ['sqlState' => $sqlState, 'driverCode' => $driverCode, 'driverMsg' => $driverMsg]);

        $match = (
            ($sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE &&
                $driverCode === Constants::PDO_CONNECTION_REFUSED_ERROR_CODE &&
                (
                    str_contains($driverMsg, Constants::PDO_CONNECTION_REFUSED_MESSAGE) ||
                    str_contains($driverMsg, Constants::PDO_CONNECTION_TIMED_OUT_IN) ||
                    str_contains($driverMsg, Constants::PDO_DNS_LOOKUP_RESOLVE_FAILED)
                )) ||
            ($sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE &&
                $driverCode === Constants::PDO_SERVER_GONE_AWAY_ERROR_CODE &&
                str_contains($driverMsg, Constants::PDO_SERVER_GONE_AWAY_MESSAGE))
        );

        logDebug(__FUNCTION__, 'retry decision', ['retryable' => $match]);
        return $match;
    }
}

// -----------------------------------------------------------------------------
// shouldRetry()
// -----------------------------------------------------------------------------
if (!\function_exists('shouldRetry') && !\function_exists('shouldRetry')) {
    /**
     * Determine if an exception should trigger a retry.
     */
    function shouldRetry(Throwable $throwable): bool
    {
        logDebug(__FUNCTION__, 'called', [
            'class' => $throwable::class,
            'msg'   => $throwable->getMessage(),
        ]);

        if ($throwable instanceof PDOException) {
            $pdoRetry = shouldPDORetry($throwable);
            logDebug(__FUNCTION__, 'pdo exception check', ['pdoRetry' => $pdoRetry]);
            return $pdoRetry;
        }

        $retryableExceptions = [
            \App\Exceptions\CreateFailedException::class,
        ];

        foreach ($retryableExceptions as $retryableException) {
            if ($throwable instanceof $retryableException) {
                logDebug(__FUNCTION__, 'matched retryable class', ['class' => $retryableException]);
                return true;
            }
        }

        $patterns = [
            '/deadlock/i',
            '/timeout/i',
            '/connection.*refused/i',
            '/temporarily.*unavailable/i',
            '/lost.*connection/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $throwable->getMessage())) {
                logDebug(__FUNCTION__, 'matched retryable message', ['pattern' => $pattern]);
                return true;
            }
        }

        logDebug(__FUNCTION__, 'not retryable');
        return false;
    }
}

// -----------------------------------------------------------------------------
// shouldRedisRetry()
// -----------------------------------------------------------------------------
if (!\function_exists('shouldRedisRetry')) {
    function shouldRedisRetry(Throwable $throwable): bool
    {
        $message = strtolower($throwable->getMessage());
        logDebug(__FUNCTION__, 'called', ['message' => $message]);

        $retryable = str_contains($message, 'connection refused') ||
            str_contains($message, 'connection lost') ||
            str_contains($message, 'went away') ||
            str_contains($message, 'read error on connection') ||
            str_contains($message, 'failed to connect') ||
            str_contains($message, 'connection timed out in');

        logDebug(__FUNCTION__, 'result', ['retryable' => $retryable]);
        return $retryable;
    }
}

// -----------------------------------------------------------------------------
// isDuplicateException()
// -----------------------------------------------------------------------------
if (!\function_exists('isDuplicateException')) {
    function isDuplicateException(Throwable $throwable): bool
    {
        if (!($throwable instanceof PDOException)) {
            logDebug(__FUNCTION__, 'not a PDOException');
            return false;
        }

        $errorInfo  = $throwable->errorInfo ?? [];
        $sqlState   = $errorInfo[0] ?? null;
        $driverCode = $errorInfo[1] ?? null;
        $driverMsg  = $errorInfo[2] ?? '';

        logDebug(__FUNCTION__, 'called', ['sqlState' => $sqlState, 'driverCode' => $driverCode, 'driverMsg' => $driverMsg]);

        $mysqlCodes = [1062, 1022];
        $sqliteMsgs = ['UNIQUE constraint failed', 'column is not unique'];
        $sqlsrvMsgs = ['Cannot insert duplicate key', 'duplicate key row'];

        $duplicate = ($sqlState === '23000' && in_array($driverCode, $mysqlCodes, true)) ||
            $sqlState === '23505' ||
            array_reduce($sqliteMsgs, fn ($carry, $pattern): bool => $carry || stripos($driverMsg, $pattern) !== false, false) ||
            array_reduce($sqlsrvMsgs, fn ($carry, $pattern): bool => $carry || stripos($driverMsg, $pattern) !== false, false);

        logDebug(__FUNCTION__, 'result', ['duplicate' => $duplicate]);
        return $duplicate;
    }
}

// -----------------------------------------------------------------------------
// maybeDecodeJson()
// -----------------------------------------------------------------------------
if (!\function_exists('maybeDecodeJson')) {
    function maybeDecodeJson(mixed $value): mixed
    {
        // logDebug(__FUNCTION__, 'called', ['type' => gettype($value)]);

        if (!is_string($value)) {
            // logDebug(__FUNCTION__, 'non-string, returning as-is');
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            // logDebug(__FUNCTION__, 'empty string, returning null');
            return null;
        }

        $decoded   = null;
        $firstChar = $value[0];
        if ($firstChar === '{' || $firstChar === '[') {
            $decoded = json_decode($value, true);
            // logDebug(__FUNCTION__, 'json_decode attempted', ['error' => json_last_error_msg()]);
        }

        // logDebug(__FUNCTION__, 'returning', ['type' => gettype($return)]);
        return (json_last_error() === JSON_ERROR_NONE && $decoded !== null)
            ? $decoded
            : $value;
    }
}

// -----------------------------------------------------------------------------
// maybeEncodeJson()
// -----------------------------------------------------------------------------
if (!\function_exists('maybeEncodeJson')) {
    function maybeEncodeJson(mixed $value, int $flags = 0, int $depth = 512): mixed
    {
        // logDebug(__FUNCTION__, 'called', ['type' => gettype($value)]);

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, $flags, $depth);
            $ok      = json_last_error() === JSON_ERROR_NONE;
            // logDebug(__FUNCTION__, 'json_encode attempted', ['success' => $ok, 'error' => json_last_error_msg()]);

            if ($ok) {
                // logDebug(__FUNCTION__, 'encoded successfully');
                return $encoded;
            }
        }

        // logDebug(__FUNCTION__, 'not encodable, returning raw');
        return $value;
    }
}
