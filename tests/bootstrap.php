<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration settings.
$config = require_once __DIR__ . '/../config/config.php';

// Set the default timezone for all date/time functions.
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kolkata');

\define('TEST_ENV', true);
