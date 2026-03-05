<?php
/**
 * Router
 * Handles HTTP request routing
 */

namespace P2P\Core;

use P2P\Core\Config;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    /**
     * Register a GET route
     */
    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Register a route
     */
    private function addRoute(string $method, string $path, callable $handler): self
    {
        $this->routes[$method][$path] = $handler;
        return $this;
    }

    /**
     * Add middleware
     */
    public function addMiddleware(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatch request
     */
    public function dispatch(string $method, string $path): void
    {
        // Remove query string
        $path = strtok($path, '?');

        // Apply middleware
        foreach ($this->middleware as $middleware) {
            $result = $middleware($method, $path);
            if ($result === false) {
                $this->jsonResponse(['error' => 'Forbidden'], 403);
                return;
            }
        }

        // Find matching route
        if (isset($this->routes[$method][$path])) {
            try {
                $handler = $this->routes[$method][$path];
                $handler();
            } catch (\Exception $e) {
                $this->jsonResponse(['error' => $e->getMessage()], 500);
            }
            return;
        }

        // 404 Not Found
        $this->jsonResponse(['error' => 'Not Found'], 404);
    }

    /**
     * Send JSON response
     */
    public function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get request body as JSON
     */
    public static function getJsonBody(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Get request headers
     */
    public static function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get authorization header
     */
    public static function getAuthHeader(): ?string
    {
        return $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    }

    /**
     * Get request method
     */
    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    /**
     * Get request path
     */
    public static function getPath(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }
}
