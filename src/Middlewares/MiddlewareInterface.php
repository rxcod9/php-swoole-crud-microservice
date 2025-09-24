<?php
namespace App\Middlewares;

use Swoole\Http\Request;
use Swoole\Http\Response;
use App\Core\Container;

/**
 * Middleware interface for handling HTTP requests and responses.
 *
 * @package App\Middlewares
 * @version 1.0.0
 * @since 1.0.0
 * @author Your Name
 * @license MIT
 * @link https://your-repo-link
 */
interface MiddlewareInterface
{
    /**
     * Handle the incoming request.
     *
     * @param Request $req
     * @param Response $res
     * @param Container $container
     * @param callable $next Middleware must call $next() to continue the chain
     */
    public function handle(Request $req, Response $res, Container $container, callable $next): void;
}
