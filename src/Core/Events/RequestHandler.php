<?php

declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Events\Request\RequestContext;
use App\Core\Events\Request\RequestDispatcher;
use App\Core\Metrics;
use App\Core\Messages;
use App\Core\Router;
use App\Core\Events\RequestLogger;
use App\Core\Events\WorkerReadyChecker;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\SecurityHeadersMiddleware;
use App\Middlewares\RateLimitMiddleware;
use App\Middlewares\HideServerHeaderMiddleware;
use App\Middlewares\CompressionMiddleware;
use ReflectionClass;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Throwable;

/**
 * Handles the full HTTP lifecycle — from request entry to response dispatch,
 * with middleware orchestration, metrics, and structured logging.
 *
 * @category  Core
 * @package   App\Core\Events
 */
final readonly class RequestHandler
{
    public function __construct(
        private Router $router,
        private Server $server,
        private Container $container
    ) {}

    /**
     * Invoked by Swoole for every incoming HTTP request.
     */
    public function __invoke(Request $request, Response $response): void
    {
        try {
            // Ensure worker is ready before handling requests
            (new WorkerReadyChecker())->wait();

            $reqId = bin2hex(random_bytes(8));
            $start = microtime(true);

            // Build request-scoped context
            $requestContext = new RequestContext($request, $response, $reqId, $start);

            // Setup global middleware pipeline
            $pipeline = new MiddlewarePipeline($this->container);
            $this->registerGlobalMiddlewares($pipeline);

            // Pass control to the dispatcher
            $dispatcher = new RequestDispatcher($this->container, $this->server);

            $pipeline->handle(
                $request,
                $response,
                fn() => $dispatcher->dispatch($this->router, $requestContext)
            );
        } catch (Throwable $throwable) {
            $this->handleException($request, $response, $throwable);
        }
    }

    /**
     * Register global middlewares — always executed before route-specific ones.
     */
    private function registerGlobalMiddlewares(MiddlewarePipeline $pipeline): void
    {
        $pipeline->addMiddleware(CorsMiddleware::class);
        $pipeline->addMiddleware(SecurityHeadersMiddleware::class);
        $pipeline->addMiddleware(RateLimitMiddleware::class);
        $pipeline->addMiddleware(HideServerHeaderMiddleware::class);
        $pipeline->addMiddleware(CompressionMiddleware::class);
    }

    /**
     * Centralized exception handling — ensures response and logs.
     */
    private function handleException(Request $request, Response $response, Throwable $throwable): void
    {
        $status = is_int($throwable->getCode()) && $throwable->getCode() >= 100 && $throwable->getCode() < 600
            ? $throwable->getCode()
            : 500;

        $response->header('Content-Type', 'application/json');
        $response->status($status);
        $response->end(json_encode([
            'error' => $this->getErrorMessage($throwable),
            'code'  => $status,
            'file'  => $throwable->getFile(),
            'line'  => $throwable->getLine(),
        ]));

        $logger = new RequestLogger();
        $logger->log('error', $this->server, $request, [
            'error' => $throwable->getMessage(),
            'code'  => $status,
            'trace' => $throwable->getTraceAsString(),
            'file'  => $throwable->getFile(),
            'line'  => $throwable->getLine(),
        ]);
    }

    private function getErrorMessage(Throwable $throwable): string
    {
        return $this->isAppException($throwable)
            ? $throwable->getMessage()
            : Messages::ERROR_INTERNAL_ERROR;
    }

    private function isAppException(Throwable $throwable): bool
    {
        $ns = (new ReflectionClass($throwable))->getNamespaceName();
        return str_starts_with($ns, 'App\\Exceptions');
    }
}
