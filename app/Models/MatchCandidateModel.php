<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;

/**
 * MatchCandidateModel — Pengelolaan data rekomendasi pencocokan barang (match_candidates)
 */
class MatchCandidateModel extends BaseModel
{
    /**
     * Simpan atau perbarui rekomendasi kandidat pencocokan (UPSERT)
     *
     * @param int $lostReportId
     * @param int $foundReportId
     * @param float $relevanceScore
     * @param string $explanation
     * @param string $source 'ai' | 'manual'
     * @return int ID match_candidate
     */
    public function saveCandidate(
        int $lostReportId,
        int $foundReportId,
        float $relevanceScore,
        string $explanation,
        string $source = 'ai'
    ): int {
        $sql = "
            INSERT INTO match_candidates (
                lost_report_id,
                found_report_id,
                relevance_score,
                matching_explanation,
                source,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE
                relevance_score = VALUES(relevance_score),
                matching_explanation = VALUES(matching_explanation),
                source = VALUES(source),
                created_at = NOW()
        ";

        $this->query($sql, [
            $lostReportId,
            $foundReportId,
            $relevanceScore,
            $explanation,
            $source,
        ]);

        $existing = $this->findPair($lostReportId, $foundReportId);
        return (int) ($existing['id'] ?? $this->lastInsertId());
    }

    /**
     * Cari kandidat berdasarkan pasangan lost_report_id dan found_report_id
     */
    public function findPair(int $lostReportId, int $foundReportId): ?array
    {
        $sql = "SELECT * FROM match_candidates WHERE lost_report_id = ? AND found_report_id = ? LIMIT 1";
        $result = $this->fetchOne($sql, [$lostReportId, $foundReportId]);
        return $result ?: null;
    }

    /**
     * Ambil semua kandidat kecocokan untuk suatu laporan kehilangan (lost_report)
     * Diberi join data laporan barang temuan (found_reports)
     */
    public function getCandidatesForLostReport(int $lostReportId, float $minScore = 0.0): array
    {
        $sql = "
            SELECT 
                mc.*,
                fr.report_number AS found_report_number,
                fr.title AS found_title,
                fr.description AS found_description,
                fr.primary_image AS found_image,
                fr.found_date,
                fr.found_time,
                fr.location_detail,
                fr.item_condition,
                fr.status AS found_status,
                c.name AS category_name,
                l.name AS location_name
            FROM match_candidates mc
            JOIN found_reports fr ON mc.found_report_id = fr.id
            LEFT JOIN categories c ON fr.category_id = c.id
            LEFT JOIN locations l ON fr.location_id = l.id
            WHERE mc.lost_report_id = ?
              AND mc.relevance_score >= ?
            ORDER BY mc.relevance_score DESC, mc.created_at DESC
        ";

        return $this->fetchAll($sql, [$lostReportId, $minScore]);
    }

    /**
     * Ambil semua kandidat kecocokan untuk suatu laporan temuan (found_report)
     */
    public function getCandidatesForFoundReport(int $foundReportId, float $minScore = 0.0): array
    {
        $sql = "
            SELECT 
                mc.*,
                lr.report_number AS lost_report_number,
                lr.title AS lost_title,
                lr.description AS lost_description,
                lr.primary_image AS lost_image,
                lr.lost_date,
                lr.lost_time,
                lr.location_detail,
                lr.status AS lost_status,
                lr.reporter_user_id AS lost_reporter_user_id,
                c.name AS category_name,
                l.name AS location_name
            FROM match_candidates mc
            JOIN lost_reports lr ON mc.lost_report_id = lr.id
            LEFT JOIN categories c ON lr.category_id = c.id
            LEFT JOIN locations l ON lr.last_seen_location_id = l.id
            WHERE mc.found_report_id = ?
              AND mc.relevance_score >= ?
            ORDER BY mc.relevance_score DESC, mc.created_at DESC
        ";

        return $this->fetchAll($sql, [$foundReportId, $minScore]);
    }

    /**
     * Update status kandidat oleh Petugas (misal: 'reviewed', 'confirmed_match', 'rejected')
     */
    public function updateStatus(int $id, string $status, int $reviewerUserId): bool
    {
        $sql = "
            UPDATE match_candidates
            SET status = ?,
                reviewed_by_user_id = ?
            WHERE id = ?
        ";

        return $this->query($sql, [$status, $reviewerUserId, $id])->rowCount() > 0;
    }
}
