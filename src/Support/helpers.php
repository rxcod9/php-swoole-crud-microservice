<?php

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

        if (!$parts) {
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
            $i++;
        }

        $result = round($bytes, $precision) . ' ' . $units[$i];

        return $negative ? '-' . $result : $result;
    }
}

if (!\function_exists('shouldPDORetry')) {
    /**
     * Is PDO Connection Refused
     */
    function shouldPDORetry(PDOException $e): bool
    {
        $errorInfo  = $e->errorInfo ?? [];
        $sqlState   = $errorInfo[0] ?? null;  // SQLSTATE
        $driverCode = $errorInfo[1] ?? null; // MySQL driver code
        $driverMsg  = $errorInfo[2] ?? null; // MySQL message

        // Retry only if "Connection refused" (MySQL error 2002)
        return (
            $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
            && $driverCode === Constants::PDO_CONNECTION_REFUSED_ERROR_CODE
            && str_contains($driverMsg, Constants::PDO_CONNECTION_REFUSED_MESSAGE)
        ) || (
            $sqlState === Constants::PDO_GENERAL_ERROR_SQL_STATE
            && $driverCode === Constants::PDO_SERVER_GONE_AWAY_ERROR_CODE
            && str_contains($driverMsg, Constants::PDO_SERVER_GONE_AWAY_MESSAGE)
        );
    }
}
