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
 * @author Your Name
 * @license MIT
 * @link https://your-repo-link
 */
final class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * MiddlewarePipeline constructor.
     */
    public function __construct()
    {
        // $this->middlewares[] = new \App\Middlewares\CorsMiddleware();
        // $this->middlewares[] = new \App\Middlewares\AuthMiddleware();
        // Add more middleware as needed
    }

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
        callable $finalHandler
    ): void {
        $index = 0;

        $runner = function () use (&$index, $req, $res, $container, &$runner, $finalHandler) {
            if ($index < count($this->middlewares)) {
                $middleware = $this->middlewares[$index++];
                $middleware->handle($req, $res, $container, $runner);
            } else {
                // All middleware called $next(), execute final handler
                $finalHandler();
            }
        };

        $runner();
    }
}
