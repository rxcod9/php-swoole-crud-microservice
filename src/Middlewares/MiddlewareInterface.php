<?php

/**
 * src/Middlewares/MiddlewareInterface.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Middlewares/MiddlewareInterface.php
 */
declare(strict_types=1);

namespace App\Middlewares;

use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Middleware interface for handling HTTP requests and responses.
 *
 * @category  Middlewares
 * @package   App\Middlewares
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://your-repo-link
 */
interface MiddlewareInterface
{
    /**
     * Handle the incoming request.
     *
     * @param callable $next Middleware must call $next() to continue the chain
     */
    public function handle(Request $request, Response $response, callable $next): void;
}
