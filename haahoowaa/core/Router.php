<?php
class Router
{
    private array $routes;
    private array $config;
    private PDO $db;

    public function __construct(array $routes, array $config, PDO $db)
    {
        $this->routes = $routes;
        $this->config = $config;
        $this->db = $db;
    }

    public function dispatch(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($uri, '/'))));

        $languages = I18n::languages();
        $languageCodes = array_column($languages, 'code');
        $defaultLanguage = $this->config['default_language'] ?? 'es';

        $lang = $defaultLanguage;
        if (!empty($segments) && in_array($segments[0], $languageCodes, true)) {
            $lang = array_shift($segments);
        }
        I18n::setLanguage($lang);

        $path = '/' . implode('/', $segments);
        $matched = $this->matchRoute($path);

        if (!$matched) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }

        $module = $matched['module'];
        $action = $matched['action'] ?? 'index';
        $params = $matched['params'] ?? [];

        $controllerClass = ucfirst($module) . 'Controller';
        $controllerFile = $this->config['base_path'] . '/modules/' . $module . '/' . $controllerClass . '.php';

        if (!file_exists($controllerFile)) {
            http_response_code(500);
            echo 'Controller not found';
            return;
        }

        require_once $controllerFile;

        $view = new View($this->config['default_theme'], $this->config['base_path']);
        $controller = new $controllerClass($this->config, $view, $this->db);

        if (!method_exists($controller, $action)) {
            http_response_code(404);
            echo 'Action not found';
            return;
        }

        call_user_func_array([$controller, $action], $params);
    }

    private function matchRoute(string $path): ?array
    {
        foreach ($this->routes as $route) {
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . rtrim($pattern, '/') . '$#';

            if (preg_match($pattern, rtrim($path, '/'), $matches)) {
                array_shift($matches);
                return [
                    'module' => $route['module'],
                    'action' => $route['action'],
                    'params' => $matches,
                ];
            }
        }
        return null;
    }
}
