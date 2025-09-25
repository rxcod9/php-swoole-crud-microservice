<?php

namespace App\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * Class Dispatcher
 *
 * Resolves and invokes controller actions using a dependency injection container.
 *
 * @package App\Core
 */
final class Dispatcher
{
    /**
     * Dispatcher constructor.
     *
     * @param Container $c Dependency Injection Container
     */
    public function __construct(private Container $c)
    {
        //
    }


    /**
     * Dispatch a controller action.
     *
     * Resolves the controller and method from the action string,
     * injects the request if supported, and invokes the method with parameters.
     *
     * @param string $action Action in the format 'Controller@method'
     * @param array $params Parameters to pass to the method
     * @param mixed $req Request object (optional)
     * @return array Response from the controller method
     *
     * @throws \InvalidArgumentException If the action format is invalid
     * @throws \RuntimeException If the controller or method does not exist
     */
    public function dispatch(string $action, array $params, $req = null): array
    {
        if (strpos($action, '@') === false) {
            throw new InvalidArgumentException("Action must be in 'Controller@method' format.");
        }

        [$ctrl, $method] = explode('@', $action, 2);
        $fqcn = "\\App\\Controllers\\$ctrl";

        if (!class_exists($fqcn)) {
            throw new RuntimeException("Controller class $fqcn does not exist.");
        }

        $controller = $this->c->get($fqcn);

        if (!method_exists($controller, $method)) {
            throw new RuntimeException("Method $method does not exist in controller $fqcn.");
        }

        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($req);
        }

        return $controller->{$method}($params);
    }
}
