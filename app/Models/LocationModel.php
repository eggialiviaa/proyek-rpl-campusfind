<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;

/**
 * LocationModel — Pengelolaan data lokasi area kampus
 */
class LocationModel extends BaseModel
{
    /**
     * Ambil semua lokasi aktif
     */
    public function getActiveLocations(): array
    {
        $sql = "SELECT * FROM locations WHERE is_active = 1 ORDER BY name ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Ambil semua lokasi beserta statistik jumlah laporan hilang & temuan (untuk Admin)
     */
    public function getAllWithCounts(): array
    {
        $sql = "
            SELECT l.*,
                   (SELECT COUNT(*) FROM lost_reports lr WHERE lr.last_seen_location_id = l.id) AS lost_count,
                   (SELECT COUNT(*) FROM found_reports fr WHERE fr.location_id = l.id) AS found_count
            FROM locations l
            ORDER BY l.name ASC
        ";
        return $this->fetchAll($sql);
    }

    /**
     * Cari lokasi berdasarkan ID
     */
    public function findById(int $id): array|false
    {
        $sql = "SELECT * FROM locations WHERE id = ? LIMIT 1";
        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Buat lokasi kampus baru
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO locations (name, code, building_name, description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ";

        $name = trim($data['name']);
        $code = !empty($data['code']) ? strtoupper(trim($data['code'])) : strtoupper(substr($name, 0, 5));
        $building = !empty($data['building_name']) ? trim($data['building_name']) : (!empty($data['campus_zone']) ? trim($data['campus_zone']) : 'Gedung Utama');
        $desc = !empty($data['description']) ? trim($data['description']) : null;

        $this->query($sql, [$name, $code, $building, $desc]);
        return (int) $this->lastInsertId();
    }

    /**
     * Update data lokasi kampus
     */
    public function updateLocation(int $id, array $data): bool
    {
        $sql = "
            UPDATE locations
            SET name = ?, code = ?, building_name = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ";

        $name = trim($data['name']);
        $code = !empty($data['code']) ? strtoupper(trim($data['code'])) : strtoupper(substr($name, 0, 5));
        $building = !empty($data['building_name']) ? trim($data['building_name']) : (!empty($data['campus_zone']) ? trim($data['campus_zone']) : 'Gedung Utama');
        $desc = !empty($data['description']) ? trim($data['description']) : null;

        return $this->query($sql, [$name, $code, $building, $desc, $id])->rowCount() >= 0;
    }

    /**
     * Toggle status aktif/nonaktif lokasi
     */
    public function toggleStatus(int $id): bool
    {
        $sql = "UPDATE locations SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?";
        return $this->query($sql, [$id])->rowCount() > 0;
    }
}
