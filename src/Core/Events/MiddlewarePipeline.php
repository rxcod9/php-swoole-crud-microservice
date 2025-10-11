<?php

/**
 * src/Core/Events/MiddlewarePipeline.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/MiddlewarePipeline.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * MiddlewarePipeline handles request/response middleware execution.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class MiddlewarePipeline
{
    public function __construct(private readonly Container $container)
    {
        //
    }

    /** @var array<callable|string> */
    private array $middlewares = [];

    /**
     * Add a single middleware to the pipeline.
     */
    public function addMiddleware(callable|string $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Add multiple middlewares at once.
     */
    public function addMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->addMiddleware($middleware);
        }
    }

    /**
     * Execute the pipeline.
     */
    public function handle(Request $request, Response $response, callable $finalHandler): void
    {
        $stack = $this->buildStack($finalHandler);
        $stack($request, $response);
    }

    /**
     * Convert the middleware list into a single callable stack.
     */
    private function buildStack(callable $finalHandler): callable
    {
        return array_reduce(
            array_reverse($this->middlewares),
            fn ($next, $middleware): \Closure => fn (Request $request, Response $response) => $this->invokeMiddleware($middleware, $request, $response, $next),
            $finalHandler
        );
    }

    /**
     * Invoke a middleware instance or callable without else expression.
     */
    private function invokeMiddleware(callable|string $middleware, Request $request, Response $response, callable $next): void
    {
        if (is_string($middleware)) {
            $middleware = $this->container->get($middleware);
            $middleware->handle($request, $response, $next);
            return;
        }

        $middleware($request, $response, $next);
    }
}
