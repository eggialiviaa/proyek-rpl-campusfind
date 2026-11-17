<?php

declare(strict_types=1);

/**
 * Migration 035: Sinkronisasi Seluruh Templat Email ke Standar Resmi (Prompt 32B)
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

use App\Services\Notifications\NotificationTemplateService;
use Core\Database;

try {
    echo "=== Menjalankan Migrasi 035: Sinkronisasi Templat Notifikasi Email ===\n";
    $pdo = Database::getInstance()->getPdo();

    // 16 Templat Kanonikal Resmi
    $officialTemplates = [
        'account_activation_otp' => [
            'name'         => 'Kode OTP Aktivasi Akun Civitas',
            'subject'      => '[Campus Find] Kode Verifikasi Aktivasi Akun Anda: {{otp_code}}',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p>' .
                              '<p>Terima kasih telah mendaftar di <strong>{{app_name}}</strong>. Gunakan kode verifikasi OTP 6 digit di bawah ini untuk mengaktifkan akun Anda:</p>' .
                              '<div style="text-align:center;margin:24px 0;"><span style="display:inline-block;font-size:32px;font-weight:700;letter-spacing:6px;color:#0284c7;background:#f0f9ff;padding:14px 28px;border-radius:10px;border:1px dashed #7dd3fc;font-family:monospace;">{{otp_code}}</span><p style="margin:8px 0 0;font-size:13px;color:#64748b;">Kode berlaku selama <strong>{{expires_in}}</strong>.</p></div>' .
                              '<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 16px;border-radius:6px;margin:16px 0;"><p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;">PERINGATAN KEAMANAN:</p><p style="margin:4px 0 0;font-size:12px;color:#b91c1c;">Jangan berikan kode verifikasi ini kepada siapa pun. Petugas Lost &amp; Found tidak akan pernah meminta kode verifikasi Anda.</p></div>' .
                              '<p style="font-size:13px;color:#64748b;">Jika Anda tidak merasa meminta aktivasi ini, silakan abaikan email ini.</p>',
            'body_text'    => "Halo {{user_name}},\n\nKode verifikasi OTP untuk aktivasi akun {{app_name}} Anda adalah:\n\n{{otp_code}}\n\nKode ini berlaku selama {{expires_in}}.\n\nPERINGATAN KEAMANAN: Jangan bagikan kode ini kepada siapa pun. Petugas tidak akan pernah meminta kode verifikasi Anda.\n\nJika Anda tidak merasa meminta ini, silakan abaikan pesan ini.",
        ],
        'password_reset_otp' => [
            'name'         => 'Kode OTP Reset Kata Sandi',
            'subject'      => '[Campus Find] Kode Verifikasi Reset Kata Sandi: {{otp_code}}',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p>' .
                              '<p>Kami menerima permintaan untuk mereset kata sandi akun {{app_name}} Anda. Berikut adalah kode verifikasi OTP 6 digit Anda:</p>' .
                              '<div style="text-align:center;margin:24px 0;"><span style="display:inline-block;font-size:32px;font-weight:700;letter-spacing:6px;color:#ea580c;background:#fff7ed;padding:14px 28px;border-radius:10px;border:1px dashed #fdba74;font-family:monospace;">{{otp_code}}</span><p style="margin:8px 0 0;font-size:13px;color:#64748b;">Kode berlaku selama <strong>{{expires_in}}</strong>.</p></div>' .
                              '<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 16px;border-radius:6px;margin:16px 0;"><p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;">PERINGATAN KEAMANAN:</p><p style="margin:4px 0 0;font-size:12px;color:#b91c1c;">Jangan bagikan kode ini kepada siapa pun. Jika Anda tidak meminta reset kata sandi, segera amankan akun Anda atau hubungi administrator.</p></div>',
            'body_text'    => "Halo {{user_name}},\n\nKode verifikasi OTP untuk reset kata sandi {{app_name}} Anda adalah:\n\n{{otp_code}}\n\nKode ini berlaku selama {{expires_in}}.\n\nPERINGATAN KEAMANAN: Jangan bagikan kode ini kepada siapa pun.\n\nJika Anda tidak meminta reset kata sandi, abaikan email ini.",
        ],
        'email_change_otp' => [
            'name'         => 'Kode Verifikasi Perubahan Email',
            'subject'      => '[Campus Find] Kode Verifikasi Perubahan Alamat Email: {{otp_code}}',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p>' .
                              '<p>Kami menerima permintaan pembaruan alamat email pada akun {{app_name}} Anda. Gunakan kode verifikasi di bawah ini untuk mengonfirmasi bahwa alamat email ini milik Anda:</p>' .
                              '<div style="text-align:center;margin:24px 0;"><span style="display:inline-block;font-size:32px;font-weight:700;letter-spacing:6px;color:#2563eb;background:#eff6ff;padding:14px 28px;border-radius:10px;border:1px dashed #93c5fd;font-family:monospace;">{{otp_code}}</span><p style="margin:8px 0 0;font-size:13px;color:#64748b;">Kode berlaku selama <strong>{{expires_in}}</strong>.</p></div>' .
                              '<p style="font-size:13px;color:#64748b;">Jika Anda tidak melakukan permintaan perubahan email ini, segera abaikan pesan ini dan periksa keamanan akun Anda.</p>',
            'body_text'    => "Halo {{user_name}},\n\nKode verifikasi OTP untuk perubahan email akun {{app_name}} Anda adalah:\n\n{{otp_code}}\n\nKode berlaku selama {{expires_in}}.\n\nJika Anda tidak meminta perubahan ini, silakan abaikan email ini.",
        ],
        'email_verification_otp' => [
            'name'         => 'Kode OTP Verifikasi Email Akun',
            'subject'      => '[Campus Find] Kode Verifikasi Email Anda: {{otp_code}}',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p>' .
                              '<p>Berikut adalah kode verifikasi OTP 6 digit untuk memverifikasi alamat email akun {{app_name}} Anda:</p>' .
                              '<div style="text-align:center;margin:24px 0;"><span style="display:inline-block;font-size:32px;font-weight:700;letter-spacing:6px;color:#0d6efd;background:#f0f4ff;padding:14px 28px;border-radius:10px;border:1px dashed #9ec5fe;font-family:monospace;">{{otp_code}}</span><p style="margin:8px 0 0;font-size:13px;color:#64748b;">Kode berlaku selama <strong>{{expires_in}}</strong>.</p></div>' .
                              '<p style="font-size:13px;color:#64748b;">Jangan berikan kode ini kepada siapa pun. Jika Anda tidak merasa meminta verifikasi ini, abaikan pesan ini.</p>',
            'body_text'    => "Halo {{user_name}},\n\nKode OTP verifikasi email {{app_name}} Anda adalah:\n\n{{otp_code}}\n\nKode berlaku selama {{expires_in}}.\n\nJangan berikan kode ini kepada siapa pun.",
        ],
        'security_email_changed' => [
            'name'         => 'Pemberitahuan Keamanan: Alamat Email Telah Diubah',
            'subject'      => '[Campus Find] Pemberitahuan Keamanan: Alamat Email Akun Telah Diubah',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p>' .
                              '<p>Pemberitahuan keamanan: Alamat email yang terhubung dengan akun {{app_name}} Anda telah berhasil diperbarui menjadi <strong>{{new_email_masked}}</strong> pada <strong>{{changed_at}}</strong>.</p>' .
                              '<p>Jika perubahan ini dilakukan oleh Anda, Anda dapat mengabaikan email ini dengan aman.</p>' .
                              '<div style="background:#fef2f2;border:1px solid #fecaca;padding:12px 16px;border-radius:6px;margin:16px 0;"><p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;">BUKAN ANDA YANG MENGUBAH?</p><p style="margin:4px 0 0;font-size:12px;color:#b91c1c;">Segera hubungi tim Administrator Lost &amp; Found kampus untuk mengamankan akun Anda.</p></div>',
            'body_text'    => "Halo {{user_name}},\n\nPemberitahuan Keamanan: Alamat email akun {{app_name}} Anda telah diubah menjadi {{new_email_masked}} pada {{changed_at}}.\n\nJika ini bukan Anda, segera hubungi Administrator kampus untuk mengamankan akun Anda.",
        ],
        'claim_status_changed' => [
            'name'         => 'Pembaruan Status Klaim',
            'subject'      => '[Campus Find] Pembaruan Status Pengajuan Klaim',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Lihat Status Klaim</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBuka berkas klaim Anda:\n{{action_url}}",
        ],
        'found_item_received' => [
            'name'         => 'Barang Temuan Diterima di Center',
            'subject'      => '[Campus Find] Barang Temuan Telah Diterima di Posko',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#198754;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka Laporan Temuan</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBuka laporan temuan Anda:\n{{action_url}}",
        ],
        'lost_report_status_changed' => [
            'name'         => 'Pembaruan Status Laporan Kehilangan',
            'subject'      => '[Campus Find] Status Laporan Kehilangan Diperbarui',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka Laporan Kehilangan</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBuka laporan kehilangan Anda:\n{{action_url}}",
        ],
        'match_candidate_found' => [
            'name'         => 'Kandidat Kecocokan Barang Hilang',
            'subject'      => '[Campus Find] Ada Kemungkinan Kecocokan Barang Hilang Anda',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#6f42c1;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Periksa Kecocokan Barang</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nPeriksa kecocokan barang:\n{{action_url}}",
        ],
        'new_claim_submitted' => [
            'name'         => 'Pengajuan Klaim Baru (Untuk Petugas)',
            'subject'      => '[Campus Find] Pengajuan Klaim Baru Memerlukan Peninjauan',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#ffc107;color:#000000;text-decoration:none;border-radius:6px;font-weight:600;">Tinjau Klaim Sekarang</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nTinjau klaim:\n{{action_url}}",
        ],
        'new_found_report' => [
            'name'         => 'Laporan Temuan Baru Menunggu Penyerahan (Untuk Petugas)',
            'subject'      => '[Campus Find] Laporan Barang Temuan Baru Menunggu Penyerahan Fisik',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0dcaf0;color:#000000;text-decoration:none;border-radius:6px;font-weight:600;">Buka Laporan Temuan</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBuka laporan temuan:\n{{action_url}}",
        ],
        'new_lost_report' => [
            'name'         => 'Laporan Kehilangan Baru (Opsional Email ke Petugas)',
            'subject'      => '[Campus Find] Laporan Kehilangan Baru Diterbitkan',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#6c757d;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Lihat Detail Laporan</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nLihat laporan:\n{{action_url}}",
        ],
        'new_chat_message' => [
            'name'         => 'Pesan Chat Baru Terkait Klaim',
            'subject'      => '[Campus Find] Pesan Baru Mengenai Pengajuan Klaim',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p style="background:#f8f9fa;border-left:4px solid #0d6efd;padding:12px;margin:16px 0;font-style:italic;">{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Balas Pesan Chat</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBalas pesan chat:\n{{action_url}}",
        ],
        'system_alert_failure' => [
            'name'         => 'Peringatan Sistem: Worker Gagal Beruntun (Untuk Admin)',
            'subject'      => '[PERINGATAN SISTEM] Kegagalan Beruntun pada Worker Notifikasi',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong style="color:#dc3545;">{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#dc3545;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka Pengaturan Notifikasi</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n[PERINGATAN SISTEM] {{title}}\n{{message}}\n\nBuka Pengaturan:\n{{action_url}}",
        ],
        'system_alert_quota' => [
            'name'         => 'Peringatan Sistem: Batas Kuota Harian Tercapai (Untuk Admin)',
            'subject'      => '[PERINGATAN SISTEM] Kuota Harian Pengiriman Email Telah Habis',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong style="color:#fd7e14;">{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#fd7e14;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Kelola Kuota Email</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n[PERINGATAN SISTEM] {{title}}\n{{message}}\n\nKelola Kuota:\n{{action_url}}",
        ],
        'test_email' => [
            'name'         => 'Uji Coba Pengiriman Email',
            'subject'      => '[Campus Find] Uji Coba Konfigurasi Notifikasi Email',
            'body_html'    => '<p>Halo <strong>{{user_name}}</strong>,</p><p><strong>{{title}}</strong></p><p>{{message}}</p><p style="margin-top:20px;"><a href="{{action_url}}" style="display:inline-block;padding:10px 20px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">Buka {{app_name}}</a></p>',
            'body_text'    => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBuka {{app_name}}:\n{{action_url}}",
        ],
    ];

    $stmtUpsert = $pdo->prepare("
        INSERT INTO notification_templates (template_key, name, subject, body_html, body_text, variables_hint, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            subject = VALUES(subject),
            body_html = VALUES(body_html),
            body_text = VALUES(body_text),
            variables_hint = VALUES(variables_hint),
            updated_at = NOW()
    ");

    foreach ($officialTemplates as $key => $tpl) {
        $variablesHint = NotificationTemplateService::getOfficialVariablesHint($key);
        $stmtUpsert->execute([
            $key,
            $tpl['name'],
            $tpl['subject'],
            $tpl['body_html'],
            $tpl['body_text'],
            $variablesHint,
        ]);
        echo "  [OK] Templat '{$key}' berhasil disinkronisasi.\n";
    }

    echo "✓ Seluruh 16 templat email berhasil disinkronkan ke standar resmi!\n";
} catch (\Throwable $e) {
    echo "Galat Migrasi 035: " . $e->getMessage() . "\n";
    exit(1);
}
