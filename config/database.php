<?php

declare(strict_types=1);

/**
 * config/database.php — Konfigurasi koneksi database
 * Seluruh nilai diambil dari .env — JANGAN hardcode credential di sini
 */

return [
    'host'    => $_ENV['DB_HOST']    ?? '127.0.0.1',
    'port'    => (int) ($_ENV['DB_PORT'] ?? 3306),
    'dbname'  => $_ENV['DB_NAME']    ?? 'campus_find',
    'user'    => $_ENV['DB_USER']    ?? 'root',
    'pass'    => $_ENV['DB_PASS']    ?? '',
    'charset' => 'utf8mb4',
];
