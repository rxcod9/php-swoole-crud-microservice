<?php

namespace App\Core\Events;

use App\Core\Container;
use App\Middlewares\MiddlewareInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Middleware pipeline to process HTTP requests through a series of middleware components.
 * Each middleware can modify the request/response and decide whether to continue the chain.
 * 
 * @package App\Core\Events
 * @version 1.0.0
 * @since 1.0.0
 */
final class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * Add a middleware to the pipeline
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Start the middleware chain
     */
    public function handle(
        Request $req,
        Response $res,
        Container $container,
        array $routeMiddlewares,
        callable $finalHandler
    ): void {
        // Merge once, not on every $next()
        $allMiddlewares = [...$this->middlewares, ...$routeMiddlewares];
        $total = count($allMiddlewares);

        $runner = function (int $index = 0) use ($req, $res, $container, $allMiddlewares, $total, $finalHandler, &$runner) {
            if ($index < $total) {
                $allMiddlewares[$index]->handle($req, $res, $container, fn() => $runner($index + 1));
            } else {
                $finalHandler();
            }
        };

        $runner();
    }
}
