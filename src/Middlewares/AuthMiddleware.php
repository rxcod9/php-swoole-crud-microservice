<?php

/**
 * src/Middlewares/AuthMiddleware.php
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
 * @link     https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/AuthMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class AuthMiddleware
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
final class AuthMiddleware implements MiddlewareInterface
{
    // allow unauthenticated paths
    private array $publicPaths = [
        '/',
        '/health',
        '/health.html',
        '/metrics',
        '/login',
        '/signup',
    ];

    /**
     * Handle the incoming request.
     *
     * @param callable $next Middleware must call $next() to continue the chain
     */
    public function handle(Request $request, Response $response, Container $container, callable $next): void
    {
        // Allow public paths without auth
        if (in_array($request->server['request_uri'], $this->publicPaths, true)) {
            $next();
            return;
        }

        // Simple auth check (e.g., check for Authorization header)
        $authHeader = $request->header['authorization'] ?? null;

        if (!$authHeader) {
            // Short-circuit: send response and DO NOT call $next
            $response->status(401);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode(['error' => 'Unauthorized']));
            return;
        }

        // Bind authenticated user
        $container->bind('currentUser', fn (): array => [
            'id'   => 1,
            'role' => 'admin',
        ]);

        // Must call $next() to continue the chain
        $next();
    }
}
