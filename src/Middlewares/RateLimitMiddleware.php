<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Container;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Table;

final class RateLimitMiddleware implements MiddlewareInterface
{
    // private Table $table;

    public function __construct()
    {
        // $this->table = $table; // Swoole table for storing IP counts
    }

    public function handle(Request $req, Response $res, Container $c, callable $next): void
    {
        $ip = $req->server['remote_addr'] ?? 'unknown';
        $limit = 10; // requests per minute
        $key = "rate:{$ip}";

        $count = $this->table->get($key)['count'] ?? 0;
        if ($count >= $limit) {
            $res->status(429);
            $res->header('Content-Type', 'application/json');
            $res->end(json_encode(['error' => 'Too many requests']));
            return;
        }

        $this->table->set($key, ['count' => $count + 1]);
        $next();
    }
}
