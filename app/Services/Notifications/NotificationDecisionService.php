<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\OtpService;
use App\Services\SettingService;
use Core\Database;
use PDO;
use Throwable;

/**
 * NotificationDecisionService — Jejak Keputusan Notifikasi & Kesiapan Penerima
 *
 * Mencatat hasil keputusan setiap peristiwa notifikasi:
 * - Apakah diantre ('queued') atau dilewati ('skipped')
 * - Alasan terperinci: tidak ada penerima, belum opt-in, email belum terverifikasi,
 *   di luar allowlist, kuota habis, saluran nonaktif, dll.
 * - Panel evaluasi kesiapan penerima (readiness check)
 */
class NotificationDecisionService
{
    /**
     * Catat hasil evaluasi keputusan peristiwa notifikasi
     */
    public static function record(
        string $eventName,
        ?int $userId,
        ?string $recipientEmail,
        string $decision,
        string $reason,
        string $channel = 'email',
        ?int $outboxId = null,
        ?array $contextData = null
    ): void {
        try {
            $pdo = Database::getInstance()->getPdo();
            $maskedEmail = !empty($recipientEmail) ? OtpService::maskEmail($recipientEmail) : null;

            $stmt = $pdo->prepare("
                INSERT INTO notification_decisions (
                    event_name, user_id, recipient_email, decision, reason, channel, outbox_id, context_data, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $eventName,
                $userId,
                $maskedEmail,
                $decision,
                $reason,
                $channel,
                $outboxId,
                $contextData ? json_encode($contextData, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable $e) {
            error_log('[NotificationDecisionService::record] Error: ' . $e->getMessage());
        }
    }

    /**
     * Ambil riwayat jejak keputusan terbaru untuk tabel Pengaturan Notifikasi
     */
    public static function getRecentDecisions(int $limit = 50, ?string $decisionFilter = null): array
    {
        try {
            $pdo = Database::getInstance()->getPdo();
            $whereClause = "";
            $params = [];

            if ($decisionFilter && in_array($decisionFilter, ['queued', 'skipped'], true)) {
                $whereClause = "WHERE nd.decision = ?";
                $params[] = $decisionFilter;
            }

            $sql = "
                SELECT nd.*, u.name as user_name, r.name as role_name,
                       no.status as outbox_status, no.sent_at as outbox_sent_at, no.error_reason as outbox_error_reason
                FROM notification_decisions nd
                LEFT JOIN users u ON u.id = nd.user_id
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN notification_outbox no ON no.id = nd.outbox_id
                {$whereClause}
                ORDER BY nd.id DESC
                LIMIT {$limit}
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[NotificationDecisionService::getRecentDecisions] Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ambil total keputusan yang dilewati ('skipped')
     */
    public static function countSkipped(): int
    {
        try {
            $pdo = Database::getInstance()->getPdo();
            return (int) $pdo->query("SELECT COUNT(*) FROM notification_decisions WHERE decision = 'skipped'")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Dapatkan daftar evaluasi kesiapan penerima (Recipient Readiness Panel)
     * Menampilkan daftar akun: email dimasker, terverifikasi?, opt-in?, ada di allowlist?, dan alasan jika belum siap
     */
    public static function getRecipientReadinessList(int $limit = 100): array
    {
        try {
            $pdo = Database::getInstance()->getPdo();
            $driver = NotificationOutboxService::getDriver('email');

            $sql = "
                SELECT u.id, u.name, u.email, u.email_verified_at, u.email_verified_by_otp,
                       u.email_notifications_enabled, u.is_active, r.name as role_name
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE u.is_active = 1
                ORDER BY r.name ASC, u.name ASC
                LIMIT {$limit}
            ";

            $stmt = $pdo->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            $appEnv = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development'));

            foreach ($users as $u) {
                $email = trim((string)($u['email'] ?? ''));
                $eligibility = MailGateway::evaluateRecipientEligibility($email, (int)$u['id']);

                $isReserved = $eligibility['is_reserved'];
                $inAllowlist = $eligibility['in_allowlist'];
                $isOtpVerified = $eligibility['is_otp_verified'];
                $isManualVerified = $eligibility['is_manual_verified'];
                $hasOptIn = $eligibility['has_opt_in'];
                $isReady = $eligibility['is_ready'];
                $readinessReason = $eligibility['readiness_reason'];
                $unreadyReasons = $eligibility['unready_reasons'];

                // Kasus Khusus: Tombol "Tambah ke allowlist" per akun
                // Berlaku di lingkungan non-produksi jika belum ada di allowlist dan bukan domain reservasi
                $canAddToAllowlist = ($appEnv !== 'production' && !$inAllowlist && !$isReserved);

                $result[] = [
                    'id'                   => (int) $u['id'],
                    'name'                 => $u['name'],
                    'role_name'            => $u['role_name'] ?? 'civitas',
                    'email_masked'         => !empty($email) ? OtpService::maskEmail($email) : '-',
                    'raw_email'            => $email,
                    'is_verified'          => !empty($u['email_verified_at']),
                    'is_otp_verified'      => $isOtpVerified,
                    'is_manual_verified'   => $isManualVerified,
                    'has_opt_in'           => $hasOptIn,
                    'is_allowed'           => $inAllowlist,
                    'is_reserved'          => $isReserved,
                    'is_ready'             => $isReady,
                    'readiness_reason'     => $readinessReason, // "allowlist" atau "terverifikasi OTP + opt-in"
                    'unready_reasons'      => $unreadyReasons,
                    'can_add_to_allowlist' => $canAddToAllowlist,
                ];
            }

            return $result;
        } catch (Throwable $e) {
            error_log('[NotificationDecisionService::getRecipientReadinessList] Error: ' . $e->getMessage());
            return [];
        }
    }
}
