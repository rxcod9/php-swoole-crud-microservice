<?php

/**
 * src/Core/Dispatcher.php
 * Project: rxcod9/php-swoole-crud-microservice
 * Description: PHP Swoole CRUD Microservice
 * PHP version 8.4
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Dispatcher.php
 */
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\ControllerMethodNotFoundException;
use InvalidArgumentException;
use Symfony\Component\VarExporter\Exception\ClassNotFoundException;

/**
 * Class Dispatcher
 * Resolves and invokes controller actions using a dependency injection container.
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final readonly class Dispatcher
{
    /**
     * Dispatcher constructor.
     *
     * @param Container $container Dependency Injection Container
     */
    public function __construct(private Container $container)
    {
        //
    }

    /**
     * Dispatch a controller action.
     *
     * Resolves the controller and method from the action string,
     * injects the request if supported, and invokes the method with parameters.
     *
     * @param string            $action Action in the format 'Controller@method'
     * @param array<string, string> $params Parameters to pass to the method
     * @param mixed             $req    Request object (optional)
     *
     * @throws InvalidArgumentException If the action format is invalid OR If the resolved controller is not an object
     * @throws ClassNotFoundException If the controller class does not exist
     * @throws ControllerMethodNotFoundException If the method does not exist in the controller
     *
     * @return array<string, mixed> Response from the controller method
     */
    public function dispatch(string $action, array $params, mixed $req = null): array
    {
        if (strpos($action, '@') === false) {
            throw new InvalidArgumentException("Action must be in 'Controller@method' format.");
        }

        [$ctrl, $method] = explode('@', $action, 2);
        $fqcn            = '\App\Controllers\\' . $ctrl;

        if (!class_exists($fqcn)) {
            throw new ClassNotFoundException(sprintf('Controller class %s does not exist.', $fqcn));
        }

        $controller = $this->container->get($fqcn);

        if (!is_object($controller)) {
            throw new InvalidArgumentException(sprintf('Controller %s is not a valid object.', $fqcn));
        }

        if (!method_exists($controller, $method)) {
            throw new ControllerMethodNotFoundException(sprintf('Method %s does not exist in controller %s.', $method, $fqcn));
        }

        if (method_exists($controller, 'setContainer')) {
            $controller->setContainer($this->container);
        }

        if (method_exists($controller, 'setRequest')) {
            $controller->setRequest($req);
        }

        /** @var object $controller */
        return $controller->{$method}($params);
    }
}
