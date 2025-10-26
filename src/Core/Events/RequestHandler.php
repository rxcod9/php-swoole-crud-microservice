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
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Events\Request\HttpExchange;
use App\Core\Events\Request\RequestContext;
use App\Core\Events\Request\RequestMeta;
use App\Core\Http\Request as HttpRequest;
use App\Core\Http\Response as HttpResponse;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Throwable;

/**
 * Handles incoming HTTP requests by delegating to specialized components.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final readonly class RequestHandler
{
    /**
     * @SuppressWarnings("PHPMD.LongVariable")
     */
    public function __construct(
        private Container $container,
        private GlobalMiddlewareRegistrar $globalMiddlewareRegistrar,
        private RouteDispatcher $routeDispatcher,
        private HttpExceptionHandler $httpExceptionHandler,
        private RequestTelemetry $requestTelemetry
    ) {
        // Empty Constructor
    }

    public function __invoke(Request $request, Response $response): void
    {
        $httpReq = new HttpRequest($request);
        $httpRes = new HttpResponse($response);

        $requestMeta    = new RequestMeta(bin2hex(random_bytes(8)), microtime(true));
        $requestContext = new RequestContext(new HttpExchange($httpReq, $httpRes), $requestMeta);

        try {
            $pipeline = $this->globalMiddlewareRegistrar->createPipeline($this->container);
            $pipeline->handle(
                $httpReq,
                $httpRes,
                fn () => $this->routeDispatcher->dispatch($requestContext)
            );
        } catch (Throwable $throwable) {
            $this->httpExceptionHandler->handle($requestContext, $throwable);
        } finally {
            $this->requestTelemetry->collect($requestContext);
        }
    }
}
