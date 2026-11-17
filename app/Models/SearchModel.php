<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use PDO;

/**
 * SearchModel — Unified public search across lost_reports and found_reports
 *
 * Privacy contract: This model NEVER exposes private_details, reporter PII,
 * internal_notes, location_detail, or item_code to the public result set.
 */
class SearchModel extends BaseModel
{
    /** Allowed sort options — whitelist prevents SQL injection */
    private const SORT_WHITELIST = [
        'newest'     => 'date DESC, r.created_at DESC',
        'oldest'     => 'date ASC, r.created_at ASC',
        'date_asc'   => 'date ASC',
        'date_desc'  => 'date DESC',
    ];

    /** Public statuses for lost reports */
    private const LOST_PUBLIC_STATUSES = ['published'];

    /** Public statuses for found reports */
    private const FOUND_PUBLIC_STATUSES = [
        'published', 'pending_handover', 'handed_over_to_center', 'verified',
    ];

    /**
     * Execute unified search with full filter support.
     *
     * @param array<string,mixed> $filters {
     *   q?:         string   keyword (searches title + description only — no private data)
     *   type?:      string   'lost' | 'found' | 'all' (default 'all')
     *   category?:  int      category ID
     *   location?:  int      location ID
     *   date_from?: string   Y-m-d
     *   date_to?:   string   Y-m-d
     *   sort?:      string   key from SORT_WHITELIST (default 'newest')
     * }
     * @param int $limit
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    public function search(array $filters, int $limit = 12, int $offset = 0): array
    {
        [$sql, $params] = $this->buildQuery($filters, false);

        $sortKey = $filters['sort'] ?? 'newest';
        $orderBy = self::SORT_WHITELIST[$sortKey] ?? self::SORT_WHITELIST['newest'];

        $sql .= " ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Count total results for pagination.
     *
     * @param array<string,mixed> $filters
     */
    public function count(array $filters): int
    {
        [$innerSql, $params] = $this->buildQuery($filters, false);
        $countSql = "SELECT COUNT(*) FROM ({$innerSql}) AS total_count";
        $stmt = $this->query($countSql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Build the UNION ALL query across lost_reports and found_reports.
     * Privacy-safe: only public columns selected.
     *
     * @param array<string,mixed> $filters
     * @param bool $countOnly unused, kept for signature compatibility
     * @return array{0: string, 1: array<mixed>}
     */
    private function buildQuery(array $filters, bool $countOnly): array
    {
        $type     = $filters['type']      ?? 'all';
        $keyword  = trim((string) ($filters['q']        ?? ''));
        $catId    = isset($filters['category'])  ? (int) $filters['category']  : null;
        $locId    = isset($filters['location'])  ? (int) $filters['location']  : null;
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo   = trim((string) ($filters['date_to']   ?? ''));

        $parts  = [];
        $params = [];

        // ── LOST REPORTS branch ──────────────────────────────────────────────
        if ($type !== 'found') {
            [$lostSql, $lostParams] = $this->buildLostBranch($keyword, $catId, $locId, $dateFrom, $dateTo);
            $parts[]  = $lostSql;
            $params   = array_merge($params, $lostParams);
        }

        // ── FOUND REPORTS branch ─────────────────────────────────────────────
        if ($type !== 'lost') {
            [$foundSql, $foundParams] = $this->buildFoundBranch($keyword, $catId, $locId, $dateFrom, $dateTo);
            $parts[]  = $foundSql;
            $params   = array_merge($params, $foundParams);
        }

        if (empty($parts)) {
            return ['SELECT NULL WHERE 1=0', []];
        }

        $sql = '(' . implode(') UNION ALL (', $parts) . ')';
        $sql = "SELECT * FROM ({$sql}) AS r";

        return [$sql, $params];
    }

    /**
     * Lost reports sub-query — privacy-safe columns only.
     *
     * @return array{0:string, 1:array<mixed>}
     */
    private function buildLostBranch(
        string $keyword,
        ?int $catId,
        ?int $locId,
        string $dateFrom,
        string $dateTo
    ): array {
        $statusIn = implode(', ', array_map(fn($s) => "'$s'", self::LOST_PUBLIC_STATUSES));

        $where  = ["lr.status IN ({$statusIn})"];
        $params = [];

        if ($keyword !== '') {
            // ONLY title and description — never private_details or location_detail
            $where[]  = '(lr.title LIKE ? OR lr.description LIKE ?)';
            $term     = '%' . $keyword . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($catId !== null && $catId > 0) {
            $where[]  = 'lr.category_id = ?';
            $params[] = $catId;
        }

        if ($locId !== null && $locId > 0) {
            $where[]  = 'lr.last_seen_location_id = ?';
            $params[] = $locId;
        }

        if ($dateFrom !== '') {
            $where[]  = 'lr.lost_date >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[]  = 'lr.lost_date <= ?';
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $where);

        // Privacy contract: SELECT only safe public columns
        $sql = "
            SELECT
                'lost'                  AS type,
                lr.id                   AS id,
                lr.report_number        AS report_number,
                lr.title                AS title,
                lr.description          AS description,
                lr.primary_image        AS primary_image,
                lr.lost_date            AS date,
                NULL                    AS item_condition,
                lr.status               AS status,
                lr.created_at           AS created_at,
                c.id                    AS category_id,
                c.name                  AS category_name,
                c.slug                  AS category_slug,
                c.icon                  AS category_icon,
                l.id                    AS location_id,
                l.name                  AS location_name
            FROM lost_reports lr
            JOIN categories c  ON lr.category_id          = c.id
            JOIN locations  l  ON lr.last_seen_location_id = l.id
            WHERE {$whereClause}
        ";

        return [$sql, $params];
    }

    /**
     * Found reports sub-query — privacy-safe columns only.
     *
     * @return array{0:string, 1:array<mixed>}
     */
    private function buildFoundBranch(
        string $keyword,
        ?int $catId,
        ?int $locId,
        string $dateFrom,
        string $dateTo
    ): array {
        $statusIn = implode(', ', array_map(fn($s) => "'$s'", self::FOUND_PUBLIC_STATUSES));

        $where  = ["fr.status IN ({$statusIn})"];
        $params = [];

        if ($keyword !== '') {
            $where[]  = '(fr.title LIKE ? OR fr.description LIKE ?)';
            $term     = '%' . $keyword . '%';
            $params[] = $term;
            $params[] = $term;
        }

        if ($catId !== null && $catId > 0) {
            $where[]  = 'fr.category_id = ?';
            $params[] = $catId;
        }

        if ($locId !== null && $locId > 0) {
            $where[]  = 'fr.location_id = ?';
            $params[] = $locId;
        }

        if ($dateFrom !== '') {
            $where[]  = 'fr.found_date >= ?';
            $params[] = $dateFrom;
        }

        if ($dateTo !== '') {
            $where[]  = 'fr.found_date <= ?';
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $where);

        // Privacy contract: SELECT only safe public columns
        $sql = "
            SELECT
                'found'                 AS type,
                fr.id                   AS id,
                fr.report_number        AS report_number,
                fr.title                AS title,
                fr.description          AS description,
                fr.primary_image        AS primary_image,
                fr.found_date           AS date,
                fr.item_condition       AS item_condition,
                fr.status               AS status,
                fr.created_at           AS created_at,
                c.id                    AS category_id,
                c.name                  AS category_name,
                c.slug                  AS category_slug,
                c.icon                  AS category_icon,
                l.id                    AS location_id,
                l.name                  AS location_name
            FROM found_reports fr
            JOIN categories c ON fr.category_id = c.id
            JOIN locations  l ON fr.location_id  = l.id
            WHERE {$whereClause}
        ";

        return [$sql, $params];
    }

    /**
     * Human-readable status label for display on public search results.
     * Only maps to public-visible statuses.
     */
    public static function getPublicStatusLabel(string $type, string $status): string
    {
        if ($type === 'lost') {
            return match ($status) {
                'published' => 'Laporan Baru',
                'matched'   => 'Terindikasi Cocok',
                'claimed'   => 'Dalam Proses Klaim',
                default     => 'Aktif',
            };
        }

        // found
        return match ($status) {
            'published'             => 'Laporan Baru',
            'pending_handover'      => 'Menunggu Penyerahan',
            'handed_over_to_center' => 'Di Tangan Petugas',
            'verified'              => 'Tersimpan di L&F Center',
            'claimed'               => 'Dalam Proses Klaim',
            default                 => 'Aktif',
        };
    }

    /**
     * CSS badge class for type pills on search results.
     */
    public static function getTypeBadgeClass(string $type): string
    {
        return $type === 'lost' ? 'cf-badge-lost' : 'cf-badge-found';
    }

    /**
     * Detail URL for a result row.
     */
    public static function getDetailUrl(string $type, int $id): string
    {
        return $type === 'lost'
            ? "/reports/lost/{$id}"
            : "/reports/found/{$id}";
    }
}
