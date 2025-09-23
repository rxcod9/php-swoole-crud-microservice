<?php
namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class CompressionMiddleware implements MiddlewareInterface
{
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $next(); // call next middleware

        if (strpos($res->header['Content-Type'] ?? '', 'application/json') !== false) {
            $res->header('Content-Encoding', 'gzip');
            $res->end(gzencode($res->body ?? ''));
        }
    }
}
