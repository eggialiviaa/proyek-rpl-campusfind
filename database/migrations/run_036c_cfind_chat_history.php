<?php

declare(strict_types=1);

/**
 * Migration 036c — Tabel cfind_chat_history untuk Admin Monitoring Chat AI (Prompt 36C)
 *
 * Menyimpan percakapan pengguna (Civitas, Petugas, Admin, Tamu) dengan chatbot C-Find:
 * - Dimasker (email dan nomor telepon disamarkan)
 * - Tanpa data pribadi digandakan (tabel/daftar hasil diringkas: judul, kolom, jumlah baris)
 * - Tanpa reasoning_content, API key, atau password
 * - Retensi otomatis CFIND_HISTORY_RETENTION_DAYS (default 30 hari)
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use Core\Database;

try {
    $pdo = Database::getInstance()->getPdo();
    echo "Menjalankan migrasi 036c: Tabel cfind_chat_history...\n";

    $sql = "
    CREATE TABLE IF NOT EXISTS `cfind_chat_history` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NULL,
        `session_hash` VARCHAR(64) NOT NULL,
        `role` VARCHAR(20) NOT NULL,
        `question` TEXT NOT NULL,
        `answer` MEDIUMTEXT NOT NULL,
        `intent` VARCHAR(100) NOT NULL DEFAULT 'unknown',
        `status` ENUM('dijawab', 'ditolak', 'tidak bisa dijawab', 'gagal') NOT NULL DEFAULT 'dijawab',
        `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
        `tokens_used` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_cfind_chat_created_at` (`created_at`),
        INDEX `idx_cfind_chat_user_id` (`user_id`),
        INDEX `idx_cfind_chat_role` (`role`),
        INDEX `idx_cfind_chat_status` (`status`),
        CONSTRAINT `fk_cfind_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);
    echo "  [OK] Tabel `cfind_chat_history` berhasil dibuat/diverifikasi.\n";

} catch (\Throwable $e) {
    echo "  [ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

