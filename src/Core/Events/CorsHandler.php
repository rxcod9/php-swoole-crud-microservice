<?php

declare(strict_types=1);

namespace App\Core\Events;

use Swoole\Http\Request;
use Swoole\Http\Response;

final class CorsHandler
{
    public function handle(Request $req, Response $res): bool
    {
        $res->header('Access-Control-Allow-Origin', '*');
        $res->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $res->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        if ($req->server['request_method'] === 'OPTIONS') {
            $res->status(204);
            $res->end();
            return true; // handled
        }

        return false; // continue
    }
}
