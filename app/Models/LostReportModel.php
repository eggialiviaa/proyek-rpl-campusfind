<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use PDO;

/**
 * LostReportModel — Pengelolaan data laporan kehilangan barang
 */
class LostReportModel extends BaseModel
{
    /**
     * Buat laporan kehilangan baru
     *
     * @param array<string, mixed> $data
     * @return int ID laporan yang dibuat
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO lost_reports (
                report_number,
                reporter_user_id,
                category_id,
                last_seen_location_id,
                title,
                description,
                location_detail,
                private_details,
                lost_date,
                lost_time,
                primary_image,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $this->query($sql, [
            $data['report_number'],
            $data['reporter_user_id'],
            $data['category_id'],
            $data['last_seen_location_id'],
            $data['title'],
            $data['description'],
            $data['location_detail'] ?? null,
            $data['private_details'] ?? null,
            $data['lost_date'],
            $data['lost_time'] ?? null,
            $data['primary_image'] ?? null,
            $data['status'] ?? 'published',
        ]);

        return (int) $this->lastInsertId();
    }

    /**
     * Update laporan kehilangan
     */
    public function updateReport(int $id, array $data): bool
    {
        $sql = "
            UPDATE lost_reports SET
                category_id = ?,
                last_seen_location_id = ?,
                title = ?,
                description = ?,
                location_detail = ?,
                private_details = ?,
                lost_date = ?,
                lost_time = ?,
                primary_image = IFNULL(?, primary_image),
                updated_at = NOW()
            WHERE id = ?
        ";

        $stmt = $this->query($sql, [
            $data['category_id'],
            $data['last_seen_location_id'],
            $data['title'],
            $data['description'],
            $data['location_detail'] ?? null,
            $data['private_details'] ?? null,
            $data['lost_date'],
            $data['lost_time'] ?? null,
            $data['primary_image'] ?? null,
            $id,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * Hapus laporan kehilangan
     */
    public function deleteReport(int $id): bool
    {
        $sql = "DELETE FROM lost_reports WHERE id = ?";
        return $this->execute($sql, [$id]) > 0;
    }

    /**
     * Ambil laporan detail lengkap beserta data relasinya
     */
    public function findDetail(int $id): array|false
    {
        $sql = "
            SELECT lr.*,
                   c.name AS category_name,
                   c.slug AS category_slug,
                   c.icon AS category_icon,
                   l.name AS location_name,
                   l.building_name,
                   u.name AS reporter_name,
                   u.email AS reporter_email,
                   u.phone AS reporter_phone,
                   u.identity_number AS reporter_identity
            FROM lost_reports lr
            JOIN categories c ON lr.category_id = c.id
            JOIN locations l ON lr.last_seen_location_id = l.id
            JOIN users u ON lr.reporter_user_id = u.id
            WHERE lr.id = ?
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
     * Ambil daftar laporan milik user tertentu
     */
    public function getUserReports(int $userId): array
    {
        $sql = "
            SELECT lr.*,
                   c.name AS category_name,
                   l.name AS location_name
            FROM lost_reports lr
            JOIN categories c ON lr.category_id = c.id
            JOIN locations l ON lr.last_seen_location_id = l.id
            WHERE lr.reporter_user_id = ?
            ORDER BY lr.created_at DESC
        ";
        return $this->fetchAll($sql, [$userId]);
    }

    /** Allowed sort columns for public catalog — whitelist prevents SQL injection */
    private const SORT_WHITELIST = [
        'newest'     => 'lr.created_at DESC',
        'oldest'     => 'lr.created_at ASC',
        'date_desc'  => 'lr.lost_date DESC',
        'date_asc'   => 'lr.lost_date ASC',
    ];

    /**
     * Build shared WHERE + params for public lost report queries.
     *
     * Privacy contract: keyword searches ONLY title & description.
     * location_detail and private_details are NEVER included in search index.
     *
     * @return array{0: string[], 1: array<mixed>}
     */
    private function buildPublicFilters(
        ?int    $categoryId,
        ?int    $locationId,
        ?string $search,
        ?string $dateFrom,
        ?string $dateTo
    ): array {
        $params = [];
        $where  = ["lr.status = 'published'"];

        if ($categoryId !== null && $categoryId > 0) {
            $where[]  = 'lr.category_id = ?';
            $params[] = $categoryId;
        }

        if ($locationId !== null && $locationId > 0) {
            $where[]  = 'lr.last_seen_location_id = ?';
            $params[] = $locationId;
        }

        if (!empty($search)) {
            // ONLY title and description — NOT private_details or location_detail
            $where[]  = '(lr.title LIKE ? OR lr.description LIKE ?)';
            $term     = '%' . trim($search) . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($dateFrom)) {
            $where[]  = 'lr.lost_date >= ?';
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where[]  = 'lr.lost_date <= ?';
            $params[] = $dateTo;
        }

        return [$where, $params];
    }

    /**
     * Ambil laporan kehilangan untuk katalog publik
     *
     * @param string|null $sortBy   Key from SORT_WHITELIST (default: 'newest')
     * @param string|null $dateFrom Y-m-d filter start
     * @param string|null $dateTo   Y-m-d filter end
     * @param int|null    $locationId Filter by campus location
     */
    public function getPublicReports(
        ?int    $categoryId = null,
        ?string $search     = null,
        int     $limit      = 12,
        int     $offset     = 0,
        ?int    $locationId = null,
        ?string $dateFrom   = null,
        ?string $dateTo     = null,
        ?string $sortBy     = 'newest'
    ): array {
        [$where, $params] = $this->buildPublicFilters($categoryId, $locationId, $search, $dateFrom, $dateTo);

        $whereClause = implode(' AND ', $where);
        $orderBy     = self::SORT_WHITELIST[$sortBy ?? 'newest'] ?? self::SORT_WHITELIST['newest'];

        // Privacy: reporter PII columns excluded (no reporter_name/email/phone)
        $sql = "
            SELECT lr.id, lr.report_number, lr.title, lr.description,
                   lr.lost_date, lr.lost_time,
                   lr.primary_image, lr.status, lr.created_at,
                   c.name AS category_name,
                   c.slug AS category_slug,
                   c.icon AS category_icon,
                   l.id   AS location_id,
                   l.name AS location_name
            FROM lost_reports lr
            JOIN categories c ON lr.category_id          = c.id
            JOIN locations  l ON lr.last_seen_location_id = l.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total laporan publik untuk pagination
     *
     * @param int|null    $locationId Filter by campus location
     * @param string|null $dateFrom   Y-m-d filter start
     * @param string|null $dateTo     Y-m-d filter end
     */
    public function countPublicReports(
        ?int    $categoryId = null,
        ?string $search     = null,
        ?int    $locationId = null,
        ?string $dateFrom   = null,
        ?string $dateTo     = null
    ): int {
        [$where, $params] = $this->buildPublicFilters($categoryId, $locationId, $search, $dateFrom, $dateTo);

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT COUNT(*)
            FROM lost_reports lr
            WHERE {$whereClause}
        ";

        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Label status yang user-friendly dalam Bahasa Indonesia
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'         => 'Draft',
            'published'     => 'Laporan Baru',
            'investigating' => 'Sedang Diperiksa',
            'matched'       => 'Terindikasi Cocok',
            'claimed'       => 'Dalam Proses Klaim',
            'resolved'      => 'Selesai / Ditemukan',
            'closed'        => 'Ditutup',
            'cancelled'     => 'Dibatalkan',
            'expired'       => 'Kedaluwarsa',
            default         => ucfirst($status),
        };
    }

    /**
     * Ambil daftar laporan kehilangan untuk Petugas & Admin (lengkap dengan filter & paginasi)
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
            $where[] = 'lr.status = ?';
            $params[] = $status;
        }
        if (!empty($categoryId) && $categoryId > 0) {
            $where[] = 'lr.category_id = ?';
            $params[] = $categoryId;
        }
        if (!empty($locationId) && $locationId > 0) {
            $where[] = 'lr.last_seen_location_id = ?';
            $params[] = $locationId;
        }
        if (!empty($dateFrom)) {
            $where[] = 'lr.lost_date >= ?';
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = 'lr.lost_date <= ?';
            $params[] = $dateTo;
        }
        if (!empty($search)) {
            $where[] = '(lr.report_number LIKE ? OR lr.title LIKE ? OR lr.description LIKE ?)';
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
            SELECT lr.*,
                   c.name AS category_name,
                   c.icon AS category_icon,
                   l.name AS location_name,
                   u.name AS reporter_name,
                   u.identity_number AS reporter_identity,
                   u.email AS reporter_email,
                   u.phone AS reporter_phone
            FROM lost_reports lr
            JOIN categories c ON lr.category_id = c.id
            JOIN locations l ON lr.last_seen_location_id = l.id
            JOIN users u ON lr.reporter_user_id = u.id
            WHERE {$whereSql}
            ORDER BY lr.created_at DESC, lr.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total laporan kehilangan untuk Petugas & Admin
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
            $where[] = 'lr.status = ?';
            $params[] = $status;
        }
        if (!empty($categoryId) && $categoryId > 0) {
            $where[] = 'lr.category_id = ?';
            $params[] = $categoryId;
        }
        if (!empty($locationId) && $locationId > 0) {
            $where[] = 'lr.last_seen_location_id = ?';
            $params[] = $locationId;
        }
        if (!empty($dateFrom)) {
            $where[] = 'lr.lost_date >= ?';
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = 'lr.lost_date <= ?';
            $params[] = $dateTo;
        }
        if (!empty($search)) {
            $where[] = '(lr.report_number LIKE ? OR lr.title LIKE ? OR lr.description LIKE ?)';
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
            FROM lost_reports lr
            JOIN users u ON lr.reporter_user_id = u.id
            WHERE {$whereSql}
        ";

        return (int) ($this->query($sql, $params)->fetchColumn() ?: 0);
    }

    /**
     * Update status laporan kehilangan dan catat history & trigger notifikasi
     */
    public function updateStatus(int $id, string $newStatus, ?int $changedByUserId = null, ?string $notes = null): bool
    {
        $report = $this->findDetail($id);
        if (!$report) {
            return false;
        }

        $oldStatus = $report['status'];
        $sql = "UPDATE lost_reports SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->query($sql, [$newStatus, $id]);

        if ($stmt->rowCount() >= 0) {
            \App\Services\StatusHistoryService::record(
                'lost_report',
                $id,
                $oldStatus,
                $newStatus,
                $changedByUserId,
                $notes
            );
            try {
                \App\Services\NotificationService::notifyLostReportStatusChanged($report, $oldStatus, $newStatus);
            } catch (\Throwable $e) {
                error_log("[LostReportModel] notifyLostReportStatusChanged error: " . $e->getMessage());
            }
            return true;
        }

        return false;
    }

    /**
     * Generate nomor laporan unik: LST-YYYYMMDD-XXXX
     */
    public function generateReportNumber(): string
    {
        $prefix = 'LST-' . date('Ymd') . '-';
        $random = strtoupper(bin2hex(random_bytes(3))); // 6 karakter hex
        return $prefix . $random;
    }
}
