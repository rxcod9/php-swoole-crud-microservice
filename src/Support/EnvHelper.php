<?php

/**
 * src/Support/EnvHelper.php
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
 * @since     2025-10-26
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Support/EnvHelper.php
 */
declare(strict_types=1);

namespace App\Support;

/**
 * Class EnvHelper
 * Handles environment variable access and resolution logic.
 *
 * @category  Support
 * @package   App\Support
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.3.0
 * @since     2025-10-26
 */
class EnvHelper
{
    /**
     * Retrieve environment variable value with fallback.
     *
     * Priority order:
     *  1. $_ENV
     *  2. $_SERVER
     *  3. getenv()
     *  4. Default value
     *
     * @param string $key      Environment variable key
     * @param mixed  $default  Default fallback value
     *
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Check sequentially through possible sources
        foreach ([$_ENV, $_SERVER] as $source) {
            if (array_key_exists($key, $source) && self::isValidValue($source[$key])) {
                return $source[$key];
            }
        }

        // getenv() last
        $value = getenv($key);
        if (self::isValidValue($value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Determine if an environment variable value is valid.
     *
     */
    private static function isValidValue(mixed $value): bool
    {
        return !in_array($value, [null, false, ''], true);
    }
}
