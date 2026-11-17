<?php

declare(strict_types=1);

namespace App\Services;

/**
 * NvidiaNimService — Integrasi layanan AI NVIDIA NIM (Hosted API Catalog)
 *
 * Menggunakan hosted API catalog NVIDIA (tanpa butuh GPU sendiri) untuk 
 * pemrosesan embedding teks dan rekomendasi pencocokan laporan barang hilang/temuan.
 */
class NvidiaNimService implements AiProviderInterface
{
    private string $apiKey;
    private string $apiUrl;
    private string $embeddingModel;
    private int $timeout;

    // Chat completions configuration (OpenAI-compatible)
    private string $chatBaseUrl;
    private string $chatApiKey;
    private string $chatModel;
    private int $chatTimeout;
    private int $maxOutputTokens;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?string $baseUrl = null
    ) {
        $nimKey = $apiKey ?? (string)SettingService::get('ai_provider_nvidia_nim_key', '');
        $this->apiUrl = rtrim($baseUrl ?? (string)SettingService::get('ai_provider_nvidia_nim_url', 'https://integrate.api.nvidia.com/v1'), '/');
        
        $this->apiKey = $nimKey;
        $this->chatApiKey = $nimKey;
        $this->chatBaseUrl = $this->apiUrl;

        $cfindModel = $model ?? (string)SettingService::get('ai_model_cfind', 'nvidia/nemotron-3.5-bolt-e4b');
        $this->chatModel = AiProviderManager::normalizeModelId($cfindModel);

        $matchingModel = (string)SettingService::get('ai_model_matching', 'nvidia/nemotron-3.5-bolt-e4b');
        $this->embeddingModel = AiProviderManager::normalizeModelId($matchingModel);
        
        $this->timeout = 6;
        $configuredTimeout = (int)SettingService::get('cfind_timeout_seconds', 60);
        $phpMaxExec = (int) ini_get('max_execution_time');
        if ($phpMaxExec > 0 && $configuredTimeout >= $phpMaxExec) {
            $this->chatTimeout = max(5, $phpMaxExec - 5);
        } else {
            $this->chatTimeout = max(5, $configuredTimeout);
        }
        $this->maxOutputTokens = (int)SettingService::get('cfind_max_output_tokens', 500);
    }


    public function getProviderId(): string
    {
        return 'nvidia_nim';
    }

    public function getProviderName(): string
    {
        return 'NVIDIA NIM';
    }

    /**
     * Dapatkan API key aktif untuk pencocokan / embedding
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Dapatkan API key aktif untuk chat / C-Find
     */
    public function getChatApiKey(): string
    {
        return $this->chatApiKey;
    }

    /**
     * Dapatkan API key efektif berdasarkan fitur ('cfind' atau 'matching')
     */
    public function getEffectiveApiKey(?string $feature = null): string
    {
        if ($feature === 'matching') {
            return $this->apiKey;
        }
        if ($feature === 'cfind') {
            return $this->chatApiKey;
        }
        return $this->chatApiKey ?: $this->apiKey;
    }

    /**
     * Cek apakah API Key terkonfigurasi untuk matching/embedding
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your_nvidia_api_key_here';
    }

    /**
     * Cek apakah Chatbot AI siap dan terkonfigurasi
     */
    public function isChatAvailable(): bool
    {
        return !empty($this->chatApiKey) && $this->chatApiKey !== 'your_api_key_here' && !empty($this->chatBaseUrl);
    }

    /**
     * Dapatkan model aktual yang sedang aktif untuk fitur tertentu
     */
    public function getCurrentModel(string $feature = 'cfind'): string
    {
        if ($feature === 'matching') {
            return $this->embeddingModel;
        }
        return $this->chatModel;
    }

    /**
     * Dapatkan vektor embedding numerik dari NVIDIA NIM API
     */
    public function getEmbedding(string $text): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cleanText = trim(strip_tags($text));
        if (empty($cleanText)) {
            return null;
        }

        $endpoint = $this->apiUrl . '/embeddings';
        $payload = [
            'input'           => [$cleanText],
            'model'           => $this->embeddingModel,
            'input_type'      => 'passage',
            'encoding_format' => 'float',
        ];

        $response = $this->sendHttpRequest($endpoint, $payload);
        if (!$response || !isset($response['data'][0]['embedding'])) {
            return null;
        }

        return $response['data'][0]['embedding'];
    }

    /**
     * Hitung pencocokan antara Laporan Hilang dan Laporan Temuan
     */
    public function matchReports(array $lostReport, array $foundReport): array
    {
        $lostText = $this->buildReportText($lostReport);
        $foundText = $this->buildReportText($foundReport);

        // Dapatkan vektor embedding
        $vecLost = $this->getEmbedding($lostText);
        $vecFound = $this->getEmbedding($foundText);

        $semanticScore = 0.0;
        if ($vecLost !== null && $vecFound !== null) {
            $semanticScore = $this->cosineSimilarity($vecLost, $vecFound) * 100.0;
        }

        // Bobot metadata tambahan
        $metaScore = $this->calculateMetadataBonus($lostReport, $foundReport);

        // Bobot akhir: 70% Semantic Embedding, 30% Metadata Matching
        $finalScore = min(100.0, max(0.0, ($semanticScore * 0.70) + ($metaScore * 0.30)));
        $finalScore = round($finalScore, 2);

        $explanation = $this->generateExplanation($lostReport, $foundReport, $semanticScore, $metaScore, $finalScore);

        return [
            'score'       => $finalScore,
            'explanation' => $explanation,
            'provider'    => 'NVIDIA NIM (' . $this->embeddingModel . ')',
        ];
    }

    /**
     * Format teks laporan publik untuk embedding (tanpa data sensitif/pribadi)
     */
    private function buildReportText(array $report): string
    {
        $parts = [];
        if (!empty($report['title'])) {
            $parts[] = "Judul: " . $report['title'];
        }
        if (!empty($report['category_name'])) {
            $parts[] = "Kategori: " . $report['category_name'];
        }
        if (!empty($report['description'])) {
            $parts[] = "Deskripsi: " . $report['description'];
        }
        if (!empty($report['location_name'])) {
            $parts[] = "Lokasi: " . $report['location_name'];
        }

        return implode("\n", $parts);
    }

    /**
     * Hitung Cosine Similarity antara dua vektor float
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = min(count($vecA), count($vecB));
        if ($count === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Hitung skor kelayakan berdasarkan pencocokan metadata dasar
     */
    private function calculateMetadataBonus(array $lostReport, array $foundReport): float
    {
        $score = 0.0;

        // 1. Kategori sama
        if (!empty($lostReport['category_id']) && !empty($foundReport['category_id']) &&
            (int)$lostReport['category_id'] === (int)$foundReport['category_id']) {
            $score += 50.0;
        }

        // 2. Lokasi sama / berdekatan
        $lostLoc = (int)($lostReport['last_seen_location_id'] ?? 0);
        $foundLoc = (int)($foundReport['found_location_id'] ?? 0);
        if ($lostLoc > 0 && $lostLoc === $foundLoc) {
            $score += 30.0;
        }

        // 3. Selisih tanggal
        $lostDateStr = $lostReport['lost_date'] ?? null;
        $foundDateStr = $foundReport['found_date'] ?? null;

        if ($lostDateStr && $foundDateStr) {
            $lostTime = strtotime($lostDateStr);
            $foundTime = strtotime($foundDateStr);

            if ($lostTime && $foundTime) {
                // Barang temuan dilaporkan pada hari yang sama atau setelah hilang
                $diffDays = ($foundTime - $lostTime) / 86400;
                if ($diffDays >= -1 && $diffDays <= 14) {
                    $score += 20.0;
                }
            }
        }

        return min(100.0, $score);
    }

    /**
     * Format narasi penjelasan hasil kecocokan
     */
    private function generateExplanation(array $lost, array $found, float $semScore, float $metaScore, float $totalScore): string
    {
        $reasons = [];

        if (!empty($lost['category_name']) && !empty($found['category_name']) &&
            mb_strtolower($lost['category_name']) === mb_strtolower($found['category_name'])) {
            $reasons[] = "Kategori barang sama (" . $lost['category_name'] . ").";
        }

        if (!empty($lost['location_name']) && !empty($found['location_name']) &&
            mb_strtolower($lost['location_name']) === mb_strtolower($found['location_name'])) {
            $reasons[] = "Lokasi temuan cocok dengan lokasi hilang (" . $lost['location_name'] . ").";
        }

        if ($semScore >= 70.0) {
            $reasons[] = "Deskripsi teks memiliki kesamaan makna semantik yang sangat tinggi (" . round($semScore, 1) . "%).";
        } elseif ($semScore >= 45.0) {
            $reasons[] = "Deskripsi barang memiliki kemiripan kata kunci dan atribut (" . round($semScore, 1) . "%).";
        } else {
            $reasons[] = "Deskripsi teks memiliki kesamaan parsial (" . round($semScore, 1) . "%).";
        }

        $summary = "Analisis AI NIM (Semantik: " . round($semScore, 1) . "%, Metadata: " . round($metaScore, 1) . "%). " . implode(" ", $reasons);
        return $summary;
    }

    /**
     * Kirim HTTP Request ke NVIDIA NIM dengan cURL
     */
    private function sendHttpRequest(string $url, array $payload): ?array
    {
        $ch = curl_init($url);
        if (!$ch) {
            return null;
        }

        $jsonPayload = json_encode($payload);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200 || !$rawResponse) {
            error_log("[NvidiaNimService] API Request Failed HTTP {$httpCode}: " . ($curlErr ?: $rawResponse));
            return null;
        }

        $decoded = json_decode($rawResponse, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Kirim percakapan ke OpenAI-compatible Chat Completions API
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{
     *   success: bool,
     *   content: ?string,
     *   finish_reason: ?string,
     *   error: ?string,
     *   status_code: int,
     *   usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     * }
     */
    public function chat(array $messages, array $options = []): array
    {
        $defaultUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $modelToUse = $options['model'] ?? $this->chatModel;

        if (!$this->isChatAvailable()) {
            return [
                'success'          => false,
                'content'          => null,
                'finish_reason'    => 'unavailable',
                'error'            => 'AI API key is missing or not configured',
                'status_code'      => 503,
                'usage'            => $defaultUsage,
                'provider'         => 'NVIDIA NIM',
                'provider_id'      => 'nvidia_nim',
                'model'            => $modelToUse,
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];
        }

        $endpoint = $this->chatBaseUrl . '/chat/completions';
        $payload = [
            'model'       => $modelToUse,
            'messages'    => $messages,
            'max_tokens'  => $options['max_tokens'] ?? $this->maxOutputTokens,
            'temperature' => $options['temperature'] ?? 0.2,
            'stream'      => false,
        ];

        $ch = curl_init($endpoint);
        if (!$ch) {
            return [
                'success'          => false,
                'content'          => null,
                'finish_reason'    => 'error',
                'error'            => 'Failed to initialize curl',
                'status_code'      => 500,
                'usage'            => $defaultUsage,
                'provider'         => 'NVIDIA NIM',
                'provider_id'      => 'nvidia_nim',
                'model'            => $modelToUse,
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];
        }

        $jsonPayload = json_encode($payload);
        $effectiveKey = !empty($options['api_key']) ? $options['api_key'] : $this->chatApiKey;
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $effectiveKey,
            'Accept: application/json',
        ];

        $timeout = (int)($options['timeout'] ?? $this->chatTimeout);
        $connectTimeout = min(5, max(1, (int)($options['connect_timeout'] ?? 5)));

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200 || empty($rawResponse)) {
            // Klasifikasi diagnostik sementara sesuai permintaan
            $isTimeout = ($curlErrNo === CURLE_OPERATION_TIMEDOUT || ($curlErr && str_contains(strtolower($curlErr), 'timed out')));
            $isSsl = ($curlErrNo === 35 || $curlErrNo === 58 || $curlErrNo === 59 || $curlErrNo === 60 || ($curlErr && preg_match('/ssl|cert/i', $curlErr)));

            if ($isTimeout) {
                $diagBranch = 'timeout';
            } elseif ($isSsl) {
                $diagBranch = "error cURL (SSL error {$curlErrNo})";
            } elseif ($curlErrNo > 0) {
                $diagBranch = "error cURL (errno {$curlErrNo})";
            } elseif ($httpCode !== 200) {
                $diagBranch = "HTTP status provider {$httpCode}";
            } else {
                $diagBranch = 'empty_response';
            }

            error_log("[NvidiaNimService::chat] Failed [{$diagBranch}]: " . ($curlErr ?: "HTTP {$httpCode}"));

            return [
                'success'          => false,
                'content'          => null,
                'finish_reason'    => $isTimeout ? 'timeout' : 'http_error',
                'diagnostic'       => $diagBranch,
                'error'            => $isTimeout ? 'Request timed out' : "HTTP {$httpCode}: Provider error",
                'status_code'      => $httpCode ?: 500,
                'usage'            => $defaultUsage,
                'provider'         => 'NVIDIA NIM',
                'provider_id'      => 'nvidia_nim',
                'model'            => $modelToUse,
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded) || !isset($decoded['choices'][0]['message'])) {
            return [
                'success'          => false,
                'content'          => null,
                'finish_reason'    => 'invalid_response',
                'diagnostic'       => 'invalid_response',
                'error'            => 'Invalid response structure from AI provider',
                'status_code'      => 200,
                'usage'            => $defaultUsage,
                'provider'         => 'NVIDIA NIM',
                'provider_id'      => 'nvidia_nim',
                'model'            => $modelToUse,
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];
        }

        $choice = $decoded['choices'][0];
        $message = $choice['message'] ?? [];
        $finishReason = $choice['finish_reason'] ?? 'stop';
        $content = isset($message['content']) ? trim((string)$message['content']) : '';

        // Capture token usage (including reasoning tokens if accounted under completion_tokens)
        $usage = [
            'prompt_tokens'     => (int)($decoded['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($decoded['usage']['completion_tokens'] ?? 0),
            'total_tokens'      => (int)($decoded['usage']['total_tokens'] ?? 0),
        ];

        // CRITICAL REASONING MODEL CONSTRAINT (Prompt Section C):
        // 1. NEVER store, return, log or expose reasoning_content to frontend.
        // 2. If content is null/empty OR finish_reason === 'length':
        //    Must NOT return empty or cut-off response. Mark as busy_or_length_truncated with specific diagnostic branch.
        if ($finishReason === 'length') {
            return [
                'success'          => false,
                'content'          => null,
                'finish_reason'    => 'length',
                'diagnostic'       => 'finish_reason length',
                'error'            => 'busy_or_length_truncated',
                'status_code'      => 200,
                'usage'            => $usage,
                'provider'         => 'NVIDIA NIM',
                'provider_id'      => 'nvidia_nim',
                'model'            => $modelToUse,
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];
        }

        if ($content === '') {
            return [
                'success'          => false,
                'content'          => null,
                'finish_reason'    => $finishReason,
                'diagnostic'       => 'content null',
                'error'            => 'busy_or_length_truncated',
                'status_code'      => 200,
                'usage'            => $usage,
                'provider'         => 'NVIDIA NIM',
                'provider_id'      => 'nvidia_nim',
                'model'            => $modelToUse,
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];
        }

        $actualModel = $decoded['model'] ?? $modelToUse;

        return [
            'success'          => true,
            'content'          => $content,
            'finish_reason'    => $finishReason,
            'diagnostic'       => 'success',
            'error'            => null,
            'status_code'      => 200,
            'usage'            => $usage,
            'provider'         => 'NVIDIA NIM',
            'provider_id'      => 'nvidia_nim',
            'model'            => $actualModel,
            'execution_status' => 'success',
            'is_fallback'      => false,
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
        $modelToUse = (string) SettingService::get('ai_model_matching', 'nvidia/nemotron-3.5-bolt-e4b');
        if (empty($modelToUse)) {
            $modelToUse = 'nvidia/nemotron-3.5-bolt-e4b';
        }
        $matchingApiKey = !empty($this->apiKey) ? $this->apiKey : $this->chatApiKey;

        // Teks Publik yang Dikirim (TANPA PII, TANPA FOTO, TANPA DATA PRIVAT)
        $publicTargetText = "Judul: " . ($targetReport['title'] ?? '-') . "\n"
            . "Kategori: " . ($targetReport['category_name'] ?? '-') . "\n"
            . "Lokasi: " . ($targetReport['location_name'] ?? '-') . "\n"
            . "Tanggal: " . ($targetReport['lost_date'] ?? $targetReport['found_date'] ?? '-') . "\n"
            . "Deskripsi Publik: " . ($targetReport['description'] ?? '-');

        $candidatesSummary = [];
        $candidatesTextParts = [];
        foreach ($candidateReports as $cand) {
            $candId = (int) $cand['id'];
            $candSummary = "ID #{$candId}: " . ($cand['title'] ?? '-') . " (Kategori: " . ($cand['category_name'] ?? '-') . ", Lokasi: " . ($cand['location_name'] ?? '-') . ")";
            $candidatesSummary[] = $candSummary;

            $candidatesTextParts[] = "--- KANDIDAT ID #{$candId} ---\n"
                . "Judul: " . ($cand['title'] ?? '-') . "\n"
                . "Kategori: " . ($cand['category_name'] ?? '-') . "\n"
                . "Lokasi: " . ($cand['location_name'] ?? '-') . "\n"
                . "Tanggal: " . ($cand['found_date'] ?? $cand['lost_date'] ?? '-') . "\n"
                . "Deskripsi Publik: " . ($cand['description'] ?? '-');
        }

        $publicTextSummary = [
            'target_summary'     => $publicTargetText,
            'candidates_summary' => $candidatesSummary,
        ];

        if (empty($matchingApiKey)) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            return [
                'status'           => 'no_api_key',
                'parse_status'     => 'failed',
                'model'            => $modelToUse,
                'duration_ms'      => $durationMs,
                'scores'           => [],
                'public_text_sent' => $publicTextSummary,
                'error'            => 'API Key AI belum dikonfigurasi',
            ];
        }

        $targetLabel = ($targetType === 'lost') ? 'KEHILANGAN' : 'TEMUAN';
        $candLabel = ($targetType === 'lost') ? 'TEMUAN' : 'KEHILANGAN';

        $systemPrompt = "Anda adalah asisten cerdas sistem Lost & Found kampus. Tugas Anda: mencocokkan satu laporan barang {$targetLabel} dengan beberapa kandidat laporan barang {$candLabel}. Berikan penilaian objektif (skor 0-100) dan alasan singkat berdasarkan kesamaan atribut: judul, kategori, warna/ciri/merek dalam deskripsi publik, serta kesesuaian lokasi dan tanggal. Jangan berasumsi data yang tidak tertulis. Output WAJIB berupa JSON array valid tanpa markdown atau teks pengantar dengan format: [{\"id\": <id_kandidat>, \"score\": <angka_bulat_0_sampai_100>, \"reason\": \"<alasan_singkat_maksimal_15_kata>\"}].";

        $userPrompt = "=== LAPORAN {$targetLabel} UTAMA ===\n" . $publicTargetText . "\n\n"
            . "=== DAFTAR KANDIDAT {$candLabel} UNTUK DINILAI ===\n" . implode("\n\n", $candidatesTextParts);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $chatOptions = [
            'model'           => $modelToUse,
            'temperature'     => 0.1,
            'max_tokens'      => 600,
            'timeout'         => 15, // Bounded total timeout maksimal 15 detik
            'connect_timeout' => 5,  // Bounded connect timeout maksimal 5 detik
            'api_key'         => $matchingApiKey,
        ];

        $chatRes = $this->chat($messages, $chatOptions);

        // Bounded automatic retry: maksimal 1x dan HANYA untuk kegagalan transien yang retryable
        if (!$chatRes['success']) {
            $finishReason = $chatRes['finish_reason'] ?? '';
            $statusCode   = (int) ($chatRes['status_code'] ?? 500);
            $errLower     = strtolower($chatRes['error'] ?? '');

            $isRetryable = ($finishReason === 'timeout'
                || str_contains($errLower, 'timed out')
                || in_array($statusCode, [429, 502, 503, 504], true));

            if ($isRetryable) {
                // Catat log transien tanpa rahasia/PII
                error_log("[NvidiaNimService] Kegagalan transien provider AI ({$statusCode}/{$finishReason}). Menjalankan retry terikat batas (percobaan 2/2)...");
                $chatRes = $this->chat($messages, $chatOptions);
            }
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        // Evaluasi status panggilan dengan state yang tegas dan dapat dibedakan
        if (!$chatRes['success']) {
            $finishReason = $chatRes['finish_reason'] ?? '';
            $statusCode = (int) ($chatRes['status_code'] ?? 500);
            $errLower = strtolower($chatRes['error'] ?? '');

            if ($finishReason === 'timeout' || str_contains($errLower, 'timed out')) {
                $status = 'provider_timeout';
            } elseif ($statusCode === 401 || $statusCode === 403) {
                $status = 'invalid_key';
            } elseif ($statusCode === 404 || $statusCode === 410) {
                $status = 'model_not_found';
            } elseif ($statusCode === 429) {
                $status = 'quota_exceeded';
            } elseif ($finishReason === 'length' || str_contains($errLower, 'truncated')) {
                $status = 'content_truncated';
            } elseif ($statusCode >= 500) {
                $status = 'provider_error';
            } elseif ($statusCode >= 400) {
                $status = 'http_error_4xx';
            } else {
                $status = 'provider_error';
            }

            return [
                'status'           => $status,
                'parse_status'     => 'failed',
                'model'            => $modelToUse,
                'duration_ms'      => $durationMs,
                'scores'           => [],
                'public_text_sent' => $publicTextSummary,
                'error'            => $chatRes['error'] ?? 'Gagal menghubungi penyedia AI',
            ];
        }

        $rawContent = trim((string) ($chatRes['content'] ?? ''));
        $jsonText = $rawContent;

        // Bersihkan pembungkus markdown code fence jika ada
        if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/is', $jsonText, $matches)) {
            $jsonText = $matches[1];
        } elseif (preg_match('/(\[.*?\])/is', $jsonText, $matches)) {
            $jsonText = $matches[1];
        }

        $decoded = json_decode($jsonText, true);
        if (!is_array($decoded)) {
            return [
                'status'           => 'invalid_json',
                'parse_status'     => 'failed',
                'model'            => $modelToUse,
                'duration_ms'      => $durationMs,
                'scores'           => [],
                'public_text_sent' => $publicTextSummary,
                'raw_content'      => $rawContent,
                'error'            => 'Keluaran model AI tidak dapat di-parse sebagai JSON terstruktur',
            ];
        }

        $scores = [];
        foreach ($decoded as $item) {
            if (isset($item['id'])) {
                $cId = (int) $item['id'];
                $sVal = isset($item['score']) ? min(100.0, max(0.0, (float) $item['score'])) : 0.0;
                $rVal = isset($item['reason']) ? trim((string) $item['reason']) : 'Kandidat menunjukkan kesamaan atribut.';
                $scores[$cId] = [
                    'score'  => $sVal,
                    'reason' => $rVal,
                ];
            }
        }

        return [
            'status'           => 'success',
            'parse_status'     => 'parsed',
            'model'            => $modelToUse,
            'duration_ms'      => $durationMs,
            'scores'           => $scores,
            'public_text_sent' => $publicTextSummary,
            'raw_content'      => $rawContent,
        ];
    }
}
