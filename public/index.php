<?php
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

ini_set('display_errors', 'On');
ini_set('display_startup_errors', 'On');
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\Servers\HttpServer;

// Set the default timezone for the application.
date_default_timezone_set('Asia/Kolkata');

// Load configuration settings.
$config = require __DIR__ . '/../config/config.php';

// Initialize the router.
$router = new Router();

/**
 * Define application routes.
 */

// Home route.
$router->get('/', 'IndexController@index');

// Health check route.
$router->get('/health', 'HealthController@check');
// $router->get('/health', 'HealthController@check', [new \App\Middlewares\RateLimitMiddleware()]);
$router->get('/health.html', 'HealthController@checkHtml');

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
