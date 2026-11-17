<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\SettingService;
use Core\Database;
use PDO;
use Throwable;

/**
 * MailGateway — Satu Pintu Pengiriman Email Campus Find (Prompt 33E)
 *
 * Menerapkan kebijakan keamanan terpusat untuk SEMUA pengiriman email (OTP, notifikasi, uji coba, peringatan):
 * 1. Saluran Aktif: Memeriksa notif_email_status dan notif_external_channel != 'none'
 * 2. Daftar Blokir Domain Reservasi & Contoh (DI SEMUA LINGKUNGAN):
 *    - RFC 2606 & RFC 6761: .test, .example, .invalid, .localhost
 *    - Domain contoh: example.com, example.org, example.net
 * 3. NOTIFY_ALLOWLIST (Di luar Production):
 *    - HANYA alamat persis (Exact Match), TANPA wildcard (*)
 * 4. Batas Kuota Harian:
 *    - Memeriksa batas notif_daily_limit harian
 * 5. Isolasi Pengujian:
 *    - Lingkungan test (APP_ENV=testing / skrip test) SELALU menggunakan FakeNotificationDriver
 *    - Spy counter melacak percobaan pengiriman SMTP nyata (harus 0 pada test)
 */
class MailGateway
{
    private static ?NotificationChannelInterface $driverOverride = null;
    public static int $realSmtpSendAttempts = 0;

    /**
     * Daftarkan driver override (misal FakeNotificationDriver saat testing)
     */
    public static function setDriverOverride(?NotificationChannelInterface $driver): void
    {
        self::$driverOverride = $driver;
    }

    /**
     * Reset driver override
     */
    public static function resetDriverOverride(): void
    {
        self::$driverOverride = null;
    }

    /**
     * Dapatkan driver aktif yang aman sesuai lingkungan
     */
    public static function getDriver(): NotificationChannelInterface
    {
        if (self::$driverOverride !== null) {
            return self::$driverOverride;
        }

        // Isolasi Pengujian: Jika berada di lingkungan test, SELALU gunakan FakeNotificationDriver
        if (self::isTestEnvironment()) {
            return new FakeNotificationDriver();
        }

        return new EmailSmtpDriver();
    }

    /**
     * Deteksi apakah proses saat ini berjalan dalam konteks testing
     */
    public static function isTestEnvironment(): bool
    {
        $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''));
        if (in_array($appEnv, ['testing', 'test'], true)) {
            return true;
        }

        if (defined('CAMPUS_FIND_TESTING') && constant('CAMPUS_FIND_TESTING')) {
            return true;
        }

        // Deteksi eksekusi skrip test di folder database/ atau tests/
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? ''));
        if (!empty($script) && preg_match('/(database[\\\\\/]test_|tests[\\\\\/])/i', $script)) {
            return true;
        }

        return false;
    }

    /**
     * Periksa apakah email menggunakan domain/TLD reservasi atau contoh (RFC 2606 / 6761)
     */
    public static function isReservedOrExampleDomain(string $email): bool
    {
        $email = strtolower(trim($email));
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return true; // Format tidak valid
        }

        $domain = substr($email, $atPos + 1);
        if (empty($domain)) {
            return true;
        }

        // Domain contoh terdaftar (RFC 2606) beserta seluruh subdomainnya
        $exampleDomains = ['example.com', 'example.org', 'example.net'];
        foreach ($exampleDomains as $ex) {
            if ($domain === $ex || str_ends_with($domain, '.' . $ex)) {
                return true;
            }
        }

        // TLD reservasi (.test, .example, .invalid, .localhost)
        $subparts = explode('.', $domain);
        $tld = end($subparts);
        $reservedTlds = ['test', 'example', 'invalid', 'localhost'];

        if (in_array($tld, $reservedTlds, true) || in_array($domain, $reservedTlds, true)) {
            return true;
        }

        return false;
    }

    /**
     * Dapatkan daftar email allowlist gabungan dari SettingService dan .env (Exact Match, tanpa wildcard)
     *
     * @return array<string>
     */
    public static function getAllowlistEmails(): array
    {
        $dbAllowlist = (string) SettingService::get('notif_allowlist', '');
        $envAllowlist = (string) ($_ENV['NOTIFY_ALLOWLIST'] ?? getenv('NOTIFY_ALLOWLIST') ?: '');

        $combined = $dbAllowlist . ',' . $envAllowlist;
        $items = array_filter(array_map('trim', preg_split('/[,\s;]+/', strtolower($combined))));
        
        $clean = [];
        foreach ($items as $pattern) {
            if (empty($pattern) || str_contains($pattern, '*')) {
                continue; // Tolak dan abaikan wildcard (*)
            }
            if (filter_var($pattern, FILTER_VALIDATE_EMAIL)) {
                $clean[$pattern] = true;
            }
        }

        return array_keys($clean);
    }

    /**
     * Periksa kecocokan NOTIFY_ALLOWLIST di lingkungan non-produksi (HANYA ALAMAT PERSIS TANPA WILDCARD)
     */
    public static function isAllowedRecipient(string $email): bool
    {
        $target = strtolower(trim($email));
        $allowlist = self::getAllowlistEmails();
        return in_array($target, $allowlist, true);
    }

    /**
     * Periksa apakah pengguna telah memverifikasi email lewat OTP sungguhan (Bukan penandaan manual Admin)
     */
    public static function isEmailVerifiedByOtp(int $userId, ?string $email = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $pdo = Database::getInstance()->getPdo();
            $stmt = $pdo->prepare("SELECT email_verified_at, email_verified_by_otp FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$u || empty($u['email_verified_at'])) {
                return false;
            }

            // Jika kolom email_verified_by_otp sudah bernilai 1
            if (!empty($u['email_verified_by_otp'])) {
                return true;
            }

            // Fallback: periksa keberadaan record penggunaan OTP di tabel otp_verifications
            $stmtOtp = $pdo->prepare("
                SELECT COUNT(*) FROM otp_verifications 
                WHERE user_id = ? AND used_at IS NOT NULL
            ");
            $stmtOtp->execute([$userId]);
            return ((int) $stmtOtp->fetchColumn()) > 0;
        } catch (Throwable $e) {
            error_log('[MailGateway::isEmailVerifiedByOtp] Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Periksa apakah template key adalah email transaksional OTP (autentikasi/keamanan)
     */
    public static function isTransactionalOtpTemplate(?string $templateKey): bool
    {
        if (empty($templateKey)) {
            return false;
        }
        $key = strtolower(trim($templateKey));
        return str_starts_with($key, 'otp_')
            || str_ends_with($key, '_otp')
            || in_array($key, [
                'account_activation_otp',
                'password_reset_otp',
                'email_change_otp',
                'email_verification_otp',
            ], true);
    }

    /**
     * Evaluasi kelayakan penerima notifikasi sesuai Prompt 34C & Task 02:
     * - Domain reservasi (.test, .example, .invalid, .localhost) diblokir di semua lingkungan.
     * - Transaksional OTP di production: diizinkan tanpa syarat prior OTP/opt-in (karena ini proses verifikasi/keamanan).
     * - Transaksional OTP di luar production: diizinkan jika ada di NOTIFY_ALLOWLIST (atau allowlist kosong).
     * - Notifikasi non-transaksional di production: wajib terverifikasi OTP + opt-in.
     * - Notifikasi non-transaksional di luar production: diizinkan jika (a) ada di NOTIFY_ALLOWLIST, ATAU (b) terverifikasi OTP + opt-in.
     *
     * @return array{
     *   is_ready: bool,
     *   readiness_reason: ?string,
     *   unready_reasons: array<string>,
     *   in_allowlist: bool,
     *   is_otp_verified: bool,
     *   is_manual_verified: bool,
     *   has_opt_in: bool,
     *   is_reserved: bool
     * }
     */
    public static function evaluateRecipientEligibility(string $email, ?int $userId = null, ?string $templateKey = null): array
    {
        $target = strtolower(trim($email));
        $isReserved = self::isReservedOrExampleDomain($target);
        $inAllowlist = self::isAllowedRecipient($target);

        // Ambil data user dari DB
        $userData = null;
        try {
            $pdo = Database::getInstance()->getPdo();
            if ($userId !== null && $userId > 0) {
                $stmt = $pdo->prepare("SELECT id, name, email, email_verified_at, email_verified_by_otp, email_notifications_enabled, is_active FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$userData && !empty($target)) {
                $stmt = $pdo->prepare("SELECT id, name, email, email_verified_at, email_verified_by_otp, email_notifications_enabled, is_active FROM users WHERE LOWER(email) = ? LIMIT 1");
                $stmt->execute([$target]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }
        } catch (Throwable) {
            $userData = null;
        }

        $uId = $userData ? (int)$userData['id'] : ($userId ?? 0);
        $isVerifiedAt = !empty($userData['email_verified_at']);
        $isOtpVerified = ($uId > 0) ? self::isEmailVerifiedByOtp($uId, $target) : false;
        $isManualVerified = $isVerifiedAt && !$isOtpVerified;
        $hasOptIn = !empty($userData) && (int)($userData['email_notifications_enabled'] ?? 0) === 1;

        $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));
        $unreadyReasons = [];
        $readinessReason = null;
        $isReady = false;

        // 1. Domain reservasi diblokir mutlak di semua lingkungan
        if ($isReserved) {
            $unreadyReasons[] = 'Domain reservasi/contoh (.test, .example, .invalid, .localhost) diblokir di semua lingkungan';
        }

        $isTransactionalOtp = self::isTransactionalOtpTemplate($templateKey);

        if ($appEnv === 'production') {
            if ($isTransactionalOtp) {
                // Email transaksional autentikasi/keamanan (OTP reset password, aktivasi akun) di production
                // tidak memerlukan prior OTP verification (karena ini proses verifikasinya) dan tidak memerlukan marketing opt-in
                if (!$isReserved) {
                    $isReady = true;
                    $readinessReason = 'transaksional OTP';
                }
            } else {
                // Production non-transaksional: Allowlist tidak berlaku, wajib terverifikasi OTP + opt-in
                if (!$isOtpVerified) {
                    if ($isManualVerified) {
                        $unreadyReasons[] = 'Email hanya ditandai manual Admin, belum diverifikasi lewat OTP';
                    } else {
                        $unreadyReasons[] = 'Email belum terverifikasi lewat OTP';
                    }
                }
                if (!$hasOptIn) {
                    $unreadyReasons[] = 'Pengguna belum opt-in notifikasi email';
                }

                if (empty($unreadyReasons) && !$isReserved) {
                    $isReady = true;
                    $readinessReason = 'terverifikasi OTP + opt-in';
                }
            }
        } else {
            // Non-Production:
            // Diizinkan jika:
            // (a) ada di NOTIFY_ALLOWLIST (atau allowlist kosong), ATAU
            // (b) terverifikasi OTP (bukan manual Admin) DAN opt-in
            $allowlistEmails = self::getAllowlistEmails();
            $qualifiesByAllowlist = empty($allowlistEmails) || $inAllowlist;
            $qualifiesByOtpOptIn  = ($isOtpVerified && $hasOptIn);

            if (!$isReserved && ($qualifiesByAllowlist || $qualifiesByOtpOptIn)) {
                $isReady = true;
                if ($qualifiesByAllowlist && $qualifiesByOtpOptIn) {
                    $readinessReason = 'allowlist';
                } elseif ($qualifiesByAllowlist) {
                    $readinessReason = 'allowlist';
                } else {
                    $readinessReason = 'terverifikasi OTP + opt-in';
                }
            } else {
                if (!$isReserved) {
                    if ($isManualVerified && !$inAllowlist) {
                        $unreadyReasons[] = 'Penandaan manual Admin tidak diizinkan di luar allowlist';
                    }
                    if (!$isOtpVerified && !$isManualVerified && !$inAllowlist) {
                        $unreadyReasons[] = 'Belum terverifikasi OTP dan di luar NOTIFY_ALLOWLIST';
                    }
                    if ($isOtpVerified && !$hasOptIn && !$inAllowlist) {
                        $unreadyReasons[] = 'Pengguna belum opt-in notifikasi email dan di luar NOTIFY_ALLOWLIST';
                    }
                }
            }
        }

        return [
            'is_ready'           => $isReady,
            'readiness_reason'   => $readinessReason,
            'unready_reasons'    => $unreadyReasons,
            'in_allowlist'       => $inAllowlist,
            'is_otp_verified'    => $isOtpVerified,
            'is_manual_verified' => $isManualVerified,
            'has_opt_in'         => $hasOptIn,
            'is_reserved'        => $isReserved,
            'user'               => $userData,
        ];
    }

    /**
     * Validasi lengkap penerima email sebelum pengiriman (Prompt 34C)
     *
     * @return array{allowed: bool, blocked: bool, reason: ?string, error: ?string}
     */
    public static function validateRecipient(string $email, ?int $userId = null, ?string $templateKey = null): array
    {
        $target = trim($email);

        // 1. Format Validasi
        if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
            return [
                'allowed' => false,
                'blocked' => false,
                'reason'  => 'dilewati: format email tidak valid',
                'error'   => "Format alamat email penerima tidak valid: '{$target}'",
            ];
        }

        // 2. Daftar Blokir Domain Reservasi & Contoh (DI SEMUA LINGKUNGAN)
        if (self::isReservedOrExampleDomain($target)) {
            return [
                'allowed' => false,
                'blocked' => true,
                'reason'  => 'dilewati: domain reservasi/contoh (.test, .example, .invalid, .localhost) diblokir di semua lingkungan',
                'error'   => "Alamat email '{$target}' menggunakan domain/TLD reservasi (.test, .example, .invalid, .localhost) yang diblokir dari pengiriman SMTP nyata.",
            ];
        }

        // 3. Saluran Aktif
        $emailEnabled = (bool) SettingService::get('notif_email_status', true);
        $externalChannel = (string) SettingService::get('notif_external_channel', 'email');
        if (!$emailEnabled || $externalChannel === 'none') {
            return [
                'allowed' => false,
                'blocked' => true,
                'reason'  => 'dilewati: saluran email nonaktif',
                'error'   => 'Saluran pengiriman email sedang dinonaktifkan di Pengaturan Sistem.',
            ];
        }

        // 4. Evaluasi Kelayakan Penerima (Prompt 34C & Task 02)
        $eligibility = self::evaluateRecipientEligibility($target, $userId, $templateKey);
        if (!$eligibility['is_ready']) {
            $reasonText = !empty($eligibility['unready_reasons'])
                ? implode('; ', $eligibility['unready_reasons'])
                : 'di luar ketentuan penerima';

            return [
                'allowed' => false,
                'blocked' => true,
                'reason'  => "dilewati: {$reasonText}",
                'error'   => "Alamat email '{$target}' belum memenuhi syarat penerimaan notifikasi: {$reasonText}",
            ];
        }

        // 5. Kuota Harian
        try {
            $pdo = Database::getInstance()->getPdo();
            $dailyLimit = (int) SettingService::get('notif_daily_limit', 100);
            $stmtDailyCount = $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'sent' AND DATE(sent_at) = CURDATE()");
            $sentToday = (int) $stmtDailyCount->fetchColumn();
            if ($sentToday >= $dailyLimit) {
                return [
                    'allowed' => false,
                    'blocked' => true,
                    'reason'  => 'dilewati: kuota habis',
                    'error'   => 'Batas kuota pengiriman email harian telah tercapai.',
                ];
            }
        } catch (Throwable $e) {
            error_log('[MailGateway::validateRecipient] Error checking daily limit: ' . $e->getMessage());
        }

        return [
            'allowed' => true,
            'blocked' => false,
            'reason'  => null,
            'error'   => null,
        ];
    }

    /**
     * SATU PINTU PENGIRIMAN: Seluruh email (OTP, notifikasi outbox, uji coba) dikirim lewat method ini
     *
     * @param array $outboxItem Format: ['recipient' => string, 'template_key' => string, 'payload' => array, ...]
     * @return array{success: bool, blocked?: bool, error?: string, message_id?: string, driver?: string}
     */
    public static function send(array $outboxItem): array
    {
        $recipient = trim((string)($outboxItem['recipient'] ?? ''));
        $userId = !empty($outboxItem['user_id']) ? (int)$outboxItem['user_id'] : null;
        $templateKey = (string)($outboxItem['template_key'] ?? '');

        // Evaluasi Terpusat di MailGateway
        $val = self::validateRecipient($recipient, $userId, $templateKey);
        if (!$val['allowed']) {
            return [
                'success' => false,
                'blocked' => $val['blocked'],
                'error'   => $val['error'] ?? $val['reason'],
                'reason'  => $val['reason'],
            ];
        }

        $driver = self::getDriver();

        // Spy pelacak percobaan SMTP nyata
        if ($driver instanceof EmailSmtpDriver) {
            self::$realSmtpSendAttempts++;

            // Perlindungan Mutlak: Jika terdeteksi di lingkungan testing, CEGAH koneksi SMTP nyata!
            if (self::isTestEnvironment()) {
                return [
                    'success' => false,
                    'blocked' => true,
                    'error'   => 'Percobaan pengiriman SMTP nyata dicegah dalam lingkungan pengujian.',
                    'reason'  => 'dilewati: dicegah dalam lingkungan test',
                ];
            }
        }

        $result = $driver->send($outboxItem);
        $result['driver'] = $driver->getName();

        return $result;
    }
}
