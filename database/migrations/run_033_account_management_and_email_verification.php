<?php

declare(strict_types=1);

/**
 * Migration 033 — Account Management, Verification & Security Notifications
 *
 * Menambahkan:
 * 1. Kolom target_email pada tabel otp_verifications dan memperluas kolom purpose (mendukung 'email_change').
 * 2. Templat email 'email_change_otp' dan 'security_email_changed' pada notification_templates.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use Core\Database;

try {
    $pdo = Database::getInstance()->getPdo();
    echo "Menjalankan migrasi 033: Account Management & Email Verification...\n";

    // 1. Modifikasi kolom purpose pada otp_verifications agar mendukung 'email_change'
    $pdo->exec("ALTER TABLE otp_verifications MODIFY COLUMN purpose VARCHAR(50) NOT NULL");
    echo "  - Kolom 'purpose' pada otp_verifications berhasil diubah ke VARCHAR(50).\n";

    // 2. Tambah kolom target_email pada otp_verifications jika belum ada
    $colCheck = $pdo->query("SHOW COLUMNS FROM otp_verifications LIKE 'target_email'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE otp_verifications ADD COLUMN target_email VARCHAR(255) NULL AFTER purpose");
        echo "  - Kolom 'target_email' berhasil ditambahkan ke otp_verifications.\n";
    } else {
        echo "  - Kolom 'target_email' sudah ada.\n";
    }

    // 3. Tambahkan templat email_change_otp
    $stmtTplCheck = $pdo->prepare("SELECT id FROM notification_templates WHERE template_key = ?");
    $stmtTplCheck->execute(['email_change_otp']);
    if (!$stmtTplCheck->fetch()) {
        $stmtInsertTpl = $pdo->prepare("
            INSERT INTO notification_templates (
                template_key, name, subject, body_html, body_text, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $tplHtml = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 560px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;">'
            . '<div style="margin-bottom: 20px; text-align: center;"><h2 style="color: #0f172a; margin: 0;">Campus Find</h2><p style="color: #64748b; font-size: 14px; margin-top: 4px;">Sistem Pelaporan &amp; Pengelolaan Barang Hilang</p></div>'
            . '<div style="background: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 20px;">'
            . '<h3 style="color: #1e293b; margin-top: 0;">Halo, {{user_name}}!</h3>'
            . '<p style="color: #475569; font-size: 14px; line-height: 1.6;">Kami menerima permintaan untuk mengubah alamat email akun Campus Find Anda ke alamat ini. Gunakan kode verifikasi (OTP) berikut untuk menyelesaikan perubahan email:</p>'
            . '<div style="text-align: center; margin: 24px 0;"><span style="display: inline-block; font-size: 32px; font-weight: 700; letter-spacing: 6px; color: #2563eb; background: #eff6ff; padding: 12px 28px; border-radius: 8px; border: 1px dashed #93c5fd;">{{otp_code}}</span></div>'
            . '<p style="color: #64748b; font-size: 13px; margin-bottom: 4px;">Kode verifikasi ini berlaku selama <strong>{{expires_in}}</strong>.</p>'
            . '<p style="color: #dc2626; font-size: 13px; font-weight: 600; margin-top: 0;">PENTING: Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari pengelola Campus Find.</p>'
            . '</div>'
            . '<p style="color: #94a3b8; font-size: 12px; text-align: center;">Jika Anda tidak merasa meminta perubahan alamat email ini, abaikan email ini.</p>'
            . '</div>';

        $tplPlain = "Halo {{user_name}},\n\n"
            . "Kami menerima permintaan untuk mengubah alamat email akun Campus Find Anda ke alamat ini.\n\n"
            . "Kode verifikasi (OTP) Anda: {{otp_code}}\n\n"
            . "Kode berlaku selama {{expires_in}}.\n\n"
            . "PENTING: Jangan bagikan kode ini kepada siapa pun.\n"
            . "Jika Anda tidak meminta perubahan ini, abaikan email ini.\n\n"
            . "-- Tim Campus Find";

        $stmtInsertTpl->execute([
            'email_change_otp',
            'Kode Verifikasi Perubahan Email',
            '[Campus Find] Kode Verifikasi Perubahan Alamat Email: {{otp_code}}',
            $tplHtml,
            $tplPlain,
        ]);
        echo "  - Templat 'email_change_otp' berhasil ditambahkan.\n";
    }

    // 4. Tambahkan templat security_email_changed
    $stmtTplCheck->execute(['security_email_changed']);
    if (!$stmtTplCheck->fetch()) {
        $stmtInsertTpl = $pdo->prepare("
            INSERT INTO notification_templates (
                template_key, name, subject, body_html, body_text, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $tplSecHtml = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 560px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff;">'
            . '<div style="margin-bottom: 20px; text-align: center;"><h2 style="color: #0f172a; margin: 0;">Campus Find</h2><p style="color: #64748b; font-size: 14px; margin-top: 4px;">Pemberitahuan Keamanan Akun</p></div>'
            . '<div style="background: #fef2f2; border: 1px solid #fee2e2; border-radius: 8px; padding: 20px; margin-bottom: 20px;">'
            . '<h3 style="color: #991b1b; margin-top: 0;">Peringatan Keamanan: Email Akun Diubah</h3>'
            . '<p style="color: #475569; font-size: 14px; line-height: 1.6;">Halo <strong>{{user_name}}</strong>, kami memberitahukan bahwa alamat email akun Campus Find Anda baru saja diubah ke alamat email baru: <strong>{{new_email_masked}}</strong> pada {{changed_at}}.</p>'
            . '<p style="color: #475569; font-size: 14px; line-height: 1.6;">Alamat email lama ini tidak akan lagi menerima notifikasi atau digunakan untuk masuk ke sistem.</p>'
            . '<div style="background: #ffffff; border-left: 4px solid #dc2626; padding: 12px; margin: 16px 0; font-size: 13px; color: #b91c1c;">'
            . 'Jika Anda <strong>TIDAK MERASA</strong> melakukan atau meminta perubahan ini, akun Anda mungkin dalam bahaya. Segera hubungi Administrator Sistem atau Helpdesk Campus Find untuk mengamankan akun Anda.'
            . '</div>'
            . '</div>'
            . '<p style="color: #94a3b8; font-size: 12px; text-align: center;">Pesan otomatis keamanan dari sistem Campus Find.</p>'
            . '</div>';

        $tplSecPlain = "Halo {{user_name}},\n\n"
            . "PEMBERITAHUAN KEAMANAN: Alamat email akun Campus Find Anda telah diubah ke: {{new_email_masked}} pada {{changed_at}}.\n\n"
            . "Alamat email lama ini tidak akan lagi menerima notifikasi atau digunakan untuk masuk ke sistem.\n\n"
            . "Jika Anda TIDAK MERASA melakukan perubahan ini, segera hubungi Administrator Sistem atau Helpdesk Campus Find.\n\n"
            . "-- Tim Keamanan Campus Find";

        $stmtInsertTpl->execute([
            'security_email_changed',
            'Pemberitahuan Keamanan: Alamat Email Telah Diubah',
            '[Campus Find] Pemberitahuan Keamanan: Alamat Email Akun Telah Diubah',
            $tplSecHtml,
            $tplSecPlain,
        ]);
        echo "  - Templat 'security_email_changed' berhasil ditambahkan.\n";
    }

    echo "Migrasi 033 selesai dengan sukses!\n";
} catch (\Throwable $e) {
    echo "Gagal migrasi 033: " . $e->getMessage() . "\n";
    exit(1);
}
