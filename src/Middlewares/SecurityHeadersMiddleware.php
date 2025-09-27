<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $res->header('X-Frame-Options', 'DENY');
        $res->header('X-Content-Type-Options', 'nosniff');
        $res->header('X-XSS-Protection', '1; mode=block');
        $res->header('Referrer-Policy', 'no-referrer');

        $next();
    }
}
