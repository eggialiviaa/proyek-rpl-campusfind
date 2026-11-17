<?php

declare(strict_types=1);

namespace App\Services;

/**
 * AiProviderInterface — Interface standar provider AI untuk Campus Find
 */
interface AiProviderInterface
{
    /**
     * Dapatkan vektor embedding untuk sebuah teks
     *
     * @param string $text
     * @return array<float>|null Array embedding float atau null jika gagal
     */
    public function getEmbedding(string $text): ?array;

    /**
     * Hitung kesamaan dua deskripsi teks dan dapatkan skor serta penjelasan
     *
     * @param array<string, mixed> $lostReport
     * @param array<string, mixed> $foundReport
     * @return array{score: float, explanation: string, provider: string}
     */
    public function matchReports(array $lostReport, array $foundReport): array;

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
    public function evaluateCandidates(array $targetReport, array $candidateReports, string $targetType = 'lost'): array;

    /**
     * Cek apakah provider AI aktif dan siap digunakan
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Jalankan percakapan chat completions
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{
     *     success: bool,
     *     content: ?string,
     *     finish_reason?: string,
     *     error?: string,
     *     status_code?: int,
     *     usage?: array{prompt_tokens: int, completion_tokens: int, total_tokens: int},
     *     model?: string,
     *     provider?: string,
     *     provider_id?: string,
     *     execution_status?: string,
     *     is_fallback?: bool
     * }
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Cek apakah kemampuan Chatbot AI siap dan terkonfigurasi
     */
    public function isChatAvailable(): bool;

    /**
     * Dapatkan API key efektif yang digunakan
     */
    public function getEffectiveApiKey(?string $feature = null): string;

    /**
     * Dapatkan model aktual yang sedang aktif untuk fitur tertentu
     */
    public function getCurrentModel(string $feature = 'cfind'): string;
}
