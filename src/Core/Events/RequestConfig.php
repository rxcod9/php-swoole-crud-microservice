<?php

/**
 * src/Core/Events/RequestServices.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: Consolidated request-related services for PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/RequestConfig.php
 */
declare(strict_types=1);

namespace App\Core\Events;

/**
 * Configuration container for request services.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class RequestConfig
{
    public function __construct(
        public array $globalMiddlewares = [],
        public string $defaultErrorMessage = \App\Core\Messages::ERROR_INTERNAL_ERROR
    ) {
    }
}
