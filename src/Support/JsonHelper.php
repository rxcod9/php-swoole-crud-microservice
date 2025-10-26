<?php

/**
 * src/Support/JsonHelper.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/JsonHelper.php
 */
declare(strict_types=1);

namespace App\Support;

/**
 * Class JsonHelper
 * Handles safe JSON encoding and decoding with graceful fallback.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-18
 */
class JsonHelper
{
    /**
     * Attempt to decode JSON if the input looks like JSON.
     */
    public static function maybeDecode(mixed $value): mixed
    {
        if (!is_string($value) || trim($value) === '') {
            return $value;
        }

        $trimmed = trim($value);
        $decoded = (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '['))
            ? json_decode($trimmed, true)
            : null;

        return (json_last_error() === JSON_ERROR_NONE && $decoded !== null)
            ? $decoded
            : $value;
    }

    /**
     * Attempt to encode arrays/objects as JSON safely.
     */
    public static function maybeEncode(mixed $value, int $flags = 0, int $depth = 512): mixed
    {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, $flags, $depth);
            return (json_last_error() === JSON_ERROR_NONE) ? $encoded : $value;
        }

        return $value;
    }
}
