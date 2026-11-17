<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use PDO;
use Throwable;

/**
 * NotificationModel — Pengelolaan data notifikasi in-app untuk pengguna
 */
class NotificationModel extends BaseModel
{
    protected string $table = 'notifications';
    protected PDO    $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->pdo;
    }

    /**
     * Buat notifikasi baru untuk pengguna tertentu
     *
     * @param int    $userId  Target user ID penerima
     * @param string $type    Tipe notifikasi (misal: claim_status_changed, new_chat_message)
     * @param array  $payload Informasi notifikasi (title, message, url, icon, dll)
     * @return int ID notifikasi baru
     */
    public function createNotification(int $userId, string $type, array $payload): int
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $sql = "
            INSERT INTO notifications (user_id, type, payload, is_read, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $type, $jsonPayload]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Ambil daftar notifikasi milik pengguna tertentu dengan paginasi & filter
     *
     * @param int  $userId
     * @param bool $unreadOnly
     * @param int  $limit
     * @param int  $offset
     * @return array
     */
    public function getUserNotifications(int $userId, bool $unreadOnly = false, int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT id, user_id, type, payload, is_read, read_at, created_at
            FROM notifications
            WHERE user_id = ?
        ";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        $sql .= " ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
        $params[] = (int) $limit;
        $params[] = (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        }

        return $rows;
    }

    /**
     * Hitung total notifikasi untuk paginasi
     */
    public function countUserNotifications(int $userId, bool $unreadOnly = false): int
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE user_id = ?";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil notifikasi terbaru untuk dropdown navbar (default: 5 teratas)
     */
    public function getRecentNotifications(int $userId, int $limit = 5): array
    {
        $sql = "
            SELECT id, user_id, type, payload, is_read, read_at, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, (int) $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        }

        return $rows;
    }

    /**
     * Dapatkan total notifikasi yang belum dibaca milik pengguna
     */
    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cari notifikasi spesifik dengan jaminan otorisasi kepemilikan
     */
    public function findUserNotification(int $notificationId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, type, payload, is_read, read_at, created_at
            FROM notifications
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$notificationId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        return $row;
    }

    /**
     * Cek apakah notifikasi ada di database (tanpa filter user, untuk mendeteksi apakah 404 atau 403)
     */
    public function exists(int $notificationId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Tandai sebuah notifikasi sebagai terbaca (hanya jika dimiliki oleh user_id yang sah)
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ? AND is_read = 0
        ");
        $stmt->execute([$notificationId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Tandai seluruh notifikasi yang belum dibaca milik pengguna sebagai terbaca
     */
    public function markAllAsRead(int $userId): int
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }
}
