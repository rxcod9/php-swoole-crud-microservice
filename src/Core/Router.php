<?php

namespace App\Core;

/**
 * Class Router
 *
 * A simple HTTP router for mapping request methods and paths to actions.
 *
 * @package App\Core
 */
final class Router
{
    /**
     * @var array<string, array<int, array{regex: string, vars: array<int, string>}, string>> $routes
     * Stores the registered routes grouped by HTTP method.
     */
    private array $routes = [];

    /**
     * Registers a route for a given HTTP method, path, and action.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $path Route path, e.g. '/users/{id}'
     * @param string $action The action handler (e.g. controller@method)
     * @return void
     */
    public function add(string $method, string $path, string $action): void
    {
        $this->routes[strtoupper($method)][] = [$this->compile($path), $action];
    }

    /**
     * Registers a GET route.
     *
     * @param string $p Route path
     * @param string $a Action handler
     * @return void
     */
    public function get(string $p, string $a): void
    {
        $this->add('GET', $p, $a);
    }

    /**
     * Registers a POST route.
     *
     * @param string $p Route path
     * @param string $a Action handler
     * @return void
     */
    public function post(string $p, string $a): void
    {
        $this->add('POST', $p, $a);
    }

    /**
     * Registers a PUT route.
     *
     * @param string $p Route path
     * @param string $a Action handler
     * @return void
     */
    public function put(string $p, string $a): void
    {
        $this->add('PUT', $p, $a);
    }

    /**
     * Registers a DELETE route.
     *
     * @param string $p Route path
     * @param string $a Action handler
     * @return void
     */
    public function delete(string $p, string $a): void
    {
        $this->add('DELETE', $p, $a);
    }

    /**
     * Matches an incoming HTTP request to a registered route.
     *
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @return array{0: string, 1: array<string, string>} Matched action and parameters
     * @throws \RuntimeException If no route matches (404)
     */
    public function match(string $method, string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes[strtoupper($method)] ?? [] as [$compiled, $action]) {
            if (preg_match($compiled['regex'], $path, $m)) {
                $params = [];
                foreach ($compiled['vars'] as $i => $name) {
                    $params[$name] = $m[$i + 1];
                }
                return [$action, $params];
            }
        }
        throw new \RuntimeException('Not Found', 404);
    }

    /**
     * Compiles a route path into a regex and extracts variable names.
     *
     * @param string $path Route path (e.g. '/users/{id}')
     * @return array{regex: string, vars: array<int, string>} Compiled regex and variable names
     */
    private function compile(string $path): array
    {
        $vars = [];
        $regex = preg_replace_callback('#\{(\w+)\}#', function ($m) use (&$vars) {
            $vars[] = $m[1];
            return '([^/]+)';
        }, $path);
        return ['regex' => '#^' . $regex . '$#', 'vars' => $vars];
    }
}
