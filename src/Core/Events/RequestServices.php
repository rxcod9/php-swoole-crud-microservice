<?php

/**
 * src/Core/Events/RequestServices.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestServices.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Events\Request\RequestDispatcher;

/**
 * Encapsulates all request-related service factories.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final class RequestServices
{
    public function __construct(
        private readonly array $globalMiddlewares = [],
        private ?RequestDispatcher $requestDispatcher = null,
        private readonly string $defaultErrorMessage = 'Internal server error'
    ) {
        $this->requestDispatcher ??= new RequestDispatcher();
    }

    public function getGlobalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    public function getDefaultErrorMessage(): string
    {
        return $this->defaultErrorMessage;
    }

    public function createDispatcher(): RequestDispatcher
    {
        return $this->requestDispatcher;
    }

    public function createPipeline(): MiddlewarePipeline
    {
        return new MiddlewarePipeline();
    }
}
