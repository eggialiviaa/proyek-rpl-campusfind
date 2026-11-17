<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use App\Services\Notifications\EmailSmtpDriver;
use PDO;
use Throwable;

/**
 * OtpService — Pengelolaan Kode OTP Email untuk Aktivasi Akun dan Reset Kata Sandi (Prompt 20B)
 *
 * Standar Keamanan:
 * 1. Kode 6 digit acak aman via random_int().
 * 2. Disimpan sebagai hash HMAC SHA-256 (tidak pernah plain text di basis data).
 * 3. Masa berlaku tepat 5 menit (300 detik).
 * 4. Sekali pakai (used_at ditandai saat sukses).
 * 5. Maksimal 5 percobaan salah per kode, lalu otomatis terkunci.
 * 6. Jeda kirim ulang 60 detik (cooldown per pengguna/email).
 * 7. Rate limiting per IP.
 * 8. Perbandingan waktu-konstan via hash_equals() mencegah timing attacks.
 * 9. Kode OTP TIDAK PERNAH masuk log atau audit log (Zero Secret Leakage).
 */
class OtpService
{
    public const COOLDOWN_SECONDS = 60;
    public const EXPIRATION_SECONDS = 300; // 5 menit
    public const MAX_ATTEMPTS = 5;

    /**
     * Generate dan kirim kode OTP email untuk akun pengguna
     *
     * @return array{success: bool, message: string, cooldown?: bool, remaining_seconds?: int, email_masked?: string}
     */
    public static function generate(int $userId, string $purpose, ?string $ip = null, ?string $ua = null, ?string $targetEmail = null): array
    {
        // Deteksi jika parameter ke-3 dioper alamat email langsung
        if (!empty($ip) && str_contains($ip, '@')) {
            $targetEmail = $ip;
            $ip = null;
        }

        $pdo = Database::getInstance()->getPdo();
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = $ua ?? substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

        // 1. Ambil data pengguna
        $stmtUser = $pdo->prepare("SELECT id, name, email, identity_number, is_active FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'Pengguna tidak ditemukan.',
            ];
        }

        // 2. Periksa Cooldown Kirim Ulang (60 detik)
        $stmtLatest = $pdo->prepare("
            SELECT id, created_at 
            FROM otp_verifications 
            WHERE user_id = ? AND purpose = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtLatest->execute([$userId, $purpose]);
        $latest = $stmtLatest->fetch(PDO::FETCH_ASSOC);

        if ($latest && !empty($latest['created_at'])) {
            $elapsed = time() - strtotime($latest['created_at']);
            if ($elapsed >= 0 && $elapsed < self::COOLDOWN_SECONDS) {
                $remaining = self::COOLDOWN_SECONDS - $elapsed;
                return [
                    'success'           => false,
                    'cooldown'          => true,
                    'remaining_seconds' => $remaining,
                    'message'           => "Harap tunggu {$remaining} detik sebelum meminta pengiriman kode OTP baru.",
                ];
            }
        }

        // 3. Rate limiting per IP (maksimal 15 request OTP per 15 menit dari IP yang sama)
        $stmtIpCount = $pdo->prepare("
            SELECT COUNT(*) FROM otp_verifications 
            WHERE ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)
        ");
        $stmtIpCount->execute([$ip]);
        if ((int)$stmtIpCount->fetchColumn() >= 15) {
            return [
                'success' => false,
                'message' => 'Terlalu banyak permintaan kode verifikasi dari perangkat Anda. Harap coba lagi dalam 15 menit.',
            ];
        }

        // Tentukan alamat email tujuan (bisa ke email baru untuk perubahan email)
        $destinationEmail = !empty($targetEmail) ? trim($targetEmail) : (string)($user['email'] ?? '');

        $templateKey = match ($purpose) {
            'activation'         => 'account_activation_otp',
            'password_reset'     => 'password_reset_otp',
            'email_change'       => 'email_change_otp',
            'email_verification' => 'email_verification_otp',
            default              => 'account_activation_otp',
        };

        // 4. Evaluasi Terpusat lewat MailGateway SEBELUM membuat record OTP
        $val = \App\Services\Notifications\MailGateway::validateRecipient($destinationEmail, $userId, 'otp_' . $purpose);
        if (!$val['allowed']) {
            \App\Services\Notifications\NotificationDecisionService::record(
                $templateKey,
                $userId,
                $destinationEmail,
                'skipped',
                $val['reason'] ?? 'dilewati: validasi email gagal',
                'email'
            );
            return [
                'success' => false,
                'message' => $val['error'] ?? 'Pengiriman kode OTP diblokir oleh kebijakan keamanan.',
            ];
        }

        // 5. Generate 6 digit acak aman
        $plainOtp = sprintf('%06d', random_int(0, 999999));
        $salt     = bin2hex(random_bytes(16));
        $otpHash  = hash_hmac('sha256', $plainOtp, $salt);
        $now = time();
        $createdAt = date('Y-m-d H:i:s', $now);
        $expiresAt = date('Y-m-d H:i:s', $now + self::EXPIRATION_SECONDS);

        // 6. Simpan hash ke database (Zero Secret Leakage: tidak pernah menyimpan plain code)
        $stmtInsert = $pdo->prepare("
            INSERT INTO otp_verifications (
                user_id, purpose, target_email, otp_hash, salt, attempts, max_attempts, is_locked, 
                expires_at, used_at, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, 0, ?, 0, ?, NULL, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $userId,
            $purpose,
            !empty($targetEmail) ? trim($targetEmail) : null,
            $otpHash,
            $salt,
            self::MAX_ATTEMPTS,
            $expiresAt,
            $ip,
            $ua,
            $createdAt,
        ]);
        $insertedOtpId = (int) $pdo->lastInsertId();

        $outboxItem = [
            'id'           => 0,
            'user_id'      => $userId,
            'recipient'    => $destinationEmail,
            'template_key' => $templateKey,
            'payload'      => [
                'user_name'       => $user['name'] ?? 'Civitas Akademika',
                'otp_code'        => $plainOtp,
                'code_otp'        => $plainOtp,
                'code'            => $plainOtp,
                'otp'             => $plainOtp,
                'expires_in'      => '5 menit',
                'expires_minutes' => '5 menit',
                'app_name'        => 'Campus Find',
            ],
        ];

        // SATU PINTU PENGIRIMAN LEWAT MAILGATEWAY
        $sendResult = \App\Services\Notifications\MailGateway::send($outboxItem);

        if (empty($sendResult['success'])) {
            // Bersihkan record OTP yang baru saja dibuat agar pengguna tidak terjebak cooldown saat pengiriman gagal
            if (!empty($insertedOtpId)) {
                try {
                    $pdo->prepare("DELETE FROM otp_verifications WHERE id = ?")->execute([$insertedOtpId]);
                } catch (\Throwable $e) {
                    error_log('[OtpService::generate] Failed to rollback OTP record on delivery failure: ' . $e->getMessage());
                }
            }

            $decisionStatus = !empty($sendResult['blocked']) ? 'skipped' : 'failed';
            \App\Services\Notifications\NotificationDecisionService::record(
                $templateKey,
                $userId,
                $destinationEmail,
                $decisionStatus,
                $sendResult['reason'] ?? $sendResult['error'] ?? 'Pengiriman kode OTP gagal',
                'email'
            );

            return [
                'success'     => false,
                'blocked'     => !empty($sendResult['blocked']),
                'message'     => $sendResult['error'] ?? 'Pengiriman kode verifikasi gagal. Silakan coba beberapa saat lagi.',
                'send_result' => $sendResult,
            ];
        }

        // Catat jejak keputusan OTP diantrekan
        \App\Services\Notifications\NotificationDecisionService::record(
            $templateKey,
            $userId,
            $destinationEmail,
            'queued',
            'diantre: OTP verifikasi email dikirimkan',
            'email'
        );

        // 7. Audit log kejadian (TANPA membocorkan kode OTP)
        AuditLogService::log('OTP_REQUESTED', $userId, 'users', $userId, [
            'purpose' => $purpose,
            'email'   => self::maskEmail($destinationEmail),
        ]);

        $maskedEmail = self::maskEmail($destinationEmail);

        return [
            'success'      => true,
            'message'      => "Kode verifikasi 6 digit telah dikirimkan ke {$maskedEmail}.",
            'email_masked' => $maskedEmail,
            'expires_at'   => $expiresAt,
            'send_result'  => $sendResult,
            '_last_otp_id' => $insertedOtpId,
        ];
    }

    /**
     * Verifikasi kode OTP yang dimasukkan pengguna
     *
     * @return array{success: bool, error?: string, remaining?: int, locked?: bool, expired?: bool, used?: bool}
     */
    public static function verify(int $userId, string $purpose, string $inputCode): array
    {
        $pdo = Database::getInstance()->getPdo();
        $cleanCode = trim($inputCode);

        // 1. Ambil OTP terbaru untuk user dan purpose yang bersangkutan
        $stmt = $pdo->prepare("
            SELECT * FROM otp_verifications 
            WHERE user_id = ? AND purpose = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $purpose]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return [
                'success' => false,
                'error'   => 'Kode verifikasi tidak ditemukan. Silakan minta kode baru.',
            ];
        }

        // 2. Periksa apakah sudah pernah dipakai
        if (!empty($record['used_at'])) {
            return [
                'success' => false,
                'used'    => true,
                'error'   => 'Kode verifikasi ini sudah pernah digunakan. Silakan minta kode baru.',
            ];
        }

        // 3. Periksa apakah terkunci (brute-force lock)
        if (!empty($record['is_locked']) || (int)$record['attempts'] >= self::MAX_ATTEMPTS) {
            return [
                'success' => false,
                'locked'  => true,
                'error'   => 'Kode verifikasi telah terkunci karena telah mencapai batas maksimal 5 kali percobaan salah. Silakan minta kode baru.',
            ];
        }

        // 4. Periksa kedaluwarsa (5 menit)
        if (strtotime($record['expires_at']) < time()) {
            return [
                'success' => false,
                'expired' => true,
                'error'   => 'Kode verifikasi telah kedaluwarsa (berlaku 5 menit). Silakan minta kode baru.',
            ];
        }

        // 5. Perbandingan waktu-konstan via hash_equals
        $computedHash = hash_hmac('sha256', $cleanCode, $record['salt']);
        $isMatch = hash_equals($record['otp_hash'], $computedHash);

        if (!$isMatch) {
            $newAttempts = (int)$record['attempts'] + 1;
            $isLocked = ($newAttempts >= self::MAX_ATTEMPTS) ? 1 : 0;

            $stmtUpdate = $pdo->prepare("UPDATE otp_verifications SET attempts = ?, is_locked = ? WHERE id = ?");
            $stmtUpdate->execute([$newAttempts, $isLocked, $record['id']]);

            // Audit log kegagalan (tanpa kode!)
            AuditLogService::log('OTP_FAILED', $userId, 'users', $userId, [
                'purpose'  => $purpose,
                'attempts' => $newAttempts,
            ]);

            if ($isLocked) {
                AuditLogService::log('OTP_LOCKED', $userId, 'users', $userId, [
                    'purpose' => $purpose,
                ]);
            }

            $remaining = max(0, self::MAX_ATTEMPTS - $newAttempts);

            return [
                'success'   => false,
                'locked'    => (bool) $isLocked,
                'remaining' => $remaining,
                'error'     => $isLocked 
                    ? 'Batas maksimal 5 kali percobaan salah tercapai. Kode verifikasi telah dikunci demi keamanan.'
                    : "Kode verifikasi salah. Sisa kesempatan: {$remaining} kali.",
            ];
        }

        // 6. Kode Cocok: Tandai OTP sebagai digunakan
        $stmtUsed = $pdo->prepare("UPDATE otp_verifications SET used_at = NOW() WHERE id = ?");
        $stmtUsed->execute([$record['id']]);

        // Audit log keberhasilan verifikasi
        AuditLogService::log('OTP_VERIFIED', $userId, 'users', $userId, [
            'purpose' => $purpose,
        ]);

        return [
            'success'      => true,
            'target_email' => $record['target_email'] ?? null,
            'record'       => $record,
        ];
    }

    /**
     * Validasi kekuatan kata sandi pengguna
     * Aturan: Minimal 8 karakter, huruf besar, huruf kecil, dan angka atau simbol
     *
     * @return array{valid: bool, message: string, errors: array<string>}
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Kata sandi minimal harus 8 karakter.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Kata sandi harus memuat setidaknya 1 huruf besar (A-Z).';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Kata sandi harus memuat setidaknya 1 huruf kecil (a-z).';
        }
        if (!preg_match('/[0-9\W_]/', $password)) {
            $errors[] = 'Kata sandi harus memuat setidaknya 1 angka (0-9) atau simbol.';
        }

        if (!empty($errors)) {
            return [
                'valid'   => false,
                'message' => implode(' ', $errors),
                'errors'  => $errors,
            ];
        }

        return [
            'valid'   => true,
            'message' => 'Kata sandi memenuhi standar kekuatan keamanan.',
            'errors'  => [],
        ];
    }

    /**
     * Buat kata sandi sementara acak yang aman (Prompt 34B: minimal 12 karakter)
     */
    public static function generateTemporaryPassword(int $length = 12): string
    {
        $length = max(8, $length);
        $uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowers = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $specials = '!@#$%&*';

        // Pastikan memuat setiap elemen
        $pwd = [
            $uppers[random_int(0, strlen($uppers) - 1)],
            $lowers[random_int(0, strlen($lowers) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $specials[random_int(0, strlen($specials) - 1)],
        ];

        $allChars = $uppers . $lowers . $digits . $specials;
        for ($i = 4; $i < $length; $i++) {
            $pwd[] = $allChars[random_int(0, strlen($allChars) - 1)];
        }

        shuffle($pwd);
        return implode('', $pwd);
    }

    /**
     * Sensor alamat email untuk tampilan UI dan audit log yang aman
     */
    public static function maskEmail(?string $email): string
    {
        if (empty($email)) {
            return '-';
        }
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(2, $len - 3)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Sensor NIM/Nomor Identitas untuk audit log dan UI yang aman
     */
    public static function maskIdentity(?string $identity): string
    {
        if (empty($identity)) {
            return '-';
        }
        $len = strlen($identity);
        if ($len <= 4) {
            return substr($identity, 0, 1) . '***';
        }
        return substr($identity, 0, 2) . str_repeat('*', max(2, $len - 4)) . substr($identity, -2);
    }

    /**
     * Sensor nomor telepon/WhatsApp
     */
    public static function maskPhone(?string $phone): string
    {
        if (empty($phone)) {
            return '-';
        }
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        $len = strlen($cleaned);
        if ($len <= 5) {
            return '***';
        }
        return substr($cleaned, 0, 3) . '****' . substr($cleaned, -3);
    }

    /**
     * Sensor nama pengguna
     */
    public static function maskName(?string $name): string
    {
        if (empty($name)) {
            return '-';
        }
        $len = strlen($name);
        if ($len <= 2) {
            return substr($name, 0, 1) . '*';
        }
        return substr($name, 0, 1) . str_repeat('*', max(2, $len - 2)) . substr($name, -1);
    }

    /**
     * Normalisasi nomor telepon/WhatsApp ke format standar Indonesia (628...)
     */
    public static function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if ($cleaned === '') {
            return '';
        }

        // Jika diawali 0, ubah ke 62 (contoh: 0812 -> 62812)
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            // Jika langsung 812..., tambahkan 62
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }
}
