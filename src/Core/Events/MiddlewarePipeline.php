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
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/MiddlewarePipeline.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Middlewares\MiddlewareInterface;
use InvalidArgumentException;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Middleware pipeline to process HTTP requests through a series of middleware components.
 * Each middleware can modify the request/response and decide whether to continue the chain.
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

    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * Add a middleware to the pipeline
     */
    public function addMiddleware(MiddlewareInterface|string $middleware): void
    {
        // If a string class name is provided, resolve it from container
        if (is_string($middleware)) {
            if (!$this->container->has($middleware)) {
                throw new InvalidArgumentException(
                    sprintf('Middleware class "%s" is not in the container', $middleware)
                );
            }

            $middleware = $this->container->get($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new InvalidArgumentException(
                sprintf('Expected instance of MiddlewareInterface, got %s', get_debug_type($middleware))
            );
        }

        $this->middlewares[] = $middleware;
    }

    /**
     * Register multiple middlewares at once.
     */
    public function addMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            $this->addMiddleware($middleware);
        }
    }

    /**
     * Start the middleware chain
     */
    public function handle(
        Request $request,
        Response $response,
        callable $finalHandler
    ): void {
        $total = count($this->middlewares);

        $runner = function (int $index = 0) use ($request, $response, $total, $finalHandler, &$runner): void {
            if ($index >= $total) {
                $finalHandler();
                return;
            }

            $this->middlewares[$index]->handle(
                $request,
                $response,
                $this->container,
                fn () => $runner($index + 1)
            );
        };

        $runner();
    }
}
