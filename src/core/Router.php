<?php

declare(strict_types=1);

/**
 * Minimal HTTP router.
 *
 * Maps request method + URL path to a handler. A handler is either a string
 * file path (relative to the document root) that is included when the route
 * matches, or any callable (e.g. a closure) that is invoked directly.
 *
 * Supports exact routes and {param} placeholder routes:
 *
 *   $router->get('/login', 'modules/auth/login.php');
 *   $router->get('/api/v1/accounts/{id}', 'modules/accounts/api.php');
 *   $router->put('/api/v1/profile', 'modules/dashboard/profile_api.php');
 *
 * Matched placeholders are exposed to handlers via route_param('id') (see
 * helpers.php) or Router::param('id').
 */
class Router
{
    /** @var array<string, array<string, string|callable>> exact: method => [path => handler] */
    private array $routes = [];

    /** @var array<string, list<array{regex:string,keys:list<string>,handler:string|callable}>> */
    private array $patterns = [];

    /** @var callable|null Handler invoked when no route matches. */
    private $notFound = null;

    /** @var array<string, string> Params captured from the matched route. */
    public static array $params = [];

    public function get(string $path, string|callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, string|callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, string|callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, string|callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, string|callable $handler): void
    {
        $method = strtoupper($method);
        $path   = $this->normalize($path);

        if (str_contains($path, '{')) {
            // Compile {param} -> named capture. Params match a single segment.
            $keys = [];
            $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function ($m) use (&$keys) {
                $keys[] = $m[1];
                return '([^/]+)';
            }, $path);
            $this->patterns[$method][] = [
                'regex'   => '#^' . $regex . '$#',
                'keys'    => $keys,
                'handler' => $handler,
            ];
            return;
        }

        $this->routes[$method][$path] = $handler;
    }

    /**
     * Register a fallback handler for unmatched routes.
     */
    public function setNotFound(callable $handler): void
    {
        $this->notFound = $handler;
    }

    /**
     * Accessor for a captured route parameter.
     */
    public static function param(string $key, ?string $default = null): ?string
    {
        return self::$params[$key] ?? $default;
    }

    /**
     * Resolve the current request and run the matching handler.
     */
    public function dispatch(?string $method = null, ?string $uri = null): void
    {
        $method = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri    = $uri ?? ($_SERVER['REQUEST_URI'] ?? '/');

        // Strip query string and normalize the path.
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');

        // 1) Exact match.
        $handler = $this->routes[$method][$path] ?? null;

        // 2) Pattern match.
        if ($handler === null && isset($this->patterns[$method])) {
            foreach ($this->patterns[$method] as $route) {
                if (preg_match($route['regex'], $path, $m)) {
                    array_shift($m);
                    self::$params = array_combine($route['keys'], $m) ?: [];
                    $handler = $route['handler'];
                    break;
                }
            }
        }

        if ($handler !== null) {
            $base = __DIR__ . '/..';
            if (is_callable($handler)) {
                ($handler)($path);
            } else {
                require $base . '/' . ltrim($handler, '/');
            }
            return;
        }

        $this->dispatchNotFound($method, $path);
    }

    private function dispatchNotFound(string $method, string $path): void
    {
        http_response_code(404);
        if ($this->notFound !== null) {
            ($this->notFound)($method, $path);
            return;
        }
        echo '404 Not Found';
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
