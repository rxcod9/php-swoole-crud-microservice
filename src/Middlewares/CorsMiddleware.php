<?php

/**
 * src/Middlewares/CorsMiddleware.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/CorsMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Config;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Class CorsMiddleware
 * Handles all user-related operations such as creation, update,
 * deletion, and retrieval. Integrates with external services and
 * logs critical operations.
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Config $config)
    {
        //
    }

    /**
     * Handle the incoming request.
     *
     * @param callable $next Middleware must call $next() to continue the chain
     */
    public function handle(Request $request, Response $response, callable $next): void
    {
        $cors           = $this->config->get('cors') ?? [];
        $originsAllowed = $cors['origin'] ?? null;
        if ($originsAllowed !== null) {
            $response->setHeader('Access-Control-Allow-Origin', $originsAllowed);
        }

        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Cache-Type, Retry-After, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
        // respond immediately to OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response->setStatus(204);
            $response->send();
            return;
        }

        $next($request, $response);
    }
}
