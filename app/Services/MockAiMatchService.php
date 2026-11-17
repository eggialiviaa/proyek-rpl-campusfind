<?php

declare(strict_types=1);

namespace App\Services;

/**
 * MockAiMatchService — Fallback lokal untuk pencocokan deskripsi laporan
 *
 * Digunakan jika NVIDIA_API_KEY belum dikonfigurasi atau API tidak tersedia.
 * Menggunakan algoritma Text Similarity lokal (Jaccard + Metadata Weighting) 
 * tanpa membuat hasil palsu (*no fake production results*).
 */
class MockAiMatchService implements AiProviderInterface
{
    private ?string $simulatedStatus = null;
    private ?string $simulatedError = null;

    /**
     * Set status simulasi untuk pengujian unit & integrasi terisolasi
     */
    public function setSimulatedStatus(?string $status, ?string $error = null): void
    {
        $this->simulatedStatus = $status;
        $this->simulatedError = $error;
    }
    public function isAvailable(): bool
    {
        return true; // Fallback lokal selalu tersedia
    }

    public function getEmbedding(string $text): ?array
    {
        return null; // Fallback tidak menggunakan eksternal vector API
    }

    public function chat(array $messages, array $options = []): array
    {
        return [
            'success'     => false,
            'content'     => null,
            'error'       => 'Mock provider fallback hanya untuk pencocokan (matching), tidak mendukung chat.',
            'is_fallback' => true,
        ];
    }

    public function isChatAvailable(): bool
    {
        return false;
    }

    public function getEffectiveApiKey(?string $feature = null): string
    {
        return '';
    }

    public function getCurrentModel(string $feature = 'cfind'): string
    {
        return 'mock-local-algorithmic-fallback';
    }

    public function matchReports(array $lostReport, array $foundReport): array
    {
        $lostTitleDesc = ($lostReport['title'] ?? '') . ' ' . ($lostReport['description'] ?? '');
        $foundTitleDesc = ($foundReport['title'] ?? '') . ' ' . ($foundReport['description'] ?? '');

        // 1. Text Similarity (Jaccard / Token Similarity)
        $textSim = $this->calculateTokenSimilarity($lostTitleDesc, $foundTitleDesc) * 100.0;

        // 2. Metadata Matching
        $categoryMatch = false;
        $locationMatch = false;
        $metaScore = 0.0;

        if (!empty($lostReport['category_id']) && !empty($foundReport['category_id']) &&
            (int)$lostReport['category_id'] === (int)$foundReport['category_id']) {
            $categoryMatch = true;
            $metaScore += 50.0;
        }

        $lostLoc = (int)($lostReport['last_seen_location_id'] ?? 0);
        $foundLoc = (int)($foundReport['found_location_id'] ?? 0);
        if ($lostLoc > 0 && $lostLoc === $foundLoc) {
            $locationMatch = true;
            $metaScore += 30.0;
        }

        $lostDate = $lostReport['lost_date'] ?? null;
        $foundDate = $foundReport['found_date'] ?? null;
        if ($lostDate && $foundDate) {
            $diffDays = (strtotime($foundDate) - strtotime($lostDate)) / 86400;
            if ($diffDays >= -1 && $diffDays <= 14) {
                $metaScore += 20.0;
            }
        }

        // Bobot: 60% Text Similarity, 40% Metadata Similarity
        $totalScore = round(($textSim * 0.60) + ($metaScore * 0.40), 2);
        $totalScore = min(100.0, max(0.0, $totalScore));

        // Format narasi penjelasan
        $reasons = [];
        if ($categoryMatch) {
            $reasons[] = "Kategori item identik (" . ($lostReport['category_name'] ?? 'Sama') . ").";
        }
        if ($locationMatch) {
            $reasons[] = "Lokasi kejadian cocok (" . ($lostReport['location_name'] ?? 'Sama') . ").";
        }
        if ($textSim >= 50.0) {
            $reasons[] = "Teks deskripsi memiliki kemiripan kata kunci signifikan (" . round($textSim, 1) . "%).";
        } elseif ($textSim > 0) {
            $reasons[] = "Ditemukan kesamaan beberapa kata kunci deskripsi (" . round($textSim, 1) . "%).";
        } else {
            $reasons[] = "Deskripsi teks belum menunjukkan kemiripan kata kunci spesifik.";
        }

        $explanation = "[Fallback Lokal] Algoritma pencocokan atribut (Teks: " . round($textSim, 1) . "%, Metadata: " . round($metaScore, 1) . "%). " . implode(" ", $reasons);

        return [
            'score'       => $totalScore,
            'explanation' => $explanation,
            'provider'    => 'Local Algorithmic Fallback (NVIDIA API Key Unset/Offline)',
        ];
    }

    /**
     * Evaluasi daftar kandidat laporan sekaligus dengan keluaran JSON terstruktur (Prompt 35D)
     *
     * @param array<string, mixed> $targetReport Laporan target (kehilangan atau temuan)
     * @param array<int, array<string, mixed>> $candidateReports Daftar kandidat yang lolos pra-seleksi
     * @param string $targetType 'lost' | 'found'
     * @return array{
     *     status: 'success'|'timeout'|'http_error_4xx'|'http_error_5xx'|'invalid_json'|'no_api_key'|'fallback_local',
     *     model: string,
     *     duration_ms: int,
     *     scores: array<int, array{score: float, reason: string}>,
     *     public_text_sent: array<string, mixed>,
     *     raw_content?: ?string,
     *     error?: string
     * }
     */
    public function evaluateCandidates(array $targetReport, array $candidateReports, string $targetType = 'lost'): array
    {
        $startTime = microtime(true);
        $scores = [];

        // Mode simulasi terisolasi untuk unit test
        if ($this->simulatedStatus !== null) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            if ($this->simulatedStatus === 'success') {
                foreach ($candidateReports as $cand) {
                    $candId = (int) $cand['id'];
                    $scores[$candId] = [
                        'score'  => 85.0,
                        'reason' => 'Simulasi AI: tingkat kesamaan atribut sangat tinggi.',
                    ];
                }
                return [
                    'status'           => 'success',
                    'model'            => 'Mock/Simulated Provider (Success)',
                    'duration_ms'      => max(1, $durationMs),
                    'scores'           => $scores,
                    'public_text_sent' => ['target_summary' => '', 'candidates_summary' => []],
                ];
            }

            return [
                'status'           => $this->simulatedStatus,
                'model'            => 'Mock/Simulated Provider (' . $this->simulatedStatus . ')',
                'duration_ms'      => max(1, $durationMs),
                'scores'           => [],
                'public_text_sent' => ['target_summary' => '', 'candidates_summary' => []],
                'error'            => $this->simulatedError ?: 'Simulated provider error/timeout',
            ];
        }

        $publicTargetText = "Judul: " . ($targetReport['title'] ?? '-') . "\n"
            . "Kategori: " . ($targetReport['category_name'] ?? '-') . "\n"
            . "Lokasi: " . ($targetReport['location_name'] ?? '-') . "\n"
            . "Deskripsi Publik: " . ($targetReport['description'] ?? '-');

        $candidatesSummary = [];

        foreach ($candidateReports as $cand) {
            $candId = (int) $cand['id'];
            $candSummary = "ID #{$candId}: " . ($cand['title'] ?? '-') . " (Kategori: " . ($cand['category_name'] ?? '-') . ")";
            $candidatesSummary[] = $candSummary;

            $res = ($targetType === 'lost')
                ? $this->matchReports($targetReport, $cand)
                : $this->matchReports($cand, $targetReport);

            $scores[$candId] = [
                'score'  => (float) $res['score'],
                'reason' => (string) $res['explanation'],
            ];
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'status'           => 'fallback_local',
            'model'            => 'Local Algorithmic Fallback (No-Token Jaccard & Attr)',
            'duration_ms'      => max(1, $durationMs),
            'scores'           => $scores,
            'public_text_sent' => [
                'target_summary'     => $publicTargetText,
                'candidates_summary' => $candidatesSummary,
            ],
            'raw_content'      => json_encode($scores),
        ];
    }

    /**
     * Hitung kemiripan token Jaccard antar dua string
     */
    private function calculateTokenSimilarity(string $str1, string $str2): float
    {
        $tokens1 = $this->tokenize($str1);
        $tokens2 = $this->tokenize($str2);

        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokens1, $tokens2));
        $union = count(array_unique(array_merge($tokens1, $tokens2)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    /**
     * Normalisasi dan pecah teks menjadi daftar kata bermakna (mengabaikan stop words singkat)
     *
     * @return array<string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (!$words) {
            return [];
        }

        // Filter stop words pendek (< 3 karakter)
        $filtered = array_filter($words, fn($w) => mb_strlen($w, 'UTF-8') >= 3);
        return array_values(array_unique($filtered));
    }
}
