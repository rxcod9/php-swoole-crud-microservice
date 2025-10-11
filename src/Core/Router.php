<?php

/**
 * src/Core/Router.php
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
 * @link      https://github.com/rxcod9/php-swoole-crud-microservice/blob/main/src/Core/Router.php
 */
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\RouteNotFoundException;

/**
 * Class Router
 * A simple HTTP router for mapping request methods and paths to actions.
 *
 * @category  Core
 * @package   App\Core
 * @author    Ramakant Gangwar <14928642+rxcod9@users.noreply.github.com>
 * @copyright Copyright (c) 2025
 * @license   MIT
 * @version   1.0.0
 * @since     2025-10-02
 */
final class Router
{
    /**
     * @var array<string, array<int, array{regex: string, vars: array<int, string>, path: string}, string, array>>
     *                                                                                                             Stores the registered routes grouped by HTTP method.
     */
    private array $routes = [];

    /**
     * Registers a route for a given HTTP method, path, action, and optional middlewares.
     *
     * @param string              $method      HTTP method (GET, POST, etc.)
     * @param string              $path        Route path, e.g. '/users/{id}'
     * @param string              $action      The action handler (e.g. controller@method)
     * @param array<class-string> $middlewares List of middlewares class names
     */
    public function add(string $method, string $path, string $action, array $middlewares = []): void
    {
        $this->routes[strtoupper($method)][] = [$this->compile($path), $action, $middlewares];
    }

    /**
     * Registers a GET route.
     *
     * @param string              $p  Route path
     * @param string              $a  Action handler
     * @param array<class-string> $mw List of middlewares class names
     */
    public function get(string $p, string $a, array $mw = []): void
    {
        $this->add('GET', $p, $a, $mw);
    }

    /**
     * Registers a POST route.
     *
     * @param string              $p  Route path
     * @param string              $a  Action handler
     * @param array<class-string> $mw List of middlewares class names
     */
    public function post(string $p, string $a, array $mw = []): void
    {
        $this->add('POST', $p, $a, $mw);
    }

    /**
     * Registers a PUT route.
     *
     * @param string              $p  Route path
     * @param string              $a  Action handler
     * @param array<class-string> $mw List of middlewares class names
     */
    public function put(string $p, string $a, array $mw = []): void
    {
        $this->add('PUT', $p, $a, $mw);
    }

    /**
     * Registers a DELETE route.
     *
     * @param string              $p  Route path
     * @param string              $a  Action handler
     * @param array<class-string> $mw List of middlewares class names
     */
    public function delete(string $p, string $a, array $mw = []): void
    {
        $this->add('DELETE', $p, $a, $mw);
    }

    /**
     * Matches an incoming HTTP request to a registered route.
     *
     * @param string $method HTTP method
     * @param string $uri    Request URI
     *
     * @throws RuntimeException If no route matches (404)
     *
     * @return array{0: string, 1: array<string, string>, 2: array<class-string>} Matched action, parameters, and middlewares
     */
    public function match(string $method, string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes[strtoupper($method)] ?? [] as [$compiled, $action, $middlewares]) {
            if (preg_match($compiled['regex'], $path, $m)) {
                $params = [];
                foreach ($compiled['vars'] as $i => $name) {
                    $params[$name] = $m[$i + 1];
                }

                return [$action, $params, $middlewares];
            }
        }

        throw new RouteNotFoundException(Messages::ROUTE_NOT_FOUND, 404);
    }

    /**
     * Compiles a route path into a regex pattern and extracts variable names.
     *
     * @param string $path Route path with optional variables (e.g. '/users/{id}')
     *
     * @return array{regex: string, vars: array<int, string>, path: string} Compiled regex, variable names, and original path
     */
    private function compile(string $path): array
    {
        $vars  = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function (array $m) use (&$vars): string {
            $vars[] = $m[1];
            return '([^/]+)';
        }, $path);
        return [
            'regex' => '#^' . $regex . '$#',
            'path'  => $path,
            'vars'  => $vars,
        ];
    }

    /**
     * Retrieves the action associated with a given HTTP method and path.
     *
     * @param string $method HTTP method
     * @param string $uri    Request URI
     *
     * @return array|null The route details (compiled, action, middlewares) if found
     */
    public function getRouteByPath(string $method, string $uri): ?array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as $routeMethod => $routes) {
            if (strtoupper($routeMethod) !== strtoupper($method)) {
                continue;
            }

            foreach ($routes as [$compiled, $action, $middlewares]) {
                if (preg_match($compiled['regex'], $path)) {
                    return [$compiled, $action, $middlewares];
                }
            }
        }

        return null;
    }
}
