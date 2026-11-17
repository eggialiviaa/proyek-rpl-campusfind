<?php

declare(strict_types=1);

/**
 * config/app.php — Konfigurasi aplikasi umum
 * Dibaca dari environment variables yang sudah di-load oleh App::loadEnvironment()
 */

return [
    'name'     => $_ENV['APP_NAME'] ?? 'CampusFind',
    'env'      => $_ENV['APP_ENV']  ?? 'production',
    'url'      => $_ENV['APP_URL']  ?? 'http://localhost',
    'key'      => $_ENV['APP_KEY']  ?? '',
    'timezone' => 'Asia/Jakarta',
    'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
];
