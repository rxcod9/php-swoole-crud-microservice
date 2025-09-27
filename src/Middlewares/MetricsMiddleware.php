<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use App\Core\Metrics;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class MetricsMiddleware implements MiddlewareInterface
{
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $start = microtime(true);

        $next(); // call next middleware

        $dur = microtime(true) - $start;
        $reg = Metrics::reg();
        $counter = $reg->getOrRegisterCounter('http_requests_total', 'Requests', 'Total HTTP requests', ['method', 'path', 'status']);
        $hist = $reg->getOrRegisterHistogram('http_request_seconds', 'Latency', 'HTTP request latency', ['method', 'path']);

        $path = parse_url($req->server['request_uri'] ?? '/', PHP_URL_PATH);
        $status = $res->status ?? 200;

        $counter->inc([$req->server['request_method'], $path, (string)$status]);
        $hist->observe($dur, [$req->server['request_method'], $path]);
    }
}
