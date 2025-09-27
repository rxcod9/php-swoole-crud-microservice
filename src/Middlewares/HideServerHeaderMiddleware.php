<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class HideServerHeaderMiddleware implements MiddlewareInterface
{
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        // Remove default server header
        $res->header('Server', null);

        $next();
    }
}
