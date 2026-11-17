<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use App\Services\StatusHistoryService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use PDO;
use Exception;
use Throwable;

/**
 * ClaimModel — Model untuk manajemen alur klaim kepemilikan & verifikasi petugas
 *
 * Mengimplementasikan:
 * - Pembuatan klaim dengan detail privat & lampiran bukti
 * - Persetujuan klaim dengan proteksi transaksi (concurrency safe)
 * - Auto-close klaim ganda saat salah satu disetujui
 * - Pencatatan serah terima fisik barang (handover)
 * - Audit logging & status history
 */
class ClaimModel extends BaseModel
{
    /** Status klaim yang sah sesuai PRD */
    public const STATUSES = [
        'pending'          => 'Menunggu Pemeriksaan',
        'under_review'     => 'Sedang Diperiksa',
        'need_more_info'   => 'Butuh Info Tambahan',
        'approved'         => 'Disetujui',
        'rejected'         => 'Ditolak',
        'completed'        => 'Selesai (Diserahkan)',
        'closed_duplicate' => 'Ditutup Otomatis (Barang Diklaim Pihak Lain)',
        'cancelled'        => 'Dibatalkan',
    ];

    /**
     * Dapatkan label human-readable untuk status klaim
     */
    public static function getStatusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Dapatkan badge styling untuk status klaim
     */
    public static function getStatusBadge(string $status): string
    {
        return match ($status) {
            'pending'          => '<span class="cf-badge cf-badge-warning"><i class="bi bi-clock me-1"></i>Menunggu Pemeriksaan</span>',
            'under_review'     => '<span class="cf-badge cf-badge-stored"><i class="bi bi-search me-1"></i>Sedang Diperiksa</span>',
            'need_more_info'   => '<span class="cf-badge cf-badge-neutral"><i class="bi bi-question-circle me-1"></i>Butuh Info Tambahan</span>',
            'approved'         => '<span class="cf-badge cf-badge-found"><i class="bi bi-check-circle me-1"></i>Disetujui</span>',
            'rejected'         => '<span class="cf-badge cf-badge-danger"><i class="bi bi-x-circle me-1"></i>Ditolak</span>',
            'completed'        => '<span class="cf-badge cf-badge-success"><i class="bi bi-check2-all me-1"></i>Selesai</span>',
            'closed_duplicate' => '<span class="cf-badge cf-badge-neutral"><i class="bi bi-slash-circle me-1"></i>Ditutup Otomatis</span>',
            'cancelled'        => '<span class="cf-badge cf-badge-neutral"><i class="bi bi-dash me-1"></i>Dibatalkan</span>',
            default            => '<span class="cf-badge cf-badge-neutral">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
        };
    }

    /**
     * Generate nomor klaim unik format CLM-YYYYMMDD-XXXX
     */
    public function generateClaimNumber(): string
    {
        $date = date('Ymd');
        do {
            $suffix = strtoupper(bin2hex(random_bytes(2)));
            $number = "CLM-{$date}-{$suffix}";
            $stmt = $this->query("SELECT COUNT(*) FROM claims WHERE claim_number = ?", [$number]);
            $exists = (int) $stmt->fetchColumn() > 0;
        } while ($exists);

        return $number;
    }

    /**
     * Cek apakah user sudah memiliki klaim aktif pada item ini (cegah duplikasi spam)
     */
    public function hasActiveClaim(int $userId, int $itemId): bool
    {
        $sql = "
            SELECT COUNT(*) FROM claims 
            WHERE claimant_user_id = ? 
              AND item_id = ? 
              AND status IN ('submitted', 'pending', 'under_review', 'need_more_info', 'additional_info_requested', 'approved')
        ";
        $stmt = $this->query($sql, [$userId, $itemId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Cek apakah user sudah memiliki klaim aktif pada laporan temuan ini atau item terkait
     * (Prompt 35A: Hak tombol klaim)
     *
     * @param int $userId ID pengguna yang sedang login
     * @param int|null $foundReportId ID laporan temuan
     * @param int|null $itemId ID item inventaris fisik (jika sudah disimpan di center)
     * @return bool
     */
    public function hasActiveClaimForFoundReport(int $userId, ?int $foundReportId = null, ?int $itemId = null): bool
    {
        $conditions = [];
        $params = [$userId];

        if ($foundReportId && $foundReportId > 0) {
            $conditions[] = "c.found_report_id = ?";
            $params[] = $foundReportId;
            $conditions[] = "fr.id = ?";
            $params[] = $foundReportId;
        }

        if ($itemId && $itemId > 0) {
            $conditions[] = "c.item_id = ?";
            $params[] = $itemId;
        }

        if (empty($conditions)) {
            return false;
        }

        $condSql = implode(" OR ", $conditions);

        $sql = "
            SELECT COUNT(*) FROM claims c
            LEFT JOIN found_reports fr ON (c.found_report_id = fr.id OR (fr.item_id IS NOT NULL AND c.item_id = fr.item_id))
            WHERE c.claimant_user_id = ?
              AND ({$condSql})
              AND c.status IN ('submitted', 'pending', 'under_review', 'need_more_info', 'additional_info_requested', 'approved')
        ";

        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Buat klaim kepemilikan baru
     *
     * @param array<string, mixed> $data
     * @param array<int, array{file_path: string, original_name: string, type: string}> $evidenceFiles
     * @return int ID klaim yang baru dibuat
     */
    public function createClaim(array $data, array $evidenceFiles = []): int
    {
        $claimNumber = $this->generateClaimNumber();
        $userId      = (int) $data['claimant_user_id'];
        $itemId      = !empty($data['item_id']) ? (int) $data['item_id'] : null;
        $foundId     = !empty($data['found_report_id']) ? (int) $data['found_report_id'] : null;
        $lostId      = !empty($data['lost_report_id']) ? (int) $data['lost_report_id'] : null;

        // Auto-resolve item_id dari found_reports jika tidak diberikan langsung
        if (!$itemId && $foundId) {
            $stmtFr = $this->query("SELECT item_id FROM found_reports WHERE id = ?", [$foundId]);
            $foundItemId = $stmtFr->fetchColumn();
            if ($foundItemId) {
                $itemId = (int) $foundItemId;
            }
        }

        $sql = "
            INSERT INTO claims (
                claim_number,
                claimant_user_id,
                lost_report_id,
                found_report_id,
                item_id,
                claim_reason,
                ownership_proof_description,
                unique_features,
                additional_notes,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ";

        $this->query($sql, [
            $claimNumber,
            $userId,
            $lostId,
            $foundId,
            $itemId,
            trim((string) $data['claim_reason']),
            trim((string) $data['ownership_proof_description']),
            trim((string) $data['unique_features']),
            !empty($data['additional_notes']) ? trim((string) $data['additional_notes']) : null,
        ]);

        $claimId = (int) $this->lastInsertId();

        // 1. Simpan detail unik ke claim_evidence (evidence_type = secret_details)
        $this->query("
            INSERT INTO claim_evidence (claim_id, evidence_type, evidence_key, evidence_value, created_at, updated_at)
            VALUES (?, 'secret_details', 'Detail Unik Barang', ?, NOW(), NOW())
        ", [$claimId, trim((string) $data['unique_features'])]);

        // 2. Simpan deskripsi bukti ke claim_evidence
        $this->query("
            INSERT INTO claim_evidence (claim_id, evidence_type, evidence_key, evidence_value, created_at, updated_at)
            VALUES (?, 'other', 'Deskripsi Bukti Kepemilikan', ?, NOW(), NOW())
        ", [$claimId, trim((string) $data['ownership_proof_description'])]);

        // 3. Simpan file-file bukti yang diunggah
        foreach ($evidenceFiles as $file) {
            $evType = str_contains($file['type'] ?? '', 'image') ? 'ownership_proof_image' : 'invoice_receipt';
            $this->query("
                INSERT INTO claim_evidence (claim_id, evidence_type, evidence_key, evidence_value, file_path, created_at, updated_at)
                VALUES (?, ?, 'Dokumen/Foto Bukti', ?, ?, NOW(), NOW())
            ", [$claimId, $evType, $file['original_name'], $file['file_path']]);
        }

        // Catat status history awal
        StatusHistoryService::record(
            'claim',
            $claimId,
            null,
            'pending',
            $userId,
            'Pengajuan klaim kepemilikan baru'
        );

        // Catat audit log
        AuditLogService::log(
            'CLAIM_SUBMITTED',
            $userId,
            'claim',
            $claimId,
            [
                'claim_number' => $claimNumber,
                'item_id'      => $itemId,
                'found_report_id' => $foundId,
                'lost_report_id'  => $lostId,
            ]
        );

        return $claimId;
    }

    /**
     * Ambil daftar klaim milik user tertentu (Civitas)
     */
    public function getClaimsByClaimant(int $userId): array
    {
        $sql = "
            SELECT c.*,
                   i.item_code, i.title AS item_title, i.primary_image AS item_image,
                   i.physical_condition, i.status AS item_physical_status,
                   cat.name AS category_name, cat.icon AS category_icon,
                   fr.report_number AS found_report_number,
                   lr.report_number AS lost_report_number
            FROM claims c
            LEFT JOIN items i ON c.item_id = i.id
            LEFT JOIN categories cat ON i.category_id = cat.id
            LEFT JOIN found_reports fr ON c.found_report_id = fr.id
            LEFT JOIN lost_reports lr ON c.lost_report_id = lr.id
            WHERE c.claimant_user_id = ?
            ORDER BY c.created_at DESC
        ";

        return $this->fetchAll($sql, [$userId]);
    }

    /**
     * Ambil daftar klaim untuk Petugas/Admin dengan filter status (string, array status, atau null)
     *
     * @param string|array<string>|null $status
     */
    public function getClaimsForPetugas(string|array|null $status = null, ?int $itemId = null, int $limit = 20, int $offset = 0): array
    {
        $params = [];
        $where  = ['1=1'];

        if (!empty($status)) {
            if (is_array($status)) {
                $validStatuses = array_values(array_filter($status, fn($s) => array_key_exists($s, self::STATUSES)));
                if (!empty($validStatuses)) {
                    $placeholders = implode(',', array_fill(0, count($validStatuses), '?'));
                    $where[] = "c.status IN ({$placeholders})";
                    foreach ($validStatuses as $vs) {
                        $params[] = $vs;
                    }
                }
            } elseif (array_key_exists($status, self::STATUSES)) {
                $where[]  = 'c.status = ?';
                $params[] = $status;
            }
        }

        if ($itemId !== null && $itemId > 0) {
            $where[]  = 'c.item_id = ?';
            $params[] = $itemId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT c.*,
                   u.name AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone,
                   i.item_code, i.title AS item_title, i.primary_image AS item_image,
                   i.physical_condition, i.status AS item_physical_status,
                   sl.name AS storage_location_name,
                   cat.name AS category_name, cat.icon AS category_icon,
                   rev.name AS reviewer_name,
                   (SELECT COUNT(*) FROM claim_evidence ce WHERE ce.claim_id = c.id) AS evidence_count
            FROM claims c
            JOIN users u ON c.claimant_user_id = u.id
            LEFT JOIN items i ON c.item_id = i.id
            LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
            LEFT JOIN categories cat ON i.category_id = cat.id
            LEFT JOIN users rev ON c.reviewed_by_user_id = rev.id
            WHERE {$whereClause}
            ORDER BY 
                CASE c.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'under_review' THEN 2 
                    WHEN 'need_more_info' THEN 3 
                    WHEN 'approved' THEN 4 
                    ELSE 5 
                END,
                c.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total klaim untuk paginasi petugas
     *
     * @param string|array<string>|null $status
     */
    public function countClaimsForPetugas(string|array|null $status = null, ?int $itemId = null): int
    {
        $params = [];
        $where  = ['1=1'];

        if (!empty($status)) {
            if (is_array($status)) {
                $validStatuses = array_values(array_filter($status, fn($s) => array_key_exists($s, self::STATUSES)));
                if (!empty($validStatuses)) {
                    $placeholders = implode(',', array_fill(0, count($validStatuses), '?'));
                    $where[] = "c.status IN ({$placeholders})";
                    foreach ($validStatuses as $vs) {
                        $params[] = $vs;
                    }
                }
            } elseif (array_key_exists($status, self::STATUSES)) {
                $where[]  = 'c.status = ?';
                $params[] = $status;
            }
        }

        if ($itemId !== null && $itemId > 0) {
            $where[]  = 'c.item_id = ?';
            $params[] = $itemId;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM claims c WHERE {$whereClause}";
        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil detail lengkap klaim beserta relasi data publik & privat
     */
    public function findDetail(int $claimId): ?array
    {
        $sql = "
            SELECT c.*,
                   u.name AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone,
                   i.item_code, i.title AS item_title, i.description AS item_description,
                   i.primary_image AS item_image, i.physical_condition, i.status AS item_physical_status,
                   i.internal_notes AS item_internal_notes,
                   sl.id AS storage_location_id, sl.name AS storage_location_name,
                   cat.id AS category_id, cat.name AS category_name, cat.icon AS category_icon,
                   COALESCE(c.found_report_id, fr.id) AS found_report_id, 
                   fr.report_number AS found_report_number, 
                   fr.title AS found_report_title,
                   COALESCE(c.lost_report_id, lr.id) AS lost_report_id, 
                   lr.report_number AS lost_report_number, 
                   lr.title AS lost_report_title,
                   rev.name AS reviewer_name,
                   hby.name AS handed_over_by_name
            FROM claims c
            JOIN users u ON c.claimant_user_id = u.id
            LEFT JOIN items i ON c.item_id = i.id
            LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id
            LEFT JOIN categories cat ON i.category_id = cat.id
            LEFT JOIN found_reports fr ON (c.found_report_id = fr.id OR (c.found_report_id IS NULL AND i.id IS NOT NULL AND fr.item_id = i.id))
            LEFT JOIN lost_reports lr ON c.lost_report_id = lr.id
            LEFT JOIN users rev ON c.reviewed_by_user_id = rev.id
            LEFT JOIN users hby ON c.handed_over_by_user_id = hby.id
            WHERE c.id = ?
        ";

        $claim = $this->fetchOne($sql, [$claimId]);
        if (!$claim) {
            return null;
        }

        // Ambil bukti private dari claim_evidence
        $evidenceSql = "SELECT * FROM claim_evidence WHERE claim_id = ? ORDER BY id ASC";
        $claim['evidence'] = $this->fetchAll($evidenceSql, [$claimId]);

        // Ambil riwayat status transisi
        $historySql = "
            SELECT sh.*, u.name AS changed_by_name 
            FROM status_histories sh
            LEFT JOIN users u ON sh.changed_by_user_id = u.id
            WHERE sh.entity_type = 'claim' AND sh.entity_id = ?
            ORDER BY sh.created_at ASC
        ";
        $claim['status_history'] = $this->fetchAll($historySql, [$claimId]);

        return $claim;
    }

    /**
     * Ambil daftar klaim terkait untuk sebuah laporan (found atau lost)
     * Digunakan untuk tautan silang detail laporan <-> klaim (Prompt 34F)
     *
     * @param string $type 'found' atau 'lost'
     * @param int $reportId ID laporan
     * @param int|null $userId ID user saat ini (untuk filter privasi civitas)
     * @param bool $isStaff True jika petugas atau admin
     * @return array<int, array<string, mixed>>
     */
    public function getClaimsForReport(string $type, int $reportId, ?int $userId = null, bool $isStaff = false): array
    {
        if ($type === 'found') {
            $sql = "
                SELECT c.id, c.claim_number, c.status, c.created_at, c.updated_at,
                       c.claimant_user_id, c.claim_reason,
                       u.name AS claimant_name, u.email AS claimant_email
                FROM claims c
                JOIN users u ON c.claimant_user_id = u.id
                LEFT JOIN found_reports fr ON (c.found_report_id = fr.id OR (fr.item_id IS NOT NULL AND c.item_id = fr.item_id))
                WHERE (c.found_report_id = ? OR fr.id = ?)
            ";
            $params = [$reportId, $reportId];
        } else {
            $sql = "
                SELECT c.id, c.claim_number, c.status, c.created_at, c.updated_at,
                       c.claimant_user_id, c.claim_reason,
                       u.name AS claimant_name, u.email AS claimant_email
                FROM claims c
                JOIN users u ON c.claimant_user_id = u.id
                WHERE c.lost_report_id = ?
            ";
            $params = [$reportId];
        }

        if (!$isStaff) {
            $sql .= " AND c.claimant_user_id = ?";
            $params[] = (int) $userId;
        }

        $sql .= " ORDER BY c.created_at DESC";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Update status klaim menjadi 'under_review' saat petugas mulai memeriksa
     */
    public function startReview(int $claimId, int $petugasUserId): bool
    {
        $claim = $this->findDetail($claimId);
        if (!$claim || !in_array($claim['status'], ['pending', 'need_more_info'], true)) {
            return false;
        }

        $oldStatus = $claim['status'];

        $sql = "
            UPDATE claims SET 
                status = 'under_review',
                reviewed_by_user_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        $this->query($sql, [$petugasUserId, $claimId]);

        StatusHistoryService::record(
            'claim',
            $claimId,
            $oldStatus,
            'under_review',
            $petugasUserId,
            'Petugas mulai meninjau berkas klaim'
        );

        AuditLogService::log('CLAIM_REVIEW_STARTED', $petugasUserId, 'claim', $claimId, [
            'claim_number' => $claim['claim_number']
        ]);

        // Notifikasi in-app ke pemilik klaim
        NotificationService::notifyClaimStatusChanged(
            array_merge($claim, ['status' => $oldStatus]),
            $oldStatus,
            'under_review'
        );

        return true;
    }

    /**
     * Minta informasi tambahan dari claimant
     */
    public function requestMoreInfo(int $claimId, int $petugasUserId, string $notes): bool
    {
        $claim = $this->findDetail($claimId);
        if (!$claim) {
            return false;
        }

        $oldStatus = $claim['status'];

        $sql = "
            UPDATE claims SET 
                status = 'need_more_info',
                reviewed_by_user_id = ?,
                reviewer_notes = ?,
                reviewed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ";
        $this->query($sql, [$petugasUserId, $notes, $claimId]);

        StatusHistoryService::record(
            'claim',
            $claimId,
            $oldStatus,
            'need_more_info',
            $petugasUserId,
            $notes
        );

        AuditLogService::log('CLAIM_NEED_MORE_INFO', $petugasUserId, 'claim', $claimId, [
            'claim_number' => $claim['claim_number'],
            'notes'        => $notes,
        ]);

        // Notifikasi in-app ke pemilik klaim
        NotificationService::notifyClaimStatusChanged(
            array_merge($claim, ['status' => $oldStatus]),
            $oldStatus,
            'need_more_info'
        );

        return true;
    }

    /**
     * Tolak klaim dengan catatan alasan penolakan
     */
    public function rejectClaim(int $claimId, int $petugasUserId, string $rejectionReason): bool
    {
        $claim = $this->findDetail($claimId);
        if (!$claim) {
            return false;
        }

        $oldStatus = $claim['status'];

        $sql = "
            UPDATE claims SET 
                status = 'rejected',
                reviewed_by_user_id = ?,
                rejection_reason = ?,
                reviewer_notes = ?,
                reviewed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ";
        $this->query($sql, [$petugasUserId, $rejectionReason, $rejectionReason, $claimId]);

        StatusHistoryService::record(
            'claim',
            $claimId,
            $oldStatus,
            'rejected',
            $petugasUserId,
            $rejectionReason
        );

        AuditLogService::log('CLAIM_REJECTED', $petugasUserId, 'claim', $claimId, [
            'claim_number'     => $claim['claim_number'],
            'rejection_reason' => $rejectionReason,
        ]);

        // Notifikasi in-app ke pemilik klaim
        NotificationService::notifyClaimStatusChanged(
            array_merge($claim, ['status' => $oldStatus]),
            $oldStatus,
            'rejected'
        );

        return true;
    }

    /**
     * SETUJUI KLAIM (APPROVE) & AUTO-CLOSE KLAIM GANDA
     *
     * Persyaratan PRD:
     * - Satu item hanya boleh memiliki SATU klaim 'approved' pada satu waktu.
     * - Ketika satu klaim di-approve, klaim lain yang aktif untuk item yang sama
     *   harus OTOMATIS berubah ke status 'closed_duplicate'.
     * - Proses dilakukan dalam SATU database transaction dengan row locking (FOR UPDATE).
     * - Catat penutupan otomatis di audit trail.
     *
     * @param int $claimId
     * @param int $petugasUserId
     * @param string $notes
     * @return array{success: bool, closed_duplicates: int}
     * @throws Exception
     */
    public function approveClaim(int $claimId, int $petugasUserId, string $notes = ''): array
    {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        try {
            // 1. Lock record klaim yang akan disetujui
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? FOR UPDATE");
            $stmt->execute([$claimId]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                throw new Exception("Klaim tidak ditemukan.");
            }

            if ($claim['status'] === 'approved') {
                throw new Exception("Klaim ini sudah disetujui sebelumnya.");
            }

            if ($claim['status'] === 'completed') {
                throw new Exception("Klaim ini sudah selesai diproses.");
            }

            $itemId        = !empty($claim['item_id']) ? (int) $claim['item_id'] : null;
            $foundReportId = !empty($claim['found_report_id']) ? (int) $claim['found_report_id'] : null;

            // Auto-resolve item_id dari found_reports jika klaim diajukan via laporan temuan
            if ($itemId === null && $foundReportId !== null) {
                $stmtFr = $pdo->prepare("SELECT item_id FROM found_reports WHERE id = ?");
                $stmtFr->execute([$foundReportId]);
                $frItemId = $stmtFr->fetchColumn();
                if ($frItemId) {
                    $itemId = (int) $frItemId;
                    $pdo->prepare("UPDATE claims SET item_id = ? WHERE id = ?")->execute([$itemId, $claimId]);
                    $claim['item_id'] = $itemId;
                }
            }

            // 2. Validasi ketat: pastikan item ini BELUM memiliki klaim lain yang berstatus Approved
            if ($itemId !== null) {
                $stmtCheck = $pdo->prepare("
                    SELECT id, claim_number FROM claims 
                    WHERE item_id = ? AND status = 'approved' AND id != ?
                    FOR UPDATE
                ");
                $stmtCheck->execute([$itemId, $claimId]);
                $existingApproved = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingApproved) {
                    throw new Exception("Item ini sudah memiliki klaim lain yang disetujui ({$existingApproved['claim_number']}). Batalkan persetujuan sebelumnya terlebih dahulu.");
                }
            }

            // 3. Update status klaim yang dipilih menjadi 'approved'
            $stmtApprove = $pdo->prepare("
                UPDATE claims SET 
                    status = 'approved',
                    reviewed_by_user_id = ?,
                    reviewed_at = NOW(),
                    reviewer_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtApprove->execute([$petugasUserId, $notes, $claimId]);

            // 4. Update status fisik item ke 'in_claim'
            if ($itemId !== null) {
                $pdo->prepare("UPDATE items SET status = 'in_claim', updated_at = NOW() WHERE id = ?")
                    ->execute([$itemId]);
            }

            // 5. Update status found_report ke 'claimed'
            if ($foundReportId !== null) {
                $pdo->prepare("UPDATE found_reports SET status = 'claimed', updated_at = NOW() WHERE id = ?")
                    ->execute([$foundReportId]);
            }

            // 6. ATURAN KLAIM GANDA: AUTO-CLOSE SELURUH KLAIM AKTIF LAINNYA UNTUK ITEM INI
            $closedDuplicatesCount = 0;
            if ($itemId !== null) {
                $stmtOther = $pdo->prepare("
                    SELECT id, claim_number, claimant_user_id, status FROM claims 
                    WHERE item_id = ? AND id != ? AND status IN ('pending', 'under_review', 'need_more_info')
                    FOR UPDATE
                ");
                $stmtOther->execute([$itemId, $claimId]);
                $otherClaims = $stmtOther->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($otherClaims)) {
                    $stmtClose = $pdo->prepare("
                        UPDATE claims SET 
                            status = 'closed_duplicate',
                            reviewer_notes = CONCAT(COALESCE(reviewer_notes, ''), '\n[Ditutup Otomatis — Item telah disetujui untuk klaim nomor ', ?, ']'),
                            reviewed_by_user_id = ?,
                            reviewed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ?
                    ");

                    foreach ($otherClaims as $other) {
                        $stmtClose->execute([$claim['claim_number'], $petugasUserId, $other['id']]);
                        $closedDuplicatesCount++;

                        // Catat status history untuk klaim yang ditutup otomatis
                        StatusHistoryService::record(
                            'claim',
                            (int) $other['id'],
                            $other['status'],
                            'closed_duplicate',
                            $petugasUserId,
                            "Ditutup otomatis karena klaim {$claim['claim_number']} telah disetujui petugas"
                        );

                        // Catat audit log penutupan otomatis klaim ganda
                        AuditLogService::log(
                            'CLAIM_AUTO_CLOSED_DUPLICATE',
                            $petugasUserId,
                            'claim',
                            (int) $other['id'],
                            [
                                'closed_claim_number'   => $other['claim_number'],
                                'approved_claim_id'     => $claimId,
                                'approved_claim_number' => $claim['claim_number'],
                                'item_id'               => $itemId,
                            ]
                        );
                    }
                }
            }

            // Catat status history klaim yang disetujui
            StatusHistoryService::record(
                'claim',
                $claimId,
                $claim['status'],
                'approved',
                $petugasUserId,
                $notes ?: 'Klaim disetujui oleh petugas'
            );

            // Catat audit log persetujuan klaim
            AuditLogService::log(
                'CLAIM_APPROVED',
                $petugasUserId,
                'claim',
                $claimId,
                [
                    'claim_number'           => $claim['claim_number'],
                    'item_id'                => $itemId,
                    'closed_duplicates_count' => $closedDuplicatesCount,
                ]
            );

            $pdo->commit();

            // ── Notifikasi SETELAH commit (di luar transaksi agar tidak di-rollback) ──

            // Notifikasi ke pemilik klaim yang disetujui
            NotificationService::notifyClaimStatusChanged(
                array_merge($claim, ['status' => $claim['status']]),
                $claim['status'],
                'approved'
            );

            // Notifikasi ke pemilik klaim yang ditutup otomatis
            if (!empty($otherClaims)) {
                foreach ($otherClaims as $other) {
                    NotificationService::notifyClaimStatusChanged(
                        array_merge($other, [
                            'item_title'   => $claim['item_title'] ?? 'Barang Temuan',
                            'claim_number' => $other['claim_number'],
                        ]),
                        $other['status'],
                        'closed_duplicate'
                    );
                }
            }

            return [
                'success'           => true,
                'closed_duplicates' => $closedDuplicatesCount,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * PROSES SERAH TERIMA FISIK BARANG (HANDOVER / PENGEMBALIAN KE PEMILIK)
     *
     * Setelah barang benar-benar dikembalikan:
     * - physical item status = 'returned', returned_at = $handoverDate, storage_location_id = NULL (kapasitas rak dilepas)
     * - found report status = 'completed' (Dikembalikan kepada Pemilik)
     * - claim status = 'completed'
     * - lost report terkait status = 'resolved' (Selesai / Ditemukan)
     * - Idempotent: jika sudah completed, aman dipanggil ulang tanpa duplikasi
     */
    public function completeHandover(
        int $claimId,
        int $petugasUserId,
        string $handoverDate,
        string $handoverNotes = ''
    ): bool {
        $pdo = $this->pdo;
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("SELECT * FROM claims WHERE id = ? FOR UPDATE");
            $stmt->execute([$claimId]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                throw new Exception("Klaim tidak ditemukan.");
            }

            // IDEMPOTENCY GUARD:
            // Jika klaim sudah selesai (completed), return true tanpa duplicate updates
            if ($claim['status'] === 'completed') {
                $pdo->rollBack();
                return true;
            }

            if ($claim['status'] !== 'approved') {
                throw new Exception("Hanya klaim dengan status 'Approved' yang dapat diproses serah terima barang.");
            }

            // 1. Update status klaim menjadi 'completed'
            $stmtHandover = $pdo->prepare("
                UPDATE claims SET 
                    status = 'completed',
                    handed_over_at = ?,
                    handed_over_by_user_id = ?,
                    handover_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmtHandover->execute([$handoverDate, $petugasUserId, $handoverNotes, $claimId]);

            // 2. Physical item status = 'returned', returned_at, storage_location_id = NULL (bebaskan rak)
            $itemId = !empty($claim['item_id']) ? (int) $claim['item_id'] : null;

            // Auto-resolve item_id dari found_reports jika tidak tercatat langsung di tabel claims
            if (!$itemId && !empty($claim['found_report_id'])) {
                $stmtFr = $pdo->prepare("SELECT item_id FROM found_reports WHERE id = ?");
                $stmtFr->execute([$claim['found_report_id']]);
                $frItemId = $stmtFr->fetchColumn();
                if ($frItemId) {
                    $itemId = (int) $frItemId;
                    $pdo->prepare("UPDATE claims SET item_id = ? WHERE id = ?")->execute([$itemId, $claimId]);
                    $claim['item_id'] = $itemId;
                }
            }

            // Auto-resolve item_id dari lost_reports jika ada
            if (!$itemId && !empty($claim['lost_report_id'])) {
                $stmtLr = $pdo->prepare("SELECT resolved_item_id FROM lost_reports WHERE id = ?");
                $stmtLr->execute([$claim['lost_report_id']]);
                $lrItemId = $stmtLr->fetchColumn();
                if ($lrItemId) {
                    $itemId = (int) $lrItemId;
                    $pdo->prepare("UPDATE claims SET item_id = ? WHERE id = ?")->execute([$itemId, $claimId]);
                    $claim['item_id'] = $itemId;
                }
            }

            $oldItemStatus = 'in_claim';
            if ($itemId) {
                $stmtItem = $pdo->prepare("SELECT id, status, storage_location_id FROM items WHERE id = ? FOR UPDATE");
                $stmtItem->execute([$itemId]);
                $itemRow = $stmtItem->fetch(PDO::FETCH_ASSOC);
                if ($itemRow && !empty($itemRow['status'])) {
                    $oldItemStatus = $itemRow['status'];
                }
                $oldStorage = $itemRow['storage_location_id'] ?? null;

                $pdo->prepare("
                    UPDATE items SET 
                        status = 'returned', 
                        returned_at = ?, 
                        storage_location_id = NULL, 
                        updated_at = NOW() 
                    WHERE id = ?
                ")->execute([$handoverDate, $itemId]);

                StatusHistoryService::record(
                    'item',
                    $itemId,
                    $oldItemStatus,
                    'returned',
                    $petugasUserId,
                    'Barang fisik telah diserahterimakan kepada pemilik sah (kapasitas rak dilepaskan): ' . $handoverNotes
                );
            }

            // 3. Found report status = 'completed' (Dikembalikan kepada Pemilik)
            $foundReport = null;
            $oldFoundStatus = 'claimed';
            if (!empty($claim['found_report_id'])) {
                $stmtFound = $pdo->prepare("SELECT * FROM found_reports WHERE id = ? FOR UPDATE");
                $stmtFound->execute([$claim['found_report_id']]);
                $foundReport = $stmtFound->fetch(PDO::FETCH_ASSOC);
                if ($foundReport && !empty($foundReport['status'])) {
                    $oldFoundStatus = $foundReport['status'];
                }

                $pdo->prepare("UPDATE found_reports SET status = 'completed', updated_at = NOW() WHERE id = ?")
                    ->execute([$claim['found_report_id']]);

                StatusHistoryService::record(
                    'found_report',
                    (int) $claim['found_report_id'],
                    $oldFoundStatus,
                    'completed',
                    $petugasUserId,
                    'Barang telah diserahkan kembali kepada pemilik melalui klaim ' . $claim['claim_number']
                );
            }

            // 4. Lost report terkait di-resolve (status = 'resolved' -> Selesai / Ditemukan)
            $lostReport = null;
            $oldLostStatus = 'claimed';
            $lostReportId = !empty($claim['lost_report_id']) ? (int) $claim['lost_report_id'] : null;

            // Fallback cari lost report jika belum di-link di tabel claims
            if (!$lostReportId && $itemId && !empty($claim['claimant_user_id'])) {
                $stmtFindLost = $pdo->prepare("SELECT id FROM lost_reports WHERE reporter_user_id = ? AND (resolved_item_id = ? OR resolved_item_id IS NULL) ORDER BY id DESC LIMIT 1");
                $stmtFindLost->execute([(int) $claim['claimant_user_id'], $itemId]);
                $foundLostId = $stmtFindLost->fetchColumn();
                if ($foundLostId) {
                    $lostReportId = (int) $foundLostId;
                }
            }

            if ($lostReportId) {
                $stmtLost = $pdo->prepare("SELECT * FROM lost_reports WHERE id = ? FOR UPDATE");
                $stmtLost->execute([$lostReportId]);
                $lostReport = $stmtLost->fetch(PDO::FETCH_ASSOC);
                if ($lostReport && !empty($lostReport['status'])) {
                    $oldLostStatus = $lostReport['status'];
                }

                $pdo->prepare("UPDATE lost_reports SET status = 'resolved', resolved_item_id = COALESCE(?, resolved_item_id), updated_at = NOW() WHERE id = ?")
                    ->execute([$itemId, $lostReportId]);

                StatusHistoryService::record(
                    'lost_report',
                    $lostReportId,
                    $oldLostStatus,
                    'resolved',
                    $petugasUserId,
                    'Barang hilang berhasil diserahterimakan kepada pemilik sah melalui klaim ' . $claim['claim_number']
                );
            }

            // 5. Catat status history klaim
            StatusHistoryService::record(
                'claim',
                $claimId,
                'approved',
                'completed',
                $petugasUserId,
                $handoverNotes ?: 'Serah terima barang selesai'
            );

            // 6. Catat audit log serah terima
            AuditLogService::log(
                'CLAIM_HANDOVER_COMPLETED',
                $petugasUserId,
                'claim',
                $claimId,
                [
                    'claim_number'   => $claim['claim_number'],
                    'item_id'        => $itemId,
                    'lost_report_id' => $lostReportId,
                    'handover_date'  => $handoverDate,
                    'handover_notes' => $handoverNotes,
                ]
            );

            $pdo->commit();

            // Notifikasi setelah commit
            try {
                NotificationService::notifyClaimStatusChanged(
                    array_merge($claim, ['status' => 'approved']),
                    'approved',
                    'completed'
                );
            } catch (Throwable $e) {
                error_log("[ClaimModel] notifyClaimStatusChanged error: " . $e->getMessage());
            }

            if ($lostReport) {
                try {
                    NotificationService::notifyLostReportStatusChanged($lostReport, $oldLostStatus, 'resolved');
                } catch (Throwable $e) {
                    error_log("[ClaimModel] notifyLostReportStatusChanged error: " . $e->getMessage());
                }
            }

            if ($foundReport) {
                try {
                    NotificationService::notifyFoundReportStatusChanged($foundReport, $oldFoundStatus, 'completed');
                } catch (Throwable $e) {
                    error_log("[ClaimModel] notifyFoundReportStatusChanged error: " . $e->getMessage());
                }
            }

            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
