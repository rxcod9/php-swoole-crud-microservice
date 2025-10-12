<?php

declare(strict_types=1);

/**
 * WebSocket Server Bootstrap File
 *
 * This file initializes and starts the WebSocket server using Swoole.
 * It ensures that only one Swoole event loop is started per process.
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

use App\Core\Servers\WebSocketServer;
use Dotenv\Dotenv;

// Load .env file from project root
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Optional: validate required env variables
$dotenv->required([
    'WS_PORT',
])->notEmpty();

/**
 * Load application configuration.
 *
 * @var array $config
 */
$config = require_once __DIR__ . '/../config/config.php';

/**
 * Get WebSocket server port from environment or use default.
 *
 * @var int|string $port
 */
$port = getenv('WS_PORT') ?: 9502;

/**
 * Ensure only one Swoole event loop is started per process.
 */
if (!\defined('SWOOLE_EVENT_LOOP_STARTED')) {
    /**
     * Define a constant to indicate the Swoole event loop has started.
     */
    \define('SWOOLE_EVENT_LOOP_STARTED', true);

    /**
     * Create and start the WebSocket server.
     *
     * @var WebSocketServer $webSocketServer
     */
    $webSocketServer = new WebSocketServer($port);
    $webSocketServer->start();
} else {
    /**
     * Log a message if the Swoole event loop is already started.
     */
    logDebug(__FILE__ , 'Swoole event loop already started. WebSocketServer not started again.');
}
