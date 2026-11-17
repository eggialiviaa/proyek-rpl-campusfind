<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Core\Database;
use PDO;
use Throwable;

/**
 * NotificationTemplateService
 *
 * Mengelola definisi resmi placeholder per templat notifikasi email,
 * normalisasi placeholder (mendukung {var} dan {{var}} serta alias seperti {code_otp}),
 * validasi ketat saat penyuntingan templat oleh Admin, dan pengamanan rendering.
 */
class NotificationTemplateService
{
    /**
     * Definisi resmi placeholder untuk setiap templat email
     */
    public const TEMPLATES = [
        'account_activation_otp' => [
            'name'        => 'Kode OTP Aktivasi Akun Civitas',
            'category'    => 'otp',
            'description' => 'Dikirimkan saat Civitas mendaftar atau mengaktifkan akun baru di kampus.',
            'placeholders' => [
                'user_name'   => 'Nama lengkap penerima',
                'otp_code'    => 'Kode OTP 6-digit angka untuk aktivasi',
                'expires_in'  => 'Masa berlaku kode (mis. 5 menit)',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
                'action_url'  => 'URL aksi verifikasi aktivasi',
            ],
            'aliases' => [
                'code_otp'        => 'otp_code',
                'code'            => 'otp_code',
                'otp'             => 'otp_code',
                'expires_minutes' => 'expires_in',
            ],
            'sample' => [
                'user_name'  => 'Budi Prasetyo',
                'otp_code'   => '384912',
                'expires_in' => '5 menit',
                'action_url' => '/activate/verify',
            ],
        ],
        'password_reset_otp' => [
            'name'        => 'Kode OTP Reset Kata Sandi',
            'category'    => 'otp',
            'description' => 'Dikirimkan saat pengguna meminta pengaturan ulang kata sandi.',
            'placeholders' => [
                'user_name'   => 'Nama lengkap penerima',
                'otp_code'    => 'Kode OTP 6-digit angka untuk reset password',
                'expires_in'  => 'Masa berlaku kode (mis. 5 menit)',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
                'action_url'  => 'URL aksi reset kata sandi',
            ],
            'aliases' => [
                'code_otp'        => 'otp_code',
                'code'            => 'otp_code',
                'otp'             => 'otp_code',
                'expires_minutes' => 'expires_in',
            ],
            'sample' => [
                'user_name'  => 'Siti Rahma',
                'otp_code'   => '719245',
                'expires_in' => '5 menit',
                'action_url' => '/forgot-password/verify',
            ],
        ],
        'email_change_otp' => [
            'name'        => 'Kode Verifikasi Perubahan Email',
            'category'    => 'otp',
            'description' => 'Dikirimkan ke alamat email BARU saat pengguna mengubah email mandiri.',
            'placeholders' => [
                'user_name'   => 'Nama lengkap penerima',
                'otp_code'    => 'Kode OTP 6-digit angka untuk verifikasi email baru',
                'expires_in'  => 'Masa berlaku kode (mis. 5 menit)',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
                'action_url'  => 'URL aksi konfirmasi perubahan email',
            ],
            'aliases' => [
                'code_otp'        => 'otp_code',
                'code'            => 'otp_code',
                'otp'             => 'otp_code',
                'expires_minutes' => 'expires_in',
            ],
            'sample' => [
                'user_name'  => 'Ahmad Fauzi',
                'otp_code'   => '552109',
                'expires_in' => '5 menit',
                'action_url' => '/profile',
            ],
        ],
        'email_verification_otp' => [
            'name'        => 'Kode OTP Verifikasi Email Akun',
            'category'    => 'otp',
            'description' => 'Dikirimkan ke email saat ini dari menu Profil untuk memverifikasi alamat.',
            'placeholders' => [
                'user_name'   => 'Nama lengkap penerima',
                'otp_code'    => 'Kode OTP 6-digit verifikasi email',
                'expires_in'  => 'Masa berlaku kode (mis. 5 menit)',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
                'action_url'  => 'URL aksi verifikasi email profil',
            ],
            'aliases' => [
                'code_otp'        => 'otp_code',
                'code'            => 'otp_code',
                'otp'             => 'otp_code',
                'expires_minutes' => 'expires_in',
            ],
            'sample' => [
                'user_name'  => 'Dewi Lestari',
                'otp_code'   => '849301',
                'expires_in' => '5 menit',
                'action_url' => '/profile',
            ],
        ],
        'security_email_changed' => [
            'name'        => 'Pemberitahuan Keamanan: Alamat Email Telah Diubah',
            'category'    => 'security',
            'description' => 'Peringatan keamanan yang dikirimkan ke alamat email LAMA setelah email berhasil diubah.',
            'placeholders' => [
                'user_name'         => 'Nama lengkap pemilik akun',
                'new_email_masked'  => 'Alamat email baru dalam bentuk tersensor',
                'changed_at'        => 'Waktu perubahan email dilakukan',
                'app_name'          => 'Nama aplikasi (Campus Find)',
                'app_url'           => 'URL portal Campus Find',
                'action_url'        => 'URL aksi bantuan/pengaduan keamanan',
            ],
            'aliases' => [
                'email_masked' => 'new_email_masked',
            ],
            'sample' => [
                'user_name'        => 'Ahmad Fauzi',
                'new_email_masked' => 'ah***@alumni.ac.id',
                'changed_at'       => '01 November 2026, 14:00 WIB',
                'action_url'       => '/profile',
            ],
        ],
        'security_password_changed' => [
            'name'        => 'Pemberitahuan Keamanan: Kata Sandi Telah Diubah',
            'category'    => 'security',
            'description' => 'Peringatan keamanan yang dikirimkan ke email terverifikasi saat kata sandi berhasil diubah.',
            'placeholders' => [
                'user_name'   => 'Nama lengkap pemilik akun',
                'changed_at'  => 'Waktu perubahan kata sandi dilakukan',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
                'action_url'  => 'URL aksi bantuan/pengaduan keamanan atau reset kata sandi',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Ahmad Fauzi',
                'changed_at' => '03 November 2026, 06:00 WIB',
                'action_url' => '/forgot-password',
            ],
        ],
        'claim_status_changed' => [
            'name'        => 'Pembaruan Status Klaim',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan ke pemilik klaim saat status pengajuan klaim berubah.',
            'placeholders' => [
                'user_name'   => 'Nama pemilik klaim',
                'title'       => 'Judul status pembaruan',
                'message'     => 'Isi ringkasan informasi perubahan status klaim',
                'action_url'  => 'URL rujukan langsung ke berkas klaim',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Rian Pratama',
                'title'      => 'Klaim Anda Telah Disetujui',
                'message'    => 'Pengajuan klaim CLM-2026-0012 untuk Laptop Asus telah disetujui petugas.',
                'action_url' => '/claims/12',
            ],
        ],
        'found_item_received' => [
            'name'        => 'Barang Temuan Diterima di Center',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan saat barang temuan fisik telah diverifikasi dan disimpan di posko.',
            'placeholders' => [
                'user_name'   => 'Nama pelapor barang temuan',
                'title'       => 'Judul konfirmasi penerimaan barang',
                'message'     => 'Detail penerimaan barang di Lost & Found Center',
                'action_url'  => 'URL rujukan ke laporan temuan',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Nurul Hidayah',
                'title'      => 'Barang Temuan Diterima di Posko',
                'message'    => 'Barang temuan yang Anda laporkan (Kunci Motor Honda) telah diterima di posko.',
                'action_url' => '/reports/found/45',
            ],
        ],
        'lost_report_status_changed' => [
            'name'        => 'Pembaruan Status Laporan Kehilangan',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan ke pelapor saat status laporan kehilangan barang diperbarui.',
            'placeholders' => [
                'user_name'   => 'Nama pelapor kehilangan',
                'title'       => 'Judul status pembaruan laporan',
                'message'     => 'Ringkasan perkembangan laporan kehilangan',
                'action_url'  => 'URL rujukan ke laporan kehilangan',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Kevin Sanjaya',
                'title'      => 'Status Laporan Kehilangan Diperbarui',
                'message'    => 'Laporan kehilangan LH-2026-0089 (Dompet Kulit Cokelat) kini berstatus selesai.',
                'action_url' => '/reports/lost/89',
            ],
        ],
        'match_candidate_found' => [
            'name'        => 'Kandidat Kecocokan Barang Hilang',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan saat sistem mencocokkan barang temuan yang mirip dengan laporan kehilangan.',
            'placeholders' => [
                'user_name'   => 'Nama pemilik laporan',
                'title'       => 'Judul notifikasi kecocokan barang',
                'message'     => 'Penjelasan kemungkinan kecocokan barang temuan',
                'action_url'  => 'URL perbandingan kecocokan barang',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Maya Anggraini',
                'title'      => 'Kandidat Kecocokan Barang Ditemukan',
                'message'    => 'Ditemukan barang temuan baru yang memiliki kemiripan dengan laporan kehilangan Anda.',
                'action_url' => '/reports/lost/55',
            ],
        ],
        'new_claim_submitted' => [
            'name'        => 'Pengajuan Klaim Baru (Untuk Petugas)',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan ke seluruh Petugas aktif saat ada Civitas yang mengajukan klaim baru.',
            'placeholders' => [
                'user_name'   => 'Nama Petugas penerima notifikasi',
                'title'       => 'Judul notifikasi pengajuan klaim',
                'message'     => 'Rincian barang klaim yang perlu diverifikasi',
                'action_url'  => 'URL dashboard peninjauan klaim petugas',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Petugas Staff',
                'title'      => 'Pengajuan Klaim Baru Memerlukan Peninjauan',
                'message'    => 'Klaim baru diajukan untuk item Tumbler Eiger (CLM-2026-0033).',
                'action_url' => '/petugas/claims',
            ],
        ],
        'new_found_report' => [
            'name'        => 'Laporan Temuan Baru Menunggu Penyerahan (Untuk Petugas)',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan ke Petugas saat ada laporan temuan baru yang menunggu barang fisik diserahkan.',
            'placeholders' => [
                'user_name'   => 'Nama Petugas penerima notifikasi',
                'title'       => 'Judul laporan temuan baru',
                'message'     => 'Informasi barang temuan yang dilaporkan civitas',
                'action_url'  => 'URL penerimaan barang temuan',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Petugas Staff',
                'title'      => 'Laporan Temuan Baru Menunggu Penyerahan Fisik',
                'message'    => 'Laporan temuan baru: Jaket Parasut Biru (FT-2026-0077) menunggu verifikasi fisik.',
                'action_url' => '/reports/found/77',
            ],
        ],
        'new_lost_report' => [
            'name'        => 'Laporan Kehilangan Baru (Opsional Email ke Petugas)',
            'category'    => 'report_claim',
            'description' => 'Dikirimkan ke Petugas jika pengaturan notif_email_new_lost_report diaktifkan.',
            'placeholders' => [
                'user_name'   => 'Nama Petugas penerima notifikasi',
                'title'       => 'Judul laporan kehilangan baru',
                'message'     => 'Ringkasan laporan kehilangan yang baru diterbitkan',
                'action_url'  => 'URL detail laporan kehilangan',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Petugas Staff',
                'title'      => 'Laporan Kehilangan Baru Diterbitkan',
                'message'    => 'Laporan kehilangan baru diterbitkan: Kunci Kos (LH-2026-0102).',
                'action_url' => '/reports/lost/102',
            ],
        ],
        'new_chat_message' => [
            'name'        => 'Pesan Chat Baru Terkait Klaim',
            'category'    => 'chat',
            'description' => 'Dikirimkan ke lawan bicara saat ada pesan chat baru dalam ruang diskusi klaim.',
            'placeholders' => [
                'user_name'   => 'Nama penerima pesan',
                'title'       => 'Judul notifikasi chat',
                'message'     => 'Cuplikan isi pesan aman (tanpa bukti privat)',
                'action_url'  => 'URL ruang percakapan klaim',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Budi Prasetyo',
                'title'      => 'Pesan Baru dari Petugas Lost & Found',
                'message'    => '"Mohon bawa kartu mahasiswa asli saat mengambil barang di posko."',
                'action_url' => '/claims/12/chat',
            ],
        ],
        'system_alert_failure' => [
            'name'        => 'Peringatan Sistem: Worker Gagal Beruntun (Untuk Admin)',
            'category'    => 'system',
            'description' => 'Peringatan otomatis ke Admin jika worker notifikasi mengalami kegagalan 5x beruntun (Circuit Breaker).',
            'placeholders' => [
                'user_name'   => 'Nama Admin penerima peringatan',
                'title'       => 'Judul peringatan sistem',
                'message'     => 'Detail kegagalan worker dan status circuit breaker',
                'action_url'  => 'URL pengaturan notifikasi dan antrean outbox',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Administrator',
                'title'      => 'Peringatan: Worker Gagal Beruntun (Circuit Breaker Aktif)',
                'message'    => 'Worker pengiriman email mengalami 5 kegagalan berturut-turut. Pengiriman ditangguhkan otomatis.',
                'action_url' => '/admin/notification-settings',
            ],
        ],
        'system_alert_quota' => [
            'name'        => 'Peringatan Sistem: Batas Kuota Harian Tercapai (Untuk Admin)',
            'category'    => 'system',
            'description' => 'Peringatan otomatis ke Admin jika kuota harian pengiriman email telah habis.',
            'placeholders' => [
                'user_name'   => 'Nama Admin penerima peringatan',
                'title'       => 'Judul peringatan kuota habis',
                'message'     => 'Penjelasan batas kuota tercapai dan penundaan email',
                'action_url'  => 'URL pengaturan kuota notifikasi',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Administrator',
                'title'      => 'Peringatan: Kuota Harian Pengiriman Email Telah Habis',
                'message'    => 'Batas kuota harian 100 email telah tercapai. Email berikutnya akan ditunda hingga besok.',
                'action_url' => '/admin/notification-settings',
            ],
        ],
        'test_email' => [
            'name'        => 'Uji Coba Pengiriman Email',
            'category'    => 'test',
            'description' => 'Dikirimkan saat Admin menekan tombol Kirim Email Uji di panel pengaturan.',
            'placeholders' => [
                'user_name'   => 'Nama Admin penerima uji coba',
                'title'       => 'Judul pesan uji coba',
                'message'     => 'Isi penjelasan tes konfigurasi SMTP',
                'action_url'  => 'URL portal Campus Find',
                'app_name'    => 'Nama aplikasi (Campus Find)',
                'app_url'     => 'URL portal Campus Find',
            ],
            'aliases' => [],
            'sample' => [
                'user_name'  => 'Administrator',
                'title'      => 'Uji Coba Konfigurasi Notifikasi Email',
                'message'    => 'Ini adalah email uji coba untuk memvalidasi konfigurasi server SMTP dan templat Campus Find.',
                'action_url' => '/admin/notification-settings',
            ],
        ],
    ];

    /**
     * Dapatkan daftar seluruh definisi templat
     */
    public static function getAllDefinitions(): array
    {
        return self::TEMPLATES;
    }

    /**
     * Dapatkan definisi templat tertentu
     */
    public static function getDefinition(string $templateKey): ?array
    {
        return self::TEMPLATES[$templateKey] ?? null;
    }

    /**
     * Dapatkan seluruh placeholder yang sah untuk suatu templat (termasuk alias resmi)
     *
     * @return array<string> Daftar nama placeholder yang valid (mis. ['user_name', 'otp_code', 'code_otp', ...])
     */
    public static function getAllowedPlaceholders(string $templateKey): array
    {
        $def = self::getDefinition($templateKey);
        if (!$def) {
            // Fallback untuk templat tidak terdaftar: placeholder standar
            return ['user_name', 'title', 'message', 'action_url', 'app_name', 'app_url'];
        }

        $canonical = array_keys($def['placeholders']);
        $aliases   = array_keys($def['aliases'] ?? []);

        return array_unique(array_merge($canonical, $aliases));
    }

    /**
     * Format string variables hint untuk tampilan form dan database
     */
    public static function getOfficialVariablesHint(string $templateKey): string
    {
        $def = self::getDefinition($templateKey);
        if (!$def) {
            return '{{user_name}}, {{title}}, {{message}}, {{action_url}}, {{app_name}}, {{app_url}}';
        }

        $items = [];
        foreach ($def['placeholders'] as $ph => $desc) {
            $items[] = '{{' . $ph . '}}';
        }

        return implode(', ', $items);
    }

    /**
     * Ekstrak seluruh token placeholder dari sebuah string (mencakup format {var} dan {{var}})
     *
     * @return array<string> Daftar nama variabel yang ditemukan (tanpa kurung kurawal)
     */
    public static function extractPlaceholders(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        // Tangkap pola {var} atau {{var}} atau { var } atau {{ var }}
        preg_match_all('/\{+([a-zA-Z0-9_]+)\}+/', $text, $matches);

        if (empty($matches[1])) {
            return [];
        }

        return array_values(array_unique(array_map('trim', $matches[1])));
    }

    /**
     * Validasi konten templat sebelum disimpan ke database
     * Memastikan hanya placeholder resmi yang sah yang digunakan.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateTemplatePlaceholders(
        string $templateKey,
        string $subject,
        string $bodyHtml,
        string $bodyText = ''
    ): array {
        $allowed = self::getAllowedPlaceholders($templateKey);
        $usedInSubject  = self::extractPlaceholders($subject);
        $usedInBodyHtml = self::extractPlaceholders($bodyHtml);
        $usedInBodyText = self::extractPlaceholders($bodyText);

        $allUsed = array_unique(array_merge($usedInSubject, $usedInBodyHtml, $usedInBodyText));
        $errors  = [];

        foreach ($allUsed as $placeholder) {
            if (!in_array($placeholder, $allowed, true)) {
                $allowedListStr = implode(', ', array_map(fn($p) => "{{{$p}}}", $allowed));
                $errors[] = "Placeholder '{{$placeholder}}' tidak valid untuk templat '{$templateKey}'. Placeholder yang sah: {$allowedListStr}.";
            }
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Siapkan kamus data variabel lengkap untuk proses rendering templat
     *
     * Menggabungkan payload spesifik dengan data sistem dan alias placeholder
     */
    public static function buildVariableMap(
        string $templateKey,
        array $payload,
        array $context = []
    ): array {
        $appUrl  = rtrim((string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://campus-find.test'), '/');
        $appName = (string)($_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Campus Find');

        $rawUrl = (string)($payload['url'] ?? $payload['action_url'] ?? '/dashboard');
        $actionUrl = str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://')
            ? $rawUrl
            : $appUrl . '/' . ltrim($rawUrl, '/');

        // Resolusi Nama Pengguna
        $userName = trim((string)($payload['user_name'] ?? ''));
        if ($userName === '' && !empty($context['user_id'])) {
            try {
                $pdo = Database::getInstance()->getPdo();
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$context['user_id']]);
                $userName = (string)$stmt->fetchColumn() ?: '';
            } catch (Throwable) {
                $userName = '';
            }
        }
        if ($userName === '') {
            $userName = 'Civitas Akademika';
        }

        $expiresIn = trim((string)($payload['expires_in'] ?? $payload['expires_minutes'] ?? $payload['expiry_minutes'] ?? '5'));
        if ($expiresIn === '') {
            $expiresIn = '5';
        }

        // Kamus Data Mentah Kanonikal (Variabel Sistem & Global)
        $rawMap = [
            'user_name'        => $userName,
            'action_url'       => $actionUrl,
            'app_name'         => $appName,
            'app_url'          => $appUrl,
            'expires_in'       => $expiresIn,
            'expires_minutes'  => $expiresIn,
        ];

        // Resolusi Kode OTP & Aliasnya (HANYA sertakan jika tersedia di payload)
        $otpCode = trim((string)($payload['otp_code'] ?? $payload['code_otp'] ?? $payload['code'] ?? $payload['otp'] ?? ''));
        if ($otpCode !== '') {
            $rawMap['otp_code'] = $otpCode;
            $rawMap['code_otp'] = $otpCode;
            $rawMap['code']     = $otpCode;
            $rawMap['otp']      = $otpCode;
        }

        // Resolusi Notifikasi Keamanan
        $newEmailMasked = trim((string)($payload['new_email_masked'] ?? $payload['email_masked'] ?? ''));
        if ($newEmailMasked !== '') {
            $rawMap['new_email_masked'] = $newEmailMasked;
            $rawMap['email_masked']     = $newEmailMasked;
        }

        $changedAt = trim((string)($payload['changed_at'] ?? ''));
        if ($changedAt !== '') {
            $rawMap['changed_at'] = $changedAt;
        }

        // Judul & Pesan Notifikasi Umum
        if (isset($payload['title']) && trim((string)$payload['title']) !== '') {
            $rawMap['title'] = (string)$payload['title'];
        }
        if (isset($payload['message']) && trim((string)$payload['message']) !== '') {
            $rawMap['message'] = (string)$payload['message'];
        }

        // Tambahkan data spesifik lain yang dikirimkan di payload jika non-empty
        foreach ($payload as $k => $v) {
            if (is_scalar($v) && trim((string)$v) !== '' && !isset($rawMap[$k])) {
                $rawMap[$k] = (string)$v;
            }
        }

        return $rawMap;
    }

    /**
     * Render subject, body HTML, dan body text dengan kamus variabel
     *
     * @return array{
     *   subject: string,
     *   body_html: string,
     *   body_text: string,
     *   has_unresolved: bool,
     *   unresolved_placeholders: array<string>
     * }
     */
    public static function render(
        string $templateKey,
        array $template,
        array $payload,
        array $context = []
    ): array {
        $variableMap = self::buildVariableMap($templateKey, $payload, $context);

        $subjectTpl  = (string)($template['subject'] ?? '');
        $bodyHtmlTpl = (string)($template['body_html'] ?? '');
        $bodyTextTpl = (string)($template['body_text'] ?? strip_tags($bodyHtmlTpl));

        // Bangun daftar penggantian untuk HTML dan Teks
        $searchKeys = [];
        $htmlReplacements = [];
        $textReplacements = [];

        foreach ($variableMap as $key => $val) {
            $valStr = (string)$val;
            $htmlVal = htmlspecialchars($valStr, ENT_QUOTES, 'UTF-8');

            // Format {{key}}
            $searchKeys[]       = '{{' . $key . '}}';
            $htmlReplacements[] = $htmlVal;
            $textReplacements[] = $valStr;

            // Format {key}
            $searchKeys[]       = '{' . $key . '}';
            $htmlReplacements[] = $htmlVal;
            $textReplacements[] = $valStr;

            // Format {{ key }}
            $searchKeys[]       = '{{ ' . $key . ' }}';
            $htmlReplacements[] = $htmlVal;
            $textReplacements[] = $valStr;

            // Format { key }
            $searchKeys[]       = '{ ' . $key . ' }';
            $htmlReplacements[] = $htmlVal;
            $textReplacements[] = $valStr;
        }

        $renderedSubject = str_replace($searchKeys, $textReplacements, $subjectTpl);
        $renderedHtml    = str_replace($searchKeys, $htmlReplacements, $bodyHtmlTpl);
        $renderedText    = str_replace($searchKeys, $textReplacements, $bodyTextTpl);

        // Pengaman: Periksa apakah masih ada pola {...} atau {{...}} yang tidak terganti
        $unresolvedSubject  = self::extractPlaceholders($renderedSubject);
        $unresolvedHtml     = self::extractPlaceholders($renderedHtml);
        $unresolvedText     = self::extractPlaceholders($renderedText);

        $unresolved = array_values(array_unique(array_merge($unresolvedSubject, $unresolvedHtml, $unresolvedText)));

        // Filter token yang menyerupai placeholder {var}
        $unresolvedFiltered = [];
        foreach ($unresolved as $token) {
            if (preg_match('/^[a-zA-Z0-9_]+$/', $token)) {
                $unresolvedFiltered[] = $token;
            }
        }

        return [
            'subject'                 => $renderedSubject,
            'body_html'               => $renderedHtml,
            'body_text'               => $renderedText,
            'has_unresolved'          => !empty($unresolvedFiltered),
            'unresolved_placeholders' => $unresolvedFiltered,
        ];
    }

    /**
     * Samarkan kode OTP dalam pesan atau log untuk mencegah secret leakage
     */
    public static function maskOtpInText(string $text): string
    {
        // Ganti angka 6 digit berturut-turut menjadi ******
        return preg_replace('/\b\d{6}\b/', '******', $text) ?? $text;
    }
}
