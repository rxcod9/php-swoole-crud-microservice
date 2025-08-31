<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Core\MetricsServer;

$config = require __DIR__ . '/../config/config.php';
$port = getenv('METRICS_PORT') ?: 9310;

// Ensure only one Swoole event loop is started per process
if (!defined('SWOOLE_EVENT_LOOP_STARTED')) {
	define('SWOOLE_EVENT_LOOP_STARTED', true);
	$metricsServer = new MetricsServer($port);
	$metricsServer->start();
} else {
	error_log('Swoole event loop already started. MetricsServer not started again.');
}
