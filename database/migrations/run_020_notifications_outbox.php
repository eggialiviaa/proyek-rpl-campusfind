<?php
/**
 * Migration 020 — Notification Outbox, Templates, and User Opt-in Fields (Prompt 20)
 *
 * Menambahkan:
 * 1. Tabel `notification_outbox` untuk antrean pengiriman email eksternal terisolasi (Outbox pattern).
 * 2. Tabel `notification_templates` untuk isi templat email yang dapat dikustomisasi Admin.
 * 3. Kolom `email_notifications_enabled`, `email_consent_at`, dan `email_consent_text_version` pada tabel `users`.
 * 4. Pengaturan default SMTP pada tabel `system_settings`.
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
define('BASE_PATH', $projectRoot);

require_once $projectRoot . '/vendor/autoload.php';

use Dotenv\Dotenv;
Dotenv::createImmutable(BASE_PATH)->load();

$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port   = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? 'campus_find';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connected\n";
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migration 020: Notification Outbox, Templates & User Opt-in ===\n\n";

// 1. Tambah kolom opt-in pada tabel users jika belum ada
$cols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('email_notifications_enabled', $cols, true)) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `email_notifications_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_verified_at`");
    echo "✓ Added column users.email_notifications_enabled\n";
} else {
    echo "• Column users.email_notifications_enabled already exists\n";
}

if (!in_array('email_consent_at', $cols, true)) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `email_consent_at` DATETIME NULL AFTER `email_notifications_enabled`");
    echo "✓ Added column users.email_consent_at\n";
} else {
    echo "• Column users.email_consent_at already exists\n";
}

if (!in_array('email_consent_text_version', $cols, true)) {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `email_consent_text_version` VARCHAR(50) NULL AFTER `email_consent_at`");
    echo "✓ Added column users.email_consent_text_version\n";
} else {
    echo "• Column users.email_consent_text_version already exists\n";
}

// 2. Buat tabel notification_outbox
$sqlOutbox = "
CREATE TABLE IF NOT EXISTS `notification_outbox` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `channel` VARCHAR(30) NOT NULL DEFAULT 'email',
    `recipient` VARCHAR(191) NOT NULL,
    `template_key` VARCHAR(60) NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('pending', 'sending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    `attempts` INT NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME NULL,
    `next_try_at` DATETIME NOT NULL,
    `error_reason` TEXT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL UNIQUE,
    `sent_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_outbox_status_next_try` (`status`, `next_try_at`),
    INDEX `idx_outbox_user_id` (`user_id`),
    INDEX `idx_outbox_channel_status` (`channel`, `status`),
    INDEX `idx_outbox_created_at` (`created_at`),
    CONSTRAINT `fk_outbox_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$pdo->exec($sqlOutbox);
echo "✓ Table notification_outbox created or already exists\n";

// 3. Buat tabel notification_templates
$sqlTemplates = "
CREATE TABLE IF NOT EXISTS `notification_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `template_key` VARCHAR(60) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(191) NOT NULL,
    `body_html` TEXT NOT NULL,
    `body_text` TEXT NOT NULL,
    `variables_hint` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$pdo->exec($sqlTemplates);
echo "✓ Table notification_templates created or already exists\n";

// 4. Seed default notification templates (singkat, tanpa data sensitif)
$defaultTemplates = [
    [
        'template_key'   => 'claim_status_changed',
        'name'           => 'Pembaruan Status Klaim',
        'subject'        => '[Campus Find] Pembaruan Status Pengajuan Klaim',
        'body_html'      => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Status pengajuan klaim Anda di Campus Find telah diperbarui:</p><div style="background:#f8fafc;border-left:4px solid #0284c7;padding:12px 16px;margin:16px 0;border-radius:4px;"><p style="margin:0;font-weight:600;color:#0f172a;">{{title}}</p><p style="margin:4px 0 0;color:#475569;">{{message}}</p></div><p>Silakan buka Campus Find untuk melihat detail dan instruksi selanjutnya.</p><p style="margin-top:24px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0284c7;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka Detail Klaim</a></p>',
        'body_text'      => "Halo {{user_name}},\n\nStatus pengajuan klaim Anda di Campus Find telah diperbarui:\n{{title}}\n{{message}}\n\nBuka detail klaim di Campus Find:\n{{action_url}}",
        'variables_hint' => '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}',
    ],
    [
        'template_key'   => 'found_item_received',
        'name'           => 'Barang Temuan Diterima di Center',
        'subject'        => '[Campus Find] Barang Temuan Telah Diterima di Posko',
        'body_html'      => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Terima kasih atas kepedulian Anda. Laporan barang temuan Anda telah diterima dan barang tersimpan dengan aman di Lost & Found Center kampus.</p><div style="background:#f8fafc;border-left:4px solid #16a34a;padding:12px 16px;margin:16px 0;border-radius:4px;"><p style="margin:0;font-weight:600;color:#0f172a;">{{title}}</p><p style="margin:4px 0 0;color:#475569;">{{message}}</p></div><p style="margin-top:24px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#16a34a;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Lihat Laporan Temuan</a></p>',
        'body_text'      => "Halo {{user_name}},\n\nBarang temuan yang Anda laporkan telah diterima di Lost & Found Center kampus:\n{{title}}\n{{message}}\n\nLihat laporan:\n{{action_url}}",
        'variables_hint' => '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}',
    ],
    [
        'template_key'   => 'new_chat_message',
        'name'           => 'Pesan Chat Baru Terkait Klaim',
        'subject'        => '[Campus Find] Pesan Baru Mengenai Pengajuan Klaim',
        'body_html'      => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Anda memiliki pesan baru dari Petugas Lost & Found Center terkait pengajuan klaim Anda:</p><div style="background:#f8fafc;border-left:4px solid #6366f1;padding:12px 16px;margin:16px 0;border-radius:4px;"><p style="margin:0;font-style:italic;color:#334155;">{{message}}</p></div><p>Silakan balas pesan melalui ruang diskusi resmi di Campus Find.</p><p style="margin-top:24px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#6366f1;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka Ruang Diskusi</a></p>',
        'body_text'      => "Halo {{user_name}},\n\nAnda memiliki pesan baru mengenai klaim Anda:\n{{message}}\n\nBuka pesan di Campus Find:\n{{action_url}}",
        'variables_hint' => '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}',
    ],
    [
        'template_key'   => 'match_candidate_found',
        'name'           => 'Kandidat Kecocokan Barang Hilang',
        'subject'        => '[Campus Find] Ada Kemungkinan Kecocokan Barang Hilang Anda',
        'body_html'      => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Sistem Campus Find menemukan barang temuan baru yang mungkin cocok dengan laporan kehilangan Anda.</p><div style="background:#f8fafc;border-left:4px solid #8b5cf6;padding:12px 16px;margin:16px 0;border-radius:4px;"><p style="margin:0;font-weight:600;color:#0f172a;">Kemungkinan Kecocokan Terdeteksi</p><p style="margin:4px 0 0;color:#475569;">{{message}}</p></div><p>Periksa detail kecocokan dan ajukan klaim jika barang tersebut memang milik Anda.</p><p style="margin-top:24px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#8b5cf6;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Tinjau Kecocokan</a></p>',
        'body_text'      => "Halo {{user_name}},\n\nAda kemungkinan kecocokan barang hilang Anda di Campus Find:\n{{message}}\n\nTinjau kecocokan:\n{{action_url}}",
        'variables_hint' => '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}',
    ],
    [
        'template_key'   => 'lost_report_status_changed',
        'name'           => 'Pembaruan Status Laporan Kehilangan',
        'subject'        => '[Campus Find] Status Laporan Kehilangan Diperbarui',
        'body_html'      => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Status laporan kehilangan Anda di Campus Find telah diperbarui:</p><div style="background:#f8fafc;border-left:4px solid #0ea5e9;padding:12px 16px;margin:16px 0;border-radius:4px;"><p style="margin:0;font-weight:600;color:#0f172a;">{{title}}</p><p style="margin:4px 0 0;color:#475569;">{{message}}</p></div><p style="margin-top:24px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0ea5e9;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Lihat Laporan</a></p>',
        'body_text'      => "Halo {{user_name}},\n\nStatus laporan kehilangan Anda telah diperbarui:\n{{title}}\n{{message}}\n\nBuka laporan:\n{{action_url}}",
        'variables_hint' => '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}',
    ],
    [
        'template_key'   => 'test_email',
        'name'           => 'Uji Coba Pengiriman Email',
        'subject'        => '[Campus Find] Uji Coba Konfigurasi Notifikasi Email',
        'body_html'      => '<p>Halo <strong>{{user_name}}</strong>,</p><p>Ini adalah email uji coba dari sistem <strong>Campus Find</strong> untuk memverifikasi bahwa konfigurasi SMTP server berfungsi dengan baik.</p><div style="background:#f0fdf4;border-left:4px solid #22c55e;padding:12px 16px;margin:16px 0;border-radius:4px;"><p style="margin:0;font-weight:600;color:#15803d;">Koneksi Berhasil!</p><p style="margin:4px 0 0;color:#166534;">Konfigurasi email SMTP Anda telah aktif dan siap mendistribusikan notifikasi eksternal.</p></div><p style="margin-top:24px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka Dasbor Admin</a></p>',
        'body_text'      => "Halo {{user_name}},\n\nIni adalah email uji coba dari sistem Campus Find untuk memverifikasi konfigurasi SMTP.\nKoneksi berhasil dan siap mendistribusikan notifikasi eksternal.\n\nBuka dasbor admin:\n{{action_url}}",
        'variables_hint' => '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}',
    ],
];

$stmtTpl = $pdo->prepare("
    INSERT INTO `notification_templates` (`template_key`, `name`, `subject`, `body_html`, `body_text`, `variables_hint`, `is_active`, `created_at`, `updated_at`)
    VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        `name` = VALUES(`name`),
        `subject` = VALUES(`subject`),
        `body_html` = VALUES(`body_html`),
        `body_text` = VALUES(`body_text`),
        `variables_hint` = VALUES(`variables_hint`),
        `updated_at` = NOW()
");

foreach ($defaultTemplates as $tpl) {
    $stmtTpl->execute([
        $tpl['template_key'],
        $tpl['name'],
        $tpl['subject'],
        $tpl['body_html'],
        $tpl['body_text'],
        $tpl['variables_hint']
    ]);
}
echo "✓ Default notification templates seeded (" . count($defaultTemplates) . " templates)\n";

// 5. Inisialisasi default settings untuk notification di system_settings jika belum ada
$defaultSettings = [
    'notif_external_channel'     => ['value' => 'email', 'encrypted' => 0, 'group' => 'notification'],
    'notif_email_status'         => ['value' => '1',     'encrypted' => 0, 'group' => 'notification'],
    'notif_smtp_host'            => ['value' => 'smtp.gmail.com', 'encrypted' => 0, 'group' => 'notification'],
    'notif_smtp_port'            => ['value' => '587',   'encrypted' => 0, 'group' => 'notification'],
    'notif_smtp_encryption'      => ['value' => 'tls',   'encrypted' => 0, 'group' => 'notification'],
    'notif_smtp_username'        => ['value' => '',      'encrypted' => 0, 'group' => 'notification'],
    'notif_smtp_password'        => ['value' => '',      'encrypted' => 1, 'group' => 'notification'],
    'notif_smtp_from_address'    => ['value' => 'no-reply@campusfind.ac.id', 'encrypted' => 0, 'group' => 'notification'],
    'notif_smtp_from_name'       => ['value' => 'Campus Find L&F', 'encrypted' => 0, 'group' => 'notification'],
    'notif_daily_limit'          => ['value' => '100',   'encrypted' => 0, 'group' => 'notification'],
    'notif_allowlist'            => ['value' => 'admin@campusfind.test', 'encrypted' => 0, 'group' => 'notification'],
    'notif_circuit_breaker_tripped' => ['value' => '0',  'encrypted' => 0, 'group' => 'notification'],
    'notif_consecutive_failures' => ['value' => '0',    'encrypted' => 0, 'group' => 'notification'],
];

$stmtSet = $pdo->prepare("
    INSERT INTO `system_settings` (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
    VALUES (?, ?, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        `group` = VALUES(`group`)
");

foreach ($defaultSettings as $k => $meta) {
    $stmtSet->execute([$k, $meta['value'], $meta['encrypted'], $meta['group']]);
}
echo "✓ Default system_settings for notification initialized\n";

echo "\nMigration 020 completed successfully!\n";
