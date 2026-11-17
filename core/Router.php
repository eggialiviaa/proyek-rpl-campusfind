<?php

declare(strict_types=1);

namespace Core;

/**
 * Router — Mendaftarkan dan mencocokkan route ke Controller
 *
 * Mendukung:
 * - Static routes: /login, /dashboard
 * - Dynamic routes dengan parameter: /items/{id}, /users/{id}/edit
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: string, params: list<string>}> */
    private array $routes = [];

    /**
     * Daftarkan route GET
     *
     * @param string $path    URI path, misal '/items/{id}'
     * @param string $handler 'ControllerClass@method'
     */
    public function get(string $path, string $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Daftarkan route POST
     */
    public function post(string $path, string $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Tambah route ke registry
     */
    private function addRoute(string $method, string $path, string $handler): void
    {
        // Konversi path dengan parameter {id} → named capture group regex
        $paramNames = [];
        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$paramNames) {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $path);

        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'params'  => $paramNames,
        ];
    }

    /**
     * Dispatch request ke handler yang sesuai
     * Dipanggil dari App::run()
     */
    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $this->parseUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Ambil captured parameter values
                array_shift($matches); // hapus full match
                $params = array_combine($route['params'], $matches) ?: [];

                // Guard Access: Allowlist route publik & autentikasi tamu
                if (!\App\Middleware\AuthMiddleware::isRouteAllowedForGuest($method, $uri)) {
                    \App\Middleware\AuthMiddleware::handleUnauthenticated($_SERVER['REQUEST_URI'] ?? $uri);
                    return;
                }

                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // Tidak ada route yang cocok
        $this->handleNotFound();
    }

    /**
     * Parse URI — bersihkan query string dan path prefix jika ada
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Hapus query string
        $uri = strtok($uri, '?');
        // Normalize trailing slash (kecuali root)
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }
        return $uri ?: '/';
    }

    /**
     * Panggil controller@method dengan parameter
     *
     * @param array<string, string> $params
     */
    private function callHandler(string $handler, array $params = []): void
    {
        [$controllerClass, $method] = explode('@', $handler, 2);

        // Resolve full qualified class name
        $fqcn = 'App\\Controllers\\' . $controllerClass;

        if (!class_exists($fqcn)) {
            error_log("[Router] Controller not found: {$fqcn}");
            $this->handleNotFound();
            return;
        }

        $controller = new $fqcn();

        if (!method_exists($controller, $method)) {
            error_log("[Router] Method not found: {$fqcn}::{$method}");
            $this->handleNotFound();
            return;
        }

        $controller->$method($params);
    }

    /**
     * Tampilkan halaman 404
     */
    private function handleNotFound(): void
    {
        http_response_code(404);
        $errorView = BASE_PATH . '/app/Views/errors/404.php';
        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo '<h1>404 — Halaman tidak ditemukan</h1>';
        }
        exit;
    }
}
