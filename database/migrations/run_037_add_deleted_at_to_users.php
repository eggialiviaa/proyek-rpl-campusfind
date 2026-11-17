<?php

declare(strict_types=1);

/**
 * Migration 037 — Tambah kolom deleted_at pada tabel users (BUG FIX 03)
 *
 * Menambahkan kolom `deleted_at` (TIMESTAMP NULL DEFAULT NULL) pada tabel `users`.
 * Migrasi ini aman dan idempotent:
 * - Memeriksa keberadaan kolom terlebih dahulu.
 * - Tidak mengubah atau menghapus data baris yang sudah ada.
 * - Menambahkan indeks `idx_users_deleted_at` untuk optimasi pencarian akun aktif vs terhapus.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use Core\Database;

try {
    $pdo = Database::getInstance()->getPdo();
    echo "Menjalankan migrasi 037: Tambah kolom deleted_at pada tabel users...\n";

    $databases = [$pdo->query("SELECT DATABASE()")->fetchColumn()];
    $checkTestDb = $pdo->query("SHOW DATABASES LIKE 'campus_find_test'")->fetchColumn();
    if (!empty($checkTestDb)) {
        $databases[] = $checkTestDb;
    }

    foreach ($databases as $db) {
        $checkSql = "
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '{$db}' 
              AND TABLE_NAME = 'users' 
              AND COLUMN_NAME = 'deleted_at'
        ";
        $columnExists = (int) $pdo->query($checkSql)->fetchColumn() > 0;

        if ($columnExists) {
            echo "  [SKIP] Kolom `deleted_at` sudah ada pada {$db}.users.\n";
        } else {
            $alterSql = "
                ALTER TABLE `{$db}`.`users` 
                ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
                ADD INDEX `idx_users_deleted_at` (`deleted_at`);
            ";
            $pdo->exec($alterSql);
            echo "  [OK] Kolom `deleted_at` dan indeks `idx_users_deleted_at` berhasil ditambahkan ke {$db}.users.\n";
        }
    }

    echo "Migrasi 037 selesai dengan sukses.\n";
} catch (\Throwable $e) {
    echo "  [ERROR] Migrasi 037 gagal: " . $e->getMessage() . "\n";
    exit(1);
}
