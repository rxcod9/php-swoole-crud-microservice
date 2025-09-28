<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Middlewares\MiddlewareInterface;

use function count;

use InvalidArgumentException;

use function sprintf;

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
     * Register multiple middlewares at once.
     */
    public function addMiddlewares(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(
                    sprintf('Expected instance of MiddlewareInterface, got %s', get_debug_type($middleware))
                );
            }
            $this->addMiddleware($middleware);
        }
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
        $total = count($this->middlewares);

        $runner = function (int $index = 0) use ($req, $res, $container, $total, $finalHandler, &$runner) {
            if ($index < $total) {
                $this->middlewares[$index]->handle($req, $res, $container, fn () => $runner($index + 1));
            } else {
                $finalHandler();
            }
        };

        $runner();
    }
}
