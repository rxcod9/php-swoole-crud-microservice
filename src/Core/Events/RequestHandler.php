<?php

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Router;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Table;

/**
 * Handles incoming HTTP requests, including routing, middleware, and response generation.
 * Also manages CORS headers and preflight requests.
 * Binds request-scoped dependencies like DbContext and connection pools.
 * Logs request details asynchronously.
 * Provides health check endpoints.
 * Ensures worker readiness before processing requests.
 * 
 * @package App\Core\Events
 * @version 1.0.0
 * @since 1.0.0
 * @author Your Name
 * @license MIT
 * @link https://your-repo-link
 */
final class RequestHandler
{
    public function __construct(
        private Router $router,
        private Server $server,
        private Table $table,
        private Container $container
    ) {}

    public function __invoke(Request $req, Response $res): void
    {
        $cors = new CorsHandler();
        if ($cors->handle($req, $res)) return;

        try {
            (new WorkerReadyChecker())->wait();

            $reqId = bin2hex(random_bytes(8));
            $start = microtime(true);

            (new PoolBinder())->bind($this->server, $this->container);
            (new MiddlewarePipeline())->handle($req, $this->container);

            // // Metrics collection
            // $reg = Metrics::reg();
            // $counter = $reg->getOrRegisterCounter(
            //     'http_requests_total',
            //     'Requests',
            //     'Total HTTP requests',
            //     ['method', 'path', 'status']
            // );
            // $hist = $reg->getOrRegisterHistogram(
            //     'http_request_seconds',
            //     'Latency',
            //     'HTTP request latency',
            //     ['method', 'path']
            // );

            $payload = (new RequestDispatcher($this->router))->dispatch($req, $this->container);

            $path = parse_url($req->server['request_uri'] ?? '/', PHP_URL_PATH);
            $status = $payload['__status'] ?? 200;
            $json = $payload['__json'] ?? null;
            $html = $payload['__html'] ?? null;
            $text = $payload['__text'] ?? null;

            # if $path ends with .html the non -json response
            if ($html) {
                $res->header('Content-Type', 'text/html');
                $res->end($status === 204 ? '' : $html);
            } elseif ($text) {
                $res->header('Content-Type', 'text/plain');
                $res->end($status === 204 ? '' : $text);
            } else {
                $res->header('Content-Type', 'application/json');
                $res->end($status === 204 ? '' : json_encode($json ?: $payload));
            }
            $res->status($status);

            // Metrics and async logging
            $dur = microtime(true) - $start;

            // $counter->inc([$req->server['request_method'], $path, (string)$status]);
            // $hist->observe($dur, [$req->server['request_method'], $path]);

            (new RequestLogger())->log(
                $this->server,
                $req,
                [
                    'id' => $reqId,
                    'method' => $req->server['request_method'],
                    'path' => $path,
                    'status' => $status,
                    'dur_ms' => (int)round($dur * 1000)
                ]
            );
        } catch (\Throwable $e) {
            $status = $e->getCode() ?: 500;
            $res->header('Content-Type', 'application/json');
            $res->status($status);
            $res->end(json_encode(['error' => $e->getMessage()]));

            (new RequestLogger())->log(
                $this->server,
                $req,
                [
                    'error' => $e->getMessage()
                ]
            );
        }
    }
}
