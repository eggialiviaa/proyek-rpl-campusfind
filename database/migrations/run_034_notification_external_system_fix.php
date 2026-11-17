<?php

declare(strict_types=1);

/**
 * Migration 034: Notification External System Fix & Decision Logs
 * 
 * 1. Membuat tabel notification_decisions untuk jejak keputusan (diantre / dilewati)
 * 2. Menambahkan setting 'notif_email_new_lost_report' ke system_settings
 * 3. Menambahkan templat notifikasi baru ke notification_templates:
 *    - new_claim_submitted
 *    - new_found_report
 *    - new_lost_report
 *    - system_alert_failure
 *    - system_alert_quota
 *    - email_verification_otp
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use Core\Database;

try {
    $pdo = Database::getInstance()->getPdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Running Migration 034...\n";

    // 1. Buat tabel notification_decisions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notification_decisions` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `event_name` VARCHAR(80) NOT NULL,
            `user_id` INT UNSIGNED NULL,
            `recipient_email` VARCHAR(191) NULL,
            `decision` ENUM('queued', 'skipped') NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `channel` VARCHAR(30) NOT NULL DEFAULT 'email',
            `outbox_id` BIGINT UNSIGNED NULL,
            `context_data` JSON NULL,
            `created_at` DATETIME NOT NULL,
            INDEX `idx_notif_dec_event` (`event_name`),
            INDEX `idx_notif_dec_decision` (`decision`),
            INDEX `idx_notif_dec_user` (`user_id`),
            INDEX `idx_notif_dec_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table notification_decisions created or already exists\n";

    // 2. Setting notif_email_new_lost_report
    $stmtCheckSetting = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE `key` = ?");
    $stmtCheckSetting->execute(['notif_email_new_lost_report']);
    if ((int)$stmtCheckSetting->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`) VALUES (?, ?, 0, 'notification', NOW(), NOW())")
            ->execute([
                'notif_email_new_lost_report',
                '0'
            ]);
        echo "✓ Setting notif_email_new_lost_report added\n";
    }

    // 3. Templat baru
    $newTemplates = [
        [
            'template_key' => 'new_claim_submitted',
            'name'         => 'Pengajuan Klaim Baru (Untuk Petugas)',
            'subject'      => '[Campus Find] Pengajuan Klaim Baru Memerlukan Peninjauan',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Ada pengajuan klaim kepemilikan baru yang masuk ke sistem dan memerlukan pemeriksaan berkas oleh Petugas:</p><p style="padding:12px;background:#f8f9fa;border-left:4px solid #0d6efd;"><strong>{{title}}</strong><br>{{message}}</p><p><a href="{{action_url}}" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;">Buka Antrean Klaim Petugas</a></p><p style="color:#6c757d;font-size:12px;">Email ini ditujukan untuk Petugas Lost &amp; Found Center Campus Find.</p>',
            'body_text'    => "Halo {{user_name}},\n\nAda pengajuan klaim kepemilikan baru yang memerlukan peninjauan Petugas:\n{{title}}\n{{message}}\n\nBuka Antrean Klaim:\n{{action_url}}\n\nCampus Find Lost & Found Center",
            'variables'    => 'user_name, title, message, action_url, app_name',
        ],
        [
            'template_key' => 'new_found_report',
            'name'         => 'Laporan Temuan Baru Menunggu Penyerahan (Untuk Petugas)',
            'subject'      => '[Campus Find] Laporan Barang Temuan Baru Menunggu Penyerahan Fisik',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Seorang pelapor telah menerbitkan laporan barang temuan baru dan sedang menunggu serah terima barang ke L&amp;F Center:</p><p style="padding:12px;background:#f8f9fa;border-left:4px solid #198754;"><strong>{{title}}</strong><br>{{message}}</p><p><a href="{{action_url}}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:6px;">Lihat Detail Laporan Temuan</a></p><p style="color:#6c757d;font-size:12px;">Email ini ditujukan untuk Petugas Lost &amp; Found Center Campus Find.</p>',
            'body_text'    => "Halo {{user_name}},\n\nLaporan barang temuan baru diterbitkan dan menunggu serah terima barang fisik:\n{{title}}\n{{message}}\n\nLihat Detail:\n{{action_url}}\n\nCampus Find Lost & Found Center",
            'variables'    => 'user_name, title, message, action_url, app_name',
        ],
        [
            'template_key' => 'new_lost_report',
            'name'         => 'Laporan Kehilangan Baru (Opsional Email ke Petugas)',
            'subject'      => '[Campus Find] Laporan Kehilangan Baru Diterbitkan',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Seorang pengguna telah menerbitkan laporan kehilangan baru:</p><p style="padding:12px;background:#f8f9fa;border-left:4px solid #ffc107;"><strong>{{title}}</strong><br>{{message}}</p><p><a href="{{action_url}}" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;">Lihat Laporan Kehilangan</a></p><p style="color:#6c757d;font-size:12px;">Pengiriman email ini dikendalikan oleh Pengaturan Notifikasi Administrator.</p>',
            'body_text'    => "Halo {{user_name}},\n\nLaporan kehilangan baru telah diterbitkan:\n{{title}}\n{{message}}\n\nLihat Laporan:\n{{action_url}}\n\nCampus Find Lost & Found Center",
            'variables'    => 'user_name, title, message, action_url, app_name',
        ],
        [
            'template_key' => 'system_alert_failure',
            'name'         => 'Peringatan Sistem: Worker Gagal Beruntun (Untuk Admin)',
            'subject'      => '[PERINGATAN SISTEM] Kegagalan Beruntun pada Worker Notifikasi',
            'body_html'    => '<p>Halo Administrator <strong>{{user_name}}</strong>,</p><p style="color:#dc3545;font-weight:bold;">Sistem mendeteksi 5 kegagalan berturut-turut pada pengiriman email notifikasi. Circuit Breaker telah diaktifkan secara otomatis untuk mencegah pemblokiran akun SMTP.</p><p style="padding:12px;background:#fff3cd;border-left:4px solid #ffc107;"><strong>Pesan Sistem:</strong><br>{{message}}</p><p><a href="{{action_url}}" style="display:inline-block;padding:10px 18px;background:#dc3545;color:#fff;text-decoration:none;border-radius:6px;">Buka Pengaturan Notifikasi &amp; Reset Circuit Breaker</a></p>',
            'body_text'    => "Halo Administrator {{user_name}},\n\n[PERINGATAN SISTEM] 5 kegagalan berturut-turut pada pengiriman email. Circuit Breaker aktif.\n\nPesan Sistem:\n{{message}}\n\nBuka Pengaturan Notifikasi:\n{{action_url}}\n\nCampus Find System Monitoring",
            'variables'    => 'user_name, title, message, action_url, app_name',
        ],
        [
            'template_key' => 'system_alert_quota',
            'name'         => 'Peringatan Sistem: Batas Kuota Harian Tercapai (Untuk Admin)',
            'subject'      => '[PERINGATAN SISTEM] Kuota Harian Pengiriman Email Telah Habis',
            'body_html'    => '<p>Halo Administrator <strong>{{user_name}}</strong>,</p><p style="color:#fd7e14;font-weight:bold;">Sistem mendeteksi bahwa batas kuota harian pengiriman email notifikasi (Daily Limit) telah tercapai.</p><p style="padding:12px;background:#f8f9fa;border-left:4px solid #fd7e14;"><strong>Rincian:</strong><br>{{message}}</p><p>Antrean email yang jatuh tempo hari ini secara otomatis ditunda ke esok hari untuk mencegah pelanggaran kuota penyedia SMTP.</p><p><a href="{{action_url}}" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;">Buka Pengaturan Notifikasi</a></p>',
            'body_text'    => "Halo Administrator {{user_name}},\n\n[PERINGATAN SISTEM] Kuota harian email tercapai. Antrean ditunda ke esok hari.\n\nRincian:\n{{message}}\n\nBuka Pengaturan:\n{{action_url}}\n\nCampus Find System Monitoring",
            'variables'    => 'user_name, title, message, action_url, app_name',
        ],
        [
            'template_key' => 'email_verification_otp',
            'name'         => 'Kode OTP Verifikasi Email Akun',
            'subject'      => '[Campus Find] Kode Verifikasi Email Anda',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Berikut adalah kode OTP untuk memverifikasi alamat email akun Campus Find Anda:</p><div style="font-size:28px;font-weight:bold;letter-spacing:6px;padding:16px;background:#f0f4ff;color:#0d6efd;text-align:center;border-radius:8px;margin:16px 0;">{{otp_code}}</div><p>Kode ini berlaku selama <strong>5 menit</strong>. Jangan berikan kode ini kepada siapa pun.</p><p style="color:#6c757d;font-size:12px;">Jika Anda tidak meminta verifikasi ini, silakan abaikan email ini.</p>',
            'body_text'    => "Halo {{user_name}},\n\nKode OTP verifikasi email Anda: {{otp_code}}\n\nKode berlaku 5 menit. Jangan berikan kepada siapa pun.\n\nCampus Find Security",
            'variables'    => 'user_name, otp_code, app_name',
        ],
    ];

    $stmtTplCheck = $pdo->prepare("SELECT id FROM notification_templates WHERE template_key = ?");
    $stmtTplInsert = $pdo->prepare("
        INSERT INTO notification_templates (
            template_key, name, subject, body_html, body_text, variables_hint, is_active, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
        )
    ");

    foreach ($newTemplates as $tpl) {
        $stmtTplCheck->execute([$tpl['template_key']]);
        if (!$stmtTplCheck->fetchColumn()) {
            $stmtTplInsert->execute([
                $tpl['template_key'],
                $tpl['name'],
                $tpl['subject'],
                $tpl['body_html'],
                $tpl['body_text'],
                $tpl['variables'],
            ]);
            echo "✓ Template {$tpl['template_key']} created\n";
        }
    }

    echo "Migration 034 finished successfully.\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
