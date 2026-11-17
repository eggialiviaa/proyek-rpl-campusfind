<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MatchCandidateModel;
use App\Models\LostReportModel;
use App\Models\FoundReportModel;

/**
 * AiMatchService — Orchestrator Layanan Pencocokan AI untuk Campus Find (Prompt 35D)
 *
 * Menerapkan:
 * 1. Pra-seleksi kandidat berbasis aturan ketat (kategori, jendela waktu, lokasi, status temuan layak)
 * 2. Skor teks sederhana (baseline/pembanding) untuk mengurutkan kandidat teratas
 * 3. Penilaian AI terstruktur (JSON: id, score 0-100, reason) untuk kandidat teratas
 * 4. Pemisahan tegas: "Belum ada temuan untuk dibandingkan" ≠ "Layanan AI gagal/lambat" ≠ "Tidak ada kecocokan"
 * 5. Log diagnostik lengkap per pencocokan (tanpa data privat/rahasia)
 * 6. Ambang (threshold) dinamis yang dapat diatur oleh Admin
 * 7. Perlindungan foto & data privat: foto dan data rahasia TIDAK PERNAH dikirim ke penyedia AI
 */
class AiMatchService
{
    private AiProviderInterface $provider;
    private MatchCandidateModel $candidateModel;
    private LostReportModel $lostReportModel;
    private FoundReportModel $foundReportModel;

    public function __construct(?AiProviderInterface $provider = null)
    {
        if ($provider !== null) {
            $this->provider = $provider;
        } else {
            $resolved = AiProviderManager::resolveFeatureProvider('matching');
            if ($resolved->isAvailable()) {
                $this->provider = $resolved;
            } else {
                $this->provider = new MockAiMatchService();
            }
        }

        $this->candidateModel = new MatchCandidateModel();
        $this->lostReportModel = new LostReportModel();
        $this->foundReportModel = new FoundReportModel();
    }

    /**
     * Dapatkan nama provider AI yang sedang aktif
     */
    public function getActiveProviderName(): string
    {
        if ($this->provider instanceof NvidiaNimService) {
            return 'NVIDIA Hosted API Catalog';
        }
        if ($this->provider instanceof GoogleGeminiService) {
            return 'Google Gemini REST API';
        }
        return 'Local Algorithmic Fallback';
    }

    /**
     * Jalankan proses AI Matching untuk 1 Laporan Kehilangan (Lost Report)
     *
     * @param int $lostReportId
     * @return array{
     *     result_status: 'match_found'|'no_match'|'no_candidates'|'ai_failed',
     *     candidates: array,
     *     diagnostic: array,
     *     threshold_applied: float,
     *     matches_count: int
     * }
     */
    public function runMatchingForLostReport(int $lostReportId): array
    {
        $lostReport = $this->lostReportModel->findById($lostReportId);
        if (!$lostReport) {
            return [
                'result_status'     => 'no_candidates',
                'candidates'        => [],
                'diagnostic'        => ['call_status' => 'no_candidates', 'result_status' => 'no_candidates'],
                'threshold_applied' => (float) SettingService::get('ai_matching_threshold', 60),
                'matches_count'     => 0,
            ];
        }

        // Validasi kelayakan status laporan kehilangan (hanya status eligible yang dapat diproses)
        $eligibleLostStatuses = ['published', 'investigating', 'matched'];
        if (!in_array($lostReport['status'] ?? '', $eligibleLostStatuses, true)) {
            return [
                'result_status'     => 'ineligible_status',
                'candidates'        => [],
                'diagnostic'        => [
                    'call_status'   => 'ineligible_status',
                    'result_status' => 'ineligible_status',
                    'error'         => "Status laporan '{$lostReport['status']}' tidak memenuhi syarat untuk pencocokan AI.",
                ],
                'threshold_applied' => (float) SettingService::get('ai_matching_threshold', 60),
                'matches_count'     => 0,
            ];
        }

        // Ambil seluruh kandidat temuan (pool) dengan join kategori dan lokasi
        $poolReports = $this->fetchFoundReportsPool();
        return $this->executeMatchingPipeline($lostReport, $poolReports, 'lost');
    }

    /**
     * Jalankan proses AI Matching untuk 1 Laporan Barang Temuan (Found Report)
     *
     * @param int $foundReportId
     * @return array{
     *     result_status: 'match_found'|'no_match'|'no_candidates'|'ai_failed'|'completed_fallback'|'provider_timeout'|'provider_error'|'ineligible_status',
     *     candidates: array,
     *     diagnostic: array,
     *     threshold_applied: float,
     *     matches_count: int
     * }
     */
    public function runMatchingForFoundReport(int $foundReportId): array
    {
        $foundReport = $this->foundReportModel->findById($foundReportId);
        if (!$foundReport) {
            return [
                'result_status'     => 'no_candidates',
                'candidates'        => [],
                'diagnostic'        => ['call_status' => 'no_candidates', 'result_status' => 'no_candidates'],
                'threshold_applied' => (float) SettingService::get('ai_matching_threshold', 60),
                'matches_count'     => 0,
            ];
        }

        // Validasi kelayakan status laporan temuan
        $eligibleFoundStatuses = ['published', 'pending_handover', 'handed_over_to_center', 'verified'];
        if (!in_array($foundReport['status'] ?? '', $eligibleFoundStatuses, true)) {
            return [
                'result_status'     => 'ineligible_status',
                'candidates'        => [],
                'diagnostic'        => [
                    'call_status'   => 'ineligible_status',
                    'result_status' => 'ineligible_status',
                    'error'         => "Status laporan '{$foundReport['status']}' tidak memenuhi syarat untuk pencocokan AI.",
                ],
                'threshold_applied' => (float) SettingService::get('ai_matching_threshold', 60),
                'matches_count'     => 0,
            ];
        }

        // Ambil seluruh kandidat kehilangan (pool) dengan join kategori dan lokasi
        $poolReports = $this->fetchLostReportsPool();
        return $this->executeMatchingPipeline($foundReport, $poolReports, 'found');
    }

    /**
     * Eksekusi pipeline pencocokan terpadu (Prompt 35D)
     */
    private function executeMatchingPipeline(array $targetReport, array $poolReports, string $targetType): array
    {
        $targetId = (int) $targetReport['id'];
        $threshold = (float) SettingService::get('ai_matching_threshold', 60);
        $minSaveScore = (float) SettingService::get('ai_matching_min_save', 30);
        $highNotifyScore = (float) SettingService::get('ai_matching_high_notify_score', 70);

        // ══════════════════════════════════════════════════════════════
        // TAHAP 1: PRA-SELEKSI KANDIDAT BERBASIS ATURAN (RULE PRE-SELECTION)
        // ══════════════════════════════════════════════════════════════
        $eligibleLostStatuses = ['published', 'investigating', 'matched'];
        $eligibleFoundStatuses = ['published', 'pending_handover', 'handed_over_to_center', 'verified'];
        $preselected = [];
        $eliminated = [
            'ineligible_status' => 0,
            'category_mismatch' => 0,
            'date_out_of_range' => 0,
        ];

        foreach ($poolReports as $cand) {
            $candStatus = (string) ($cand['status'] ?? '');

            // Aturan 1: Status temuan/laporan harus layak
            $isCandEligible = ($targetType === 'lost')
                ? in_array($candStatus, $eligibleFoundStatuses, true)
                : in_array($candStatus, $eligibleLostStatuses, true);

            if (!$isCandEligible) {
                $eliminated['ineligible_status']++;
                continue;
            }

            // Aturan 2: Kategori harus sama persis
            $candCatId = (int) ($cand['category_id'] ?? 0);
            $targetCatId = (int) ($targetReport['category_id'] ?? 0);
            if ($candCatId <= 0 || $targetCatId <= 0 || $candCatId !== $targetCatId) {
                $eliminated['category_mismatch']++;
                continue;
            }

            // Aturan 3: Jendela waktu / rentang tanggal masuk akal
            $targetDate = $targetReport['lost_date'] ?? $targetReport['found_date'] ?? null;
            $candDate = $cand['found_date'] ?? $cand['lost_date'] ?? null;

            if ($targetDate && $candDate) {
                $lostTime = ($targetType === 'lost') ? strtotime($targetDate) : strtotime($candDate);
                $foundTime = ($targetType === 'lost') ? strtotime($candDate) : strtotime($targetDate);

                if ($lostTime && $foundTime) {
                    $diffDays = ($foundTime - $lostTime) / 86400;
                    // Barang temuan tidak boleh lebih awal dari 1 hari sebelum hilang (toleransi pelaporan)
                    // dan tidak boleh lebih dari 60 hari setelah hilang
                    if ($diffDays < -1 || $diffDays > 60) {
                        $eliminated['date_out_of_range']++;
                        continue;
                    }
                }
            }

            // Kandidat lolos pra-seleksi: hitung skor teks sederhana (baseline score)
            $baseline = $this->calculateBaselineScore(
                ($targetType === 'lost') ? $targetReport : $cand,
                ($targetType === 'lost') ? $cand : $targetReport
            );
            $cand['baseline_score'] = $baseline['score'];
            $cand['baseline_explanation'] = $baseline['explanation'];
            $preselected[] = $cand;
        }

        // KASUS 1: Tidak ada kandidat yang lolos pra-seleksi ("Belum ada barang temuan yang bisa dibandingkan")
        if (empty($preselected)) {
            $diagnostic = [
                'target_report_id'     => $targetId,
                'target_type'          => $targetType,
                'pool_count'           => count($poolReports),
                'preselected_count'    => 0,
                'eliminated_reasons'   => $eliminated,
                'model_used'           => $this->getActiveProviderName(),
                'duration_ms'          => 0,
                'call_status'          => 'no_candidates',
                'raw_scores'           => [],
                'threshold_applied'    => $threshold,
                'result_status'        => 'no_candidates',
                'matches_count'        => 0,
                'public_text_sent'     => [
                    'target_summary'     => ($targetReport['title'] ?? '-') . " (Kategori: " . ($targetReport['category_name'] ?? '-') . ")",
                    'candidates_summary' => [],
                ],
            ];

            $_SESSION['ai_match_diagnostic'] = $diagnostic;
            AuditLogService::log('ai_matching_executed', ($targetType === 'lost' ? 'lost_reports' : 'found_reports'), $targetId, $diagnostic);

            return [
                'result_status'     => 'no_candidates',
                'candidates'        => [],
                'diagnostic'        => $diagnostic,
                'threshold_applied' => $threshold,
                'matches_count'     => 0,
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // TAHAP 2: SKOR TEKS SEDERHANA & SELEKSI TOP KANDIDAT
        // ══════════════════════════════════════════════════════════════
        usort($preselected, fn($a, $b) => ($b['baseline_score'] <=> $a['baseline_score']));
        $topCandidates = array_slice($preselected, 0, 5);

        // ══════════════════════════════════════════════════════════════
        // TAHAP 3: PENILAIAN AI DENGAN KELUARAN JSON TERSTRUKTUR
        // ══════════════════════════════════════════════════════════════
        $aiEval = $this->provider->evaluateCandidates($targetReport, $topCandidates, $targetType);

        $callStatus = $aiEval['status'] ?? 'fallback_local';
        $parseStatus = $aiEval['parse_status'] ?? ($callStatus === 'success' ? 'parsed' : 'failed');
        $modelUsed = $aiEval['model'] ?? $this->getActiveProviderName();
        $durationMs = (int) ($aiEval['duration_ms'] ?? 0);
        $aiScores = $aiEval['scores'] ?? [];
        $rawScores = [];
        $savedResults = [];

        if ($callStatus === 'model_not_found') {
            error_log("[AiMatchService::executeMatchingPipeline] Model '{$modelUsed}' tidak ditemukan pada provider (HTTP 404). Kemungkinan model sudah tidak tersedia atau telah di-sunset oleh provider. Silakan periksa Pengaturan AI.");
        }

        // Syarat Ketat Task 2B.1:
        // AiMatchService WAJIB TIDAK menyimpan hasil pencocokan atau memicu notifikasi jika parse_status bukan 'parsed'
        if ($parseStatus !== 'parsed') {
            $parseReason = $aiEval['error'] ?? 'Parsing JSON gagal, tidak lengkap, atau tidak valid';
            error_log("[AiMatchService::executeMatchingPipeline] Status parsing model adalah '{$parseStatus}' (call_status: '{$callStatus}'). Tidak ada hasil yang disimpan atau notifikasi yang dikirim. Alasan: {$parseReason}");
        } else {
            $reporterUserId = (int) ($targetType === 'lost'
                ? ($targetReport['reporter_user_id'] ?? 0)
                : ($topCandidates[0]['reporter_user_id'] ?? 0)
            );

            foreach ($topCandidates as $cand) {
                $candId = (int) $cand['id'];

                if (isset($aiScores[$candId]['score'])) {
                    $finalScore = (float) $aiScores[$candId]['score'];
                    $finalExplanation = (string) $aiScores[$candId]['reason'];
                    $source = 'ai';

                    $rawScores[$candId] = $finalScore;

                    if ($finalScore >= $minSaveScore) {
                        $lostId = ($targetType === 'lost') ? $targetId : $candId;
                        $foundId = ($targetType === 'lost') ? $candId : $targetId;

                        $cId = $this->candidateModel->saveCandidate(
                            $lostId,
                            $foundId,
                            $finalScore,
                            $finalExplanation,
                            $source
                        );

                        if ($finalScore >= $highNotifyScore && $reporterUserId > 0) {
                            NotificationService::notifyMatchCandidateFound(
                                $lostId,
                                $reporterUserId,
                                $cand['title'] ?? 'Barang Temuan',
                                $finalScore
                            );
                        }

                        $savedResults[] = [
                            'candidate_id'    => $cId,
                            'found_report_id' => $foundId,
                            'lost_report_id'  => $lostId,
                            'title'           => $cand['title'],
                            'relevance_score' => $finalScore,
                            'explanation'     => $finalExplanation,
                            'is_match'        => ($finalScore >= $threshold),
                        ];
                    }
                }
            }
        }

        // ══════════════════════════════════════════════════════════════
        // TAHAP 4: PEMISAHAN LOGIKA HASIL (LOGICAL CLASSIFICATION)
        // ══════════════════════════════════════════════════════════════
        $matchesCount = count(array_filter($savedResults, fn($r) => $r['relevance_score'] >= $threshold));
        $isFallback = ($parseStatus !== 'parsed') || in_array($callStatus, ['timeout', 'provider_timeout', 'http_error_4xx', 'http_error_5xx', 'provider_error', 'invalid_json', 'no_api_key', 'fallback_local', 'invalid_key', 'model_not_found', 'quota_exceeded', 'content_truncated'], true);

        if ($callStatus === 'timeout' || $callStatus === 'provider_timeout') {
            // Skenario timeout provider: pertahankan baseline, jangan klaim "tidak ada kecocokan"
            $resultStatus = ($matchesCount > 0) ? 'completed_fallback' : 'provider_timeout';
        } elseif ($parseStatus !== 'parsed' || in_array($callStatus, ['http_error_5xx', 'http_error_4xx', 'provider_error', 'invalid_json', 'no_api_key', 'invalid_key', 'model_not_found', 'quota_exceeded', 'content_truncated'], true)) {
            $resultStatus = ($matchesCount > 0) ? 'completed_fallback' : 'provider_error';
        } elseif ($callStatus === 'fallback_local') {
            $resultStatus = ($matchesCount > 0) ? 'completed_fallback' : 'no_match';
        } elseif ($matchesCount === 0) {
            $resultStatus = 'no_match';
        } else {
            $resultStatus = 'match_found';
        }

        $diagnostic = [
            'target_report_id'     => $targetId,
            'target_type'          => $targetType,
            'pool_count'           => count($poolReports),
            'preselected_count'    => count($preselected),
            'eliminated_reasons'   => $eliminated,
            'model_used'           => $modelUsed,
            'duration_ms'          => $durationMs,
            'call_status'          => $callStatus,
            'parse_status'         => $parseStatus,
            'raw_scores'           => $rawScores,
            'threshold_applied'    => $threshold,
            'result_status'        => $resultStatus,
            'matches_count'        => $matchesCount,
            'is_fallback'          => $isFallback,
            'public_text_sent'     => $aiEval['public_text_sent'] ?? [],
            'error'                => $aiEval['error'] ?? null,
        ];

        $_SESSION['ai_match_diagnostic'] = $diagnostic;
        AuditLogService::log('ai_matching_executed', ($targetType === 'lost' ? 'lost_reports' : 'found_reports'), $targetId, [
            'result_status'     => $resultStatus,
            'model_used'        => $modelUsed,
            'duration_ms'       => $durationMs,
            'call_status'       => $callStatus,
            'parse_status'      => $parseStatus,
            'preselected_count' => count($preselected),
            'matches_count'     => $matchesCount,
            'is_fallback'       => $isFallback,
        ]);

        return [
            'result_status'     => $resultStatus,
            'candidates'        => $savedResults,
            'diagnostic'        => $diagnostic,
            'threshold_applied' => $threshold,
            'matches_count'     => $matchesCount,
        ];
    }

    /**
     * Hitung skor kemiripan teks sederhana dan atribut aturan (Baseline/Pembanding)
     *
     * @param array<string, mixed> $lost
     * @param array<string, mixed> $found
     * @return array{score: float, text_sim: float, meta_score: float, explanation: string}
     */
    public function calculateBaselineScore(array $lost, array $found): array
    {
        $lostText = ($lost['title'] ?? '') . ' ' . ($lost['description'] ?? '');
        $foundText = ($found['title'] ?? '') . ' ' . ($found['description'] ?? '');

        // 1. Text Similarity (Token Jaccard)
        $textSim = $this->calculateTokenSimilarity($lostText, $foundText) * 100.0;

        // 2. Metadata Matching
        $metaScore = 0.0;
        $reasons = [];

        // Kategori sama (bobot 50 poin)
        if (!empty($lost['category_id']) && !empty($found['category_id']) && (int) $lost['category_id'] === (int) $found['category_id']) {
            $metaScore += 50.0;
            $reasons[] = "Kategori identik (" . ($lost['category_name'] ?? 'Sama') . ")";
        }

        // Lokasi sama (bobot 30 poin)
        $lostLoc = (int) ($lost['last_seen_location_id'] ?? $lost['location_id'] ?? 0);
        $foundLoc = (int) ($found['location_id'] ?? $found['found_location_id'] ?? 0);
        if ($lostLoc > 0 && $lostLoc === $foundLoc) {
            $metaScore += 30.0;
            $reasons[] = "Lokasi cocok (" . ($lost['location_name'] ?? 'Sama') . ")";
        }

        // Tanggal sesuai (bobot 20 poin)
        $lostDate = $lost['lost_date'] ?? null;
        $foundDate = $found['found_date'] ?? null;
        if ($lostDate && $foundDate) {
            $diffDays = (strtotime($foundDate) - strtotime($lostDate)) / 86400;
            if ($diffDays >= -1 && $diffDays <= 14) {
                $metaScore += 20.0;
                $reasons[] = "Waktu pelaporan sesuai (" . round($diffDays) . " hari selisih)";
            } elseif ($diffDays >= -1 && $diffDays <= 60) {
                $metaScore += 10.0;
            }
        }

        // Bobot: 50% teks, 50% metadata
        $finalScore = min(100.0, max(0.0, round(($textSim * 0.50) + ($metaScore * 0.50), 1)));

        if ($textSim >= 40.0) {
            $reasons[] = "Kemiripan kata kunci deskripsi signifikan (" . round($textSim, 1) . "%)";
        } elseif ($textSim > 0.0) {
            $reasons[] = "Ditemukan kecocokan kata kunci (" . round($textSim, 1) . "%)";
        }

        $explanation = "[Analisis Atribut Dasar] " . (empty($reasons) ? "Kemiripan umum." : implode(', ', $reasons) . ".");

        return [
            'score'       => $finalScore,
            'text_sim'    => $textSim,
            'meta_score'  => $metaScore,
            'explanation' => $explanation,
        ];
    }

    private function calculateTokenSimilarity(string $str1, string $str2): float
    {
        $tokens1 = $this->tokenize($str1);
        $tokens2 = $this->tokenize($str2);

        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokens1, $tokens2));
        $union = count(array_unique(array_merge($tokens1, $tokens2)));

        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return [];
        }
        $filtered = array_filter($words, fn($w) => mb_strlen($w, 'UTF-8') >= 3);
        return array_values(array_unique($filtered));
    }

    private function fetchFoundReportsPool(): array
    {
        $pdo = \Core\Database::getInstance()->getPdo();
        $sql = "
            SELECT fr.*, c.name AS category_name, l.name AS location_name
            FROM found_reports fr
            LEFT JOIN categories c ON fr.category_id = c.id
            LEFT JOIN locations l ON fr.location_id = l.id
            ORDER BY fr.id DESC
        ";
        return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function fetchLostReportsPool(): array
    {
        $pdo = \Core\Database::getInstance()->getPdo();
        $sql = "
            SELECT lr.*, c.name AS category_name, l.name AS location_name
            FROM lost_reports lr
            LEFT JOIN categories c ON lr.category_id = c.id
            LEFT JOIN locations l ON lr.last_seen_location_id = l.id
            ORDER BY lr.id DESC
        ";
        return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
