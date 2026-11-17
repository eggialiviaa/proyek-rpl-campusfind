<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use App\Services\CFind\AbstractRoleProfile;
use App\Services\CFind\GuestProfile;
use App\Services\CFind\CivitasProfile;
use App\Services\CFind\PetugasProfile;
use App\Services\CFind\AdminProfile;
use App\Services\CFind\DataMasker;
use App\Services\CFind\DataQueryHandler;
use App\Services\CFind\CFindHistoryService;
use App\Middleware\AuthMiddleware;
use App\Services\SettingService;
use PDO;
use Throwable;

/**
 * CFindService — Layanan Chatbot C-Find (NVIDIA NIM) Bertingkat (Tier 1 & Tier 2)
 *
 * Mengimplementasikan:
 * 1. Pemisahan 4 profil role independen (Guest, Civitas, Petugas, Admin)
 * 2. Otorisasi berlapis (Role & User ID HANYA dari server session, Layer 2 assert pada tiap tool)
 * 3. Tier 1: Dasar info bot/user, tolak out-of-scope sebelum AI dipanggil
 * 4. Tier 2: Tanya data ter-parameter per role allowlist, data masking (tanpa password, bukti klaim, dll)
 * 5. Cost control: rate limit per menit, daily token budget, session history
 * 6. Layer 3: Validasi output & pencegahan kebocoran secret
 */
class CFindService
{
    /** Nama resmi chatbot (konstanta kode tunggal sesuai spesifikasi) */
    public const BOT_NAME = 'C-Find';

    private AiProviderInterface $nimService;
    private PDO $pdo;
    private DataQueryHandler $queryHandler;
    private CFindHistoryService $historyService;
    private int $maxInputChars;
    private int $rateLimitPerMin;
    private int $dailyTokenBudget;
    private string $budgetLogPath;
    private string $usageLogPath;
    private array $lastRuntimeMetadata = [];

    public function __construct(?AiProviderInterface $nimService = null)
    {
        $this->nimService = $nimService ?? AiProviderManager::resolveFeatureProvider('cfind');
        $this->pdo = Database::getInstance()->getPdo();
        $this->queryHandler = new DataQueryHandler($this->pdo, null, $this->nimService);
        $this->historyService = new CFindHistoryService($this->pdo);

        $this->maxInputChars = (int) ($_ENV['CFIND_MAX_INPUT_CHARS'] ?? getenv('CFIND_MAX_INPUT_CHARS') ?: 300);
        $this->rateLimitPerMin = (int) SettingService::get('cfind_rate_limit_guest', 10);
        $this->dailyTokenBudget = (int) SettingService::get('cfind_daily_budget_civitas', 50000);

        $logDir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $this->budgetLogPath = $logDir . '/cfind_daily_budget.json';
        $this->usageLogPath = $logDir . '/cfind_usage.log';

        $runtime = AiProviderManager::getFeatureRuntimeMetadata('cfind');
        $this->lastRuntimeMetadata = [
            'provider'         => $this->nimService->getProviderName(),
            'provider_id'      => $this->nimService->getProviderId(),
            'model'            => method_exists($this->nimService, 'getCurrentModel') ? $this->nimService->getCurrentModel('cfind') : $runtime['model'],
            'execution_status' => $this->nimService->isChatAvailable() ? 'success' : 'fallback',
            'is_fallback'      => !$this->nimService->isChatAvailable(),
        ];
    }

    /**
     * Dapatkan metadata runtime aktual eksekusi C-Find
     */
    public function getLastRuntimeMetadata(): array
    {
        if (empty($this->lastRuntimeMetadata)) {
            $runtime = AiProviderManager::getFeatureRuntimeMetadata('cfind');
            return [
                'provider'         => $this->nimService->getProviderName(),
                'provider_id'      => $this->nimService->getProviderId(),
                'model'            => method_exists($this->nimService, 'getCurrentModel') ? $this->nimService->getCurrentModel('cfind') : $runtime['model'],
                'execution_status' => $this->nimService->isChatAvailable() ? 'success' : 'fallback',
                'is_fallback'      => !$this->nimService->isChatAvailable(),
            ];
        }
        return $this->lastRuntimeMetadata;
    }

    /**
     * Tetapkan metadata runtime secara eksplisit (misal untuk simulasi pengujian)
     */
    public function setRuntimeMetadata(array $metadata): void
    {
        $this->lastRuntimeMetadata = $metadata;
    }

    /**
     * Cek apakah layanan AI C-Find siap digunakan
     */
    public function isAvailable(): bool
    {
        return $this->nimService->isChatAvailable();
    }

    /**
     * Dapatkan nama resmi chatbot
     */
    public function getBotName(): string
    {
        return self::BOT_NAME;
    }

    /**
     * Dapatkan instance DataQueryHandler
     */
    public function getQueryHandler(): DataQueryHandler
    {
        return $this->queryHandler;
    }

    /**
     * Dapatkan instance CFindHistoryService (Prompt 36C)
     */
    public function getHistoryService(): CFindHistoryService
    {
        return $this->historyService;
    }

    /**
     * Pabrik pembuatan profil role berdasarkan data session server (Layer 1)
     * Data role dan user_id HANYA diambil dari session server.
     */
    public function resolveProfile(?array $sessionUser): AbstractRoleProfile
    {
        $role = strtolower(trim((string)($sessionUser['role_name'] ?? $sessionUser['role'] ?? 'guest')));
        $userId = isset($sessionUser['id']) ? (int)$sessionUser['id'] : null;

        return match ($role) {
            'admin'   => new AdminProfile($this->pdo, 'admin', $userId, $sessionUser),
            'petugas' => new PetugasProfile($this->pdo, 'petugas', $userId, $sessionUser),
            'civitas' => new CivitasProfile($this->pdo, 'civitas', $userId, $sessionUser),
            default   => new GuestProfile($this->pdo, 'guest', null, null),
        };
    }

    /**
     * Proses pesan chat dari pengguna melalui otorisasi per-role & guardrail bertingkat
     *
     * @param string $userInput Pesan masukan dari user
     * @param array<string, mixed>|null $sessionUser Informasi user dari session server (bukan dari client)
     * @param string|null $displayText Teks tampilan ramah jika pesan berupa kunci metrik
     * @return array{status: string, reply: string|array, available: bool}
     */
    public function processMessage(string $userInput, ?array $sessionUser = null, ?string $displayText = null): array
    {
        $startTime = microtime(true);

        // Role dan User ID HANYA bersumber dari server session
        $profile = $this->resolveProfile($sessionUser);
        $role = $profile->getRoleName();
        $userId = isset($sessionUser['id']) ? (int)$sessionUser['id'] : null;

        // 0. Cek Status Aktif Chatbot per Role (Prompt 21 Pengaturan Admin)
        $isRoleEnabled = (bool) SettingService::get("cfind_enabled_{$role}", true);
        if (!$isRoleEnabled) {
            $this->logUsage($userId, $role, 'role_disabled', 0, 'denied');
            $disabledReply = 'Layanan asisten ' . self::BOT_NAME . ' untuk peran ' . ucfirst($role) . ' sedang dinonaktifkan sementara oleh Administrator.';
            $this->recordAuditHistory($userId, $role, $userInput, $disabledReply, 'role_disabled', 'denied', $startTime, 0);
            return [
                'status'    => 'error',
                'reply'     => $disabledReply,
                'available' => false,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // TIER 1 / LAPISAN 1: PRA-FILTER DASAR (HEMAT TOKEN & VALIDASI)
        // ══════════════════════════════════════════════════════════════

        // 1.1 Normalisasi Pesan & Pemotongan Sapaan di Awal (Prompt 29D)
        $rawClean = trim($userInput);
        $cleanInput = self::stripGreetingPrefix($rawClean);
        $displayInput = !empty($displayText) ? trim($displayText) : $rawClean;

        if ($cleanInput === '') {
            return [
                'status'    => 'error',
                'reply'     => 'Silakan ketik pertanyaan Anda terlebih dahulu.',
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.2 Batas Karakter Input
        if (mb_strlen($cleanInput) > $this->maxInputChars) {
            $lenReply = "Pertanyaan terlalu panjang (maksimal {$this->maxInputChars} karakter). Silakan persingkat pesan Anda.";
            $this->recordAuditHistory($userId, $role, $rawClean, $lenReply, 'length_exceeded', 'denied', $startTime, 0);
            return [
                'status'    => 'error',
                'reply'     => $lenReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.3 Deteksi Spam / Karakter Berulang
        if (preg_match('/(.)\1{9,}/u', $cleanInput)) {
            $spamReply = 'Pertanyaan mengandung karakter berulang yang tidak wajar. Mohon gunakan kalimat yang jelas.';
            $this->recordAuditHistory($userId, $role, $rawClean, $spamReply, 'spam_detected', 'denied', $startTime, 0);
            return [
                'status'    => 'error',
                'reply'     => $spamReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.4 Rate Limiting per Menit per Role (Kunci IP + Sesi untuk Guest) (Prompt 21 & 29D)
        $roleRateLimit = (int) SettingService::get("cfind_rate_limit_{$role}", $this->rateLimitPerMin);
        $rateLimitCheck = $this->checkRateLimit($roleRateLimit, $userId);
        if ($rateLimitCheck['limited']) {
            $this->logUsage($userId, $role, 'rate_limit', 0, 'rate_limited');
            $secondsRemaining = $rateLimitCheck['seconds_remaining'];
            $resetTime = date('H:i:s', time() + $secondsRemaining);
            $limitReply = "Batas penggunaan pesan per menit telah tercapai ({$roleRateLimit} pesan/menit). Harap tunggu {$secondsRemaining} detik (dapat mengirim pesan kembali pada {$resetTime} WIB).";
            $this->recordAuditHistory($userId, $role, $cleanInput, $limitReply, 'rate_limit', 'rate_limited', $startTime, 0);
            return [
                'status'    => 'limit',
                'reply'     => $limitReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.5 Klasifikasi Niat (Intent Classification)
        $intent = $this->classifyIntent($cleanInput);

        // 1.6 Tolak Topik di Luar Campus Find di Backend SEBELUM Panggil AI (Tier 1)
        if ($intent === 'out_of_scope') {
            $this->recordRateLimit($userId);
            $this->logUsage($userId, $role, 'out_of_scope', 0, 'rejected_out_of_scope');
            $scopeReply = 'Maaf, ' . self::BOT_NAME . ' hanya melayani pertanyaan seputar sistem Lost & Found Campus Find (lapor barang hilang, temuan, klaim, lokasi posko, dan jam operasional). Ada hal seputar layanan kampus yang bisa saya bantu?';
            $this->recordAuditHistory($userId, $role, $cleanInput, $scopeReply, 'out_of_scope', 'rejected_out_of_scope', $startTime, 0);
            return [
                'status'    => 'success',
                'reply'     => $scopeReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.7 Tolak Prompt Injection & Jailbreak di Backend
        if ($intent === 'prompt_injection') {
            $this->recordRateLimit($userId);
            $this->logUsage($userId, $role, 'prompt_injection', 0, 'rejected_prompt_injection');
            $rejectReply = 'Maaf, permintaan tersebut tidak dapat diproses. ' . self::BOT_NAME . ' beroperasi dengan batasan keamanan ketat sesuai panduan resmi sistem Campus Find.';
            $this->appendChatHistory($displayInput, $rejectReply, $userId);
            $this->recordAuditHistory($userId, $role, $cleanInput, $rejectReply, 'prompt_injection', 'rejected_prompt_injection', $startTime, 0);
            return [
                'status'    => 'success',
                'reply'     => $rejectReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.8 Sapaan Ramah Ringkas (0 Token AI)
        if ($intent === 'sapaan') {
            $this->recordRateLimit($userId);
            $this->logUsage($userId, $role, 'sapaan', 0, 'success');
            $sapaanReply = 'Halo! Saya **' . self::BOT_NAME . '**, asisten virtual Campus Find. Ada yang bisa saya bantu seputar pelaporan barang hilang atau klaim barang hari ini?';
            $this->appendChatHistory($displayInput, $sapaanReply, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $sapaanReply, 'sapaan', 'success', $startTime, 0);
            return [
                'status'    => 'success',
                'reply'     => $sapaanReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // 1.8b Pertanyaan Transparansi AI Provider & Model (Task 14 Additional Requirement)
        if ($intent === 'ai_provider_model_info') {
            $this->recordRateLimit($userId);

            $providerName = $this->nimService->getProviderName();
            $providerId = $this->nimService->getProviderId();
            $actualModel = method_exists($this->nimService, 'getCurrentModel')
                ? $this->nimService->getCurrentModel('cfind')
                : (string)SettingService::get('ai_model_cfind', 'default');
            $isAvailable = $this->nimService->isChatAvailable();

            // Cek apakah sistem berada dalam status fallback (misal API key tidak tersedia atau request sebelumnya gagal)
            $isFallback = (!$isAvailable) || 
                          (!empty($this->lastRuntimeMetadata['is_fallback']) && in_array($this->lastRuntimeMetadata['execution_status'] ?? '', ['fallback', 'failed'], true));
            $status = $isFallback ? 'fallback' : 'success';

            $this->lastRuntimeMetadata = [
                'provider'         => $providerName,
                'provider_id'      => $providerId,
                'model'            => $actualModel,
                'execution_status' => $status,
                'is_fallback'      => $isFallback,
            ];

            $reply = AiProviderManager::generateTransparencyAnswer($this->lastRuntimeMetadata);

            $this->logUsage($userId, $role, $intent, 0, $status);
            $this->appendChatHistory($displayInput, $reply, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $reply, $intent, $status, $startTime, 0);

            return [
                'status'    => 'success',
                'reply'     => $reply,
                'available' => true,
                'metadata'  => $this->lastRuntimeMetadata,
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // TIER 1 & TIER 2: DATA QUERY HANDLER & FAQ (PROMPT 28)
        // Backend yang tahu data: regex rule / AI classifier -> query ter-parameter
        // Jawaban tepat, singkat (1 angka / max 5 baris), 0 token untuk penulisan jawaban.
        // ══════════════════════════════════════════════════════════════
        $queryResult = $this->queryHandler->handle($cleanInput, $sessionUser, $role, $userId);
        if ($queryResult !== null && !empty($queryResult['handled'])) {
            $this->recordRateLimit($userId);
            $tokensUsed = (int) ($queryResult['tokens'] ?? 0);
            if ($tokensUsed > 0) {
                $this->addDailyTokensUsed($tokensUsed, $role);
            }
            $queryStatus = $queryResult['status'] ?? 'success';
            $this->logUsage($userId, $role, $queryResult['intent'] ?? $intent, $tokensUsed, $queryStatus);
            $safeReply = $this->validateAndSanitizeOutput($queryResult['reply']);
            $this->appendChatHistory($displayInput, $safeReply, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $safeReply, $queryResult['intent'] ?? $intent, $queryStatus, $startTime, $tokensUsed);

            $branch = $queryResult['branch'] ?? '';
            $is404Model = (str_contains(strtolower($branch), '404') || str_contains(strtolower($branch), 'model_not_found') || str_contains(strtolower($branch), 'not found'));
            if ($queryStatus === 'error' && $is404Model) {
                $actualModel = method_exists($this->nimService, 'getCurrentModel') ? $this->nimService->getCurrentModel('cfind') : 'default';
                error_log("[CFindService::queryHandler] Model '{$actualModel}' tidak ditemukan pada provider (HTTP 404). Kemungkinan model sudah tidak tersedia atau telah di-sunset oleh provider. Silakan pilih model lain di Pengaturan AI.");
                AuditLogService::log(
                    'cfind_model_error',
                    'cfind',
                    $userId,
                    ['model' => $actualModel, 'error' => 'model_not_found_404', 'source' => 'query_planner'],
                    $userId
                );
            }

            return [
                'status'    => $queryStatus,
                'reply'     => $safeReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // OTORISASI BERLAPIS: CEK HAK AKSES ROLE (CATALOG ALLOWLIST)
        // ══════════════════════════════════════════════════════════════
        $allowedIntents = $profile->getAllowedIntents();

        // Jika intent spesifik terdeteksi namun TIDAK diizinkan untuk profil role ini:
        // Tolak dengan sopan tanpa membocorkan bahwa datanya ada, dan catat status "denied"
        if (!in_array($intent, $allowedIntents, true) && $intent !== 'ambigu') {
            $this->recordRateLimit($userId);
            $this->logUsage($userId, $role, $intent, 0, 'denied');
            $deniedReply = $profile->getDeniedResponse($intent);
            $this->appendChatHistory($displayInput, $deniedReply, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $deniedReply, $intent, 'denied', $startTime, 0);

            return [
                'status'    => 'success',
                'reply'     => $deniedReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // TIER 1 & TIER 2 FAST PATH: JAWAB LANGSUNG DENGAN TEMPLATE
        // ══════════════════════════════════════════════════════════════
        $directReply = $profile->handleDirectTemplate($cleanInput, $intent);
        if ($directReply !== null) {
            $this->recordRateLimit($userId);
            $this->logUsage($userId, $role, $intent, 0, 'success');
            $this->appendChatHistory($displayInput, $directReply, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $directReply, $intent, 'success', $startTime, 0);

            return [
                'status'    => 'success',
                'reply'     => $directReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // GUEST FAQ RULE GUARDRAIL: MENJAWAB DARI FAQ ATURAN (TANPA AI)
        // Chatbot guest menjawab dari FAQ dengan aturan (tanpa AI), maks 3 kalimat,
        // dan jujur bila tidak ada jawabannya di FAQ resmi (Prompt 35C).
        // ══════════════════════════════════════════════════════════════
        if ($role === 'guest') {
            $this->recordRateLimit($userId);
            $this->logUsage($userId, $role, $intent, 0, 'guest_faq_honest_fallback');
            $honestReply = 'Maaf, saya tidak menemukan jawaban untuk pertanyaan tersebut dalam panduan resmi. Silakan hubungi Petugas di Posko Lost & Found Center (Gedung Rektorat Lt. 1) atau hubungi Administrator kampus untuk informasi lebih lanjut.';
            $this->appendChatHistory($displayInput, $honestReply, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $honestReply, $intent, 'guest_faq_honest_fallback', $startTime, 0);

            return [
                'status'    => 'success',
                'reply'     => $honestReply,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // TIER 2 AI REASONING PATH: PANGGILAN MODEL DENGAN GROUNDING
        // ══════════════════════════════════════════════════════════════
        // 2.1 Cek Anggaran Harian Token AI Khusus Role (Prompt 21 & 29D)
        $roleDailyBudget = (int) SettingService::get("cfind_daily_budget_{$role}", $this->dailyTokenBudget);
        if ($this->isDailyBudgetExceeded($role, $roleDailyBudget)) {
            $this->logUsage($userId, $role, 'budget_check', 0, 'budget_exceeded');
            $budgetMsg = "Batas penggunaan AI harian untuk peran " . ucfirst($role) . " telah tercapai ({$roleDailyBudget} token/hari). Layanan pemikiran AI akan direset besok pukul 00:00 WIB. Pertanyaan seputar panduan, lokasi posko, dan katalog publik tetap dapat dijawab.";
            $this->appendChatHistory($displayInput, $budgetMsg, $userId);
            $this->recordAuditHistory($userId, $role, $displayInput, $budgetMsg, 'budget_check', 'budget_exceeded', $startTime, 0);
            return [
                'status'    => 'limit',
                'reply'     => $budgetMsg,
                'available' => true,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        if (!$this->isAvailable()) {
            $unavailReply = self::BOT_NAME . ' sedang tidak tersedia saat ini. Silakan coba kembali nanti.';
            $this->recordAuditHistory($userId, $role, $displayInput, $unavailReply, 'service_unavailable', 'unavailable', $startTime, 0);
            return [
                'status'    => 'unavailable',
                'reply'     => $unavailReply,
                'available' => false,
                'metadata'  => $this->getLastRuntimeMetadata(),
            ];
        }

        // Kumpulkan Grounding Data Ter-parameter Khusus Role (Tanpa SQL dari AI)
        $groundingData = $profile->buildGroundingContext($cleanInput);
        $systemPrompt = $profile->getSystemPrompt() . "\n\nKONTEKS DATA TERVERIFIKASI SISTEM:\n" . $groundingData;

        // Ambil riwayat sesi (maks 4 pesan)
        $sessionHistory = $this->getSessionHistory();
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        foreach ($sessionHistory as $hist) {
            $c = $hist['content'];
            if (is_array($c)) {
                $textParts = [];
                foreach ($c as $block) {
                    if (is_array($block) && ($block['type'] ?? '') === 'table') {
                        $textParts[] = "[Tabel data: " . count($block['rows'] ?? []) . " baris]";
                    } elseif (is_array($block) && isset($block['content'])) {
                        $textParts[] = (string)$block['content'];
                    }
                }
                $c = implode("\n", $textParts);
            }
            $messages[] = ['role' => $hist['role'], 'content' => (string)$c];
        }
        $messages[] = ['role' => 'user', 'content' => $cleanInput];

        // Panggilan AI
        $this->recordRateLimit($userId);
        $aiResult = $this->nimService->chat($messages);

        $tokensUsed = (int) ($aiResult['usage']['total_tokens'] ?? 0);
        $this->addDailyTokensUsed($tokensUsed, $role);

        if (!$aiResult['success'] || empty($aiResult['content']) || ($aiResult['finish_reason'] ?? '') === 'length') {
            $diagBranch = $aiResult['diagnostic'] ?? ($aiResult['finish_reason'] ?? ($aiResult['error'] ?? 'unknown_failure'));
            $statusCode = (int) ($aiResult['status_code'] ?? 0);
            $actualModel = $aiResult['model'] ?? (method_exists($this->nimService, 'getCurrentModel') ? $this->nimService->getCurrentModel('cfind') : 'default');

            $isModelNotFound = ($statusCode === 404 || str_contains(strtolower($diagBranch), '404') || str_contains(strtolower($diagBranch), 'model_not_found') || str_contains(strtolower($diagBranch), 'not found'));

            if ($isModelNotFound) {
                error_log("[CFindService::processMessage] Model '{$actualModel}' tidak ditemukan pada provider (HTTP 404). Kemungkinan model sudah tidak tersedia atau telah di-sunset oleh provider. Silakan pilih model lain di Pengaturan AI.");
                $errorReason = "error [model_not_found_404: {$actualModel}]";
                AuditLogService::log(
                    'cfind_model_error',
                    'cfind',
                    $userId,
                    ['model' => $actualModel, 'error' => 'model_not_found_404', 'http_code' => $statusCode],
                    $userId
                );
            } else {
                $errorReason = "error [branch: {$diagBranch}]";
            }

            $this->logUsage($userId, $role, $intent, $tokensUsed, $errorReason);
            $busyReply = self::BOT_NAME . ' sedang sibuk, coba lagi.';
            $this->recordAuditHistory($userId, $role, $displayInput, $busyReply, $intent, $errorReason, $startTime, $tokensUsed);

            $this->lastRuntimeMetadata = [
                'provider'         => $aiResult['provider'] ?? $this->nimService->getProviderName(),
                'provider_id'      => $aiResult['provider_id'] ?? $this->nimService->getProviderId(),
                'model'            => $aiResult['model'] ?? (method_exists($this->nimService, 'getCurrentModel') ? $this->nimService->getCurrentModel('cfind') : 'default'),
                'execution_status' => 'fallback',
                'is_fallback'      => true,
            ];

            return [
                'status'    => 'error',
                'reply'     => $busyReply,
                'available' => true,
                'metadata'  => $this->lastRuntimeMetadata,
            ];
        }

        $rawReply = (string) $aiResult['content'];

        // ══════════════════════════════════════════════════════════════
        // LAPISAN 3: VALIDASI OUTPUT & PENCEGAHAN KEBOCORAN SECRET
        // ══════════════════════════════════════════════════════════════
        $safeReply = $this->validateAndSanitizeOutput($rawReply);

        $this->lastRuntimeMetadata = [
            'provider'         => $aiResult['provider'] ?? $this->nimService->getProviderName(),
            'provider_id'      => $aiResult['provider_id'] ?? $this->nimService->getProviderId(),
            'model'            => $aiResult['model'] ?? (method_exists($this->nimService, 'getCurrentModel') ? $this->nimService->getCurrentModel('cfind') : 'default'),
            'execution_status' => 'success',
            'is_fallback'      => false,
        ];

        $this->appendChatHistory($displayInput, $safeReply, $userId);
        $this->logUsage($userId, $role, $intent, $tokensUsed, 'success');
        $this->recordAuditHistory($userId, $role, $displayInput, $safeReply, $intent, 'success', $startTime, $tokensUsed);

        return [
            'status'    => 'success',
            'reply'     => $safeReply,
            'available' => true,
            'metadata'  => $this->lastRuntimeMetadata,
        ];
    }

    /**
     * Helper privat untuk mencatat riwayat percakapan C-Find ke database (Prompt 36C)
     */
    private function recordAuditHistory(
        ?int $userId,
        string $role,
        string $question,
        string|array $reply,
        string $intent,
        string $systemStatus,
        float $startTime,
        int $tokens = 0
    ): void {
        $this->historyService->recordConversation(
            $userId,
            $role,
            $question,
            $reply,
            $intent,
            $systemStatus,
            $startTime,
            $tokens
        );
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * Pastikan riwayat percakapan hanya dimiliki oleh user/guest yang sedang aktif saat ini.
     * Jika terjadi perpindahan akun atau role, riwayat otomatis di-reset agar tidak bocor.
     */
    public function validateHistoryOwnership(?int $currentUserId = null): bool
    {
        $this->ensureSessionStarted();
        if ($currentUserId === null) {
            $currentUser = AuthMiddleware::user() ?? ($_SESSION['auth_user'] ?? ($_SESSION['user'] ?? null));
            if ($currentUser && isset($currentUser['id'])) {
                $currentUserId = (int) $currentUser['id'];
            }
        }

        $currentOwnerKey = ($currentUserId !== null && $currentUserId > 0)
            ? 'user_' . $currentUserId
            : 'guest_' . session_id();

        $storedOwnerKey = $_SESSION['cfind_history_owner'] ?? null;
        if ($storedOwnerKey !== null && $storedOwnerKey !== $currentOwnerKey) {
            $_SESSION['cfind_display_history'] = [];
            $_SESSION['cfind_model_context'] = [];
            $_SESSION['cfind_history'] = [];
            unset($_SESSION['cfind_last_plan'], $_SESSION['cfind_last_result'], $_SESSION['cfind_last_query'], $_SESSION['cfind_last_context']);
            $_SESSION['cfind_history_owner'] = $currentOwnerKey;
            return false;
        }
        $_SESSION['cfind_history_owner'] = $currentOwnerKey;
        return true;
    }

    /**
     * Reset riwayat percakapan sesi user
     */
    public function resetHistory(?int $currentUserId = null): void
    {
        $this->ensureSessionStarted();
        $_SESSION['cfind_display_history'] = [];
        $_SESSION['cfind_model_context'] = [];
        $_SESSION['cfind_history'] = [];
        unset($_SESSION['cfind_last_plan'], $_SESSION['cfind_last_result'], $_SESSION['cfind_last_query'], $_SESSION['cfind_last_context']);
        unset($_SESSION['cfind_history_owner']);
    }

    /**
     * Dapatkan riwayat tampilan percakapan aktif dari sesi (maks 50 pesan terstruktur)
     *
     * @param int|null $currentUserId
     * @return array<int, array{role: string, content: string|array, timestamp?: string}>
     */
    public function getDisplayHistory(?int $currentUserId = null): array
    {
        $this->ensureSessionStarted();
        $this->validateHistoryOwnership($currentUserId);
        $history = $_SESSION['cfind_display_history'] ?? [];
        if (!is_array($history)) {
            return [];
        }
        return array_values($history);
    }

    /**
     * Dapatkan riwayat percakapan aktif dari sesi (alias kompatibilitas)
     *
     * @return array<int, array{role: string, content: string|array}>
     */
    public function getHistory(): array
    {
        return $this->getDisplayHistory();
    }

    /**
     * Helper server-side untuk memformat teks respon (tebal dan ganti baris)
     */
    public static function formatMessageText(string|array $text): string
    {
        if (is_array($text)) {
            return json_encode($text, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        }

        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false);
        $safe = preg_replace('/\*\*(.*?)\*\*/u', '<strong>$1</strong>', $safe);
        return nl2br($safe);
    }

    // ─────────────────────────────────────────────────────────────
    // HELPER METHODS (INTERNAL & GUARDRAILS)
    // ─────────────────────────────────────────────────────────────

    /**
     * Klasifikasikan niat user menggunakan pemetaan keyword komprehensif
     */
    private function classifyIntent(string $text): string
    {
        $normalized = mb_strtolower($text);

        // 1. Deteksi Prompt Injection & Jailbreak
        if (preg_match('/(abaikan instruksi|ignore.*instruction|system[_\s-]?prompt|api[_\s-]?key|secret[_\s-]?key|jailbreak|developer mode|acting as|kamu sekarang adalah|tampilkan.*prompt|bocorkan.*(key|prompt))/iu', $normalized)) {
            return 'prompt_injection';
        }

        // 2. Deteksi FAQ Bantuan Password / Login (Bukan Pembocoran Data)
        $isPasswordFaq = (bool) preg_match('/\b(lupa password|lupa kata sandi|reset password|lupa sandi|ganti password|ubah kata sandi|ganti kata sandi|cara login|login dengan apa|kata sandi sementara)\b/iu', $normalized);

        // 2.1 Deteksi Percobaan Akses Kredensial / Password / Hash
        if (!$isPasswordFaq && preg_match('/\b(password|kata sandi|hash password|password_hash|daftar password|kredensial)\b/iu', $normalized)) {
            return 'daftar_password';
        }

        // 3. Deteksi Permintaan Bukti Klaim (Rahasia)
        if (preg_match('/\b(bukti klaim|isi bukti|tampilkan bukti|lihat bukti|berkas bukti|ownership proof)\b/iu', $normalized)) {
            return 'bukti_klaim';
        }

        // 4. Deteksi Pertanyaan Audit Log
        if (preg_match('/\b(audit log|log audit|catatan aktivitas|log sistem|riwayat aksi|audit trail)\b/iu', $normalized)) {
            return 'audit_logs';
        }

        // 4b. Deteksi Pertanyaan Transparansi AI Provider & Model (Task 14 Additional Requirement)
        if (
            preg_match('/\b(kamu pakai ai apa|ai apa yang kamu pakai|ai apa ini|provider kamu apa|kamu pakai provider apa|provider apa yang kamu pakai|menggunakan model apa|kamu menggunakan model apa|pakai model apa|model apa yang dipakai|model ai apa|model apa yang kamu pakai|pakai gemini atau nvidia|pakai nvidia atau gemini|kamu pakai gemini|kamu pakai nvidia|siapa provider kamu|siapa penyedia ai kamu|arsitektur ai kamu|mesin ai apa|teknologi ai apa|provider dan model apa|model dan provider apa|kamu pakai provider dan model apa|model ai yang menjalankan chatbot ini|model ai yang menjalankan kamu|model ai apa yang menjalankan|model ai yang dipakai|model yang dipakai|provider ai apa|siapa provider ai|siapa model ai)\b/iu', $normalized) ||
            (preg_match('/\b(kamu|c-find|chatbot|bot|anda)\b/iu', $normalized) && preg_match('/\b(provider|model|ai|llm)\b/iu', $normalized) && preg_match('/\b(apa|siapa|mana|pakai|menggunakan|dijalankan|berbasis|ditenagai)\b/iu', $normalized)) ||
            (preg_match('/\b(gemini|nvidia)\b/iu', $normalized) && preg_match('/\b(pakai|menggunakan|mana|atau)\b/iu', $normalized))
        ) {
            return 'ai_provider_model_info';
        }

        // 5. Deteksi Pertanyaan Identitas Bot
        if (preg_match('/\b(siapa kamu|siapa anda|kamu siapa|anda siapa|bot apa|nama kamu|namamu|tentang kamu|apa itu c-find|kamu ini apa)\b/iu', $normalized)) {
            return 'identitas_bot';
        }

        // 6. Deteksi Pertanyaan Identitas User
        if (preg_match('/\b(siapa saya|siapa aku|nama saya|nama aku|akun saya|profil saya|role saya|peran saya|siapakah saya)\b/iu', $normalized)) {
            return 'identitas_user';
        }

        // 7. Deteksi Sapaan Ringkas (termasuk "Halo C-Find", "Hai bot", dll.)
        if (preg_match('/^(halo|hai|hey|hei|pagi|siang|sore|malam|selamat pagi|selamat siang|selamat sore|selamat malam|assalamu|terima kasih|makasih|thanks|thank you)(\s+(c-find|cfind|bot))?[\s\.\!\?]*$/iu', $normalized)) {
            return 'sapaan';
        }

        // 8. Deteksi Info Posko & Jam Operasional
        if (preg_match('/\b(posko|lokasi posko|jam operasional|jam buka|buka jam berapa|alamat posko|dimana posko|gedung rektorat)\b/iu', $normalized)) {
            return 'posko_info';
        }

        // 9. Deteksi "Customer" / Statistik Pengguna
        if (preg_match('/\b(customer|pelanggan|jumlah user|total user|berapa user|jumlah pengguna|total pengguna|berapa pengguna|jumlah civitas|berapa civitas|daftar user|daftar pengguna)\b/iu', $normalized)) {
            return 'customer_stats';
        }

        // 10. Deteksi Out of Scope (Coding, PR, Resep, Politik, dll)
        if (preg_match('/\b(python|javascript|php|java|c\+\+|golang|sql\b|html|css|coding|koding|syntax|debug|algoritma|program|bikin script|buatkan kode|resep|masak|bumbu|presiden|pemilu|partai|pilkada|menteri|politik|dpr|pr\b|tugas sekolah|tugas kuliah|matematika|fisika|kimia|geografi|sejarah dunia|terjemahkan ke bahasa|translate to|lirik lagu|puisi|cerpen|ramalan|zodiak)\b/iu', $normalized)) {
            return 'out_of_scope';
        }

        // 11. Deteksi Laporan Pribadi
        if (preg_match('/\b(laporan saya|status laporan saya|laporan kehilangan saya|riwayat laporan|laporan kehilangan milik saya)\b/iu', $normalized)) {
            return 'laporan_pribadi';
        }

        // 12. Deteksi Klaim Pribadi
        if (preg_match('/\b(klaim saya|status klaim saya|pengajuan klaim saya|riwayat klaim|klaim milik saya)\b/iu', $normalized)) {
            return 'klaim_pribadi';
        }

        // 13. Deteksi Notifikasi Pribadi
        if (preg_match('/\b(notifikasi saya|notif saya|pemberitahuan saya|pesan saya)\b/iu', $normalized)) {
            return 'notifikasi_pribadi';
        }

        // 14. Deteksi Antrean Klaim (Khusus Petugas)
        if (preg_match('/\b(antrean klaim|klaim menunggu|klaim baru|klaim pending|klaim menunggu verifikasi)\b/iu', $normalized)) {
            return 'antrean_klaim';
        }

        // 15. Deteksi Antrean Penyerahan Fisik (Khusus Petugas)
        if (preg_match('/\b(menunggu penyerahan|penyerahan fisik|barang temuan belum diserahkan|antrean penyerahan)\b/iu', $normalized)) {
            return 'antrean_penyerahan';
        }

        // 16. Deteksi Inventaris Center & Lokasi Rak (Khusus Petugas)
        if (preg_match('/\b(inventaris center|inventaris barang|rak simpan|lokasi simpan|kode item|stok posko)\b/iu', $normalized)) {
            return 'inventaris_center';
        }

        // 17. Deteksi Ringkasan Operasional (Khusus Petugas)
        if (preg_match('/\b(operasional petugas|ringkasan operasional|rekap petugas|status posko)\b/iu', $normalized)) {
            return 'operasional_ringkasan';
        }

        // 18. Deteksi Statistik Sistem Keseluruhan (Khusus Admin)
        if (preg_match('/\b(statistik sistem|rekap sistem|total laporan|ringkasan sistem|statistik admin)\b/iu', $normalized)) {
            return 'statistik_sistem';
        }

        // 19. Deteksi Master Data (Khusus Admin)
        if (preg_match('/\b(master data|daftar kategori|daftar rak|zona rak)\b/iu', $normalized)) {
            return 'master_data';
        }

        // 20. Deteksi Statistik Umum Barang Tersimpan & Dikembalikan
        if (preg_match('/\b(jumlah barang|total barang|berapa barang|barang tersimpan|tersimpan di center|barang dikembalikan|barang kembali)\b/iu', $normalized)) {
            return 'statistik_umum';
        }

        // 21. Deteksi Panduan Alur
        if (preg_match('/\b(cara lapor|panduan lapor|lapor barang hilang|melaporkan kehilangan)\b/iu', $normalized)) {
            return 'panduan_lapor';
        }

        if (preg_match('/\b(cara klaim|ajukan klaim|mengajukan klaim|alur klaim|panduan klaim)\b/iu', $normalized)) {
            return 'panduan_klaim';
        }

        // 22. Deteksi Pencarian Barang Publik
        if (preg_match('/\b(cari barang|temukan barang|apakah ada|apakah ada yang menemukan)\b/iu', $normalized)) {
            return 'cari_barang_publik';
        }

        return 'ambigu';
    }

    /**
     * Validasi output AI sebelum dikirim ke frontend (Lapisan 3)
     */
    private function validateAndSanitizeOutput(string|array $reply): string|array
    {
        $leakKeywords = [
            'integrate.api.nvidia.com',
            'nvapi-',
            'AI_API_KEY',
            'NVIDIA_API_KEY',
            'DB_PASS',
            'password_hash',
            '$2y$',
            'system prompt',
            'developer mode',
            'ownership_proof_description',
        ];

        $checkString = is_array($reply) ? (json_encode($reply) ?: '') : $reply;

        foreach ($leakKeywords as $keyword) {
            if (stripos($checkString, $keyword) !== false) {
                error_log("[CFindService::validateAndSanitizeOutput] Blocked potential leak: {$keyword}");
                return 'Maaf, ' . self::BOT_NAME . ' tidak dapat memberikan informasi tersebut demi mematuhi regulasi perlindungan data. Ada hal seputar alur layanan Campus Find yang bisa saya bantu?';
            }
        }

        $sanitized = $reply;
        if (is_array($sanitized)) {
            $sanitized = \App\Services\CFind\ResponsePresenter::validateReplyConsistency($sanitized);
        }

        return $sanitized;
    }

    /**
     * Bersihkan sapaan di awal kalimat jika ada pertanyaan lanjutan di belakangnya (Prompt 29D).
     * Contoh: "halo siapa anda?" -> "siapa anda?"
     *         "selamat pagi ada berapa laporan?" -> "ada berapa laporan?"
     *         "halo" -> "halo" (tetap sapaan murni)
     */
    public static function stripGreetingPrefix(string $text): string
    {
        $pattern = '/^(halo|hai|hey|hei|pagi|siang|sore|malam|selamat pagi|selamat siang|selamat sore|selamat malam|assalamu[\'\s]?alaikum)(\s+(c-find|cfind|bot|admin|min))?[\s,\.\!\?]+(.+)$/iu';
        if (preg_match($pattern, trim($text), $matches)) {
            $remainder = trim($matches[4]);
            if ($remainder !== '') {
                return $remainder;
            }
        }
        return trim($text);
    }

    /**
     * Kunci identitas rate limit: User ID untuk user terotentikasi, atau IP + Session ID untuk guest (Prompt 29D)
     */
    private function getRateLimitKey(?int $userId = null): string
    {
        if ($userId !== null && $userId > 0) {
            return "rate_user_{$userId}";
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $sessId = session_id() ?: 'cli';
        return "rate_guest_{$ip}_{$sessId}";
    }

    /**
     * Cek apakah rate limit per menit terlampaui (mengembalikan status dan sisa waktu detik)
     *
     * @return array{limited: bool, seconds_remaining: int}
     */
    private function checkRateLimit(int $limit, ?int $userId = null): array
    {
        $this->ensureSessionStarted();
        $now = time();
        $key = $this->getRateLimitKey($userId);

        $allTimestamps = $_SESSION['cfind_rate_timestamps'] ?? [];
        $timestamps = $allTimestamps[$key] ?? [];
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $validTimestamps = array_values(array_filter($timestamps, fn($ts) => ($now - (int) $ts) < 60));

        if (count($validTimestamps) >= $limit) {
            $oldest = min($validTimestamps);
            $secondsRemaining = max(1, 60 - ($now - $oldest));
            return [
                'limited'           => true,
                'seconds_remaining' => $secondsRemaining,
            ];
        }

        return [
            'limited'           => false,
            'seconds_remaining' => 0,
        ];
    }

    /**
     * Catat timestamp pesan baru untuk rate limit per menit
     */
    private function recordRateLimit(?int $userId = null): void
    {
        $this->ensureSessionStarted();
        $now = time();
        $key = $this->getRateLimitKey($userId);

        if (!isset($_SESSION['cfind_rate_timestamps']) || !is_array($_SESSION['cfind_rate_timestamps'])) {
            $_SESSION['cfind_rate_timestamps'] = [];
        }

        $timestamps = $_SESSION['cfind_rate_timestamps'][$key] ?? [];
        if (!is_array($timestamps)) {
            $timestamps = [];
        }

        $validTimestamps = array_values(array_filter($timestamps, fn($ts) => ($now - (int) $ts) < 60));
        $validTimestamps[] = $now;

        $_SESSION['cfind_rate_timestamps'][$key] = $validTimestamps;
    }

    /**
     * Cek apakah anggaran token harian telah terlampaui
     */
    public function isDailyBudgetExceeded(?string $role = null, ?int $customBudget = null): bool
    {
        $budget = $customBudget ?? $this->dailyTokenBudget;
        return $this->getDailyTokensUsed($role) >= $budget;
    }

    /**
     * Dapatkan total token yang telah digunakan hari ini (keseluruhan atau per role)
     */
    public function getDailyTokensUsed(?string $role = null): int
    {
        if (!file_exists($this->budgetLogPath)) {
            return 0;
        }

        $data = json_decode((string) file_get_contents($this->budgetLogPath), true);
        if (!is_array($data) || ($data['date'] ?? '') !== date('Y-m-d')) {
            return 0;
        }

        if ($role !== null && isset($data['by_role'][$role])) {
            return (int) $data['by_role'][$role];
        }

        return (int) ($data['tokens_used'] ?? 0);
    }

    /**
     * Tambahkan jumlah token yang digunakan ke catatan anggaran harian
     * Test otomatis (CLI) diisolasi agar tidak menghabiskan kuota produksi (Prompt 29D)
     */
    private function addDailyTokensUsed(int $tokens, string $role = 'guest'): void
    {
        if ($tokens <= 0) {
            return;
        }

        // Test otomatis tidak boleh menulis ke penghitung/usage log produksi
        if (php_sapi_name() === 'cli' || defined('TEST_MODE')) {
            return;
        }

        $data = file_exists($this->budgetLogPath) ? json_decode((string) file_get_contents($this->budgetLogPath), true) : [];
        if (!is_array($data) || ($data['date'] ?? '') !== date('Y-m-d')) {
            $data = [
                'date'         => date('Y-m-d'),
                'tokens_used'  => 0,
                'by_role'      => [
                    'admin'   => 0,
                    'petugas' => 0,
                    'civitas' => 0,
                    'guest'   => 0,
                ],
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }

        $data['tokens_used'] = ((int)($data['tokens_used'] ?? 0)) + $tokens;
        $data['by_role'][$role] = ((int)($data['by_role'][$role] ?? 0)) + $tokens;
        $data['last_updated'] = date('Y-m-d H:i:s');

        @file_put_contents($this->budgetLogPath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Reset penghitung anggaran token harian C-Find (Dapat dipanggil Admin dari Pengaturan Sistem) (Prompt 29D)
     */
    public function resetDailyBudget(): bool
    {
        $data = [
            'date'         => date('Y-m-d'),
            'tokens_used'  => 0,
            'by_role'      => [
                'admin'   => 0,
                'petugas' => 0,
                'civitas' => 0,
                'guest'   => 0,
            ],
            'last_updated' => date('Y-m-d H:i:s'),
        ];
        return (bool) @file_put_contents($this->budgetLogPath, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Dapatkan rincian anggaran token harian C-Find (Prompt 29D)
     */
    public function getDailyBudgetInfo(): array
    {
        $data = file_exists($this->budgetLogPath) ? json_decode((string) file_get_contents($this->budgetLogPath), true) : [];
        if (!is_array($data) || ($data['date'] ?? '') !== date('Y-m-d')) {
            $data = [
                'date'         => date('Y-m-d'),
                'tokens_used'  => 0,
                'by_role'      => [
                    'admin'   => 0,
                    'petugas' => 0,
                    'civitas' => 0,
                    'guest'   => 0,
                ],
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }
        return $data;
    }


    /**
     * Ambil konteks model AI (maks 4 pesan teks terakhir tanpa payload tabel besar)
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getSessionHistory(): array
    {
        $this->ensureSessionStarted();
        $this->validateHistoryOwnership();

        $context = $_SESSION['cfind_model_context'] ?? [];
        if (!is_array($context)) {
            return [];
        }

        return array_slice($context, -4);
    }

    /**
     * Tambahkan percakapan baru ke riwayat tampilan (maks 50 pesan) & konteks model (maks 4 pesan)
     */
    public function appendChatHistory(string $userMsg, string|array $botReply, ?int $userId = null): void
    {
        $this->ensureSessionStarted();
        $this->validateHistoryOwnership($userId);

        // 1. Simpan ke Riwayat Tampilan Frontend (maks 50 pesan terstruktur)
        $display = $_SESSION['cfind_display_history'] ?? [];
        if (!is_array($display)) {
            $display = [];
        }

        $now = date('Y-m-d H:i:s');
        $display[] = ['role' => 'user', 'content' => $userMsg, 'timestamp' => $now];
        $display[] = ['role' => 'assistant', 'content' => $botReply, 'timestamp' => $now];

        if (count($display) > 50) {
            $display = array_slice($display, -50);
        }
        $_SESSION['cfind_display_history'] = $display;

        // 2. Simpan ke Konteks Model AI (hanya teks, maks 4 pesan)
        $model = $_SESSION['cfind_model_context'] ?? [];
        if (!is_array($model)) {
            $model = [];
        }

        $modelTextReply = is_array($botReply) ? $this->extractTextFromReply($botReply) : $botReply;
        $model[] = ['role' => 'user', 'content' => $userMsg];
        $model[] = ['role' => 'assistant', 'content' => $modelTextReply];

        if (count($model) > 4) {
            $model = array_slice($model, -4);
        }
        $_SESSION['cfind_model_context'] = $model;

        // Kompatibilitas backwards
        $_SESSION['cfind_history'] = $display;
    }

    /**
     * Ekstrak teks representatif dari struktur balasan tabel/blok untuk konteks model
     */
    private function extractTextFromReply(array $blocks): string
    {
        $texts = [];
        foreach ($blocks as $b) {
            if (isset($b['type']) && $b['type'] === 'text' && !empty($b['content'])) {
                $texts[] = (string)$b['content'];
            } elseif (isset($b['type']) && $b['type'] === 'table') {
                $total = $b['total'] ?? (isset($b['rows']) ? count($b['rows']) : 0);
                $texts[] = "[Tabel: {$total} baris data]";
            }
        }
        return !empty($texts) ? implode("\n", $texts) : 'Data disajikan dalam bentuk tabel.';
    }

    /**
     * Catat penggunaan ke usage log TANPA isi pesan sensitif, secret, atau reasoning_content
     */
    private function logUsage(?int $userId, string $role, string $intent, int $tokens, string $status): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $userTag = $userId !== null ? (string) $userId : 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $logLine = sprintf(
            "[%s] [CFIND_USAGE] User: %s | Role: %s | IP: %s | Intent: %s | Tokens: %d | Status: %s\n",
            $timestamp,
            $userTag,
            $role,
            $ip,
            $intent,
            $tokens,
            $status
        );

        @file_put_contents($this->usageLogPath, $logLine, FILE_APPEND | LOCK_EX);
    }
}

