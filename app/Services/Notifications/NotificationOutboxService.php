<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\SettingService;
use Core\Database;
use PDO;
use Throwable;

/**
 * NotificationOutboxService — Layanan Manajemen Outbox & Worker Notifikasi (Prompt 20)
 *
 * Mengimplementasikan Transactional Outbox Pattern:
 * - Menyimpan antrean pesan secara independen dari transaksi utama
 * - Alur bisnis (klaim, verifikasi, laporan) tidak pernah terblokir oleh kegagalan email
 * - Pengiriman asinkron berkala dengan retry berjangka (exponential backoff)
 * - Idempotensi berbasis kunci unik (mencegah pengiriman ganda)
 * - Safeguards: Opt-in pengguna, verifikasi email, NOTIFY_ALLOWLIST, daily limit, dan circuit breaker
 */
class NotificationOutboxService
{
    private static ?NotificationChannelInterface $driverOverride = null;
    private static bool $autoDispatchScheduled = false;

    /**
     * Daftarkan driver kustom (misal FakeNotificationDriver saat testing)
     */
    public static function setDriver(?NotificationChannelInterface $driver): void
    {
        self::$driverOverride = $driver;
        MailGateway::setDriverOverride($driver);
    }

    /**
     * Dapatkan driver aktif untuk saluran tertentu
     */
    public static function getDriver(string $channel = 'email'): NotificationChannelInterface
    {
        if (self::$driverOverride !== null && self::$driverOverride->getName() === $channel) {
            return self::$driverOverride;
        }

        if ($channel === 'email') {
            return MailGateway::getDriver();
        }

        return match ($channel) {
            'email' => MailGateway::getDriver(),
            default => MailGateway::getDriver(),
        };
    }

    /**
     * Masukkan notifikasi ke dalam antrean outbox jika pengguna memenuhi syarat opt-in
     *
     * @param int    $userId Target penerima
     * @param string $templateKey Kunci template pesan
     * @param array  $payload Data minimal (title, message, url, entity_type, entity_id)
     * @param string $channel Saluran target (default: 'email')
     * @param string|null $customIdempotencyKey Kunci idempotensi khusus
     * @return int|null ID outbox yang dibuat atau ditemukan
     */
    public static function enqueue(
        int $userId,
        string $templateKey,
        array $payload,
        string $channel = 'email',
        ?string $customIdempotencyKey = null
    ): ?int {
        try {
            $pdo = Database::getInstance()->getPdo();

            // 1. Periksa sakelar global saluran eksternal
            $emailEnabled = (bool) SettingService::get('notif_email_status', true);
            $externalChannel = (string) SettingService::get('notif_external_channel', 'email');

            if (!$emailEnabled || ($channel === 'email' && $externalChannel === 'none')) {
                NotificationDecisionService::record($templateKey, $userId, null, 'skipped', 'dilewati: saluran nonaktif', $channel);
                return null;
            }

            // 2. Ambil data pengguna dan periksa persetujuan (opt-in) & status verifikasi email
            $stmtUser = $pdo->prepare("SELECT id, name, email, email_verified_at, email_notifications_enabled FROM users WHERE id = ? LIMIT 1");
            $stmtUser->execute([$userId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                NotificationDecisionService::record($templateKey, $userId, null, 'skipped', 'dilewati: tidak ada penerima', $channel);
                return null;
            }

            $recipientEmail = trim((string) ($user['email'] ?? ''));
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                NotificationDecisionService::record($templateKey, $userId, $recipientEmail, 'skipped', 'dilewati: format email tidak valid', $channel);
                return null;
            }

            // Aturan Wajib Prompt 20C: Notifikasi eksternal HANYA dikirim jika pengguna menyetujui (opt-in) & email terverifikasi
            $hasOptIn = (int) ($user['email_notifications_enabled'] ?? 0) === 1;
            $isVerified = !empty($user['email_verified_at']);

            if (!$hasOptIn) {
                NotificationDecisionService::record($templateKey, $userId, $recipientEmail, 'skipped', 'dilewati: belum opt-in', $channel);
                return null;
            }

            if (!$isVerified) {
                NotificationDecisionService::record($templateKey, $userId, $recipientEmail, 'skipped', 'dilewati: email belum terverifikasi', $channel);
                return null;
            }

            // Evaluasi Terpusat MailGateway (Format, TLD reservasi, allowlist exact match, kuota harian)
            $gatewayVal = MailGateway::validateRecipient($recipientEmail, $userId, $templateKey);
            if (!$gatewayVal['allowed']) {
                NotificationDecisionService::record($templateKey, $userId, $recipientEmail, 'skipped', $gatewayVal['reason'] ?? 'dilewati: diblokir oleh MailGateway', $channel);
                return null;
            }

            // Sertakan nama user di payload
            $payload['user_name'] = $user['name'] ?? 'Pengguna';

            // 3. Pembatasan frekuensi (Rate Limiting) per pengguna
            // Khusus pesan chat: batasi maksimal 1 notifikasi email per room per 5 menit
            if ($templateKey === 'new_chat_message' && !empty($payload['entity_id'])) {
                $roomId = (int) $payload['entity_id'];
                $stmtChatThrottling = $pdo->prepare("
                    SELECT id FROM notification_outbox 
                    WHERE user_id = ? AND template_key = 'new_chat_message' 
                      AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.entity_id')) = ?
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    LIMIT 1
                ");
                $stmtChatThrottling->execute([$userId, (string) $roomId]);
                if ($stmtChatThrottling->fetchColumn()) {
                    NotificationDecisionService::record($templateKey, $userId, $recipientEmail, 'skipped', 'dilewati: dibatasi frekuensi (maks 1 email / 5 menit)', $channel);
                    return null;
                }
            }

            // 4. Pembentukan Kunci Idempotensi Unik
            if ($customIdempotencyKey !== null) {
                $idempotencyKey = $customIdempotencyKey;
            } else {
                $entityType = $payload['entity_type'] ?? '';
                $entityId   = $payload['entity_id'] ?? '';
                $actionKey  = $payload['title'] ?? $templateKey;
                $msgId      = !empty($payload['message_id']) ? ('msg_' . $payload['message_id']) : '';
                $idempotencyKey = sha1("{$channel}:{$userId}:{$templateKey}:{$entityType}:{$entityId}:{$msgId}:{$actionKey}");
            }

            // 5. Cek apakah sudah pernah ada outbox dengan idempotency_key yang sama
            $stmtCheck = $pdo->prepare("SELECT id FROM notification_outbox WHERE idempotency_key = ? LIMIT 1");
            $stmtCheck->execute([$idempotencyKey]);
            $existingId = $stmtCheck->fetchColumn();
            if ($existingId) {
                return (int) $existingId;
            }

            // 6. Masukkan ke tabel notification_outbox
            $stmtInsert = $pdo->prepare("
                INSERT INTO notification_outbox (
                    user_id, channel, recipient, template_key, payload, status,
                    attempts, next_try_at, idempotency_key, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, 'pending', 0, NOW(), ?, NOW(), NOW()
                )
            ");

            $stmtInsert->execute([
                $userId,
                $channel,
                $recipientEmail,
                $templateKey,
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                $idempotencyKey,
            ]);

            $outboxId = (int) $pdo->lastInsertId();
            NotificationDecisionService::record($templateKey, $userId, $recipientEmail, 'queued', 'diantre: memenuhi semua kriteria', $channel, $outboxId);

            // Auto-dispatch otomatis di akhir request web
            self::scheduleAutoDispatch();

            return $outboxId;
        } catch (Throwable $e) {
            error_log('[NotificationOutboxService::enqueue] Gagal menyimpan ke outbox: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Jadwalkan pemrosesan outbox otomatis di akhir request web (shutdown function)
     * Memastikan email terkirim nyata tanpa menahan render halaman browser
     */
    public static function scheduleAutoDispatch(): void
    {
        if (self::$autoDispatchScheduled) {
            return;
        }

        self::$autoDispatchScheduled = true;

        register_shutdown_function(function () {
            // Jika dalam pengujian unit yang sengaja ingin memverifikasi status pending manual, lewati
            if (defined('CAMPUS_FIND_TESTING_NO_AUTO_DISPATCH') && constant('CAMPUS_FIND_TESTING_NO_AUTO_DISPATCH')) {
                return;
            }

            try {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } elseif (ob_get_level() > 0) {
                    @ob_end_flush();
                    @flush();
                }

                // Jalankan worker pemroses antrean (proses hingga 10 item pending)
                self::processOutbox(10);
            } catch (Throwable $e) {
                error_log('[NotificationOutboxService::autoDispatch] Error: ' . $e->getMessage());
            }
        });
    }

    /**
     * Masukkan ke outbox dari pemicu in-app NotificationService
     */
    public static function enqueueFromInApp(int $userId, string $type, array $payload): ?int
    {
        $templateKey = match ($type) {
            'claim_status_changed'       => 'claim_status_changed',
            'found_item_received'        => 'found_item_received',
            'new_chat_message'           => 'new_chat_message',
            'match_candidate_found'      => 'match_candidate_found',
            'lost_report_status_changed' => 'lost_report_status_changed',
            'found_report_status_changed'=> 'found_item_received',
            'new_claim_submitted'        => 'new_claim_submitted',
            'new_found_report'           => 'new_found_report',
            'new_lost_report'            => 'new_lost_report',
            'system_alert'               => !empty($payload['alert_template']) ? $payload['alert_template'] : 'system_alert_failure',
            default                      => $type,
        };

        return self::enqueue($userId, $templateKey, $payload);
    }

    /**
     * Jalankan worker pemroses antrean notification_outbox (perintah CLI / Scheduler)
     *
     * @param int $batchSize Maksimal antrean yang diproses dalam satu putaran
     * @return array{processed: int, sent: int, failed: int, postponed: int, circuit_breaker: bool}
     */
    public static function processOutbox(int $batchSize = 20): array
    {
        $result = [
            'processed'       => 0,
            'sent'            => 0,
            'failed'          => 0,
            'blocked'         => 0,
            'skipped'         => 0,
            'postponed'       => 0,
            'circuit_breaker' => false,
        ];

        try {
            $pdo = Database::getInstance()->getPdo();

            // 1. Periksa Circuit Breaker
            $isTripped = (bool) SettingService::get('notif_circuit_breaker_tripped', false);
            if ($isTripped) {
                $result['circuit_breaker'] = true;
                return $result;
            }

            // 2. Periksa Batas Global Harian (Default 100 email per hari)
            $dailyLimit = (int) SettingService::get('notif_daily_limit', 100);
            $stmtDailyCount = $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'sent' AND DATE(sent_at) = CURDATE()");
            $sentToday = (int) $stmtDailyCount->fetchColumn();

            if ($sentToday >= $dailyLimit) {
                // Tunda antrean pending yang jatuh tempo hari ini ke esok hari (bukan dibuang)
                $pdo->exec("
                    UPDATE notification_outbox 
                    SET next_try_at = DATE_ADD(CURDATE(), INTERVAL 1 DAY),
                        error_reason = '[DAILY_LIMIT_REACHED] Kuota harian email tercapai. Ditunda ke besok.',
                        updated_at = NOW()
                    WHERE status = 'pending' AND next_try_at <= NOW()
                    LIMIT {$batchSize}
                ");
                $result['postponed'] = $batchSize;
                $result['skipped']   = $batchSize;

                // Pemicu Peringatan Sistem ke Admin: Kuota Habis
                try {
                    \App\Services\NotificationService::notifySystemAlert(
                        'quota_exceeded',
                        "Batas kuota harian ({$dailyLimit} email) telah tercapai hari ini. Antrean outbox yang tersisa ditunda ke esok hari."
                    );
                } catch (Throwable) {
                }

                return $result;
            }

            // Hitung sisa kuota hari ini
            $quotaRemaining = max(0, $dailyLimit - $sentToday);
            $effectiveBatch = min($batchSize, $quotaRemaining);

            if ($effectiveBatch <= 0) {
                return $result;
            }

            // 3. Ambil antrean pending yang siap dikirim
            $stmt = $pdo->prepare("
                SELECT * FROM notification_outbox 
                WHERE status = 'pending' AND next_try_at <= NOW()
                ORDER BY id ASC
                LIMIT {$effectiveBatch}
            ");
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                return $result;
            }

            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                $channel = $item['channel'] ?? 'email';

                // Tandai sedang dikirim dan naikkan percobaan
                $attempts = ((int) $item['attempts']) + 1;
                $pdo->prepare("UPDATE notification_outbox SET status = 'sending', attempts = ?, last_attempt_at = NOW(), updated_at = NOW() WHERE id = ?")
                    ->execute([$attempts, $itemId]);

                $result['processed']++;

                // Eksekusi pengiriman lewat MailGateway (Satu Pintu Kirim)
                if ($channel === 'email') {
                    $sendRes = MailGateway::send($item);
                } else {
                    $driver = self::getDriver($channel);
                    $sendRes = $driver->send($item);
                }

                if (!empty($sendRes['success'])) {
                    // SUKSES
                    $pdo->prepare("UPDATE notification_outbox SET status = 'sent', sent_at = NOW(), error_reason = NULL, updated_at = NOW() WHERE id = ?")
                        ->execute([$itemId]);

                    $result['sent']++;

                    // Reset hitungan kegagalan berturut-turut pada system_settings
                    self::recordSuccessStatus();
                } else {
                    // GAGAL
                    $rawError = (string) ($sendRes['error'] ?? 'Pengiriman gagal tanpa rincian');
                    $isBlocked = !empty($sendRes['blocked']);

                    if ($isBlocked) {
                        // Diblokir oleh allowlist: tandai 'cancelled', tidak perlu di-retry
                        $pdo->prepare("UPDATE notification_outbox SET status = 'cancelled', error_reason = ?, updated_at = NOW() WHERE id = ?")
                            ->execute([$rawError, $itemId]);
                        $result['blocked']++;
                        $result['failed']++;
                    } elseif (str_contains($rawError, 'placeholder belum terganti')) {
                        // Kesalahan sintaks templat / variabel: langsung gagalkan tanpa retry berulang
                        $pdo->prepare("UPDATE notification_outbox SET status = 'failed', error_reason = ?, updated_at = NOW() WHERE id = ?")
                            ->execute([$rawError, $itemId]);
                        $result['failed']++;
                    } else {
                        // Gagal koneksi/transient error: tentukan retry dengan exponential backoff (maks 3x)
                        self::recordFailureStatus();

                        if ($attempts < 3) {
                            // Backoff: Percobaan 1: 2 menit, Percobaan 2: 10 menit
                            $backoffMinutes = match ($attempts) {
                                1       => 2,
                                2       => 10,
                                default => 30,
                            };

                            $pdo->prepare("
                                UPDATE notification_outbox 
                                SET status = 'pending', 
                                    next_try_at = DATE_ADD(NOW(), INTERVAL {$backoffMinutes} MINUTE), 
                                    error_reason = ?, 
                                    updated_at = NOW() 
                                WHERE id = ?
                            ")->execute([$rawError, $itemId]);

                            $result['postponed']++;
                            $result['skipped']++;
                        } else {
                            // Mencapai batas maksimal 3x percobaan: status menjadi 'failed'
                            $pdo->prepare("UPDATE notification_outbox SET status = 'failed', error_reason = ?, updated_at = NOW() WHERE id = ?")
                                ->execute([$rawError, $itemId]);

                            $result['failed']++;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[NotificationOutboxService::processOutbox] Exception: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Catat pengiriman sukses dan reset consecutive failures
     */
    private static function recordSuccessStatus(): void
    {
        try {
            SettingService::set('notif_consecutive_failures', 0);
        } catch (Throwable) {
        }
    }

    /**
     * Catat kegagalan dan periksa apakah circuit breaker harus aktif
     */
    private static function recordFailureStatus(): void
    {
        try {
            $currentFailures = (int) SettingService::get('notif_consecutive_failures', 0);
            $newFailures = $currentFailures + 1;
            SettingService::set('notif_consecutive_failures', $newFailures);

            // Jika gagal 5 kali berturut-turut, picu circuit breaker
            if ($newFailures >= 5) {
                SettingService::set('notif_circuit_breaker_tripped', '1');
                error_log('[NotificationOutboxService] CIRCUIT BREAKER TRIPPED! 5 kegagalan berturut-turut pada pengiriman email.');

                // Pemicu Peringatan Sistem ke Admin: Worker Gagal Beruntun
                try {
                    \App\Services\NotificationService::notifySystemAlert(
                        'circuit_breaker',
                        "Worker pengiriman email mengalami 5 kegagalan berturut-turut. Circuit Breaker telah aktif otomatis untuk menghentikan pengiriman sementara."
                    );
                } catch (Throwable) {
                }
            }
        } catch (Throwable) {
        }
    }

    /**
     * Reset circuit breaker (oleh Admin)
     */
    public static function resetCircuitBreaker(): void
    {
        try {
            SettingService::set('notif_circuit_breaker_tripped', '0');
            SettingService::set('notif_consecutive_failures', 0);
        } catch (Throwable $e) {
            error_log('[NotificationOutboxService::resetCircuitBreaker] Error: ' . $e->getMessage());
        }
    }

    /**
     * Kirim ulang item outbox yang gagal atau dibatalkan
     */
    public static function retryItem(int $id): bool
    {
        try {
            $pdo = Database::getInstance()->getPdo();
            $stmt = $pdo->prepare("
                UPDATE notification_outbox 
                SET status = 'pending', attempts = 0, next_try_at = NOW(), error_reason = NULL, updated_at = NOW() 
                WHERE id = ? AND status IN ('failed', 'cancelled')
            ");
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Dapatkan ringkasan statistik outbox untuk Dashboard Admin
     */
    public static function getStats(): array
    {
        try {
            $pdo = Database::getInstance()->getPdo();

            $pending = (int) $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'pending'")->fetchColumn();
            $sent    = (int) $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'sent'")->fetchColumn();
            $failed  = (int) $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'failed'")->fetchColumn();
            $cancelled = (int) $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'cancelled'")->fetchColumn();

            $sentToday = (int) $pdo->query("SELECT COUNT(*) FROM notification_outbox WHERE status = 'sent' AND DATE(sent_at) = CURDATE()")->fetchColumn();
            $dailyLimit = (int) SettingService::get('notif_daily_limit', 100);

            $circuitBreaker = (bool) SettingService::get('notif_circuit_breaker_tripped', false);
            $consecutiveFailures = (int) SettingService::get('notif_consecutive_failures', 0);
            $skipped = NotificationDecisionService::countSkipped();

            return [
                'pending'              => $pending,
                'sent'                 => $sent,
                'failed'               => $failed,
                'cancelled'            => $cancelled,
                'skipped'              => $skipped,
                'sent_today'           => $sentToday,
                'daily_limit'          => $dailyLimit,
                'circuit_breaker'      => $circuitBreaker,
                'consecutive_failures' => $consecutiveFailures,
            ];
        } catch (Throwable) {
            return [
                'pending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0,
                'skipped' => 0, 'sent_today' => 0, 'daily_limit' => 100,
                'circuit_breaker' => false, 'consecutive_failures' => 0,
            ];
        }
    }
}
