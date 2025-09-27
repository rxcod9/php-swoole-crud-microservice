<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class CompressionMiddleware implements MiddlewareInterface
{
    /**
     * Compress JSON responses using gzip.
     */
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $next(); // call next middleware

        if (strpos($res->header['Content-Type'] ?? '', 'application/json') !== false) {
            $res->header('Content-Encoding', 'gzip');
            $res->end(gzencode($res->body ?? ''));
        }
    }
}
