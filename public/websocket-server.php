<?php

/**
 * WebSocket Server Bootstrap File
 *
 * This file initializes and starts the WebSocket server using Swoole.
 * It ensures that only one Swoole event loop is started per process.
 *
 * PHP version 7.4+
 *
 * @package   App\Public
 * @author    Your Name
 * @copyright Copyright (c) 2024
 * @license   MIT
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Servers\WebSocketServer;

/**
 * Load application configuration.
 *
 * @var array $config
 */
$config = require __DIR__ . '/../config/config.php';

/**
 * Get WebSocket server port from environment or use default.
 *
 * @var int|string $port
 */
$port = getenv('WS_PORT') ?: 9502;

/**
 * Ensure only one Swoole event loop is started per process.
 */
if (!defined('SWOOLE_EVENT_LOOP_STARTED')) {
	/**
	 * Define a constant to indicate the Swoole event loop has started.
	 */
	define('SWOOLE_EVENT_LOOP_STARTED', true);

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
	error_log('Swoole event loop already started. WebSocketServer not started again.');
}
