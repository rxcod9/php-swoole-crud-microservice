<?php

namespace App\Middlewares;

use App\Core\Container;

/**
 * Class AuthMiddleware
 *
 * Middleware responsible for authenticating requests.
 * Parses authentication tokens or sessions from request headers,
 * and attaches the current user information to the container.
 *
 * @package App\Middlewares
 */
final class AuthMiddleware
{
  /**
   * Handles authentication for the incoming request.
   *
   * @param mixed $req The incoming request object.
   * @param Container $c The dependency injection container.
   * 
   * @return void
   */
  public function handle($req, Container $c): void
  {
    // TODO: Parse token/session from headers and authenticate user.
    // For now, attach a default guest user to the container.
    $c->bind('currentUser', fn () => [
      'id' => 0,
      'role' => 'guest'
    ]);
  }
}
