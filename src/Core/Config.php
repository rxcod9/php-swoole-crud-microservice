<?php

/**
 * src/Core/Config.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Config.php
 */
declare(strict_types=1);

namespace App\Core;

/**
 * Class Config
 * Handles all config operations.
 *
 * @category Core
 * @package  App\Core
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class Config
{
    public function __construct(private array $config)
    {
        //
    }

    public function all(): array
    {
        return $this->config;
    }

    public function get($key)
    {
        return $this->config[$key] ?? null;
    }
}
