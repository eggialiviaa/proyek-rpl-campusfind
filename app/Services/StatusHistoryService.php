<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;

/**
 * StatusHistoryService — Layanan pencatatan jejak audit transisi status
 */
class StatusHistoryService
{
    /**
     * Catat perpindahan status entitas ke tabel status_histories
     *
     * @param string $entityType 'lost_report' | 'found_report' | 'item' | 'claim'
     * @param int $entityId ID entitas terkait
     * @param string|null $oldStatus Status lama
     * @param string $newStatus Status baru
     * @param int|null $changedByUserId ID user yang melakukan perubahan
     * @param string|null $notes Catatan tambahan perubahan status
     * @return int ID history yang tercatat
     */
    public static function record(
        string $entityType,
        int $entityId,
        ?string $oldStatus,
        string $newStatus,
        ?int $changedByUserId = null,
        ?string $notes = null
    ): int {
        $pdo = Database::getInstance()->getPdo();

        $sql = "
            INSERT INTO status_histories (
                entity_type,
                entity_id,
                old_status,
                new_status,
                changed_by_user_id,
                notes,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $entityType,
            $entityId,
            $oldStatus,
            $newStatus,
            $changedByUserId,
            $notes,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Ambil riwayat status untuk entitas tertentu
     */
    public static function getHistories(string $entityType, int $entityId): array
    {
        $pdo = Database::getInstance()->getPdo();

        $sql = "
            SELECT sh.*, u.name AS changed_by_name, u.email AS changed_by_email, r.name AS changed_by_role
            FROM status_histories sh
            LEFT JOIN users u ON sh.changed_by_user_id = u.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE sh.entity_type = ? AND sh.entity_id = ?
            ORDER BY sh.created_at ASC, sh.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$entityType, $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
