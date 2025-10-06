<?php

/**
 * src/Core/Events/RequestHandler.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestContext;
use App\Core\Events\Request\RequestDispatcher;
use App\Core\Events\Request\RequestMetricsLogger;
use App\Core\Router;
use Swoole\Http\Server;
use Throwable;

/**
 * Handles dispatching, metrics logging, and error handling in a single service.
 * This class reduces coupling for RequestServiceFacade.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestHandlerNew
{
    public function __construct(
        private readonly RequestDispatcher $requestDispatcher,
        private readonly RequestMetricsLogger $requestMetricsLogger,
        private readonly RequestErrorHandler $requestErrorHandler
    ) {
    }

    /**
     * Create a request context with ID and timestamp.
     */
    public function createContext(mixed $request, mixed $response): RequestContext
    {
        $reqId = bin2hex(random_bytes(8));
        $start = microtime(true);
        return new RequestContext($request, $response, $reqId, $start);
    }

    /**
     * Handle request dispatch with metrics and error logging.
     */
    public function handle(Router $router, RequestContext $requestContext, Server $server): void
    {
        try {
            // Dispatch route + controller
            $this->requestDispatcher->dispatch($router, $requestContext);

            // Log metrics
            $this->requestMetricsLogger->log($server, $requestContext);
        } catch (Throwable $throwable) {
            // Handle errors (App exceptions or generic)
            $this->requestErrorHandler->handle($throwable, $server, $requestContext);
        }
    }
}
