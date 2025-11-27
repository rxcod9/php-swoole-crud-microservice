<?php

/**
 * src/Core/Events/HttpExceptionHandler.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.5
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/HttpExceptionHandler.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestContext;
use App\Core\Messages;
use ReflectionClass;
use Throwable;

/**
 * Class HttpExceptionHandler
 * Handles all http exception operations.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final class HttpExceptionHandler
{
    public function handle(RequestContext $requestContext, Throwable $throwable): void
    {
        $status  = is_int($throwable->getCode()) && $throwable->getCode() > 0 ? $throwable->getCode() : 500;
        $payload = $this->buildErrorPayload($throwable, $status);

        $response = $requestContext->exchange()->response();
        $response->setHeader('Content-Type', 'application/json');
        $response->setStatus($status);
        $response->setBody(json_encode($payload));
        $response->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildErrorPayload(Throwable $throwable, int $status): array
    {
        return [
            'code'       => $status,
            'file'       => $throwable->getFile(),
            'line'       => $throwable->getLine(),
            'error'      => $this->getErrorMessage($throwable),
            'error_full' => $throwable->getMessage(),
            'trace'      => $throwable->getTraceAsString(),
        ];
    }

    private function getErrorMessage(Throwable $throwable): string
    {
        return $this->isAppException($throwable)
            ? $throwable->getMessage()
            : Messages::ERROR_INTERNAL_ERROR;
    }

    private function isAppException(Throwable $throwable): bool
    {
        $reflectionClass = new ReflectionClass($throwable);
        $ns              = $reflectionClass->getNamespaceName();
        return str_starts_with($ns, 'App\\Exceptions');
    }
}
