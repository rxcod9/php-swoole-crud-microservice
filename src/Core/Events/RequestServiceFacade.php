<?php

/**
 * src/Core/Events/RequestServiceFacade.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestServiceFacade.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestContext;
use App\Core\Router;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

/**
 * Facade for request-related services.
 * Responsibilities:
 * - Create request context
 * - Register global middleware
 * - Handle request dispatch, metrics, and error logging via RequestHandler
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class RequestServiceFacade
{
    /**
     * Default global middlewares.
     *
     * @var array<class-string>
     */
    private const DEFAULT_MIDDLEWARES = [
        \App\Middlewares\CorsMiddleware::class,
        \App\Middlewares\SecurityHeadersMiddleware::class,
        \App\Middlewares\RateLimitMiddleware::class,
        \App\Middlewares\HideServerHeaderMiddleware::class,
        \App\Middlewares\CompressionMiddleware::class,
    ];

    /** @var array<class-string> Global middlewares to register */
    private array $globalMiddlewares;

    public function __construct(
        RequestServices $requestServices,
        private RequestHandler $requestHandler
    ) {
        // Use provided middlewares or fallback to default
        $this->globalMiddlewares = $requestServices->getGlobalMiddlewares() ?: self::DEFAULT_MIDDLEWARES;
    }

    /**
     * Create a request context with unique ID and timestamp.
     */
    public function createContext(Request $request, Response $response): RequestContext
    {
        return $this->requestHandler->createContext($request, $response);
    }

    /**
     * Register global middleware on a pipeline.
     */
    public function registerGlobalMiddlewares(mixed $pipeline): void
    {
        $pipeline->registerGlobal($this->globalMiddlewares);
    }

    /**
     * Dispatch a request via RequestHandler (dispatcher + metrics + error handling).
     */
    public function handleRequest(Router $router, RequestContext $requestContext, Server $server): void
    {
        $this->requestHandler->handle($router, $requestContext, $server);
    }
}
