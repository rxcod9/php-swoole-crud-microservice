<?php

/**
 * src/Core/Events/RouteDispatcher.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RouteDispatcher.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestContext;
use App\Core\Events\Request\RequestDispatcher;
use App\Core\Router;

/**
 * Handles dispatching of routes with their middleware.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RouteDispatcher
{
    public function __construct(private readonly RequestDispatcher $requestDispatcher)
    {
    }

    public function dispatch(Router $router, RequestContext $requestContext): void
    {
        [$action, $params, $routeMiddlewares] = $router->match(
            $requestContext->request->server['request_method'],
            $requestContext->request->server['request_uri']
        );

        $middlewarePipeline = new MiddlewarePipeline();
        $middlewarePipeline->addMiddlewares($routeMiddlewares);

        $middlewarePipeline->handle($requestContext->request, $requestContext->response, fn () => $this->requestDispatcher->dispatch($action, $params));
    }
}
