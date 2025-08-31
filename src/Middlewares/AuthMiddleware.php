<?php

namespace App\Middlewares;

use App\Core\Container;

final class AuthMiddleware
{
    public function handle($req, Container $c): void
    {
      // parse token/session from headers; attach user to container if needed
        $c->bind('currentUser', fn()=> ['id' => 0,'role' => 'guest']);
    }
}
