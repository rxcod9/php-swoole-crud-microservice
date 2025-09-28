<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Constants;
use App\Core\Container;

use function sprintf;

use Swoole\Http\Request;
use Swoole\Http\Response;

final class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $start = microtime(true);

        $next(); // call next middleware first

        $dur = microtime(true) - $start;
        echo sprintf(
            "[%s] %s %s - %.2fms\n",
            date(Constants::DATETIME_FORMAT),
            $req->server['request_method'] ?? '-',
            $req->server['request_uri'] ?? '-',
            $dur * 1000
        );
    }
}
