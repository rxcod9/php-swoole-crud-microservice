<?php
/**
 * Bootstrap file for initializing the test environment.
 *
 * Loads Composer's autoloader and sets the default timezone.
 *
 * @package php-swoole-crud-microservice
 */

require __DIR__ . '/../vendor/autoload.php';

// Set the default timezone for all date/time functions.
date_default_timezone_set('Asia/Kolkata');
