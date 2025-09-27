<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Handle the incoming request.
     *
     * @param callable $next Middleware must call $next() to continue the chain
     *
     */
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $res->header('Access-Control-Allow-Origin', '*');
        $res->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $res->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        // respond immediately to OPTIONS
        if ($req->server['request_method'] === 'OPTIONS') {
            $res->status(204);
            $res->end();
            return;
        }
        $next();
    }
}
