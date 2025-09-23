<?php
namespace App\Middlewares;

use Swoole\Http\Request;
use Swoole\Http\Response;
use App\Core\Container;

interface MiddlewareInterface
{
    /**
     * Handle the incoming request.
     *
     * @param Request $req
     * @param Response $res
     * @param Container $container
     * @param callable $next Middleware must call $next() to continue the chain
     */
    public function handle(Request $req, Response $res, Container $container, callable $next): void;
}
