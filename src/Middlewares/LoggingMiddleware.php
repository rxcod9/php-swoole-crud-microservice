<?php

/**
 * src/Middlewares/LoggingMiddleware.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/LoggingMiddleware.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Class LoggingMiddleware
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
final class LoggingMiddleware implements MiddlewareInterface
{
    public const TAG = 'LoggingMiddleware';

    public function handle(Request $request, Response $response, callable $next): void
    {
        $start = microtime(true);

        $next($request, $response); // call next middleware first

        $dur = microtime(true) - $start;
        logDebug(self::TAG . ':' . __LINE__ . '] [' . __FUNCTION__, sprintf(
            "[%s] %s %s - %.2fms\n",
            $request->server['request_method'] ?? '-',
            $request->server['request_uri'] ?? '-',
            $response->status ?? '-',
            $dur * 1000
        ));
    }
}
