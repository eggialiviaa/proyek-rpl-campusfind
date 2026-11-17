<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use App\Services\StatusHistoryService;
use App\Services\AuditLogService;
use PDO;

/**
 * FoundReportModel — Pengelolaan data laporan barang ditemukan (Found Item Report)
 */
class FoundReportModel extends BaseModel
{
    /**
     * Buat laporan penemuan barang baru
     *
     * @param array<string, mixed> $data
     * @return int ID laporan yang dibuat
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO found_reports (
                report_number,
                reporter_user_id,
                category_id,
                location_id,
                title,
                description,
                location_detail,
                item_condition,
                private_details,
                found_date,
                found_time,
                primary_image,
                status,
                is_handed_over_to_center,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $status = $data['status'] ?? 'published';
        $isHandedOver = (int) ($data['is_handed_over_to_center'] ?? 0);

        $this->query($sql, [
            $data['report_number'],
            $data['reporter_user_id'],
            $data['category_id'],
            $data['location_id'],
            $data['title'],
            $data['description'],
            $data['location_detail'] ?? null,
            $data['item_condition'] ?? 'baik',
            $data['private_details'] ?? null,
            $data['found_date'],
            $data['found_time'] ?? null,
            $data['primary_image'] ?? null,
            $status,
            $isHandedOver,
        ]);

        $reportId = (int) $this->lastInsertId();

        // Rekam initial status history
        StatusHistoryService::record(
            'found_report',
            $reportId,
            null,
            $status,
            (int) $data['reporter_user_id'],
            'Laporan penemuan baru dibuat oleh penemu'
        );

        return $reportId;
    }

    /**
     * Update data laporan penemuan (hanya boleh jika masih tahap awal / published)
     */
    public function updateReport(int $id, array $data): bool
    {
        $sql = "
            UPDATE found_reports SET
                category_id = ?,
                location_id = ?,
                title = ?,
                description = ?,
                location_detail = ?,
                item_condition = ?,
                private_details = ?,
                found_date = ?,
                found_time = ?,
                primary_image = IFNULL(?, primary_image),
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->query($sql, [
            $data['category_id'],
            $data['location_id'],
            $data['title'],
            $data['description'],
            $data['location_detail'] ?? null,
            $data['item_condition'] ?? 'baik',
            $data['private_details'] ?? null,
            $data['found_date'],
            $data['found_time'] ?? null,
            $data['primary_image'] ?? null,
            $id,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * Hapus laporan penemuan
     */
    public function deleteReport(int $id): bool
    {
        $sql = "DELETE FROM found_reports WHERE id = ?";
        return $this->execute($sql, [$id]) > 0;
    }

    /**
     * Ambil detail lengkap laporan penemuan beserta relasi
     */
    public function findDetail(int $id): array|false
    {
        $sql = "
            SELECT fr.*,
                   c.name AS category_name,
                   c.slug AS category_slug,
                   c.icon AS category_icon,
                   l.name AS location_name,
                   l.building_name,
                   u.name AS reporter_name,
                   u.email AS reporter_email,
                   u.phone AS reporter_phone,
                   u.identity_number AS reporter_identity,
                   i.item_code,
                   i.physical_condition AS item_physical_condition,
                   i.status AS item_status,
                   sl.name AS storage_name,
                   sl.code AS storage_code,
                   sl.zone AS storage_zone,
                   v.name AS verifier_name
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations l ON fr.location_id = l.id
            JOIN users u ON fr.reporter_user_id = u.id
            LEFT JOIN items i ON fr.item_id = i.id
            LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
            LEFT JOIN users v ON i.verified_by_user_id = v.id
            WHERE fr.id = ?
            LIMIT 1
        ";

        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Alias findById untuk konsistensi query
     */
    public function findById(int $id): array|false
    {
        return $this->findDetail($id);
    }

    /**
     * Ambil daftar laporan penemuan milik user tertentu
     */
    public function getUserReports(int $userId): array
    {
        $sql = "
            SELECT fr.*,
                   c.name AS category_name,
                   l.name AS location_name,
                   i.item_code
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations l ON fr.location_id = l.id
            LEFT JOIN items i ON fr.item_id = i.id
            WHERE fr.reporter_user_id = ?
            ORDER BY fr.created_at DESC
        ";

        return $this->fetchAll($sql, [$userId]);
    }

    /** Allowed sort columns for public catalog — whitelist prevents SQL injection */
    private const SORT_WHITELIST = [
        'newest'     => 'fr.created_at DESC',
        'oldest'     => 'fr.created_at ASC',
        'date_desc'  => 'fr.found_date DESC',
        'date_asc'   => 'fr.found_date ASC',
    ];

    /** Allowed condition filter values */
    private const CONDITION_WHITELIST = ['sangat_baik', 'baik', 'rusak_ringan', 'rusak_berat'];

    /**
     * Build shared WHERE + params for public found report queries.
     *
     * Privacy contract: keyword searches ONLY title & description.
     * private_details, location_detail, and reporter PII are NEVER searched.
     *
     * @return array{0: string[], 1: array<mixed>}
     */
    private function buildPublicFilters(
        ?int    $categoryId,
        ?int    $locationId,
        ?string $search,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $condition
    ): array {
        $params = [];
        $where  = ["fr.status IN ('published', 'pending_handover', 'handed_over_to_center', 'verified')"];

        if ($categoryId !== null && $categoryId > 0) {
            $where[]  = 'fr.category_id = ?';
            $params[] = $categoryId;
        }

        if ($locationId !== null && $locationId > 0) {
            $where[]  = 'fr.location_id = ?';
            $params[] = $locationId;
        }

        if (!empty($search)) {
            // ONLY title and description — NOT private_details or location_detail
            $where[]  = '(fr.title LIKE ? OR fr.description LIKE ?)';
            $term     = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($dateFrom)) {
            $where[]  = 'fr.found_date >= ?';
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where[]  = 'fr.found_date <= ?';
            $params[] = $dateTo;
        }

        if (!empty($condition) && in_array($condition, self::CONDITION_WHITELIST, true)) {
            $where[]  = 'fr.item_condition = ?';
            $params[] = $condition;
        }

        return [$where, $params];
    }

    /**
     * Ambil laporan barang temuan untuk katalog publik
     *
     * @param string|null $sortBy    Key from SORT_WHITELIST (default 'newest')
     * @param int|null    $locationId Filter by campus location ID
     * @param string|null $dateFrom  Y-m-d filter start
     * @param string|null $dateTo    Y-m-d filter end
     * @param string|null $condition Filter by item_condition whitelist value
     */
    public function getPublicReports(
        ?int    $categoryId = null,
        ?string $search     = null,
        int     $limit      = 12,
        int     $offset     = 0,
        ?int    $locationId = null,
        ?string $dateFrom   = null,
        ?string $dateTo     = null,
        ?string $sortBy     = 'newest',
        ?string $condition  = null
    ): array {
        [$where, $params] = $this->buildPublicFilters($categoryId, $locationId, $search, $dateFrom, $dateTo, $condition);

        $whereClause = implode(' AND ', $where);
        $orderBy     = self::SORT_WHITELIST[$sortBy ?? 'newest'] ?? self::SORT_WHITELIST['newest'];

        // Privacy: no reporter PII (name/email/phone) selected
        $sql = "
            SELECT fr.id, fr.report_number, fr.title, fr.description,
                   fr.found_date, fr.found_time, fr.item_condition,
                   fr.primary_image, fr.status,
                   fr.is_handed_over_to_center, fr.created_at,
                   c.name AS category_name,
                   c.slug AS category_slug,
                   c.icon AS category_icon,
                   l.id   AS location_id,
                   l.name AS location_name
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations  l ON fr.location_id  = l.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total laporan publik untuk paginasi
     *
     * @param int|null    $locationId Filter by campus location ID
     * @param string|null $dateFrom   Y-m-d filter start
     * @param string|null $dateTo     Y-m-d filter end
     * @param string|null $condition  Filter by item_condition whitelist value
     */
    public function countPublicReports(
        ?int    $categoryId = null,
        ?string $search     = null,
        ?int    $locationId = null,
        ?string $dateFrom   = null,
        ?string $dateTo     = null,
        ?string $condition  = null
    ): int {
        [$where, $params] = $this->buildPublicFilters($categoryId, $locationId, $search, $dateFrom, $dateTo, $condition);

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*)
            FROM found_reports fr
            WHERE {$whereClause}
        ";

        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil antrean laporan barang temuan yang menunggu tindakan petugas (Handover / Verifikasi)
     */
    public function getPendingVerificationReports(int $limit = 20, int $offset = 0, ?string $status = null): array
    {
        $where = ["fr.status IN ('published', 'pending_handover', 'handed_over_to_center')"];
        $params = [];

        if (!empty($status)) {
            $where = ["fr.status = ?"];
            $params = [$status];
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT fr.*,
                   c.name AS category_name,
                   l.name AS location_name,
                   u.name AS reporter_name,
                   u.email AS reporter_email,
                   u.phone AS reporter_phone
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations l ON fr.location_id = l.id
            JOIN users u ON fr.reporter_user_id = u.id
            WHERE {$whereClause}
            ORDER BY fr.created_at ASC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total laporan antrean petugas
     */
    public function countPendingVerificationReports(?string $status = null): int
    {
        $where = ["fr.status IN ('published', 'pending_handover', 'handed_over_to_center')"];
        $params = [];

        if (!empty($status)) {
            $where = ["fr.status = ?"];
            $params = [$status];
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM found_reports fr WHERE {$whereClause}";
        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Update transisi status laporan & rekam ke status_histories
     */
    public function updateStatus(int $id, string $newStatus, ?int $changedByUserId = null, ?string $notes = null): bool
    {
        $report = $this->findDetail($id);
        if (!$report) {
            return false;
        }

        $oldStatus = $report['status'];

        $sql = "UPDATE found_reports SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->query($sql, [$newStatus, $id]);

        if ($stmt->rowCount() >= 0) {
            StatusHistoryService::record(
                'found_report',
                $id,
                $oldStatus,
                $newStatus,
                $changedByUserId,
                $notes
            );
            try {
                \App\Services\NotificationService::notifyFoundReportStatusChanged($report, $oldStatus, $newStatus);
            } catch (\Throwable $e) {
                error_log("[FoundReportModel] notifyFoundReportStatusChanged error: " . $e->getMessage());
            }
            return true;
        }

        return false;
    }

    /**
     * Petugas memverifikasi fisik barang dan memasukkan ke inventaris Center (items)
     *
     * @return array{success: bool, item_id?: int, error?: string}
     */
    public function verifyAndStoreToInventory(
        int $foundReportId,
        int $storageLocationId,
        int $verifierUserId,
        ?string $physicalCondition = null,
        ?string $notes = null
    ): array {
        $report = $this->findDetail($foundReportId);
        if (!$report) {
            return ['success' => false, 'error' => 'Laporan temuan tidak ditemukan.'];
        }

        if ($report['item_id'] !== null && $report['status'] === 'verified') {
            return ['success' => false, 'error' => 'Barang temuan ini sudah diverifikasi dan masuk gudang sebelumnya.'];
        }

        $itemModel = new ItemModel();
        $itemCode  = $itemModel->generateItemCode();

        // 1. Buat record di tabel items
        $condition = $physicalCondition ?: ($report['item_condition'] ?? 'baik');
        $itemId = $itemModel->createItem([
            'item_code'           => $itemCode,
            'category_id'         => (int) $report['category_id'],
            'storage_location_id' => $storageLocationId,
            'verified_by_user_id' => $verifierUserId,
            'title'               => $report['title'],
            'description'         => $report['description'],
            'primary_image'       => $report['primary_image'],
            'physical_condition'  => $condition,
            'status'              => 'stored',
        ]);

        // 2. Catat status history untuk item baru
        StatusHistoryService::record(
            'item',
            $itemId,
            null,
            'stored',
            $verifierUserId,
            "Item fisik berhasil didaftarkan dari Laporan Penemuan {$report['report_number']}"
        );

        // 3. Update status found_reports ke verified & set item_id & is_handed_over_to_center = 1
        $oldReportStatus = $report['status'];
        $sql = "
            UPDATE found_reports SET
                item_id = ?,
                is_handed_over_to_center = 1,
                handed_over_at = IFNULL(handed_over_at, NOW()),
                status = 'verified',
                updated_at = NOW()
            WHERE id = ?
        ";
        $this->query($sql, [$itemId, $foundReportId]);

        // 4. Catat status history untuk found_reports
        StatusHistoryService::record(
            'found_report',
            $foundReportId,
            $oldReportStatus,
            'verified',
            $verifierUserId,
            $notes ?: "Barang fisik diverifikasi oleh Petugas dan disimpan di rak/lemari (Kode Item: {$itemCode})"
        );

        // 5. Catat audit log
        AuditLogService::log('VERIFY_FOUND_REPORT', $verifierUserId, 'found_reports', $foundReportId, [
            'report_number' => $report['report_number'],
            'item_id'       => $itemId,
            'item_code'     => $itemCode,
            'storage_id'    => $storageLocationId,
        ]);

        // 6. Notifikasi pelapor bahwa barang temuan telah diterima & tersimpan di Center
        try {
            $storageName = 'Posko Lost & Found Center';
            if ($storageLocationId) {
                $locStmt = $this->query("SELECT name, code FROM storage_locations WHERE id = ?", [$storageLocationId]);
                $loc = $locStmt->fetch(\PDO::FETCH_ASSOC);
                if ($loc) {
                    $storageName = $loc['name'] . (!empty($loc['code']) ? " ({$loc['code']})" : '');
                }
            }
            \App\Services\NotificationService::notifyFoundItemReceived($report, $itemCode, $storageName);
        } catch (\Throwable $e) {
            error_log("[FoundReportModel] notifyFoundItemReceived error: " . $e->getMessage());
        }

        return [
            'success'   => true,
            'item_id'   => $itemId,
            'item_code' => $itemCode,
        ];
    }

    /**
     * Generate nomor laporan unik: FND-YYYYMMDD-XXXX
     */
    public function generateReportNumber(): string
    {
        $prefix = 'FND-' . date('Ymd') . '-';
        $random = strtoupper(bin2hex(random_bytes(3))); // 6 karakter hex
        return $prefix . $random;
    }

    /**
     * Label kondisi fisik yang user-friendly dalam Bahasa Indonesia
     */
    public static function getConditionLabel(?string $condition): string
    {
        return match ($condition) {
            'sangat_baik'  => 'Sangat Baik',
            'baik'         => 'Baik',
            'rusak_ringan' => 'Rusak Ringan',
            'rusak_berat'  => 'Rusak Berat',
            default        => 'Baik',
        };
    }

    /**
     * Label status yang user-friendly dalam Bahasa Indonesia
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'                  => 'Draft',
            'published'              => 'Laporan Baru',
            'pending_handover'       => 'Menunggu Penyerahan ke Center',
            'handed_over_to_center'  => 'Diserahkan ke Petugas',
            'verified'               => 'Terverifikasi di Gudang',
            'claimed'                => 'Dalam Proses Klaim',
            'completed'              => 'Selesai / Dikembalikan',
            'cancelled'              => 'Dibatalkan',
            default                  => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Ambil daftar laporan temuan untuk Petugas & Admin (lengkap dengan filter & paginasi)
     */
    public function getStaffReports(
        ?string $status = null,
        ?int $categoryId = null,
        ?int $locationId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null,
        ?string $reporter = null,
        int $limit = 15,
        int $offset = 0
    ): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($status)) {
            $where[] = 'fr.status = ?';
            $params[] = $status;
        }
        if (!empty($categoryId) && $categoryId > 0) {
            $where[] = 'fr.category_id = ?';
            $params[] = $categoryId;
        }
        if (!empty($locationId) && $locationId > 0) {
            $where[] = 'fr.location_id = ?';
            $params[] = $locationId;
        }
        if (!empty($dateFrom)) {
            $where[] = 'fr.found_date >= ?';
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = 'fr.found_date <= ?';
            $params[] = $dateTo;
        }
        if (!empty($search)) {
            $where[] = '(fr.report_number LIKE ? OR fr.title LIKE ? OR fr.description LIKE ?)';
            $kw = '%' . trim($search) . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($reporter)) {
            $where[] = '(u.name LIKE ? OR u.identity_number LIKE ? OR u.email LIKE ?)';
            $repKw = '%' . trim($reporter) . '%';
            $params[] = $repKw;
            $params[] = $repKw;
            $params[] = $repKw;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT fr.*,
                   c.name AS category_name,
                   c.icon AS category_icon,
                   l.name AS location_name,
                   u.name AS reporter_name,
                   u.identity_number AS reporter_identity,
                   u.email AS reporter_email,
                   u.phone AS reporter_phone,
                   i.item_code,
                   i.status AS item_physical_status
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations l ON fr.location_id = l.id
            JOIN users u ON fr.reporter_user_id = u.id
            LEFT JOIN items i ON fr.item_id = i.id
            WHERE {$whereSql}
            ORDER BY fr.created_at DESC, fr.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total laporan temuan untuk Petugas & Admin
     */
    public function countStaffReports(
        ?string $status = null,
        ?int $categoryId = null,
        ?int $locationId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null,
        ?string $reporter = null
    ): int {
        $where = ['1=1'];
        $params = [];

        if (!empty($status)) {
            $where[] = 'fr.status = ?';
            $params[] = $status;
        }
        if (!empty($categoryId) && $categoryId > 0) {
            $where[] = 'fr.category_id = ?';
            $params[] = $categoryId;
        }
        if (!empty($locationId) && $locationId > 0) {
            $where[] = 'fr.location_id = ?';
            $params[] = $locationId;
        }
        if (!empty($dateFrom)) {
            $where[] = 'fr.found_date >= ?';
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = 'fr.found_date <= ?';
            $params[] = $dateTo;
        }
        if (!empty($search)) {
            $where[] = '(fr.report_number LIKE ? OR fr.title LIKE ? OR fr.description LIKE ?)';
            $kw = '%' . trim($search) . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (!empty($reporter)) {
            $where[] = '(u.name LIKE ? OR u.identity_number LIKE ? OR u.email LIKE ?)';
            $repKw = '%' . trim($reporter) . '%';
            $params[] = $repKw;
            $params[] = $repKw;
            $params[] = $repKw;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*)
            FROM found_reports fr
            JOIN users u ON fr.reporter_user_id = u.id
            WHERE {$whereSql}
        ";

        return (int) ($this->query($sql, $params)->fetchColumn() ?: 0);
    }
}
