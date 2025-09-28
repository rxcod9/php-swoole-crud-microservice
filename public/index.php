<?php

declare(strict_types=1);

/**
 * Entry point for the Swoole CRUD Microservice.
 *
 * This file initializes the application, sets up routing for Users and Items CRUD operations,
 * and starts the HTTP server using Swoole.
 *
 * PHP version 7.4+
 *
 * @package   App
 * @author    Your Name
 * @copyright Copyright (c) 2024
 * @license   MIT
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Servers\HttpServer;
use Dotenv\Dotenv;

// Load .env file from project root
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Optional: validate required env variables
$dotenv->required([
    // 'APP_ENV',
    // 'APP_DEBUG',
    // 'SWOOLE_WORKER_NUM',
    // 'SWOOLE_TASK_WORKER_NUM',
    // 'SWOOLE_MAX_REQUEST',
    // 'SSL_ENABLE',
    'DB_DRIVER',
    'DB_HOST',
    'DB_DSN',
    'DB_USER',
    'DB_PASS',
    'DB_DATABASE',
    // 'DB_CHARSET',
    // 'DB_TIMEOUT',
    // 'DB_POOL_MIN',
    // 'DB_POOL_MAX',
    'REDIS_HOST',
    'REDIS_PORT',
    // 'REDIS_PASSWORD',
    // 'REDIS_DATABASE',
    // 'REDIS_POOL_MIN',
    // 'REDIS_POOL_MAX',
    // 'QUEUE_MAX_PENDING',
])->notEmpty();

// Load configuration settings.
$config = require_once __DIR__ . '/../config/config.php';

// Set the default timezone for the application.
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kolkata');

// Initialize the router.
$router = new Router();

/**
 * Define application routes.
 */

// Home route.
$router->get('/', 'IndexController@index');

// Health check route.
$router->get('/health', 'HealthController@check');
$router->get('/health.html', 'HealthController@checkHtml'); // , [new \App\Middlewares\RateLimitMiddleware()]);

// Metrics route.
$router->get('/metrics', 'MetricsController@check');

// Users CRUD routes.
$router->post('/users', 'UserController@create');      // Create a new user.
$router->get('/users', 'UserController@index');        // List all users.
$router->get('/users/{id}', 'UserController@show');    // Show a specific user.
$router->get('/users/email/{email}', 'UserController@showByEmail');    // Show a specific user by email.
$router->put('/users/{id}', 'UserController@update');  // Update a specific user.
$router->delete('/users/{id}', 'UserController@destroy'); // Delete a specific user.

// Items CRUD routes.
$router->post('/items', 'ItemController@create');      // Create a new item.
$router->get('/items', 'ItemController@index');        // List all items.
$router->get('/items/{id}', 'ItemController@show');    // Show a specific item.
$router->get('/items/sku/{sku}', 'ItemController@showBySku');    // Show a specific item by SKU.
$router->put('/items/{id}', 'ItemController@update');  // Update a specific item.
$router->delete('/items/{id}', 'ItemController@destroy'); // Delete a specific item.

// Initialize and start the HTTP server.
$server = new HttpServer($config, $router);
$server->start();
