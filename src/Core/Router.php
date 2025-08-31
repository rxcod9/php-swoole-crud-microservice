<?php

namespace App\Core;

final class Router
{
    private array $routes = [];
    public function add(string $method, string $path, string $action): void
    {
        $this->routes[strtoupper($method)][] = [$this->compile($path), $action];
    }
    public function get($p, $a)
    {
        $this->add('GET', $p, $a);
    }
    public function post($p, $a)
    {
        $this->add('POST', $p, $a);
    }
    public function put($p, $a)
    {
        $this->add('PUT', $p, $a);
    }
    public function delete($p, $a)
    {
        $this->add('DELETE', $p, $a);
    }
    public function match(string $method, string $uri): array
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
      // error_log("Matching route: method=" . strtoupper($method) . ", path=" . $path);
        foreach ($this->routes[strtoupper($method)] ?? [] as [$compiled, $action]) {
          // error_log("Trying regex: " . $compiled['regex']);
            if (preg_match($compiled['regex'], $path, $m)) {
                $params = [];
                foreach ($compiled['vars'] as $i => $name) {
                    $params[$name] = $m[$i + 1];
                }
                // error_log("Matched action: $action, params: " . json_encode($params));
                return [$action, $params];
            }
        }
        throw new \RuntimeException('Not Found', 404);
    }
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
