<?php

/**
 * src/Core/Config.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Config.php
 */
declare(strict_types=1);

namespace App\Core;

/**
 * Class Config
 * Handles all config operations.
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class Config
{
    /**
     * Constructor to initialize configuration.
     *
     * @param array<string, mixed> $config Configuration array.
     */
    public function __construct(private array $config)
    {
        //
    }

    /**
     * Get all configuration settings.
     *
     * @return array<string, mixed> All configuration settings.
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Get a specific configuration value by key.
     *
     * @param string $key Configuration key.
     *
     * @return mixed Configuration value or null if key does not exist.
     */
    public function get(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}
