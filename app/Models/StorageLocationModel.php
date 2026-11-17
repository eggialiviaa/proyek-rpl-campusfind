<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use PDO;

/**
 * StorageLocationModel — Pengelolaan rak, lemari, dan zona penyimpanan fisik di Lost & Found Center
 */
class StorageLocationModel extends BaseModel
{
    /**
     * Ambil semua lokasi penyimpanan beserta statistik kapasitas dan jumlah isi saat ini
     */
    public function getAllWithCapacity(): array
    {
        $sql = "
            SELECT sl.*, 
                   COUNT(i.id) AS current_items_count,
                   (sl.capacity - COUNT(i.id)) AS remaining_capacity,
                   ROUND((COUNT(i.id) / sl.capacity) * 100, 1) AS usage_percentage
            FROM storage_locations sl
            LEFT JOIN items i ON sl.id = i.storage_location_id AND i.status IN ('stored', 'registered', 'in_inspection', 'in_claim', 'ready_for_handover')
            WHERE sl.is_active = 1
            GROUP BY sl.id
            ORDER BY sl.code ASC
        ";

        return $this->fetchAll($sql);
    }

    /**
     * Ambil detail lokasi penyimpanan berdasarkan ID
     */
    public function findById(int $id): array|false
    {
        $sql = "
            SELECT sl.*, 
                   COUNT(i.id) AS current_items_count,
                   (sl.capacity - COUNT(i.id)) AS remaining_capacity
            FROM storage_locations sl
            LEFT JOIN items i ON sl.id = i.storage_location_id AND i.status IN ('stored', 'registered', 'in_inspection', 'in_claim', 'ready_for_handover')
            WHERE sl.id = ?
            GROUP BY sl.id
            LIMIT 1
        ";

        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Ambil lokasi penyimpanan berdasarkan kode unik
     */
    public function findByCode(string $code): array|false
    {
        $sql = "SELECT * FROM storage_locations WHERE code = ? LIMIT 1";
        return $this->fetchOne($sql, [strtoupper(trim($code))]);
    }

    /**
     * Cek apakah kode lokasi penyimpanan sudah digunakan (validasi duplikasi)
     */
    public function isCodeExists(string $code, ?int $excludeId = null): bool
    {
        $code = strtoupper(trim($code));
        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM storage_locations WHERE code = ? AND id != ?";
            $stmt = $this->query($sql, [$code, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) FROM storage_locations WHERE code = ?";
            $stmt = $this->query($sql, [$code]);
        }

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Buat lokasi penyimpanan baru di L&F Center
     *
     * @param array<string, mixed> $data
     * @return int ID lokasi yang dibuat
     */
    public function create(array $data): int
    {
        $code = strtoupper(trim((string) $data['code']));
        if ($this->isCodeExists($code)) {
            throw new \InvalidArgumentException("Kode lokasi penyimpanan '{$code}' sudah ada! Gunakan kode yang unik.");
        }

        $sql = "
            INSERT INTO storage_locations (
                code,
                name,
                zone,
                capacity,
                description,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ";

        $this->query($sql, [
            $code,
            trim((string) $data['name']),
            !empty($data['zone']) ? trim((string) $data['zone']) : 'Zona Umum',
            max(1, (int) ($data['capacity'] ?? 50)),
            !empty($data['description']) ? trim((string) $data['description']) : null,
        ]);

        return (int) $this->lastInsertId();
    }

    /**
     * Update lokasi penyimpanan
     */
    public function updateLocation(int $id, array $data): bool
    {
        $code = strtoupper(trim((string) $data['code']));
        if ($this->isCodeExists($code, $id)) {
            throw new \InvalidArgumentException("Kode lokasi penyimpanan '{$code}' sudah digunakan oleh rak lain.");
        }

        $sql = "
            UPDATE storage_locations SET
                code = ?,
                name = ?,
                zone = ?,
                capacity = ?,
                description = ?,
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->query($sql, [
            $code,
            trim((string) $data['name']),
            !empty($data['zone']) ? trim((string) $data['zone']) : 'Zona Umum',
            max(1, (int) ($data['capacity'] ?? 50)),
            !empty($data['description']) ? trim((string) $data['description']) : null,
            $id,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * Toggle status aktif/nonaktif lokasi penyimpanan
     */
    public function toggleStatus(int $id): bool
    {
        $sql = "UPDATE storage_locations SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?";
        return $this->query($sql, [$id])->rowCount() > 0;
    }

    /**
     * Ambil daftar seluruh barang yang disimpan di lokasi/rak tertentu
     */
    public function getItemsInLocation(int $storageLocationId): array
    {
        $sql = "
            SELECT i.*, c.name AS category_name, u.name AS verifier_name
            FROM items i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN users u ON i.verified_by_user_id = u.id
            WHERE i.storage_location_id = ?
            ORDER BY i.registered_at DESC
        ";

        return $this->fetchAll($sql, [$storageLocationId]);
    }
}

