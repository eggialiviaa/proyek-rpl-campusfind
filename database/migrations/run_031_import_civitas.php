<?php

declare(strict_types=1);

/**
 * Migration 031 — Import Civitas Metadata Columns
 * Prompt 31: Impor Data Civitas dari Excel (Admin)
 *
 * Menambahkan kolom program studi (study_program) dan angkatan (batch_year) ke tabel users,
 * serta indeks pencarian pada identity_number (NIM/NIDN/NIP).
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use Core\Database;

try {
    $pdo = Database::getInstance()->getPdo();
    echo "Menjalankan migrasi 031: Penambahan kolom civitas metadata...\n";

    // 1. Periksa kolom study_program
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'study_program'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN study_program VARCHAR(100) NULL AFTER identity_number");
        echo "  - Kolom 'study_program' berhasil ditambahkan ke tabel users.\n";
    } else {
        echo "  - Kolom 'study_program' sudah ada.\n";
    }

    // 2. Periksa kolom batch_year
    $colCheck2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'batch_year'")->fetch();
    if (!$colCheck2) {
        $pdo->exec("ALTER TABLE users ADD COLUMN batch_year VARCHAR(10) NULL AFTER study_program");
        echo "  - Kolom 'batch_year' berhasil ditambahkan ke tabel users.\n";
    } else {
        echo "  - Kolom 'batch_year' sudah ada.\n";
    }

    // 3. Periksa index identity_number
    $idxCheck = $pdo->query("SHOW INDEX FROM users WHERE Key_name = 'idx_users_identity_number'")->fetch();
    if (!$idxCheck) {
        $pdo->exec("ALTER TABLE users ADD INDEX idx_users_identity_number (identity_number)");
        echo "  - Indeks 'idx_users_identity_number' berhasil ditambahkan.\n";
    } else {
        echo "  - Indeks 'idx_users_identity_number' sudah ada.\n";
    }

    echo "Migrasi 031 selesai dengan sukses!\n";
} catch (\Throwable $e) {
    echo "Gagal migrasi 031: " . $e->getMessage() . "\n";
    exit(1);
}
