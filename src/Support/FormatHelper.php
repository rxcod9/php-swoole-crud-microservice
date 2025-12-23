<?php

/**
 * src/Support/FormatHelper.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/FormatHelper.php
 */
declare(strict_types=1);

namespace App\Support;

/**
 * Class FormatHelper
 * Provides formatting utilities for bytes and time.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 */
class FormatHelper
{
    /**
     * Convert seconds to human-readable format.
     */
    public static function secondsReadable(int $seconds): string
    {
        $negative = $seconds < 0;
        $seconds  = abs($seconds);

        $units = ['h' => 3600, 'm' => 60, 's' => 1];
        $parts = [];

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

    /**
     * Convert bytes into human-readable size.
     */
    public static function bytesReadable(int|float $bytes, int $precision = 2): string
    {
        $negative = $bytes < 0;
        $bytes    = abs($bytes);

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $i     = 0;

        $count = count($units);
        while ($bytes >= 1024 && $i < $count - 1) {
            $bytes /= 1024;
            ++$i;
        }

        $result = round($bytes, $precision) . ' ' . $units[$i];
        return $negative ? '-' . $result : $result;
    }
}
