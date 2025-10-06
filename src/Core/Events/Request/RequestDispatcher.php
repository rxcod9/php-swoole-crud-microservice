<?php

declare(strict_types=1);

namespace App\Core\Events\Request;

use App\Core\Container;
use App\Core\Events\MiddlewarePipeline;
use App\Core\Router;
use App\Core\Metrics;
use App\Core\Events\RequestLogger;
use App\Exceptions\ControllerNotFoundException;
use App\Exceptions\ControllerMethodNotFoundException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use RuntimeException;
use Swoole\Http\Server;
use Throwable;

/**
 * Resolves routes, invokes controllers, and records metrics/logs.
 *
 * @category Core
 * @package  App\Core\Events\Request
 */
final class RequestDispatcher
{
    private const CONTROLLER_NAMESPACE = 'App\\Controllers\\';

    public function __construct(
        private readonly Container $container,
        private readonly Server $server,
    ) {}

    /**
     * Dispatch route → controller/action → response
     */
    public function dispatch(Router $router, RequestContext $ctx): void
    {
        [$action, $params, $routeMiddlewares] = $router->match(
            $ctx->request->server['request_method'] ?? 'GET',
            $ctx->request->server['request_uri'] ?? '/'
        );

        $pipeline = new MiddlewarePipeline($this->container);
        $pipeline->addMiddlewares($routeMiddlewares);

        $pipeline->handle(
            $ctx->request,
            $ctx->response,
            fn() => $this->invokeAction($action, $params, $ctx)
        );
    }

    /**
     * Executes the controller or callable action.
     *
     * @throws ControllerNotFoundException
     * @throws ControllerMethodNotFoundException
     */
    private function invokeAction(callable|string $action, array $params, RequestContext $ctx): void
    {
        $method = $ctx->request->server['request_method'] ?? 'GET';
        $uri    = $ctx->request->server['request_uri'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH);

        $metrics = Metrics::reg();
        $counter = $metrics->getOrRegisterCounter(
            'http_requests_total', 'Requests', 'Total HTTP requests', ['method', 'path', 'status']
        );
        $histogram = $metrics->getOrRegisterHistogram(
            'http_request_seconds', 'Latency', 'HTTP request latency', ['method', 'path']
        );

        try {
            // === CONTROLLER@METHOD syntax ===
            if (is_string($action)) {
                [$controllerName, $methodName] = explode('@', $action);

                $fqcn = class_exists($controllerName)
                    ? $controllerName
                    : self::CONTROLLER_NAMESPACE . $controllerName;

                if (!class_exists($fqcn)) {
                    throw new ControllerNotFoundException(sprintf('Controller "%s" not found.', $fqcn));
                }

                $controller = $this->container->get($fqcn);

                if (!method_exists($controller, $methodName)) {
                    throw new ControllerMethodNotFoundException(sprintf(
                        'Method "%s" not found in controller "%s".', $methodName, $fqcn
                    ));
                }

                if (method_exists($controller, 'setContainer')) {
                    $controller->setContainer($this->container);
                }

                if (method_exists($controller, 'setRequest')) {
                    $controller->setRequest($ctx->request);
                }

                $payload = $controller->$methodName($params);
            } else {
                // === Closure or Callable ===
                $payload = $action($params);
            }

            $this->sendResponse($ctx->response, $payload);
        } catch (Throwable $t) {
            $this->handleActionError($ctx, $t);
            $counter->inc([$method, $path, '500']);
            return;
        }

        // === Metrics & Logging ===
        $duration = microtime(true) - $ctx->start;
        $status = $ctx->response->statusCode ?? 200;
        $counter->inc([$method, $path, (string)$status]);
        $histogram->observe($duration, [$method, $path]);

        $logger = new RequestLogger();
        $logger->log('debug', $this->server, $ctx->request, [
            'id'     => $ctx->reqId,
            'method' => $method,
            'path'   => $path,
            'status' => $status,
            'dur_ms' => (int) round($duration * 1000),
        ]);
    }

    /**
     * Formats and sends controller output.
     */
    private function sendResponse(Response $response, mixed $payload): void
    {
        if (is_array($payload) && isset($payload['__status'])) {
            $status = $payload['__status'];
            $ctype  = $payload['__contentType'] ?? 'application/json';
            $body   = $payload['__json'] ?? $payload['__html'] ?? $payload['__text'] ?? $payload;
            $cacheTagType   = $payload['__cacheTagType'] ?? null;
        } else {
            $status = 200;
            $ctype  = 'application/json';
            $body   = $payload;
        }

        $response->status($status);
        $response->header('Content-Type', $ctype);
        $response->header('X-Cache-Type', $cacheTagType);
        if($status === 204) {
            $response->end();
            return;
        }
        $response->end(is_scalar($body) ? (string)$body : json_encode($body));
    }

    /**
     * Handles controller or action-level exceptions.
     */
    private function handleActionError(RequestContext $ctx, Throwable $t): void
    {
        $ctx->response->status(500);
        $ctx->response->header('Content-Type', 'application/json');
        $ctx->response->end(json_encode([
            'error' => $t->getMessage(),
            'file'  => $t->getFile(),
            'line'  => $t->getLine(),
        ]));

        $logger = new RequestLogger();
        $logger->log('error', $this->server, $ctx->request, [
            'error' => $t->getMessage(),
            'trace' => $t->getTraceAsString(),
        ]);
    }
}
