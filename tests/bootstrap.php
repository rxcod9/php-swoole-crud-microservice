<?php

/**
 * tests/bootstrap.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  General
 * @package   Global
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/tests/bootstrap.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration settings.
$config = require_once __DIR__ . '/../config/config.php';

// Set the default timezone for all date/time functions.
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kolkata');

\define('TEST_ENV', true);
