<?php

namespace App\Core\Events;

use App\Core\Container;
use App\Core\Dispatcher;
use App\Core\Router;
use Swoole\Http\Request;

final class RequestDispatcher
{
    public function __construct(private Router $router) {}

    public function dispatch(Request $req, Container $container): array
    {
        [$action, $params] = $this->router->match($req->server['request_method'], $req->server['request_uri']);
        return (new Dispatcher($container))->dispatch($action, $params, $req);
    }
}
