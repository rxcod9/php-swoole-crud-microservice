<?php
namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    // allow unauthenticated paths
    private array $publicPaths = [
        '/',
        '/health',
        '/health.html',
        '/metrics',
        '/login',
        '/signup'
    ];

    /**
     * Handle the incoming request.
     *
     * @param Request $req
     * @param Response $res
     * @param Container $c
     * @param callable $next Middleware must call $next() to continue the chain
     * @return void
     */
    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        // Allow public paths without auth
        if (in_array($req->server['request_uri'], $this->publicPaths, true)) {
            $next();
            return;
        }
    
        // Simple auth check (e.g., check for Authorization header)
        $authHeader = $req->header['authorization'] ?? null;

        if (!$authHeader) {
            // Short-circuit: send response and DO NOT call $next
            $res->status(401);
            $res->header('Content-Type', 'application/json');
            $res->end(json_encode(['error' => 'Unauthorized']));
            return;
        }

        // Bind authenticated user
        $c->bind('currentUser', fn () => [
            'id' => 1,
            'role' => 'admin'
        ]);

        // Must call $next() to continue the chain
        $next();
    }
}
