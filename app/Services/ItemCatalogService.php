<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use App\Models\SearchModel;
use PDO;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * ItemCatalogService — Single Source of Truth untuk Katalog Barang, Inventaris Fisik, & Metrik
 *
 * Digunakan bersama oleh:
 * 1. Halaman Publik Cari Barang (SearchController)
 * 2. Chatbot C-Find (Prompt 28)
 * 3. Panel Dashboard / Operasional
 *
 * Menghilangkan inkonsistensi data antara pencarian katalog publik dan inventaris rak center.
 */
class ItemCatalogService
{
    private PDO $pdo;
    private SearchModel $searchModel;
    private DateTimeZone $timezone;

    public function __construct(?PDO $pdo = null, ?SearchModel $searchModel = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
        $this->searchModel = $searchModel ?? new SearchModel();
        $this->timezone = new DateTimeZone('Asia/Jakarta');
    }

    /**
     * Dapatkan rentang waktu [start, end] berdasarkan zona Asia/Jakarta
     *
     * @param string $range 'today' | 'this_week' | 'this_month' | 'all'
     * @return array{start: ?string, end: ?string}
     */
    public function getTimeRangeBounds(string $range): array
    {
        $now = new DateTimeImmutable('now', $this->timezone);

        return match ($range) {
            'today' => [
                'start' => $now->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            'this_week' => [
                'start' => $now->modify('this week monday')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->modify('this week sunday')->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            'this_month' => [
                'start' => $now->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
                'end'   => $now->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
            ],
            default => [
                'start' => null,
                'end'   => null,
            ],
        };
    }

    // ─────────────────────────────────────────────────────────────
    // 1. KATALOG PUBLIK (Cari Barang & Status Publik)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hitung total laporan aktif di Katalog Publik (sesuai definisi SearchModel)
     *
     * @param array<string, mixed> $filters
     */
    public function getPublicCatalogCount(array $filters = []): int
    {
        return $this->searchModel->count($filters);
    }

    /**
     * Cari laporan aktif di Katalog Publik
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function searchPublicCatalog(array $filters = [], int $limit = 12, int $offset = 0): array
    {
        return $this->searchModel->search($filters, $limit, $offset);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. INVENTARIS FISIK RAK POSKO (Gudang Lost & Found Center)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hitung jumlah barang fisik aktif di rak gudang posko (menggunakan status kanonik POSKO_ITEM_STATUSES)
     */
    public function getStoredItemsCount(?int $storageLocationId = null, bool $onlyStored = false): int
    {
        if ($onlyStored) {
            $sql = "SELECT COUNT(*) FROM items WHERE status = 'stored'";
            $params = [];
        } else {
            $statuses = \App\Services\MetricDefinitionService::getPoskoItemStatuses();
            $placeholders = implode("', '", $statuses);
            $sql = "SELECT COUNT(*) FROM items WHERE status IN ('{$placeholders}')";
            $params = [];
        }

        if ($storageLocationId !== null && $storageLocationId > 0) {
            $sql .= " AND storage_location_id = ?";
            $params[] = $storageLocationId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Hitung seluruh barang fisik aktif di posko (tersimpan, proses klaim, siap serah terima, registrasi, inspeksi)
     * Single Source of Truth: MetricDefinitionService::POSKO_ITEM_STATUSES
     */
    public function getAllActiveInventoryCount(): int
    {
        $statuses = \App\Services\MetricDefinitionService::getPoskoItemStatuses();
        $placeholders = implode("', '", $statuses);
        $sql = "SELECT COUNT(*) FROM items WHERE status IN ('{$placeholders}')";
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * Hitung barang yang berhasil dikembalikan ke pemilik sah (status = 'returned')
     */
    public function getReturnedItemsCount(): int
    {
        $sql = "SELECT COUNT(*) FROM items WHERE status = 'returned'";
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    /**
     * Hitung barang temuan yang menunggu penyerahan fisik / verifikasi intake
     */
    public function getPendingHandoverCount(): int
    {
        $sql = "SELECT COUNT(*) FROM found_reports WHERE status IN ('pending_handover', 'published') AND item_id IS NULL";
        return (int) $this->pdo->query($sql)->fetchColumn();
    }

    // ─────────────────────────────────────────────────────────────
    // 3. KLAIM KEPEMILIKAN (Claims)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hitung klaim menunggu verifikasi / perlu tindakan
     *
     * @param string $range 'today' | 'this_week' | 'this_month' | 'all'
     * @param int|null $userId Filter pemilik klaim (jika Civitas)
     */
    public function getPendingClaimsCount(string $range = 'all', ?int $userId = null): int
    {
        $bounds = $this->getTimeRangeBounds($range);
        $where = ["status IN ('pending', 'under_review', 'need_more_info')"];
        $params = [];

        if ($userId !== null) {
            $where[] = "claimant_user_id = ?";
            $params[] = $userId;
        }

        if ($bounds['start'] !== null && $bounds['end'] !== null) {
            $where[] = "created_at >= ? AND created_at <= ?";
            $params[] = $bounds['start'];
            $params[] = $bounds['end'];
        }

        $sql = "SELECT COUNT(*) FROM claims WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Hitung total klaim yang diajukan (berdasarkan rentang waktu & filter role)
     *
     * @param string $range 'today' | 'this_week' | 'this_month' | 'all'
     * @param int|null $userId
     * @param string|null $status Spesifik status (misal 'approved', 'completed')
     */
    public function getTotalClaimsCount(string $range = 'all', ?int $userId = null, ?string $status = null): int
    {
        $bounds = $this->getTimeRangeBounds($range);
        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = "claimant_user_id = ?";
            $params[] = $userId;
        }

        if (!empty($status) && $status !== 'all') {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if ($bounds['start'] !== null && $bounds['end'] !== null) {
            $where[] = "created_at >= ? AND created_at <= ?";
            $params[] = $bounds['start'];
            $params[] = $bounds['end'];
        }

        $whereClause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
        $sql = "SELECT COUNT(*) FROM claims" . $whereClause;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil daftar klaim ringkas (Maksimal N baris + sisa)
     *
     * @param string $role 'civitas' | 'petugas' | 'admin' | 'guest'
     * @param int|null $userId
     * @param string $range
     * @param string|null $status
     * @param int $limit
     * @return array{total: int, items: array<int, array<string, mixed>>, remainder: int}
     */
    public function getClaimsList(
        string $role,
        ?int $userId = null,
        string $range = 'all',
        ?string $status = null,
        int $limit = 5
    ): array {
        if ($role === 'guest') {
            return ['total' => 0, 'items' => [], 'remainder' => 0];
        }

        $bounds = $this->getTimeRangeBounds($range);
        $where = [];
        $params = [];

        // Civitas HANYA boleh membaca klaim miliknya sendiri; Petugas & Admin melihat antrean operasional
        if ($role === 'civitas' && $userId !== null) {
            $where[] = "c.claimant_user_id = ?";
            $params[] = $userId;
        }

        if (!empty($status) && $status !== 'all') {
            if ($status === 'pending_action') {
                $where[] = "c.status IN ('pending', 'under_review', 'need_more_info')";
            } else {
                $where[] = "c.status = ?";
                $params[] = $status;
            }
        }

        if ($bounds['start'] !== null && $bounds['end'] !== null) {
            $where[] = "c.created_at >= ? AND c.created_at <= ?";
            $params[] = $bounds['start'];
            $params[] = $bounds['end'];
        }

        $whereClause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";

        // Hitung total
        $countSql = "SELECT COUNT(*) FROM claims c" . $whereClause;
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        if ($total === 0) {
            return ['total' => 0, 'items' => [], 'remainder' => 0];
        }

        // Ambil data (JOIN user untuk Petugas/Admin, TANPA data bukti rahasia)
        $dataSql = "
            SELECT 
                c.id,
                c.claim_number,
                c.status,
                c.created_at,
                u.name AS claimant_name,
                i.item_code,
                COALESCE(fr.title, i.title, 'Barang Temuan') AS item_title
            FROM claims c
            LEFT JOIN users u ON c.claimant_user_id = u.id
            LEFT JOIN items i ON c.item_id = i.id
            LEFT JOIN found_reports fr ON c.found_report_id = fr.id
            {$whereClause}
            ORDER BY c.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->pdo->prepare($dataSql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Jika Civitas: sembunyikan nama user lain
        if ($role === 'civitas') {
            foreach ($items as &$item) {
                unset($item['claimant_name']);
            }
        }

        $remainder = max(0, $total - count($items));

        return [
            'total'     => $total,
            'items'     => $items,
            'remainder' => $remainder,
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // 4. LAPORAN KEHILANGAN & PENEMUAN (Reports)
    // ─────────────────────────────────────────────────────────────

    /**
     * Hitung jumlah laporan (kehilangan, penemuan, atau keduanya)
     *
     * @param string $type 'all' | 'lost' | 'found'
     * @param string $range 'today' | 'this_week' | 'this_month' | 'all'
     * @param int|null $userId
     */
    public function getReportsCount(string $type = 'all', string $range = 'all', ?int $userId = null): int
    {
        $bounds = $this->getTimeRangeBounds($range);
        $total = 0;

        if ($type !== 'found') {
            $where = [];
            $params = [];
            if ($userId !== null) {
                $where[] = "reporter_user_id = ?";
                $params[] = $userId;
            }
            if ($bounds['start'] !== null && $bounds['end'] !== null) {
                $where[] = "created_at >= ? AND created_at <= ?";
                $params[] = $bounds['start'];
                $params[] = $bounds['end'];
            }
            $whereClause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM lost_reports" . $whereClause);
            $stmt->execute($params);
            $total += (int) $stmt->fetchColumn();
        }

        if ($type !== 'lost') {
            $where = [];
            $params = [];
            if ($userId !== null) {
                $where[] = "reporter_user_id = ?";
                $params[] = $userId;
            }
            if ($bounds['start'] !== null && $bounds['end'] !== null) {
                $where[] = "created_at >= ? AND created_at <= ?";
                $params[] = $bounds['start'];
                $params[] = $bounds['end'];
            }
            $whereClause = !empty($where) ? " WHERE " . implode(' AND ', $where) : "";
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM found_reports" . $whereClause);
            $stmt->execute($params);
            $total += (int) $stmt->fetchColumn();
        }

        return $total;
    }

    // ─────────────────────────────────────────────────────────────
    // 5. AGREGAT SISTEM ADMIN (Admin Only)
    // ─────────────────────────────────────────────────────────────

    /**
     * Agregat statistik sistem untuk Administrator
     */
    public function getAggregatedAdminStats(): array
    {
        $userCounts = $this->pdo->query("
            SELECT r.name AS role_name, COUNT(u.id) AS count
            FROM roles r
            LEFT JOIN users u ON u.role_id = r.id
            GROUP BY r.id, r.name
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $totalUsers = (int) array_sum($userCounts);
        $totalLostReports = (int) $this->pdo->query("SELECT COUNT(*) FROM lost_reports")->fetchColumn();
        $totalFoundReports = (int) $this->pdo->query("SELECT COUNT(*) FROM found_reports")->fetchColumn();
        $totalClaims = (int) $this->pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
        $totalItems = (int) $this->pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $totalStored = (int) $this->pdo->query("SELECT COUNT(*) FROM items WHERE status = 'stored'")->fetchColumn();
        $totalReturned = (int) $this->pdo->query("SELECT COUNT(*) FROM items WHERE status = 'returned'")->fetchColumn();

        return [
            'total_users'     => $totalUsers,
            'users_by_role'   => $userCounts,
            'lost_reports'    => $totalLostReports,
            'found_reports'   => $totalFoundReports,
            'total_claims'    => $totalClaims,
            'total_items'     => $totalItems,
            'stored_items'    => $totalStored,
            'returned_items'  => $totalReturned,
        ];
    }
}
