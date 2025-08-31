<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Core\WebSocketServer;

$config = require __DIR__ . '/../config/config.php';
$port = getenv('WS_PORT') ?: 9502;

// Ensure only one Swoole event loop is started per process
if (!defined('SWOOLE_EVENT_LOOP_STARTED')) {
	define('SWOOLE_EVENT_LOOP_STARTED', true);
	$webSocketServer = new WebSocketServer($port);
	$webSocketServer->start();
} else {
	error_log('Swoole event loop already started. WebSocketServer not started again.');
}
