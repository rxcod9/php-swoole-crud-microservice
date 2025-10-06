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

use App\Core\Events\Request\RequestMetricsLogger;

/**
 * Encapsulates dependencies for RequestServiceFacade.
 * - Logger
 * - Global middleware
 * - Default error message
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class RequestServiceDependencies
{
    /**
     * @param RequestMetricsLogger $logger            Logger instance
     * @param array<class-string>  $globalMiddlewares Optional global middleware
     * @param string               $defaultErrorMessage Default message for unknown exceptions
     */
    public function __construct(
        public RequestMetricsLogger $logger,
        public array $globalMiddlewares = [],
        public string $defaultErrorMessage = \App\Core\Messages::ERROR_INTERNAL_ERROR
    ) {
    }
}
