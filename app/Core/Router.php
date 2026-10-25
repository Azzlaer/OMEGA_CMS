<?php
declare(strict_types=1);
namespace Omega\Core;

final class Router
{
    private array $routes = [];
    public function get(string $path, callable $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, callable $handler): void { $this->routes['POST'][$path] = $handler; }
    public function dispatch(string $method, string $uri): mixed
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) { http_response_code(404); return ['error'=>'Not Found']; }
        return $handler();
    }
}
