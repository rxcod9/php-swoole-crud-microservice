<?php

namespace App\Core;

final class Dispatcher
{
    public function __construct(private Container $c)
    {
    }
    public function dispatch(string $action, array $params, $req = null): array
    {
        [$ctrl, $method] = explode('@', $action);
        $fqcn = "\\App\\Controllers\\$ctrl";
        $controller = $this->c->get($fqcn);
        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($req);
        }
        // if (method_exists($controller, 'setResponse')) {
        //     $controller->setResponse($res);
        // }
        return $controller->$method($params);
    }
}
