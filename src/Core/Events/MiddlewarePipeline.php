<?php

namespace App\Core\Events;

use App\Core\Container;
use App\Middlewares\AuthMiddleware;
use Swoole\Http\Request;

final class MiddlewarePipeline
{
    /** @var array<int, object> */
    private array $middlewares = [];

    public function __construct()
    {
        $this->middlewares[] = new AuthMiddleware();
        // add more middlewares here if needed
    }

    public function handle(Request $req, Container $container): void
    {
        foreach ($this->middlewares as $middleware) {
            $middleware->handle($req, $container);
        }
    }
}
