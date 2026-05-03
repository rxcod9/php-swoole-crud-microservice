<?php

/**
 * src/Core/Events/RouteDispatcher.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events\Routing
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RouteDispatcher.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Dispatcher;
use App\Core\Events\Request\RequestContext;
use App\Core\Router;

/**
 * Class RouteDispatcher
 * Handles all route dispatcher operations.
 *
 * @category  Core
 * @package   App\Core\Events\Routing
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final class RouteDispatcher
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container
    ) {
        // Empty Constructor
    }

    public function dispatch(RequestContext $requestContext): void
    {
        [$action, $params, $middlewares] = $this->router->match(
            $requestContext->exchange()->request()->getMethod(),
            $requestContext->exchange()->request()->getPath()
        );

        $middlewarePipeline = new MiddlewarePipeline($this->container);
        $middlewarePipeline->addMiddlewares($middlewares);

        $middlewarePipeline->handle(
            $requestContext->exchange()->request(),
            $requestContext->exchange()->response(),
            fn () => $this->runController($requestContext, $action, $params)
        );
    }

    /**
     * @param array<string, string> $params Params
     */
    private function runController(RequestContext $requestContext, string $action, array $params): void
    {
        $dispatcher = new Dispatcher($this->container);
        $response   = $dispatcher->dispatch(
            $action,
            $params,
            $requestContext->exchange()->request(),
            $requestContext->exchange()->response()
        );

        $response->send();
    }
}
