<?php

/**
 * src/Core/Servers/EventRegistrar.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Servers/EventRegistrar.php
 */
declare(strict_types=1);

namespace App\Core\Servers;

use App\Core\Container;
use App\Core\Events\{
    GlobalMiddlewareRegistrar,
    HttpExceptionHandler,
    RequestHandler,
    RequestTelemetry,
    RouteDispatcher,
    TaskFinishHandler,
    TaskRequestHandler,
    WorkerStartHandler
};
use App\Core\Pools\PDOPool;
use App\Core\Pools\PoolFacade;
use App\Core\Pools\RedisPool;
use App\Services\Cache\CacheService;
use Swoole\Http\Server;

/**
 * Class EventRegistrar
 * Handles all event registrar operations.
 *
 * @category  Core
 * @package   App\Core\Servers
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-22
 */
final class EventRegistrar
{
    public function register(Server $server, Container $container, TableManager $tableManager): void
    {
        $poolFacade = new PoolFacade(
            $container->get(PDOPool::class),
            $container->get(RedisPool::class),
            $container->get(CacheService::class)
        );

        $workerStartHandler = new WorkerStartHandler($tableManager->healthTable, $poolFacade);
        $workerManager      = new WorkerManager($workerStartHandler, $tableManager->healthTable);

        $server->on('WorkerStart', $workerStartHandler);
        $server->on('WorkerStop', fn (Server $server, int $id) => $workerManager->handleStop($id));
        $server->on('WorkerExit', fn (Server $server, int $id) => $workerManager->handleStop($id));
        $server->on(
            'WorkerError',
            fn (Server $server, int $id, int $pid, int $exit, int $sig) => $workerManager->handleError($id, $pid, $exit, $sig)
        );

        $requestHandler = new RequestHandler(
            $container,
            $container->get(GlobalMiddlewareRegistrar::class),
            $container->get(RouteDispatcher::class),
            $container->get(HttpExceptionHandler::class),
            $container->get(RequestTelemetry::class)
        );

        $server->on('request', $requestHandler);
        $server->on('task', new TaskRequestHandler($container));
        $server->on('finish', new TaskFinishHandler());
    }
}
