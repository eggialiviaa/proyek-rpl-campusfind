<?php

declare(strict_types=1);

/**
 * public/index.php — Entry Point Tunggal Campus Find
 *
 * Semua HTTP request diarahkan ke file ini via .htaccess
 * Urutan: define constants → autoload → bootstrap App → run
 */

// ──────────────────────────────────────────────
// 1. Definisi konstanta path
// ──────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// ──────────────────────────────────────────────
// 2. Composer autoload (PSR-4 + phpdotenv + vendor)
// ──────────────────────────────────────────────
$autoloadFile = BASE_PATH . '/vendor/autoload.php';

if (!file_exists($autoloadFile)) {
    http_response_code(500);
    echo '<h1>500 — Dependensi belum terinstall</h1>';
    echo '<p>Jalankan: <code>composer install</code> di root project.</p>';
    exit;
}

require $autoloadFile;

// ──────────────────────────────────────────────
// 3. Bootstrap dan jalankan aplikasi
// ──────────────────────────────────────────────
use Core\App;

try {
    $app = new App();
    $app->run();
} catch (\Throwable $e) {
    // Error handling untuk production: log, tampilkan pesan generik
    error_log(sprintf(
        "[App] Fatal error: %s in %s:%d\nStack Trace:\n%s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    $env = $_ENV['APP_ENV'] ?? 'production';
    $debug = filter_var($_ENV['APP_DEBUG'] ?? ($env === 'local'), FILTER_VALIDATE_BOOLEAN);

    http_response_code(500);

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
            echo '<!DOCTYPE html><html lang="id"><head><title>500 — Terjadi Kesalahan</title></head><body>';
            echo '<h1>500 — Terjadi Kendala Server</h1><p>Mohon maaf, sistem mengalami kendala teknis internal. Silakan coba beberapa saat lagi.</p>';
            echo '</body></html>';
        }
    }
    exit;
}
