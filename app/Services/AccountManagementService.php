<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use App\Services\Notifications\NotificationOutboxService;
use App\Services\AuditLogService;
use App\Services\OtpService;
use PDO;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * AccountManagementService — Pengelolaan Data Akun & Verifikasi
 *
 * Mengimplementasikan:
 * 1. Admin: Ubah data akun (nama, NIM, email, No WA, prodi, angkatan) dengan validasi unik & normalisasi.
 *    Alasan perubahan wajib jika email atau NIM diubah. Nilai lama & baru disamarkan di audit log.
 * 2. Penanganan perubahan email:
 *    - Tandai email belum terverifikasi (jika diubah admin).
 *    - Batalkan antrean notifikasi ke alamat lama.
 *    - Tidak ada notifikasi ke alamat belum terverifikasi.
 *    - Kirim pemberitahuan keamanan ke email lama bila sebelumnya terverifikasi.
 * 3. Civitas: Ubah No WhatsApp & Email sendiri via verifikasi OTP ke email baru + konfirmasi kata sandi saat ini.
 *    Nama dan NIM hanya-baca.
 * 4. Petugas: Tidak diizinkan mengubah data pribadi akun pengguna lain.
 */
class AccountManagementService
{
    /**
     * Dapatkan instance PDO aktif
     */
    protected static function getPdo(): PDO
    {
        return Database::getInstance()->getPdo();
    }

    /**
     * Normalisasi nomor telepon/WhatsApp ke format standar
     */
    public static function normalizePhone(?string $phone): string
    {
        return OtpService::normalizePhone($phone);
    }

    /**
     * Validasi keunikan email
     */
    public static function validateUniqueEmail(string $email, ?int $excludeUserId = null, ?PDO $pdo = null): void
    {
        $pdo = $pdo ?? self::getPdo();
        $cleanEmail = strtolower(trim($email));

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Format alamat email '{$cleanEmail}' tidak valid.");
        }

        $sql = "SELECT id FROM users WHERE LOWER(email) = LOWER(?)";
        $params = [$cleanEmail];

        if ($excludeUserId !== null && $excludeUserId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            throw new InvalidArgumentException("Alamat email '{$cleanEmail}' sudah terdaftar dalam sistem.");
        }
    }

    /**
     * Validasi keunikan NIM / Nomor Identitas
     */
    public static function validateUniqueIdentity(?string $identityNumber, ?int $excludeUserId = null, ?PDO $pdo = null): void
    {
        $cleanNim = trim((string) $identityNumber);
        if ($cleanNim === '') {
            return;
        }

        $pdo = $pdo ?? self::getPdo();
        $sql = "SELECT id FROM users WHERE identity_number = ?";
        $params = [$cleanNim];

        if ($excludeUserId !== null && $excludeUserId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }

        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            throw new InvalidArgumentException("NIM / Nomor Identitas '{$cleanNim}' sudah digunakan oleh pengguna lain.");
        }
    }

    /**
     * Batalkan antrean notifikasi outbox pending milik pengguna
     */
    public static function cancelPendingOutbox(int $userId, ?PDO $pdo = null): int
    {
        $pdo = $pdo ?? self::getPdo();
        try {
            $stmt = $pdo->prepare("
                UPDATE notification_outbox 
                SET status = 'cancelled', 
                    error_reason = 'Dibatalkan: Alamat email pengguna telah diubah',
                    updated_at = NOW() 
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$userId]);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    /**
     * Kirim pemberitahuan keamanan ke alamat email lama jika terverifikasi
     */
    public static function sendSecurityEmailAlert(string $oldEmail, string $userName, string $newEmail): bool
    {
        if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $maskedNewEmail = OtpService::maskEmail($newEmail);
            $driver = NotificationOutboxService::getDriver('email');

            $outboxItem = [
                'id'           => 0,
                'user_id'      => 0,
                'recipient'    => $oldEmail,
                'template_key' => 'security_email_changed',
                'payload'      => [
                    'user_name'        => $userName,
                    'new_email_masked' => $maskedNewEmail,
                    'changed_at'       => date('d M Y H:i \W\I\B'),
                    'app_name'         => 'Campus Find',
                ],
            ];

            $res = $driver->send($outboxItem);
            return (bool) ($res['success'] ?? false);
        } catch (Throwable $e) {
            error_log('[AccountManagementService] Gagal kirim email peringatan keamanan: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Admin: Ubah data lengkap pengguna
     *
     * @param int   $targetUserId ID pengguna yang diubah
     * @param array $data         Data input form (name, email, identity_number, phone, study_program, batch_year, change_reason)
     * @param int   $adminUserId  ID admin yang melakukan perubahan
     * @return array{success: bool, message: string, changes: array}
     */
    public static function adminUpdateAccount(int $targetUserId, array $data, int $adminUserId): array
    {
        $pdo = self::getPdo();

        // 1. Ambil data pengguna saat ini
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$targetUserId]);
        $oldUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldUser) {
            throw new InvalidArgumentException("Pengguna dengan ID {$targetUserId} tidak ditemukan.");
        }

        // 2. Validasi input nama
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException("Nama pengguna tidak boleh kosong.");
        }

        // 3. Validasi & keunikan email
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '') {
            throw new InvalidArgumentException("Alamat email tidak boleh kosong.");
        }
        self::validateUniqueEmail($email, $targetUserId, $pdo);

        // 4. Validasi & keunikan NIM
        $identityNumber = trim((string) ($data['identity_number'] ?? ''));
        if ($identityNumber !== '') {
            self::validateUniqueIdentity($identityNumber, $targetUserId, $pdo);
        } else {
            $identityNumber = null;
        }

        // 5. Normalisasi nomor WhatsApp
        $rawPhone = trim((string) ($data['phone'] ?? ''));
        $phone = $rawPhone !== '' ? self::normalizePhone($rawPhone) : null;

        // 6. Metadata opsional
        $studyProgram = trim((string) ($data['study_program'] ?? '')) ?: null;
        $batchYear    = trim((string) ($data['batch_year'] ?? '')) ?: null;

        // 7. Deteksi perubahan
        $oldEmail = strtolower(trim((string) $oldUser['email']));
        $oldNim   = trim((string) ($oldUser['identity_number'] ?? ''));
        $oldPhone = trim((string) ($oldUser['phone'] ?? ''));
        $oldName  = trim((string) $oldUser['name']);
        $oldProdi = trim((string) ($oldUser['study_program'] ?? ''));
        $oldBatch = trim((string) ($oldUser['batch_year'] ?? ''));

        $emailChanged = ($oldEmail !== $email);
        $nimChanged   = ($oldNim !== ($identityNumber ?? ''));
        $phoneChanged = ($oldPhone !== ($phone ?? ''));
        $nameChanged  = ($oldName !== $name);
        $prodiChanged = ($oldProdi !== ($studyProgram ?? ''));
        $batchChanged = ($oldBatch !== ($batchYear ?? ''));

        // 8. Aturan Wajib: Perubahan email dan NIM wajib menyertakan alasan perubahan
        $changeReason = trim((string) ($data['change_reason'] ?? ''));
        if (($emailChanged || $nimChanged) && $changeReason === '') {
            throw new InvalidArgumentException("Alasan perubahan wajib diisi jika mengubah Email atau NIM.");
        }

        // 9. Tindakan khusus saat perubahan email
        $newVerifiedAt = $oldUser['email_verified_at'];
        $newVerifiedByOtp = (int)($oldUser['email_verified_by_otp'] ?? 0);
        if ($emailChanged) {
            // Tandai email belum terverifikasi
            $newVerifiedAt = null;
            $newVerifiedByOtp = 0;

            // Batalkan antrean notifikasi ke alamat lama
            self::cancelPendingOutbox($targetUserId, $pdo);

            // Kirim pemberitahuan keamanan ke email lama bila sebelumnya terverifikasi
            if (!empty($oldUser['email_verified_at'])) {
                self::sendSecurityEmailAlert($oldEmail, $oldUser['name'], $email);
            }
        }

        // 10. Simpan perubahan ke tabel users
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET name = ?,
                email = ?,
                identity_number = ?,
                phone = ?,
                study_program = ?,
                batch_year = ?,
                email_verified_at = ?,
                email_verified_by_otp = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $name,
            $email,
            $identityNumber,
            $phone,
            $studyProgram,
            $batchYear,
            $newVerifiedAt,
            $newVerifiedByOtp,
            $targetUserId,
        ]);

        // 11. Susun catatan audit log dengan nilai lama dan baru yang disamarkan
        $changes = [];
        if ($nameChanged) {
            $changes['name'] = [
                'old' => OtpService::maskName($oldName),
                'new' => OtpService::maskName($name),
            ];
        }
        if ($emailChanged) {
            $changes['email'] = [
                'old' => OtpService::maskEmail($oldEmail),
                'new' => OtpService::maskEmail($email),
            ];
        }
        if ($nimChanged) {
            $changes['identity_number'] = [
                'old' => OtpService::maskIdentity($oldNim),
                'new' => OtpService::maskIdentity($identityNumber ?? ''),
            ];
        }
        if ($phoneChanged) {
            $changes['phone'] = [
                'old' => OtpService::maskPhone($oldPhone),
                'new' => OtpService::maskPhone($phone ?? ''),
            ];
        }
        if ($prodiChanged) {
            $changes['study_program'] = [
                'old' => $oldProdi ?: '-',
                'new' => $studyProgram ?: '-',
            ];
        }
        if ($batchChanged) {
            $changes['batch_year'] = [
                'old' => $oldBatch ?: '-',
                'new' => $batchYear ?: '-',
            ];
        }

        AuditLogService::log('USER_DATA_UPDATED', $adminUserId, 'users', $targetUserId, [
            'target_user_id' => $targetUserId,
            'admin_user_id'  => $adminUserId,
            'reason'         => $changeReason ?: 'Pembaruan data pengguna oleh Administrator',
            'changes'        => $changes,
        ]);

        $changedFields = array_keys($changes);
        $msg = $emailChanged 
            ? MessageCatalog::userUpdated($name, true, OtpService::maskEmail($email), $changedFields)
            : MessageCatalog::userUpdated($name, false, null, $changedFields);

        \App\Middleware\AuthMiddleware::refreshUser($targetUserId);

        return [
            'success'       => true,
            'message'       => $msg,
            'changes'       => $changes,
            'email_changed' => $emailChanged,
        ];
    }

    /**
     * Civitas: Perbarui nomor WhatsApp sendiri
     */
    public static function civitasUpdatePhone(int $userId, string $phone): array
    {
        $pdo = self::getPdo();

        $stmt = $pdo->prepare("SELECT id, name, phone FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new InvalidArgumentException("Pengguna tidak ditemukan.");
        }

        $rawPhone = trim($phone);
        $normPhone = $rawPhone !== '' ? self::normalizePhone($rawPhone) : null;
        $oldPhone = trim((string) ($user['phone'] ?? ''));

        if ($oldPhone === ($normPhone ?? '')) {
            return [
                'success' => true,
                'message' => 'Tidak ada perubahan pada nomor WhatsApp.',
            ];
        }

        $stmtUpdate = $pdo->prepare("UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$normPhone, $userId]);

        AuditLogService::log('USER_DATA_UPDATED', $userId, 'users', $userId, [
            'target_user_id' => $userId,
            'reason'         => 'Pengguna memperbarui nomor WhatsApp mandiri',
            'changes'        => [
                'phone' => [
                    'old' => OtpService::maskPhone($oldPhone),
                    'new' => OtpService::maskPhone($normPhone ?? ''),
                ],
            ],
        ]);

        \App\Middleware\AuthMiddleware::refreshUser($userId);

        return [
            'success'   => true,
            'message'   => 'Nomor WhatsApp berhasil diperbarui.',
            'new_phone' => $normPhone,
        ];
    }

    /**
     * Civitas: Minta OTP untuk perubahan email (butuh kata sandi saat ini)
     */
    public static function civitasRequestEmailChange(
        int $userId,
        string $newEmail,
        string $currentPassword,
        ?string $ip = null,
        ?string $ua = null
    ): array {
        $pdo = self::getPdo();

        // 1. Ambil pengguna
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, email_verified_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
        }

        // 2. Konfirmasi kata sandi saat ini
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Kata sandi saat ini yang Anda masukkan salah.'];
        }

        // 3. Validasi email baru
        $cleanEmail = strtolower(trim($newEmail));
        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Format alamat email baru tidak valid.'];
        }

        if ($cleanEmail === strtolower(trim($user['email']))) {
            return ['success' => false, 'message' => 'Alamat email baru harus berbeda dari email Anda saat ini.'];
        }

        // Keunikan email baru
        try {
            self::validateUniqueEmail($cleanEmail, $userId, $pdo);
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        // 4. Generate & kirim OTP ke email BARU
        $otpRes = OtpService::generate($userId, 'email_change', $ip, $ua, $cleanEmail);
        if (!$otpRes['success']) {
            return $otpRes;
        }

        return [
            'success'           => true,
            'message'           => "Kode verifikasi (OTP) 6 digit telah dikirim ke alamat email baru: " . OtpService::maskEmail($cleanEmail),
            'target_email'      => $cleanEmail,
            'email_masked'      => OtpService::maskEmail($cleanEmail),
            'cooldown_seconds'  => OtpService::COOLDOWN_SECONDS,
            'remaining_seconds' => $otpRes['remaining_seconds'] ?? null,
        ];
    }

    /**
     * Civitas: Konfirmasi kode OTP email baru untuk menyelesaikan perubahan email
     */
    public static function civitasConfirmEmailChange(int $userId, string $otpCode): array
    {
        $pdo = self::getPdo();

        // 1. Ambil data pengguna
        $stmtUser = $pdo->prepare("SELECT id, name, email, email_verified_at FROM users WHERE id = ? LIMIT 1");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
        }

        // 2. Verifikasi kode OTP untuk purpose 'email_change'
        $verifyRes = OtpService::verify($userId, 'email_change', $otpCode);
        if (!$verifyRes['success']) {
            return [
                'success'   => false,
                'message'   => $verifyRes['error'] ?? 'Kode verifikasi tidak valid.',
                'remaining' => $verifyRes['remaining'] ?? 0,
                'locked'    => $verifyRes['locked'] ?? false,
            ];
        }

        // 3. Ambil target email yang sudah diverifikasi
        $newEmail = strtolower(trim((string) ($verifyRes['target_email'] ?? '')));
        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Alamat email target verifikasi tidak valid.'];
        }

        // Pastikan kembali email belum direbut orang lain
        try {
            self::validateUniqueEmail($newEmail, $userId, $pdo);
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $oldEmail = strtolower(trim((string) $user['email']));

        // 4. Batalkan antrean notifikasi outbox pending ke alamat email lama
        self::cancelPendingOutbox($userId, $pdo);

        // 5. Kirim pemberitahuan keamanan ke email lama bila sebelumnya terverifikasi
        if (!empty($user['email_verified_at'])) {
            self::sendSecurityEmailAlert($oldEmail, $user['name'], $newEmail);
        }

        // 6. Update email di tabel users dan tandai terverifikasi (karena baru saja diverifikasi dengan OTP)
        $stmtUpdate = $pdo->prepare("
            UPDATE users 
            SET email = ?, 
                email_verified_at = NOW(), 
                email_verified_by_otp = 1,
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$newEmail, $userId]);

        // 7. Catat ke audit log
        AuditLogService::log('USER_EMAIL_CHANGED', $userId, 'users', $userId, [
            'target_user_id' => $userId,
            'reason'         => 'Pengguna berhasil memverifikasi dan mengubah email mandiri via OTP',
            'changes'        => [
                'email' => [
                    'old' => OtpService::maskEmail($oldEmail),
                    'new' => OtpService::maskEmail($newEmail),
                ],
            ],
        ]);

        \App\Middleware\AuthMiddleware::refreshUser($userId);

        return [
            'success'   => true,
            'message'   => 'Alamat email Anda berhasil diubah dan telah terverifikasi.',
            'new_email' => $newEmail,
        ];
    }

    /**
     * Hitung jumlah Administrator aktif yang belum dihapus
     */
    public static function countActiveAdmins(?PDO $pdo = null): int
    {
        $pdo = $pdo ?? self::getPdo();
        $stmt = $pdo->query("
            SELECT COUNT(*) 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.name = 'admin' AND u.is_active = 1 AND u.deleted_at IS NULL
        ");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Evaluasi dependensi historis akun pengguna pada seluruh transaksi sistem (Section 3B & 3C)
     *
     * @return array{has_dependencies: bool, total: int, details: array<string, int>}
     */
    public static function getHistoricalDependencies(int $userId, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? self::getPdo();
        $counts = [];

        // 1. Laporan kehilangan
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lost_reports WHERE reporter_user_id = ?");
        $stmt->execute([$userId]);
        $counts['lost_reports'] = (int) $stmt->fetchColumn();

        // 2. Laporan temuan
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM found_reports WHERE reporter_user_id = ?");
        $stmt->execute([$userId]);
        $counts['found_reports'] = (int) $stmt->fetchColumn();

        // 3. Klaim barang (pemohon, reviewer, penyerah)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE claimant_user_id = ? OR reviewed_by_user_id = ? OR handed_over_by_user_id = ?");
        $stmt->execute([$userId, $userId, $userId]);
        $counts['claims'] = (int) $stmt->fetchColumn();

        // 4. Pesan percakapan internal
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE sender_user_id = ?");
        $stmt->execute([$userId]);
        $counts['chat_messages'] = (int) $stmt->fetchColumn();

        // 5. Ruang percakapan yang dibuat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_rooms WHERE created_by_user_id = ?");
        $stmt->execute([$userId]);
        $counts['chat_rooms'] = (int) $stmt->fetchColumn();

        // 6. Riwayat chat AI C-Find
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cfind_chat_history WHERE user_id = ?");
            $stmt->execute([$userId]);
            $counts['cfind_chats'] = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            $counts['cfind_chats'] = 0;
        }

        // 7. Barang posko yang diverifikasi
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE verified_by_user_id = ?");
            $stmt->execute([$userId]);
            $counts['verified_items'] = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            $counts['verified_items'] = 0;
        }

        // 8. Bukti klaim yang diverifikasi
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM claim_evidence WHERE verified_by_user_id = ?");
            $stmt->execute([$userId]);
            $counts['claim_evidence'] = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            $counts['claim_evidence'] = 0;
        }

        // 9. Riwayat perubahan status barang/klaim
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM status_histories WHERE changed_by_user_id = ?");
            $stmt->execute([$userId]);
            $counts['status_histories'] = (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            $counts['status_histories'] = 0;
        }

        $total = array_sum($counts);
        return [
            'has_dependencies' => $total > 0,
            'total'            => $total,
            'details'          => array_filter($counts, fn($c) => $c > 0),
        ];
    }

    /**
     * Hapus Akun Pengguna Secara Permanen oleh Administrator (BUG FIX 03)
     *
     * Kebijakan:
     * - Wajib berstatus nonaktif (is_active = 0). Akun aktif DITOLAK.
     * - Admin tidak dapat menghapus akunnya sendiri.
     * - Admin tidak dapat menghapus satu-satunya administrator aktif terakhir.
     * - Jika akun memiliki dependensi historis -> Permanent Anonymization (soft-delete + anonimisasi identitas).
     * - Jika akun tidak memiliki dependensi historis -> Hard Deletion (hapus baris secara aman dalam transaksi).
     * - Audit log dicatat TANPA membocorkan PII.
     *
     * @param int    $targetUserId        ID akun pengguna yang akan dihapus
     * @param int    $adminUserId         ID administrator yang mengeksekusi
     * @param string $confirmationPhrase  Frasa konfirmasi teks (wajib persis 'HAPUS PERMANEN')
     * @return array{success: bool, action_type: string, message: string, dep_count: int}
     * @throws InvalidArgumentException
     */
    public static function deleteAccountPermanently(int $targetUserId, int $adminUserId, string $confirmationPhrase): array
    {
        $pdo = self::getPdo();

        // 1. Validasi frasa konfirmasi teks
        if (trim($confirmationPhrase) !== 'HAPUS PERMANEN') {
            throw new InvalidArgumentException("Frasa konfirmasi tidak sesuai. Harap ketik 'HAPUS PERMANEN' untuk melanjutkan.");
        }

        // 2. Cegah Admin menghapus akun dirinya sendiri (Section 4 Rule 1)
        if ($targetUserId === $adminUserId) {
            throw new InvalidArgumentException("Anda tidak dapat menghapus akun Anda sendiri.");
        }

        // 3. Ambil data target user
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name, r.label AS role_label 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ? LIMIT 1
        ");
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            throw new InvalidArgumentException("Pengguna dengan ID {$targetUserId} tidak ditemukan.");
        }

        // 4. Cegah penghapusan akun yang sudah dihapus permanen sebelumnya
        if (!empty($targetUser['deleted_at'])) {
            throw new InvalidArgumentException("Akun ini telah dihapus permanen sebelumnya.");
        }

        // 5. Wajib nonaktif sebelum dapat dihapus permanen (Section 3A & Section 4 Rule 3)
        if ((int)$targetUser['is_active'] === 1) {
            throw new InvalidArgumentException("Akun harus dinonaktifkan terlebih dahulu sebelum dapat dihapus permanen.");
        }

        // 6. Cegah penghapusan satu-satunya Administrator aktif (Section 4 Rule 2 & 10)
        if ($targetUser['role_name'] === 'admin') {
            $activeAdmins = self::countActiveAdmins($pdo);
            if ($activeAdmins <= 1) {
                throw new InvalidArgumentException("Tidak dapat menghapus satu-satunya Administrator aktif yang tersisa dalam sistem.");
            }
        }

        // 7. Evaluasi dependensi historis
        $dep = self::getHistoricalDependencies($targetUserId, $pdo);

        $pdo->beginTransaction();
        try {
            // Batalkan seluruh antrean notifikasi outbox pending
            self::cancelPendingOutbox($targetUserId, $pdo);

            if ($dep['has_dependencies']) {
                // ANONYMIZATION PERMANEN (Section 3C)
                $placeholderEmail = 'deleted_' . $targetUserId . '_' . bin2hex(random_bytes(4)) . '@deleted.local';
                $dummyPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);

                $updateSql = "
                    UPDATE users 
                    SET name = 'Pengguna Dihapus',
                        email = ?,
                        identity_number = NULL,
                        phone = NULL,
                        avatar = NULL,
                        study_program = NULL,
                        batch_year = NULL,
                        is_active = 0,
                        deleted_at = NOW(),
                        password_hash = ?,
                        email_verified_at = NULL,
                        email_verified_by_otp = 0,
                        email_notifications_enabled = 0,
                        email_consent_at = NULL,
                        email_consent_text_version = NULL,
                        must_change_password = 0,
                        temp_password_expires_at = NULL,
                        session_version = session_version + 1,
                        updated_at = NOW()
                    WHERE id = ?
                ";
                $stmtUpdate = $pdo->prepare($updateSql);
                $stmtUpdate->execute([$placeholderEmail, $dummyPasswordHash, $targetUserId]);

                $actionType = 'anonymization';
                $message = "Akun berhasil dihapus secara permanen. Karena akun memiliki {$dep['total']} data histori, identitas akun telah dianonimkan untuk menjaga integritas transaksi sistem.";
            } else {
                // HARD DELETION (Section 3B)
                // Bersihkan record pendukung yang aman dihapus
                try { $pdo->prepare("DELETE FROM otp_verifications WHERE user_id = ?")->execute([$targetUserId]); } catch (\PDOException $e) {}
                try { $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$targetUserId]); } catch (\PDOException $e) {}
                try { $pdo->prepare("DELETE FROM notification_outbox WHERE user_id = ?")->execute([$targetUserId]); } catch (\PDOException $e) {}

                // Hapus baris user
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetUserId]);

                $actionType = 'hard_delete';
                $message = "Akun berhasil dihapus permanen secara keseluruhan karena tidak memiliki dependensi transaksi historis.";
            }

            // Catat Audit Log (HANYA metadata minimal TANPA PII, Section 3C)
            AuditLogService::log(
                'ACCOUNT_PERMANENTLY_DELETED',
                'users',
                $targetUserId,
                [
                    'target_user_id' => $targetUserId,
                    'actor_admin_id' => $adminUserId,
                    'action_type'    => $actionType,
                    'timestamp'      => date('c'),
                    'dep_count'      => $dep['total'],
                    'dep_details'    => $dep['details'],
                ],
                $adminUserId
            );

            $pdo->commit();

            return [
                'success'     => true,
                'action_type' => $actionType,
                'message'     => $message,
                'dep_count'   => $dep['total'],
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
