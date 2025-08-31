<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\HttpServer;

date_default_timezone_set('Asia/Kolkata');
$config = require __DIR__ . '/../config/config.php';
$router = new Router();
// Users CRUD
$router->post('/users', 'UserController@create');
$router->get('/users', 'UserController@index');
$router->get('/users/{id}', 'UserController@show');
$router->put('/users/{id}', 'UserController@update');
$router->delete('/users/{id}', 'UserController@destroy');
// Items CRUD
$router->post('/items', 'ItemController@create');
$router->get('/items', 'ItemController@index');
$router->get('/items/{id}', 'ItemController@show');
$router->put('/items/{id}', 'ItemController@update');
$router->delete('/items/{id}', 'ItemController@destroy');
// Health/metrics
$router->get('/healthz', 'HealthController@check');
// $router->get('/serveMetrics', 'MetricsController@index');

$server = new HttpServer($config, $router);
$server->start();
