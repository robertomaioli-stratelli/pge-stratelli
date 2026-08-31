<?php
namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void { $this->add('GET',$pattern,$handler,$middleware); }
    public function post(string $pattern, callable|array $handler, array $middleware = []): void { $this->add('POST',$pattern,$handler,$middleware); }

    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $this->routes[] = compact('method','pattern','handler','middleware');
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['pattern']);
            $regex = '#^' . rtrim($regex, '/') . '/?$#';
            if (!preg_match($regex, $path, $m)) continue;
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->runMiddleware($route['middleware'], $params);
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class,$action] = $handler;
                (new $class())->$action(...array_values($params));
            } else {
                $handler(...array_values($params));
            }
            return;
        }
        Response::abort(404, 'Página não encontrada.');
    }

    private function runMiddleware(array $middlewares, array $params): void
    {
        foreach ($middlewares as $middleware) {
            if ($middleware === 'auth' && !Auth::check()) Response::redirect('/login');
            if ($middleware === 'guest' && Auth::check()) Response::redirect(self::homeForUser());
            if ($middleware === 'platform_admin' && !Auth::isPlatformAdmin()) Response::abort(403,'Acesso restrito à administração Stratelli.');
            if ($middleware === 'tenant') Tenant::resolveBySlug((string)($params['municipio'] ?? ''));
            if ($middleware === 'manager' && !Auth::isPlatformAdmin() && !in_array(Auth::role(), ['ADMINISTRADOR','GESTOR'], true)) {
                Response::abort(403,'Acesso restrito a gestores.');
            }
        }
    }

    public static function homeForUser(): string
    {
        $u = Auth::user();
        if (!$u) return '/login';
        if (Auth::isPlatformAdmin()) return '/admin';
        return '/' . rawurlencode((string)$u['municipio_slug']) . '/dashboard';
    }
}
