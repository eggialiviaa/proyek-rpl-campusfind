<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationModel;
use Throwable;

/**
 * NotificationService — Layanan notifikasi in-app proaktif
 *
 * Bertanggung jawab membuat dan mendistribusikan notifikasi internal secara aman:
 * - Bebas biaya & tanpa dependensi eksternal pihak ketiga
 * - Tidak mengekspos data privat / bukti sensitif ke payload notifikasi
 * - Terintegrasi dengan alur klaim, pesan chat, dan verifikasi barang temuan
 */
class NotificationService
{
    private static ?NotificationModel $model = null;
    private static bool $chatHookRegistered = false;
    private static array $processedMessageIds = [];

    private static function getModel(): NotificationModel
    {
        if (self::$model === null) {
            self::$model = new NotificationModel();
        }
        return self::$model;
    }

    /**
     * Daftarkan hook event listener otomatis (misal dari ChatService)
     */
    public static function bootstrap(): void
    {
        if (self::$chatHookRegistered) {
            return;
        }

        ChatService::registerHook('chat.message_sent', function (array $payload) {
            self::notifyNewChatMessage($payload);
        });

        self::$chatHookRegistered = true;
    }

    /**
     * Kirim notifikasi in-app ke seorang pengguna
     *
     * @param int    $userId Target penerima
     * @param string $type   Tipe notifikasi
     * @param string $title  Judul aman
     * @param string $message Ringkasan teks aman (TIDAK BOLEH MEMUAT BUKTI PRIVAT)
     * @param string $url    URL rujukan ke halaman berotorisasi
     * @param array  $extra  Data pembantu tambahan (icon, entity_id, badge_class)
     * @return int|null ID notifikasi yang dibuat
     */
    public static function send(
        int $userId,
        string $type,
        string $title,
        string $message,
        string $url,
        array $extra = []
    ): ?int {
        try {
            // Simpan teks MENTAH tervalidasi (escape dilakukan satu kali saat render output sesuai konteks)
            $safeTitle = trim((string)$title);
            $safeMessage = trim((string)$message);

            $payload = [
                'title'       => $safeTitle,
                'message'     => $safeMessage,
                'url'         => $url,
                'action_text' => $extra['action_text'] ?? null,
                'icon'        => $extra['icon'] ?? 'bi-bell-fill',
                'badge_class' => $extra['badge_class'] ?? 'primary',
                'entity_type' => $extra['entity_type'] ?? null,
                'entity_id'   => $extra['entity_id'] ?? null,
                'message_id'  => $extra['message_id'] ?? null,
            ];

            $notifId = self::getModel()->createNotification($userId, $type, $payload);

            // Integrasi Outbox Notifikasi Email Eksternal (Prompt 20)
            // Alur utama in-app TIDAK BOLEH menunggu atau gagal karena pengiriman email
            try {
                \App\Services\Notifications\NotificationOutboxService::enqueueFromInApp($userId, $type, $payload);
            } catch (Throwable $outboxEx) {
                error_log('[NotificationService::send] Gagal menambahkan ke outbox email: ' . $outboxEx->getMessage());
            }

            return $notifId;
        } catch (Throwable $e) {
            error_log('[NotificationService::send] Gagal mengirim notifikasi: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pemicu: Status Klaim Berubah
     */
    public static function notifyClaimStatusChanged(array $claim, string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $userId = (int) ($claim['claimant_user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $itemTitle = !empty($claim['item_title']) ? $claim['item_title'] : 'Barang Temuan';
        $claimNumber = $claim['claim_number'] ?? ('#' . ($claim['id'] ?? ''));

        $config = match ($newStatus) {
            'under_review' => [
                'title'       => 'Klaim Anda Sedang Diperiksa',
                'message'     => "Petugas sedang memeriksa berkas pengajuan klaim {$claimNumber} untuk {$itemTitle}.",
                'icon'        => 'bi-search',
                'badge_class' => 'info',
            ],
            'need_more_info' => [
                'title'       => 'Informasi Tambahan Diperlukan',
                'message'     => "Petugas memerlukan bukti tambahan untuk verifikasi klaim {$claimNumber}. Silakan buka detail klaim atau hubungi petugas via chat.",
                'icon'        => 'bi-question-diamond-fill',
                'badge_class' => 'warning',
            ],
            'approved' => [
                'title'       => 'Klaim Kepemilikan Disetujui!',
                'message'     => "Selamat! Klaim {$claimNumber} untuk {$itemTitle} telah disetujui. Silakan koordinasikan pengambilan barang dengan Petugas.",
                'icon'        => 'bi-check-circle-fill',
                'badge_class' => 'success',
            ],
            'rejected' => [
                'title'       => 'Status Pengajuan Klaim',
                'message'     => "Pengajuan klaim {$claimNumber} untuk {$itemTitle} belum dapat disetujui oleh Petugas.",
                'icon'        => 'bi-x-circle-fill',
                'badge_class' => 'danger',
            ],
            'completed' => [
                'title'       => 'Serah Terima Barang Selesai',
                'message'     => "Barang {$itemTitle} pada klaim {$claimNumber} telah diserahkan kepada Anda. Terima kasih!",
                'icon'        => 'bi-box2-heart-fill',
                'badge_class' => 'success',
            ],
            'closed_duplicate' => [
                'title'       => 'Klaim Ditutup Otomatis',
                'message'     => "Klaim {$claimNumber} untuk {$itemTitle} ditutup otomatis karena item telah disetujui untuk pemilik yang sah.",
                'icon'        => 'bi-shield-slash',
                'badge_class' => 'secondary',
            ],
            default => [
                'title'       => 'Pembaruan Status Klaim',
                'message'     => "Status klaim {$claimNumber} kini telah diperbarui.",
                'icon'        => 'bi-arrow-repeat',
                'badge_class' => 'primary',
            ],
        };

        $claimId = (int) ($claim['id'] ?? 0);
        self::send(
            $userId,
            'claim_status_changed',
            $config['title'],
            $config['message'],
            "/claims/{$claimId}",
            [
                'icon'        => $config['icon'],
                'badge_class' => $config['badge_class'],
                'entity_type' => 'claim',
                'entity_id'   => $claimId,
            ]
        );
    }

    /**
     * Pemicu: Pesan Chat Baru
     */
    public static function notifyNewChatMessage(array $payload): void
    {
        $recipients = $payload['recipient_user_ids'] ?? [];
        if (empty($recipients)) {
            \App\Services\Notifications\NotificationDecisionService::record(
                'new_chat_message',
                null,
                null,
                'skipped',
                'dilewati: tidak ada penerima pihak lawan',
                'email'
            );
            return;
        }

        $senderName = $payload['sender_name'] ?? 'Petugas';
        $preview = mb_substr($payload['message_preview'] ?? 'Pesan baru masuk', 0, 80, 'UTF-8');
        $roomId = (int) ($payload['room_id'] ?? 0);
        $messageId = (int) ($payload['message_id'] ?? 0);
        $url = "/chats/{$roomId}";

        // Mencegah pemanggilan ganda jika hook dan service call terpanggil bersamaan
        if ($messageId > 0) {
            if (isset(self::$processedMessageIds[$messageId])) {
                return;
            }
            self::$processedMessageIds[$messageId] = true;
        }

        foreach ($recipients as $recipientId) {
            $recipientId = (int) $recipientId;
            if ($recipientId <= 0 || $recipientId === (int) ($payload['sender_user_id'] ?? 0)) {
                continue;
            }

            self::send(
                $recipientId,
                'new_chat_message',
                "Pesan Baru dari {$senderName}",
                "\"{$preview}\"",
                $url,
                [
                    'icon'        => 'bi-chat-dots-fill',
                    'badge_class' => 'primary',
                    'entity_type' => 'chat_room',
                    'entity_id'   => $roomId,
                    'message_id'  => $messageId,
                ]
            );
        }
    }

    /**
     * Pemicu: Barang Temuan Diterima di Lost & Found Center
     */
    public static function notifyFoundReportReceived(array $foundReport, ?array $item = null): void
    {
        $userId = (int) ($foundReport['reporter_user_id'] ?? $foundReport['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $reportNumber = $foundReport['report_number'] ?? '';
        $title = $foundReport['title'] ?? 'Barang Temuan';
        $itemCode = $item['item_code'] ?? '';

        $message = "Barang temuan yang Anda laporkan ({$title}) telah diterima dan disimpan di Lost & Found Center kampus.";
        if (!empty($itemCode)) {
            $message .= " Kode Inventaris: {$itemCode}.";
        }

        $reportId = (int) ($foundReport['id'] ?? 0);

        self::send(
            $userId,
            'found_item_received',
            'Barang Temuan Telah Diterima Petugas',
            $message,
            "/reports/found/{$reportId}",
            [
                'icon'        => 'bi-box-seam-fill',
                'badge_class' => 'success',
                'entity_type' => 'found_report',
                'entity_id'   => $reportId,
            ]
        );
    }

    /**
     * Pemicu: Barang Temuan Diterima & Disimpan Petugas (Alias pemanggilan FoundReportModel)
     */
    public static function notifyFoundItemReceived(array $foundReport, string $itemCode = '', string $storageName = ''): void
    {
        $userId = (int) ($foundReport['reporter_user_id'] ?? $foundReport['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $title = $foundReport['title'] ?? 'Barang Temuan';
        $storage = !empty($storageName) ? $storageName : 'Lost & Found Center';

        $message = "Barang temuan yang Anda laporkan ({$title}) telah diterima dan disimpan di {$storage}.";
        if (!empty($itemCode)) {
            $message .= " Kode Inventaris: {$itemCode}.";
        }

        $reportId = (int) ($foundReport['id'] ?? 0);

        self::send(
            $userId,
            'found_item_received',
            'Barang Temuan Telah Diterima Petugas',
            $message,
            "/reports/found/{$reportId}",
            [
                'icon'        => 'bi-box-seam-fill',
                'badge_class' => 'success',
                'entity_type' => 'found_report',
                'entity_id'   => $reportId,
            ]
        );
    }

    /**
     * Pemicu: Kandidat Kecocokan Barang Ditemukan (TASK 11)
     */
    public static function notifyMatchCandidateFound(int $lostReportId, int $userId, string $foundItemTitle, float $score): void
    {
        if ($userId <= 0) {
            return;
        }

        $percent = round($score);
        self::send(
            $userId,
            'match_candidate_found',
            'Kemungkinan Barang Cocok Ditemukan',
            "Sistem menemukan barang temuan ({$foundItemTitle}) dengan indikasi kecocokan {$percent}% terhadap laporan kehilangan Anda.",
            "/ai-match/lost/{$lostReportId}",
            [
                'icon'        => 'bi-stars',
                'badge_class' => 'purple',
                'entity_type' => 'match_candidate',
                'entity_id'   => $lostReportId,
                'action_text' => 'Bandingkan Barang',
            ]
        );
    }

    /**
     * Pemicu: Status Laporan Kehilangan Berubah (Prompt 20D)
     */
     public static function notifyLostReportStatusChanged(array $lostReport, string $oldStatus, string $newStatus): void
     {
         if ($oldStatus === $newStatus) {
             return;
         }

         $userId = (int) ($lostReport['reporter_user_id'] ?? 0);
         if ($userId <= 0) {
             return;
         }

         $title = $lostReport['title'] ?? 'Barang Hilang';
         $reportNumber = $lostReport['report_number'] ?? ('#' . ($lostReport['id'] ?? ''));

         $statusText = match ($newStatus) {
             'published'    => 'telah diverifikasi dan dipublikasikan',
             'investigating'=> 'sedang diperiksa dan ditindaklanjuti oleh Petugas',
             'resolved'     => 'telah diselesaikan',
             'cancelled'    => 'telah dibatalkan',
             default        => "telah diperbarui menjadi {$newStatus}",
         };

         $reportId = (int) ($lostReport['id'] ?? 0);

         self::send(
             $userId,
             'lost_report_status_changed',
             'Pembaruan Laporan Kehilangan',
             "Laporan kehilangan {$reportNumber} ({$title}) {$statusText}.",
             "/reports/lost/{$reportId}",
             [
                 'icon'        => 'bi-info-circle-fill',
                 'badge_class' => 'info',
                 'entity_type' => 'lost_report',
                 'entity_id'   => $reportId,
             ]
         );
     }

    /**
     * Pemicu: Status Laporan Temuan Berubah (Prompt 20D)
     */
     public static function notifyFoundReportStatusChanged(array $foundReport, string $oldStatus, string $newStatus): void
     {
         if ($oldStatus === $newStatus) {
             return;
         }

         $userId = (int) ($foundReport['reporter_user_id'] ?? 0);
         if ($userId <= 0) {
             return;
         }

         $title = $foundReport['title'] ?? 'Barang Temuan';
         $reportNumber = $foundReport['report_number'] ?? ('#' . ($foundReport['id'] ?? ''));

         $statusText = match ($newStatus) {
             'pending_handover' => 'menunggu penyerahan fisik ke Lost & Found Center',
             'verified'         => 'telah diverifikasi oleh Petugas',
             'claimed'          => 'sedang dalam proses verifikasi klaim kepemilikan',
             'completed'        => 'telah berhasil diserahkan kepada pemilik yang sah',
             default            => "telah diperbarui menjadi {$newStatus}",
         };

         $reportId = (int) ($foundReport['id'] ?? 0);

         self::send(
             $userId,
             'found_report_status_changed',
             'Pembaruan Laporan Temuan',
             "Laporan barang temuan {$reportNumber} ({$title}) {$statusText}.",
             "/reports/found/{$reportId}",
             [
                 'icon'        => 'bi-check2-circle',
                 'badge_class' => 'primary',
                 'entity_type' => 'found_report',
                 'entity_id'   => $reportId,
             ]
         );

         // Notifikasi ke Petugas jika pelapor menandai siap serah terima fisik ke Posko
         if ($newStatus === 'pending_handover') {
             $petugasList = self::getPetugasUsers();
             if (empty($petugasList)) {
                 \App\Services\Notifications\NotificationDecisionService::record(
                     'new_found_report',
                     null,
                     null,
                     'skipped',
                     'dilewati: tidak ada penerima (Petugas tidak ditemukan)',
                     'email'
                 );
             } else {
                 foreach ($petugasList as $p) {
                     $petugasId = (int) $p['id'];
                     self::send(
                         $petugasId,
                         'new_found_report',
                         'Barang Temuan Siap Diserahkan ke Posko',
                         "Pelapor telah menandai siap menyerahkan fisik barang temuan {$title} ({$reportNumber}) ke Lost & Found Center.",
                         "/reports/manage?tab=found&status=pending_handover",
                         [
                             'icon'        => 'bi-box-arrow-in-down',
                             'badge_class' => 'warning',
                             'entity_type' => 'found_report',
                             'entity_id'   => $reportId,
                         ]
                     );
                 }
             }
         }
     }

    /**
     * Dapatkan daftar akun Petugas aktif untuk pengiriman notifikasi
     */
    public static function getPetugasUsers(): array
    {
        try {
            $pdo = \Core\Database::getInstance()->getPdo();
            $stmt = $pdo->query("
                SELECT u.id, u.name, u.email, u.email_verified_at, u.email_notifications_enabled
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE r.name = 'petugas' AND u.is_active = 1
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[NotificationService::getPetugasUsers] Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Dapatkan daftar akun Admin aktif untuk pengiriman peringatan sistem
     */
    public static function getAdminUsers(): array
    {
        try {
            $pdo = \Core\Database::getInstance()->getPdo();
            $stmt = $pdo->query("
                SELECT u.id, u.name, u.email, u.email_verified_at, u.email_notifications_enabled
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE r.name = 'admin' AND u.is_active = 1
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[NotificationService::getAdminUsers] Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Pemicu: Klaim Baru Diajukan → Ditujukan ke Petugas
     */
    public static function notifyNewClaimSubmitted(array $claim): void
    {
        $petugasList = self::getPetugasUsers();
        if (empty($petugasList)) {
            \App\Services\Notifications\NotificationDecisionService::record(
                'new_claim_submitted',
                null,
                null,
                'skipped',
                'dilewati: tidak ada penerima (Petugas tidak ditemukan)',
                'email'
            );
            return;
        }

        $claimId = (int) ($claim['id'] ?? 0);
        $claimNumber = $claim['claim_number'] ?? ('#' . $claimId);
        $itemTitle = !empty($claim['item_title']) ? $claim['item_title'] : 'Barang Temuan';

        foreach ($petugasList as $p) {
            $petugasId = (int) $p['id'];
            self::send(
                $petugasId,
                'new_claim_submitted',
                'Pengajuan Klaim Baru',
                "Klaim baru diajukan untuk item {$itemTitle} ({$claimNumber}). Memerlukan peninjauan berkas bukti.",
                "/petugas/claims",
                [
                    'icon'        => 'bi-file-earmark-check-fill',
                    'badge_class' => 'warning',
                    'entity_type' => 'claim',
                    'entity_id'   => $claimId,
                ]
            );
        }
    }

    /**
     * Pemicu: Laporan Temuan Baru Menunggu Penyerahan Fisik → Ditujukan ke Petugas
     */
    public static function notifyNewFoundReportPending(array $foundReport): void
    {
        $petugasList = self::getPetugasUsers();
        if (empty($petugasList)) {
            \App\Services\Notifications\NotificationDecisionService::record(
                'new_found_report',
                null,
                null,
                'skipped',
                'dilewati: tidak ada penerima (Petugas tidak ditemukan)',
                'email'
            );
            return;
        }

        $reportId = (int) ($foundReport['id'] ?? 0);
        $reportNumber = $foundReport['report_number'] ?? ('#' . $reportId);
        $title = $foundReport['title'] ?? 'Barang Temuan';

        foreach ($petugasList as $p) {
            $petugasId = (int) $p['id'];
            self::send(
                $petugasId,
                'new_found_report',
                'Laporan Temuan Baru Menunggu Penyerahan',
                "Laporan barang temuan baru diterbitkan: {$title} ({$reportNumber}). Menunggu serah terima fisik di Lost & Found Center.",
                "/reports/found/{$reportId}",
                [
                    'icon'        => 'bi-box-arrow-in-down',
                    'badge_class' => 'info',
                    'entity_type' => 'found_report',
                    'entity_id'   => $reportId,
                ]
            );
        }
    }

    /**
     * Pemicu: Laporan Kehilangan Baru Diterbitkan → Petugas (In-App selalu; Email opsional lewat pengaturan)
     */
    public static function notifyNewLostReportCreated(array $lostReport): void
    {
        $petugasList = self::getPetugasUsers();
        if (empty($petugasList)) {
            \App\Services\Notifications\NotificationDecisionService::record(
                'new_lost_report',
                null,
                null,
                'skipped',
                'dilewati: tidak ada penerima (Petugas tidak ditemukan)',
                'email'
            );
            return;
        }

        $reportId = (int) ($lostReport['id'] ?? 0);
        $reportNumber = $lostReport['report_number'] ?? ('#' . $reportId);
        $title = $lostReport['title'] ?? 'Barang Hilang';

        foreach ($petugasList as $p) {
            $petugasId = (int) $p['id'];
            self::send(
                $petugasId,
                'new_lost_report',
                'Laporan Kehilangan Baru',
                "Laporan kehilangan baru diterbitkan: {$title} ({$reportNumber}).",
                "/reports/lost/{$reportId}",
                [
                    'icon'        => 'bi-search',
                    'badge_class' => 'secondary',
                    'entity_type' => 'lost_report',
                    'entity_id'   => $reportId,
                ]
            );
        }
    }

    /**
     * Pemicu: Peringatan Sistem (Worker Gagal Beruntun / Kuota Habis) → Ditujukan ke Admin
     */
    public static function notifySystemAlert(string $alertType, string $alertMessage): void
    {
        $adminList = self::getAdminUsers();
        if (empty($adminList)) {
            \App\Services\Notifications\NotificationDecisionService::record(
                'system_alert',
                null,
                null,
                'skipped',
                'dilewati: tidak ada penerima (Admin tidak ditemukan)',
                'email'
            );
            return;
        }

        $templateKey = ($alertType === 'quota_exceeded') ? 'system_alert_quota' : 'system_alert_failure';
        $title = ($alertType === 'quota_exceeded') ? 'Peringatan: Kuota Harian Email Habis' : 'Peringatan: Worker Gagal Beruntun';

        foreach ($adminList as $admin) {
            $adminId = (int) $admin['id'];
            self::send(
                $adminId,
                'system_alert',
                $title,
                $alertMessage,
                '/admin/notification-settings',
                [
                    'icon'           => 'bi-exclamation-triangle-fill',
                    'badge_class'    => 'danger',
                    'entity_type'    => 'system_alert',
                    'entity_id'      => 0,
                    'alert_template' => $templateKey,
                ]
            );
        }
    }
}

// Inisialisasi otomatis hook saat file dimuat
NotificationService::bootstrap();
