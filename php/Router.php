<?php

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }

    private function add(
        string $method,
        string $path,
        callable $handler
    ): void {
        $this->routes[] = [
            'method' => $method,
            'path' => $this->normalize($path),
            'handler' => $handler,
        ];
    }

    private function respond(int $status, string $message): void
    {
        http_response_code($status);

        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
    }

    public function dispatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $path = parse_url(
            $_SERVER['REQUEST_URI'] ?? '/',
            PHP_URL_PATH
        );

        $path = $this->normalize($path ?: '/');

        // Remove the /api prefix
        $path = preg_replace('#^/api(?=/|$)#', '', $path);
        $path = $this->normalize($path);

        $allowedMethods = [];

        foreach ($this->routes as $route) {
            // Convert /posts/{id} to a regex
            $pattern = preg_quote($route['path'], '#');

            $pattern = preg_replace(
                '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
                '(?P<$1>[^/]+)',
                $pattern
            );

            if (!preg_match('#^' . $pattern . '$#D', $path, $matches)) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = rawurldecode($value);
                }
            }

            call_user_func_array(
                $route['handler'],
                array_values($params)
            );

            return;
        }

        if (!empty($allowedMethods)) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));

            $this->respond(405, 'Method not allowed');
            return;
        }

        $this->respond(404, 'Route not found');
    }
}
