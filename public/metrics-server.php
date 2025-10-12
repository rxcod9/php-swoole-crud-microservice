<?php

declare(strict_types=1);

/**
 * Metrics Server Bootstrap File
 *
 * This file initializes and starts the MetricsServer for collecting and exposing metrics.
 * It ensures that only one Swoole event loop is started per process to avoid conflicts.
 *
 * PHP version 7.4+
 *
 * @package    App
 * @subpackage Public
 * @author     Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright  Copyright (c) 2025
 * @license    MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Servers\MetricsServer;
use Dotenv\Dotenv;

// Load .env file from project root
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Optional: validate required env variables
$dotenv->required([
    'METRICS_PORT',
])->notEmpty();

/**
 * Load application configuration.
 *
 * @var array $config Application configuration array.
 */
$config = require_once __DIR__ . '/../config/config.php';

/**
 * Metrics server port.
 *
 * Uses the METRICS_PORT environment variable if set, otherwise defaults to 9310.
 *
 * @var int|string $port
 */
$port = getenv('METRICS_PORT') ?: 9310;

/**
 * Ensure only one Swoole event loop is started per process.
 */
if (!\defined('SWOOLE_EVENT_LOOP_STARTED')) {
    /**
     * Define a constant to indicate the Swoole event loop has started.
     */
    \define('SWOOLE_EVENT_LOOP_STARTED', true);

    /**
     * Instantiate and start the MetricsServer.
     *
     * @var MetricsServer $metricsServer
     */
    $metricsServer = new MetricsServer($port);
    $metricsServer->start();
} else {
    /**
     * Log a warning if the Swoole event loop is already started.
     */
    logDebug(__FILE__ , 'Swoole event loop already started. MetricsServer not started again.');
}
