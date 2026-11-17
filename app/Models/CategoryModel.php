<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;

/**
 * CategoryModel — Pengelolaan data kategori barang hilang & temuan
 */
class CategoryModel extends BaseModel
{
    /**
     * Ambil semua kategori aktif (untuk dropdown form user)
     */
    public function getActiveCategories(): array
    {
        $sql = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Ambil semua kategori beserta statistik jumlah laporan hilang & temuan (untuk Admin)
     */
    public function getAllWithCounts(): array
    {
        $sql = "
            SELECT c.*,
                   c.slug AS code,
                   (SELECT COUNT(*) FROM lost_reports lr WHERE lr.category_id = c.id) AS lost_count,
                   (SELECT COUNT(*) FROM found_reports fr WHERE fr.category_id = c.id) AS found_count
            FROM categories c
            ORDER BY c.name ASC
        ";
        return $this->fetchAll($sql);
    }

    /**
     * Hitung total kategori yang berstatus aktif
     */
    public function countActive(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM categories WHERE is_active = 1";
        $row = $this->fetchOne($sql);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Cari kategori berdasarkan ID
     */
    public function findById(int $id): array|false
    {
        $sql = "SELECT * FROM categories WHERE id = ? LIMIT 1";
        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Cari kategori berdasarkan nama yang dinormalisasi (case-insensitive & trimmed)
     */
    public function findByNameNormalized(string $name, ?int $excludeId = null): ?array
    {
        $normalized = strtolower(trim($name));
        if ($excludeId !== null) {
            $sql = "SELECT * FROM categories WHERE LOWER(TRIM(name)) = ? AND id != ? LIMIT 1";
            $res = $this->fetchOne($sql, [$normalized, $excludeId]);
        } else {
            $sql = "SELECT * FROM categories WHERE LOWER(TRIM(name)) = ? LIMIT 1";
            $res = $this->fetchOne($sql, [$normalized]);
        }
        return $res ?: null;
    }

    /**
     * Buat kategori baru
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO categories (name, slug, icon, description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ";

        $name = trim($data['name']);
        $slugRaw = !empty($data['slug']) ? trim($data['slug']) : (!empty($data['code']) ? trim($data['code']) : '');
        if ($slugRaw === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
            $slug = trim($slug, '_');
        } else {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $slugRaw));
        }

        $icon = !empty($data['icon']) ? trim($data['icon']) : 'bi-tag';
        $desc = !empty($data['description']) ? trim($data['description']) : null;

        $this->query($sql, [$name, $slug, $icon, $desc]);
        return (int) $this->lastInsertId();
    }

    /**
     * Update data kategori
     */
    public function updateCategory(int $id, array $data): bool
    {
        $sql = "
            UPDATE categories
            SET name = ?, slug = ?, icon = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ";

        $name = trim($data['name']);
        $slugRaw = !empty($data['slug']) ? trim($data['slug']) : (!empty($data['code']) ? trim($data['code']) : '');
        if ($slugRaw === '') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
            $slug = trim($slug, '_');
        } else {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $slugRaw));
        }

        $icon = !empty($data['icon']) ? trim($data['icon']) : 'bi-tag';
        $desc = !empty($data['description']) ? trim($data['description']) : null;

        return $this->query($sql, [$name, $slug, $icon, $desc, $id])->rowCount() >= 0;
    }

    /**
     * Toggle status aktif/nonaktif kategori
     */
    public function toggleStatus(int $id): bool
    {
        $sql = "UPDATE categories SET is_active = IF(is_active = 1, 0, 1), updated_at = NOW() WHERE id = ?";
        return $this->query($sql, [$id])->rowCount() > 0;
    }
}
