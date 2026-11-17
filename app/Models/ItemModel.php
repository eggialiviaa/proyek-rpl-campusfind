<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use App\Services\StatusHistoryService;
use App\Services\AuditLogService;
use PDO;

/**
 * ItemModel — Pengelolaan data fisik kanonik barang di Lost & Found Center
 */
class ItemModel extends BaseModel
{
    /**
     * Cek apakah item_code sudah ada di database (validasi duplikat ketat)
     */
    public function isCodeExists(string $code, ?int $excludeId = null): bool
    {
        $code = strtoupper(trim($code));
        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM items WHERE item_code = ? AND id != ?";
            $stmt = $this->query($sql, [$code, $excludeId]);
        } else {
            $sql = "SELECT COUNT(*) FROM items WHERE item_code = ?";
            $stmt = $this->query($sql, [$code]);
        }

        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Buat record fisik item baru di gudang / inventory L&F Center
     *
     * @param array<string, mixed> $data
     * @return int ID item yang dibuat
     */
    public function createItem(array $data): int
    {
        $itemCode = strtoupper(trim((string) $data['item_code']));
        if ($this->isCodeExists($itemCode)) {
            throw new \InvalidArgumentException("Kode barang '{$itemCode}' sudah digunakan di inventaris! Gunakan kode unik.");
        }

        $sql = "
            INSERT INTO items (
                item_code,
                category_id,
                storage_location_id,
                verified_by_user_id,
                title,
                description,
                internal_notes,
                primary_image,
                additional_images,
                physical_condition,
                status,
                registered_at,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
        ";

        $status = $data['status'] ?? 'stored';
        $verifierId = !empty($data['verified_by_user_id']) ? (int) $data['verified_by_user_id'] : null;

        $this->query($sql, [
            $itemCode,
            $data['category_id'],
            $data['storage_location_id'] ?? null,
            $verifierId,
            $data['title'],
            $data['description'],
            $data['internal_notes'] ?? null,
            $data['primary_image'] ?? null,
            isset($data['additional_images']) ? json_encode($data['additional_images']) : null,
            $data['physical_condition'] ?? 'baik',
            $status,
        ]);

        $itemId = (int) $this->lastInsertId();

        // Catat initial status history
        StatusHistoryService::record(
            'item',
            $itemId,
            null,
            $status,
            $verifierId,
            'Barang fisik pertama kali diregistrasi ke gudang Center'
        );

        return $itemId;
    }

    /**
     * Update data fisik item
     */
    public function updateItem(int $id, array $data): bool
    {
        $item = $this->findDetail($id);
        if (!$item) {
            return false;
        }

        // Terminal protection: Barang yang sudah returned, donated, atau disposed tidak dapat diedit
        $terminalStatuses = ['returned', 'donated', 'disposed'];
        if (in_array($item['status'], $terminalStatuses, true)) {
            throw new \InvalidArgumentException("Barang fisik berstatus final ('{$item['status']}'). Data barang terkunci dari perubahan.");
        }

        // Cek juga jika ada klaim selesai yang mengunci item ini
        $stmtClaimCheck = $this->query("
            SELECT id, claim_number FROM claims 
            WHERE (item_id = ? OR (found_report_id IS NOT NULL AND found_report_id = ?))
              AND status = 'completed' AND handed_over_at IS NOT NULL
            LIMIT 1
        ", [$id, $item['found_report_id'] ?? 0]);
        $compClaim = $stmtClaimCheck->fetch(PDO::FETCH_ASSOC);
        if ($compClaim) {
            throw new \InvalidArgumentException("Barang fisik telah diserahterimakan pada klaim {$compClaim['claim_number']}. Lokasi rak tidak dapat diubah kembali.");
        }

        if (isset($data['item_code'])) {
            $code = strtoupper(trim((string) $data['item_code']));
            if ($this->isCodeExists($code, $id)) {
                throw new \InvalidArgumentException("Kode barang '{$code}' sudah ada di inventaris.");
            }
        } else {
            $code = $item['item_code'];
        }

        $sql = "
            UPDATE items SET
                item_code = ?,
                category_id = ?,
                storage_location_id = ?,
                title = ?,
                description = ?,
                internal_notes = ?,
                physical_condition = ?,
                primary_image = IFNULL(?, primary_image),
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->query($sql, [
            $code,
            $data['category_id'] ?? $item['category_id'],
            $data['storage_location_id'] ?? $item['storage_location_id'],
            $data['title'] ?? $item['title'],
            $data['description'] ?? $item['description'],
            $data['internal_notes'] ?? $item['internal_notes'],
            $data['physical_condition'] ?? $item['physical_condition'],
            $data['primary_image'] ?? null,
            $id,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * Ubah status fisik barang dan rekam jejaknya ke status_histories
     */
    public function updatePhysicalStatus(int $id, string $newStatus, int $changedByUserId, ?string $notes = null): bool
    {
        $allowedStatuses = [
            'pending_inspection',
            'in_inspection',
            'stored',
            'in_claim',
            'ready_for_handover',
            'returned',
            'donated',
            'disposed'
        ];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new \InvalidArgumentException("Status fisik '{$newStatus}' tidak valid.");
        }

        $item = $this->findDetail($id);
        if (!$item) {
            return false;
        }

        // Terminal protection: Barang yang sudah returned, donated, atau disposed terkunci dari perubahan
        $terminalStatuses = ['returned', 'donated', 'disposed'];
        if (in_array($item['status'], $terminalStatuses, true)) {
            throw new \InvalidArgumentException("Barang fisik berstatus final ('{$item['status']}'). Status fisik terkunci dan tidak dapat diubah kembali.");
        }

        // Cek juga jika ada klaim selesai yang mengunci item ini
        $stmtClaimCheck = $this->query("
            SELECT id, claim_number FROM claims 
            WHERE (item_id = ? OR (found_report_id IS NOT NULL AND found_report_id = ?))
              AND status = 'completed' AND handed_over_at IS NOT NULL
            LIMIT 1
        ", [$id, $item['found_report_id'] ?? 0]);
        $compClaim = $stmtClaimCheck->fetch(PDO::FETCH_ASSOC);
        if ($compClaim) {
            throw new \InvalidArgumentException("Barang fisik telah diserahterimakan pada klaim {$compClaim['claim_number']}. Status fisik tidak dapat diubah kembali.");
        }

        $oldStatus = $item['status'];
        if ($oldStatus === $newStatus) {
            return true;
        }

        $sql = "
            UPDATE items SET
                status = ?,
                returned_at = IF(? = 'returned', NOW(), returned_at),
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->query($sql, [$newStatus, $newStatus, $id]);
        if ($stmt->rowCount() >= 0) {
            StatusHistoryService::record(
                'item',
                $id,
                $oldStatus,
                $newStatus,
                $changedByUserId,
                $notes ?: "Status fisik barang diubah dari '{$oldStatus}' ke '{$newStatus}'"
            );

            AuditLogService::log('UPDATE_ITEM_STATUS', $changedByUserId, 'items', $id, [
                'item_code'  => $item['item_code'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Pindahkan barang ke lokasi rak penyimpanan lain
     */
    public function updateStorageLocation(int $id, int $storageLocationId, int $changedByUserId, ?string $notes = null): bool
    {
        $item = $this->findDetail($id);
        if (!$item) {
            return false;
        }

        // Terminal protection: Barang yang sudah returned, donated, atau disposed tidak dapat diedit
        $terminalStatuses = ['returned', 'donated', 'disposed'];
        if (in_array($item['status'], $terminalStatuses, true)) {
            throw new \InvalidArgumentException("Barang fisik berstatus final ('{$item['status']}'). Data barang terkunci dari perubahan.");
        }

        // Cek juga jika ada klaim selesai yang mengunci item ini
        $stmtClaimCheck = $this->query("
            SELECT id, claim_number FROM claims 
            WHERE (item_id = ? OR (found_report_id IS NOT NULL AND found_report_id = ?))
              AND status = 'completed' AND handed_over_at IS NOT NULL
            LIMIT 1
        ", [$id, $item['found_report_id'] ?? 0]);
        $compClaim = $stmtClaimCheck->fetch(PDO::FETCH_ASSOC);
        if ($compClaim) {
            throw new \InvalidArgumentException("Barang fisik telah diserahterimakan pada klaim {$compClaim['claim_number']}. Lokasi rak tidak dapat diubah kembali.");
        }

        $sql = "UPDATE items SET storage_location_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->query($sql, [$storageLocationId, $id]);

        if ($stmt->rowCount() >= 0) {
            AuditLogService::log('RELOCATE_ITEM_STORAGE', $changedByUserId, 'items', $id, [
                'item_code'   => $item['item_code'],
                'old_storage' => $item['storage_location_id'],
                'new_storage' => $storageLocationId,
                'notes'       => $notes,
            ]);
            return true;
        }

        return false;
    }

    /**
     * Ambil detail item beserta relasi kategori, lokasi penyimpanan, petugas verifikator, dan laporan temuan terkait
     */
    public function findDetail(int $id): array|false
    {
        $sql = "
            SELECT i.*,
                   c.name AS category_name,
                   c.slug AS category_slug,
                   sl.name AS storage_name,
                   sl.code AS storage_code,
                   sl.zone AS storage_zone,
                   u.name AS verifier_name,
                   u.email AS verifier_email,
                   fr.id AS found_report_id,
                   fr.report_number AS found_report_number,
                   fr.status AS found_report_status,
                   fr.reporter_user_id,
                   rep.name AS reporter_name,
                   rep.email AS reporter_email,
                   rep.phone AS reporter_phone,
                   cl.id AS claim_id,
                   cl.claim_number,
                   cl.status AS claim_status,
                   cl.handed_over_at,
                   cl.handover_notes,
                   claimant.name AS claimant_name
            FROM items i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
            LEFT JOIN users u ON i.verified_by_user_id = u.id
            LEFT JOIN found_reports fr ON fr.item_id = i.id
            LEFT JOIN users rep ON fr.reporter_user_id = rep.id
            LEFT JOIN claims cl ON (cl.item_id = i.id OR (cl.found_report_id IS NOT NULL AND cl.found_report_id = fr.id)) AND cl.status = 'completed'
            LEFT JOIN users claimant ON cl.claimant_user_id = claimant.id
            WHERE i.id = ?
            LIMIT 1
        ";

        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Ambil detail item berdasarkan kode unik
     */
    public function findByItemCode(string $code): array|false
    {
        $sql = "SELECT id FROM items WHERE item_code = ? LIMIT 1";
        $res = $this->fetchOne($sql, [strtoupper(trim($code))]);
        if ($res && isset($res['id'])) {
            return $this->findDetail((int) $res['id']);
        }
        return false;
    }

    /**
     * Ambil semua lokasi penyimpanan aktif di L&F Center
     */
    public function getStorageLocations(): array
    {
        $storageModel = new StorageLocationModel();
        return $storageModel->getAllWithCapacity();
    }

    /**
     * Historical non-posko statuses
     */
    public const HISTORICAL_STATUSES = ['returned', 'donated', 'disposed'];

    /**
     * Ambil daftar item di inventaris Center (Inventaris Aktif vs Riwayat Barang)
     *
     * @param int $limit
     * @param int $offset
     * @param string|null $status
     * @param int|null $storageLocationId
     * @param string|null $search
     * @param string $tab 'active' | 'history' | 'all'
     * @return array
     */
    public function getAllItems(
        int $limit = 20, 
        int $offset = 0, 
        ?string $status = null, 
        ?int $storageLocationId = null, 
        ?string $search = null,
        string $tab = 'active'
    ): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($status)) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        } elseif ($tab === 'active') {
            $activeStatuses = \App\Services\MetricDefinitionService::getPoskoItemStatuses();
            $placeholders = implode("', '", $activeStatuses);
            $where[] = "i.status IN ('{$placeholders}')";
        } elseif ($tab === 'history') {
            $histStatuses = self::HISTORICAL_STATUSES;
            $placeholders = implode("', '", $histStatuses);
            $where[] = "i.status IN ('{$placeholders}')";
        }

        if ($storageLocationId !== null && $storageLocationId > 0) {
            $where[] = 'i.storage_location_id = ?';
            $params[] = $storageLocationId;
        }

        if (!empty($search)) {
            $where[] = '(i.item_code LIKE ? OR i.title LIKE ? OR i.description LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);
        $orderClause = ($tab === 'history') 
            ? 'ORDER BY COALESCE(i.returned_at, i.updated_at) DESC, i.id DESC' 
            : 'ORDER BY i.id DESC';

        $sql = "
            SELECT i.*,
                   c.name AS category_name,
                   sl.name AS storage_name,
                   sl.code AS storage_code,
                   u.name AS verifier_name,
                   cl.id AS claim_id,
                   cl.claim_number,
                   cl.handed_over_at,
                   cl.handover_notes,
                   claimant.name AS receiver_name
            FROM items i
            JOIN categories c ON i.category_id = c.id
            LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
            LEFT JOIN users u ON i.verified_by_user_id = u.id
            LEFT JOIN claims cl ON (cl.item_id = i.id AND cl.status = 'completed')
            LEFT JOIN users claimant ON cl.claimant_user_id = claimant.id
            WHERE {$whereClause}
            {$orderClause}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total items untuk paginasi inventaris (berdasarkan tab aktif vs riwayat)
     *
     * @param string|null $status
     * @param int|null $storageLocationId
     * @param string|null $search
     * @param string $tab 'active' | 'history' | 'all'
     * @return int
     */
    public function countAllItems(
        ?string $status = null, 
        ?int $storageLocationId = null, 
        ?string $search = null,
        string $tab = 'active'
    ): int {
        $where = ['1=1'];
        $params = [];

        if (!empty($status)) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        } elseif ($tab === 'active') {
            $activeStatuses = \App\Services\MetricDefinitionService::getPoskoItemStatuses();
            $placeholders = implode("', '", $activeStatuses);
            $where[] = "i.status IN ('{$placeholders}')";
        } elseif ($tab === 'history') {
            $histStatuses = self::HISTORICAL_STATUSES;
            $placeholders = implode("', '", $histStatuses);
            $where[] = "i.status IN ('{$placeholders}')";
        }

        if ($storageLocationId !== null && $storageLocationId > 0) {
            $where[] = 'i.storage_location_id = ?';
            $params[] = $storageLocationId;
        }

        if (!empty($search)) {
            $where[] = '(i.item_code LIKE ? OR i.title LIKE ? OR i.description LIKE ?)';
            $term = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM items i WHERE {$whereClause}";
        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Generate kode barang unik kanonik: ITM-YYYYMMDD-XXXX
     */
    public function generateItemCode(): string
    {
        do {
            $prefix = 'ITM-' . date('Ymd') . '-';
            $random = strtoupper(bin2hex(random_bytes(3))); // 6 karakter hex
            $code   = $prefix . $random;
        } while ($this->isCodeExists($code));

        return $code;
    }

    /**
     * Label kondisi fisik yang user-friendly
     */
    public static function getConditionLabel(string $condition): string
    {
        return match ($condition) {
            'sangat_baik'  => 'Sangat Baik (Mulus / Berfungsi Normal)',
            'baik'         => 'Baik (Normal / Wajar)',
            'rusak_ringan' => 'Rusak Ringan (Lecet / Gores)',
            'rusak_berat'  => 'Rusak Berat (Pecah / Mati Total)',
            default        => ucfirst(str_replace('_', ' ', $condition)),
        };
    }

    /**
     * Label Status Fisik Barang (Physical Item Status) — Bahasa Indonesia
     */
    public static function getPhysicalStatusLabel(string $status): string
    {
        return match ($status) {
            'pending_inspection' => 'Menunggu Pemeriksaan',
            'in_inspection'      => 'Sedang Diperiksa',
            'stored'             => 'Tersimpan di Gudang',
            'in_claim'           => 'Dalam Proses Klaim',
            'ready_for_handover' => 'Siap Diserahkan ke Pemilik',
            'returned'           => 'Dikembalikan ke Pemilik',
            'donated'            => 'Didonasikan',
            'disposed'           => 'Dimusnahkan',
            'registered'         => 'Terdaftar',
            default              => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Label status bawaan
     */
    public static function getStatusLabel(string $status): string
    {
        return self::getPhysicalStatusLabel($status);
    }
}
