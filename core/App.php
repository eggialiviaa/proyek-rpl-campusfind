<?php

declare(strict_types=1);

namespace Core;

use Dotenv\Dotenv;

/**
 * App — Bootstrap utama aplikasi Campus Find
 *
 * Urutan inisialisasi:
 * 1. Load .env
 * 2. Set konfigurasi PHP (error reporting, timezone, session)
 * 3. Inisialisasi Router
 * 4. Load routes
 * 5. Dispatch request
 */
class App
{
    private Router $router;

    public function __construct()
    {
        $this->loadEnvironment();
        $this->configure();
        $this->router = new Router();
    }

    /**
     * Load .env menggunakan vlucas/phpdotenv
     */
    private function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable(BASE_PATH);
        $dotenv->load();

        // Validasi variabel wajib ada
        $dotenv->required([
            'APP_NAME',
            'APP_ENV',
            'APP_URL',
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
        ])->notEmpty();
    }

    /**
     * Konfigurasi PHP runtime berdasarkan APP_ENV
     */
    private function configure(): void
    {
        $env = $_ENV['APP_ENV'] ?? 'production';
        $debug = filter_var($_ENV['APP_DEBUG'] ?? ($env === 'local'), FILTER_VALIDATE_BOOLEAN);

        // Timezone
        date_default_timezone_set('Asia/Jakarta');

        // Error log
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/error.log');

        // Error reporting: saat APP_DEBUG=false atau APP_ENV=production, JANGAN tampilkan error/trace di layar
        if ($debug && $env !== 'production') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            error_reporting(0);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        // Global Exception Handler agar tidak membocorkan stack trace / file path saat production/debug=false
        set_exception_handler(function (\Throwable $e) use ($debug, $env) {
            error_log(sprintf(
                "[App] Fatal Exception: %s in %s:%d\nStack Trace:\n%s",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));

            if (!headers_sent()) {
                http_response_code(500);
            }

            if (\App\Middleware\AuthMiddleware::isApiRequest()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'error'   => 'Internal Server Error',
                    'message' => 'Terjadi kendala teknis pada server.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if ($debug && $env !== 'production') {
                echo '<pre style="padding:2rem;font-family:monospace;background:#1e1e1e;color:#d4d4d4;">';
                echo '<strong style="color:#f87171;">Fatal Error:</strong> ' . htmlspecialchars($e->getMessage()) . "\n\n";
                echo 'File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
                echo htmlspecialchars($e->getTraceAsString());
                echo '</pre>';
            } else {
                $errorView = BASE_PATH . '/app/Views/errors/500.php';
                if (file_exists($errorView)) {
                    require $errorView;
                } else {
                    echo '<h1>500 Internal Server Error</h1>';
                }
            }
            exit;
        });

        // Deteksi koneksi HTTPS aman
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        // Session config (sebelum session_start)
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($isHttps) {
            ini_set('session.cookie_secure', '1');
        }

        // Set security headers
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Jalankan aplikasi: load routes dan dispatch request
     */
    public function run(): void
    {
        // Mulai session dengan parameter cookie terkonfigurasi
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }


        // Inisialisasi service hooks (misal NotificationService)
        \App\Services\NotificationService::bootstrap();

        // Load definisi routes
        $router = $this->router;
        require BASE_PATH . '/config/routes.php';

        // Dispatch request
        $this->router->dispatch();
    }
}
