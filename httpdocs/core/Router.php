<?php
/**
 * Axent - Router.php
 * Front Controller : dispatche les requêtes vers les bons contrôleurs
 * Activé via .htaccess : RewriteRule ^(.*)$ index.php?_route=$1
 */

declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->routes[] = ['ANY', $pattern, $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = '/' . trim($_GET['_route'] ?? $_SERVER['PATH_INFO'] ?? '', '/');
        // Enlever le query string
        $uri    = strtok($uri, '?') ?: '/';

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== 'ANY' && $routeMethod !== $method) continue;

            // Convertir {param} en regex
            $regex = '#^' . preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (!preg_match($regex, $uri, $matches)) continue;

            // Extraire les paramètres nommés
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $handler($params);
            return;
        }

        // 404
        http_response_code(404);
        require ROOT_PATH . '/errors/404.php';
    }
}
