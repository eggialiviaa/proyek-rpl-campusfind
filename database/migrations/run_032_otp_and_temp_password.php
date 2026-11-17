<?php

declare(strict_types=1);

/**
 * Migration 032 — OTP Verifications & Mandatory Temporary Password (Prompt 20B)
 *
 * Menambahkan:
 * 1. Tabel `otp_verifications` untuk verifikasi OTP email (Aktivasi Akun & Lupa Kata Sandi).
 * 2. Kolom `must_change_password` pada tabel `users`.
 * 3. Templat email `account_activation_otp` dan `password_reset_otp` pada `notification_templates`.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use Core\Database;

try {
    $pdo = Database::getInstance()->getPdo();
    echo "Menjalankan migrasi 032: OTP Verifications & Temp Password...\n";

    // 1. Tambah kolom must_change_password pada tabel users jika belum ada
    $colCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
        echo "  - Kolom 'must_change_password' berhasil ditambahkan ke tabel users.\n";
    } else {
        echo "  - Kolom 'must_change_password' sudah ada pada tabel users.\n";
    }

    // 2. Buat tabel otp_verifications
    $sqlOtp = "
    CREATE TABLE IF NOT EXISTS `otp_verifications` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL,
        `purpose` ENUM('activation', 'password_reset') NOT NULL,
        `otp_hash` VARCHAR(255) NOT NULL,
        `salt` VARCHAR(32) NOT NULL,
        `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
        `max_attempts` INT UNSIGNED NOT NULL DEFAULT 5,
        `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
        `expires_at` DATETIME NOT NULL,
        `used_at` DATETIME NULL,
        `ip_address` VARCHAR(45) NULL,
        `user_agent` VARCHAR(255) NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_otp_user_purpose` (`user_id`, `purpose`),
        INDEX `idx_otp_expires_at` (`expires_at`),
        INDEX `idx_otp_created_at` (`created_at`),
        CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verifikasi OTP email untuk aktivasi akun dan reset password';
    ";
    $pdo->exec($sqlOtp);
    echo "  - Tabel 'otp_verifications' berhasil dibuat atau sudah ada.\n";

    // 3. Tambahkan template email OTP ke notification_templates jika belum ada
    $tplCheck = $pdo->query("SELECT COUNT(*) FROM notification_templates WHERE template_key = 'account_activation_otp'")->fetchColumn();
    if ((int)$tplCheck === 0) {
        $stmtTpl = $pdo->prepare("
            INSERT INTO notification_templates (template_key, name, subject, body_html, body_text, variables_hint, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");

        // Template Aktivasi Akun
        $stmtTpl->execute([
            'account_activation_otp',
            'Kode OTP Aktivasi Akun Civitas',
            '[Campus Find] Kode Verifikasi Aktivasi Akun Anda: {{otp_code}}',
            '<p>Halo <strong>{{user_name}}</strong>,</p>' .
            '<p>Terima kasih telah bergabung dengan <strong>Campus Find Lost &amp; Found</strong>. Berikut adalah kode verifikasi OTP 6 digit untuk mengaktifkan akun Anda:</p>' .
            '<div style="background:#f1f5f9;border-left:4px solid #0284c7;padding:16px 20px;margin:20px 0;border-radius:6px;text-align:center;">' .
            '  <span style="font-size:32px;font-weight:700;letter-spacing:6px;color:#0f172a;font-family:monospace;">{{otp_code}}</span>' .
            '  <p style="margin:8px 0 0;font-size:13px;color:#64748b;">Kode berlaku selama <strong>5 menit</strong>.</p>' .
            '</div>' .
            '<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 16px;border-radius:6px;margin:16px 0;">' .
            '  <p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;"><i class="bi bi-shield-lock"></i> PERINGATAN KEAMANAN:</p>' .
            '  <p style="margin:4px 0 0;font-size:12px;color:#b91c1c;">Jangan bagikan kode verifikasi ini kepada siapa pun, termasuk pihak kampus atau petugas Lost &amp; Found. Sistem Campus Find tidak akan pernah meminta kode ini melalui telepon atau pesan pribadi.</p>' .
            '</div>' .
            '<p style="font-size:13px;color:#64748b;">Jika Anda tidak merasa meminta aktivasi akun ini, Anda dapat mengabaikan email ini dengan aman.</p>',
            "Halo {{user_name}},\n\nKode verifikasi OTP untuk aktivasi akun Campus Find Anda adalah:\n\n{{otp_code}}\n\nKode ini berlaku selama 5 menit.\n\nPERINGATAN KEAMANAN: Jangan bagikan kode ini kepada siapa pun. Petugas kami tidak akan pernah meminta kode verifikasi Anda.\n\nJika Anda tidak merasa meminta ini, silakan abaikan pesan ini.",
            '{{user_name}}, {{otp_code}}, {{expires_in}}, {{app_name}}',
        ]);
        echo "  - Templat 'account_activation_otp' berhasil didaftarkan.\n";
    }

    $tplCheck2 = $pdo->query("SELECT COUNT(*) FROM notification_templates WHERE template_key = 'password_reset_otp'")->fetchColumn();
    if ((int)$tplCheck2 === 0) {
        $stmtTpl2 = $pdo->prepare("
            INSERT INTO notification_templates (template_key, name, subject, body_html, body_text, variables_hint, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");

        // Template Reset Kata Sandi
        $stmtTpl2->execute([
            'password_reset_otp',
            'Kode OTP Reset Kata Sandi',
            '[Campus Find] Kode Verifikasi Reset Kata Sandi: {{otp_code}}',
            '<p>Halo <strong>{{user_name}}</strong>,</p>' .
            '<p>Kami menerima permintaan untuk mereset kata sandi akun Campus Find Anda. Berikut adalah kode verifikasi OTP 6 digit Anda:</p>' .
            '<div style="background:#f1f5f9;border-left:4px solid #ea580c;padding:16px 20px;margin:20px 0;border-radius:6px;text-align:center;">' .
            '  <span style="font-size:32px;font-weight:700;letter-spacing:6px;color:#0f172a;font-family:monospace;">{{otp_code}}</span>' .
            '  <p style="margin:8px 0 0;font-size:13px;color:#64748b;">Kode berlaku selama <strong>5 menit</strong>.</p>' .
            '</div>' .
            '<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 16px;border-radius:6px;margin:16px 0;">' .
            '  <p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;">PERINGATAN KEAMANAN:</p>' .
            '  <p style="margin:4px 0 0;font-size:12px;color:#b91c1c;">Jangan bagikan kode ini kepada siapa pun. Jika Anda tidak meminta reset kata sandi, segera amankan akun Anda atau hubungi administrator.</p>' .
            '</div>',
            "Halo {{user_name}},\n\nKode verifikasi OTP untuk reset kata sandi Campus Find Anda adalah:\n\n{{otp_code}}\n\nKode ini berlaku selama 5 menit.\n\nPERINGATAN KEAMANAN: Jangan bagikan kode ini kepada siapa pun.\n\nJika Anda tidak meminta reset kata sandi, abaikan email ini.",
            '{{user_name}}, {{otp_code}}, {{expires_in}}, {{app_name}}',
        ]);
        echo "  - Templat 'password_reset_otp' berhasil didaftarkan.\n";
    }

    echo "Migrasi 032 selesai dengan sukses!\n";
} catch (\Throwable $e) {
    echo "Gagal migrasi 032: " . $e->getMessage() . "\n";
    exit(1);
}
