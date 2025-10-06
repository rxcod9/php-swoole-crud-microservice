<?php

/**
 * src/Core/Events/GlobalMiddlewareProvider.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Events/GlobalMiddlewareProvider.php
 */
declare(strict_types=1);

namespace App\Core\Events;

/**
 * Provides global middleware for all requests.
 *
 * @category  Core
 * @package   App\Core\Events
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-05
 */
final readonly class GlobalMiddlewareProvider
{
    /** @var array<class-string> */
    private array $middlewares;

    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares !== [] ? $middlewares : [
            \App\Middlewares\CorsMiddleware::class,
            \App\Middlewares\SecurityHeadersMiddleware::class,
            \App\Middlewares\RateLimitMiddleware::class,
            \App\Middlewares\HideServerHeaderMiddleware::class,
            \App\Middlewares\CompressionMiddleware::class,
        ];
    }

    public function get(): array
    {
        return $this->middlewares;
    }
}
