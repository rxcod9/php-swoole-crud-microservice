<?php

/**
 * src/Core/Events/GlobalMiddlewareRegistrar.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/GlobalMiddlewareRegistrar.php
 */
declare(strict_types=1);

namespace App\Core\Events;

use App\Core\Container;
use App\Middlewares\{CorsMiddleware, HideServerHeaderMiddleware, LoggingMiddleware, RateLimitMiddleware, SecurityHeadersMiddleware};

/**
 * Class GlobalMiddlewareRegistrar
 * Handles all global middleware registrar operations.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-23
 */
final class GlobalMiddlewareRegistrar
{
    public function createPipeline(Container $container): MiddlewarePipeline
    {
        $middlewarePipeline = new MiddlewarePipeline($container);

        $middlewarePipeline->addMiddlewares([
            LoggingMiddleware::class,
            HideServerHeaderMiddleware::class,
            SecurityHeadersMiddleware::class,
            CorsMiddleware::class,
            RateLimitMiddleware::class,
        ]);

        return $middlewarePipeline;
    }
}
