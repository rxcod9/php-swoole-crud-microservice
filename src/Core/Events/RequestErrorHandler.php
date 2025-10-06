<?php

/**
 * src/Core/Events/RequestErrorHandler.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestErrorHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\LogIdentity;
use App\Core\Events\Request\Metrics;
use App\Core\Events\Request\RequestContext;
use App\Core\Events\Request\RequestLogContext;
use ReflectionClass;
use Swoole\Http\Server;
use Throwable;

/**
 * Handles error detection, logging, and friendly messages.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestErrorHandler
{
    public function __construct(private readonly string $defaultErrorMessage = 'An internal error occurred')
    {
    }

    public function handle(Throwable $throwable, Server $server, RequestContext $requestContext): never
    {
        $requestLogContext = new RequestLogContext(
            new LogIdentity(level: 'error', server: $server, requestContext: $requestContext),
            new Metrics(
                payload: [
                    'error' => $throwable->getMessage(),
                    'trace' => $throwable->getTraceAsString(),
                    'file'  => $throwable->getFile(),
                    'line'  => $throwable->getLine(),
                ],
                duration: microtime(true) - $requestContext->start
            )
        );

        $this->log($requestLogContext);
        throw $throwable; // Bubble up if needed
    }

    public function isAppException(Throwable $throwable): bool
    {
        $reflection = new ReflectionClass($throwable);
        $namespace = $reflection->getNamespaceName();
        return str_starts_with($namespace, 'App\\Exceptions');
    }

    public function getErrorMessage(Throwable $throwable): string
    {
        return $this->isAppException($throwable)
            ? $throwable->getMessage()
            : $this->defaultErrorMessage;
    }

    public function log(RequestLogContext $requestLogContext): void
    {
        // Actual logging implementation here (Monolog / async / Swoole)
        error_log(json_encode([
            'level'       => $requestLogContext->identity->level,
            'request'     => $requestLogContext->identity->requestContext->reqId,
            'duration_ms' => (int) round($requestLogContext->metrics->duration * 1000),
            'payload'     => $requestLogContext->metrics->payload,
        ]));
    }
}
