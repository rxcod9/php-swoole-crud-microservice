<?php

/**
 * src/Middlewares/CompressionMiddleware.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category Middlewares
 * @package  App\Middlewares
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/CompressionMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class CompressionMiddleware
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category Middlewares
 * @package  App\Middlewares
 * @author   Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @license  MIT
 * @version  1.0.0
 * @since    2025-10-02
 */
final class CompressionMiddleware implements MiddlewareInterface
{
    /**
     * Compress JSON responses using gzip.
     */
    public function handle(Request $request, Response $response, Container $container, callable $next): void
    {
        $next(); // call next middleware

        if (strpos($response->header['Content-Type'] ?? '', 'application/json') !== false) {
            $response->header('Content-Encoding', 'gzip');
            $response->end(gzencode($response->body ?? ''));
        }
    }
}
