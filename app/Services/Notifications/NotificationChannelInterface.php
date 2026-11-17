<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * NotificationChannelInterface — Kontrak untuk Saluran Notifikasi Eksternal (Prompt 20)
 *
 * Memungkinkan arsitektur modular di mana saluran baru (misal SMS/WhatsApp di masa depan)
 * dapat ditambahkan tanpa merombak alur outbox worker.
 */
interface NotificationChannelInterface
{
    /**
     * Dapatkan nama unik saluran (misal: 'email')
     */
    public function getName(): string;

    /**
     * Kirim pesan berdasarkan data baris outbox
     *
     * @param array $outboxItem Baris dari tabel `notification_outbox`
     * @return array{success: bool, message_id?: ?string, error?: ?string, blocked?: bool}
     */
    public function send(array $outboxItem): array;

    /**
     * Uji koneksi ke provider eksternal (misal SMTP handshake)
     *
     * @param array|null $configOverride Konfigurasi sementara untuk pengujian live
     * @return array{success: bool, message: string, details?: array}
     */
    public function testConnection(?array $configOverride = null): array;
}
