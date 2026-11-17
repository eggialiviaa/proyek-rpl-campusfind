<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * ChatService — Layanan pendukung Internal Chat
 *
 * Mengelola:
 * 1. Sanitasi pesan anti-XSS
 * 2. Hook / Event Dispatcher untuk integrasi modular in-app notification (Prompt 11)
 * 3. Audit trail aktivitas perpesanan
 */
class ChatService
{
    /**
     * @var array<string, array<callable>> Daftar event listeners
     */
    private static array $listeners = [];

    /**
     * Daftarkan pendengar (listener) untuk sebuah event
     * Contoh penggunaan di masa depan (Prompt 11):
     * ChatService::registerHook('chat.message_sent', function(array $payload) {
     *     NotificationService::send(...);
     * });
     */
    public static function registerHook(string $eventName, callable $callback): void
    {
        if (!isset(self::$listeners[$eventName])) {
            self::$listeners[$eventName] = [];
        }
        self::$listeners[$eventName][] = $callback;
    }

    /**
     * Pemicu hook event saat terjadi aktivitas chat
     */
    public static function triggerHook(string $eventName, array $payload): void
    {
        if (empty(self::$listeners[$eventName])) {
            return;
        }

        foreach (self::$listeners[$eventName] as $callback) {
            try {
                $callback($payload);
            } catch (Throwable $e) {
                error_log("[ChatService Hook Error] Event '{$eventName}': " . $e->getMessage());
            }
        }
    }

    /**
     * Sanitasi pesan teks anti-XSS
     * Memotong whitespace berlebih, mencegah null byte, dan melakukan encoding HTML aman.
     */
    public static function sanitizeMessage(string $message): string
    {
        // 1. Hilangkan null byte
        $message = str_replace(chr(0), '', $message);

        // 2. Normalisasi baris baru (CRLF -> LF)
        $message = str_replace(["\r\n", "\r"], "\n", $message);

        // 3. Trim whitespace
        $message = trim($message);

        // 4. Hapus potensi event handlers berbahaya (onerror, onclick, onload, dll) & javascript pseudo-protocol
        $message = preg_replace('/\bon\w+\s*=/i', 'x_attr=', $message);
        $message = preg_replace('/javascript\s*:/i', 'x_protocol:', $message);

        // 5. Simpan teks MENTAH tervalidasi (escape HTML dilakukan satu kali saat render output di view)
        return $message;
    }

    /**
     * Validasi panjang dan format pesan
     */
    public static function validateMessage(string $message): ?string
    {
        $len = mb_strlen(trim($message), 'UTF-8');
        if ($len === 0) {
            return 'Pesan tidak boleh kosong.';
        }
        if ($len > 2000) {
            return 'Pesan terlalu panjang (maksimal 2.000 karakter).';
        }
        return null;
    }

    /**
     * Catat pengiriman pesan baru dan picu event hook untuk Prompt 11
     */
    public static function onMessageSent(array $messageData, array $roomData, array $recipients): void
    {
        // 1. Picu event hook untuk Prompt 11 (in-app notification hook point)
        $payload = [
            'event'              => 'chat.message_sent',
            'message_id'         => $messageData['id'] ?? null,
            'room_id'            => $roomData['id'] ?? null,
            'room_code'          => $roomData['room_code'] ?? null,
            'room_type'          => $roomData['room_type'] ?? 'claim',
            'claim_id'           => $roomData['claim_id'] ?? null,
            'lost_report_id'     => $roomData['lost_report_id'] ?? null,
            'found_report_id'    => $roomData['found_report_id'] ?? null,
            'sender_user_id'     => $messageData['sender_user_id'] ?? null,
            'sender_name'        => $messageData['sender_name'] ?? 'User',
            'sender_role'        => $messageData['sender_role'] ?? 'civitas',
            'message_preview'    => mb_substr($messageData['message'] ?? '', 0, 100, 'UTF-8'),
            'recipient_user_ids' => $recipients,
            'created_at'         => $messageData['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        if (!empty(self::$hooks['chat.message_sent'])) {
            self::triggerHook('chat.message_sent', $payload);
        } else {
            try {
                \App\Services\NotificationService::notifyNewChatMessage($payload);
            } catch (\Throwable $notifEx) {
                error_log('[ChatService::onMessageSent] NotificationService error: ' . $notifEx->getMessage());
            }
        }

        // 2. Catat jejak audit log keamanan
        AuditLogService::log(
            'CHAT_MESSAGE_SENT',
            (int) ($messageData['sender_user_id'] ?? 0),
            'chat_rooms',
            (int) ($roomData['id'] ?? 0),
            [
                'room_code'  => $roomData['room_code'] ?? null,
                'message_id' => $messageData['id'] ?? null,
                'char_count' => mb_strlen($messageData['message'] ?? '', 'UTF-8'),
            ]
        );
    }
}
