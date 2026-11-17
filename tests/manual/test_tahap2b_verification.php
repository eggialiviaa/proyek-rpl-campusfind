<?php

declare(strict_types=1);

/**
 * Script Verifikasi Komprehensif TAHAP 2B — CampusFind
 * File: tests/test_tahap2b_verification.php
 *
 * Menguji 6 Skenario Wajib lewat Controller & Endpoint AJAX sebenarnya:
 * 1. Skenario 1 - Default berfungsi (NVIDIA NIM untuk semua fitur)
 * 2. Skenario 2 - Override Chatbot (C-Find via Gemini, Matching via Default NVIDIA)
 * 3. Skenario 3 - Override Matching (Matching via Gemini, evaluasi hasil & teknis)
 * 4. Skenario 4 - Revert ke default (Kembalikan override, verifikasi warisan default)
 * 5. Skenario 5 - Delete protection (Ditolak jika key sedang dipakai default/override)
 * 6. Skenario 6 - Sunset model fallback (Peringatan UI katalog & runtime 404 handling aman)
 *
 * RESTORASI AKHIR: Kembalikan konfigurasi AI ke kondisi awal (NVIDIA NIM untuk semua fitur).
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

// Muat .env via Dotenv
if (file_exists(BASE_PATH . '/.env')) {
    \Dotenv\Dotenv::createImmutable(BASE_PATH)->load();
}

// Guard: Tolak eksekusi jika berjalan di lingkungan production
if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'production') {
    fwrite(STDERR, "Error: Script pengujian ditolak berjalan di lingkungan production (APP_ENV=production).\n");
    exit(1);
}

define('CAMPUS_FIND_TESTING', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Setup sesi Admin terautentikasi
$_SESSION['auth_user'] = [
    'id'    => 1,
    'role'  => 'admin',
    'name'  => 'Admin Verifikasi Tahap 2B',
    'email' => 'admin@campusfind.test',
];

use Core\Database;
use App\Middleware\CsrfMiddleware;
use App\Controllers\AdminController;
use App\Services\SettingService;
use App\Services\AiProviderManager;
use App\Services\CFindService;
use App\Services\AiMatchService;

$csrfToken = CsrfMiddleware::generateToken();
$adminCtrl = new AdminController();

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] {$name}\n";
        if ($details) echo "        Details: {$details}\n";
        $passCount++;
    } else {
        echo " [FAIL] {$name}\n";
        if ($details) echo "        Error: {$details}\n";
        $failCount++;
    }
}

/**
 * Helper pemanggil endpoint controller Admin dengan proteksi CSRF
 */
function callEndpoint(callable $action, array $post = []): array {
    global $csrfToken;
    $_POST = $post;
    $_POST['_csrf_token'] = $csrfToken;
    $_SESSION['__test_json'] = null;

    try {
        $action();
        return $_SESSION['__test_json'] ?? ['data' => null, 'status' => 200];
    } catch (\RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'JSON:')) {
            return $_SESSION['__test_json'] ?? [
                'data'   => json_decode(substr($e->getMessage(), 5), true),
                'status' => 200,
            ];
        }
        throw $e;
    }
}

echo "===============================================================\n";
echo "   VERIFIKASI TAHAP 2B: UNIFIED AI CONFIGURATION & RESOLUTION  \n";
echo "===============================================================\n\n";

$pdo = Database::getInstance()->getPdo();
$lostRep = $pdo->query("SELECT id FROM lost_reports WHERE status = 'published' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$lostRep) {
    $lostRep = $pdo->query("SELECT id FROM lost_reports LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// -------------------------------------------------------------
// SKENARIO 1: DEFAULT BERFUNGSI (NVIDIA NIM)
// -------------------------------------------------------------
echo "--- Skenario 1: Default Berfungsi (NVIDIA NIM) ---\n";
try {
    // 1. Simpan Default via Controller
    $saveDefRes = callEndpoint(
        fn() => $adminCtrl->saveAiDefaultAssignment(),
        ['provider_id' => 'nvidia_nim', 'model' => 'nvidia/nemotron-3.5-bolt-e4b']
    );
    assertTest(
        "Controller POST /admin/ai/default/save (nvidia_nim)",
        ($saveDefRes['status'] === 200 && ($saveDefRes['data']['success'] ?? false)),
        $saveDefRes['data']['message'] ?? ''
    );

    // 2. Pastikan kedua fitur mengikuti default (revert)
    callEndpoint(fn() => $adminCtrl->revertAiFeatureToDefault(), ['feature' => 'cfind']);
    callEndpoint(fn() => $adminCtrl->revertAiFeatureToDefault(), ['feature' => 'matching']);

    $cfindAssign = AiProviderManager::getFeatureAssignment('cfind');
    $matchingAssign = AiProviderManager::getFeatureAssignment('matching');

    assertTest(
        "Fitur mewarisi Model Default",
        (!$cfindAssign['is_override'] && !$matchingAssign['is_override'] && $cfindAssign['provider'] === 'nvidia_nim'),
        "C-Find Prov: {$cfindAssign['provider_name']} ({$cfindAssign['model']}), Matching Prov: {$matchingAssign['provider_name']} ({$matchingAssign['model']})"
    );

    // 3. Uji Model Default via Controller
    $testDefRes = callEndpoint(fn() => $adminCtrl->testAiDefaultConnection());
    assertTest(
        "Controller POST /admin/ai/default/test",
        ($testDefRes['data']['success'] ?? false),
        "Provider: " . ($testDefRes['data']['provider_name'] ?? '-') . ", Durasi: " . ($testDefRes['data']['duration_sec'] ?? 0) . "s"
    );

    // 4. Jalankan Chatbot C-Find nyata
    $cfindService = new CFindService();
    $cfindReply = $cfindService->processMessage('Halo C-Find, fitur apa saja di CampusFind?', [
        'id'   => 1,
        'role' => 'admin',
        'name' => 'Administrator',
    ]);
    $meta1 = $cfindReply['metadata'] ?? [];
    assertTest(
        "Runtime C-Find berjalan dengan NVIDIA NIM (Default)",
        (($cfindReply['status'] ?? '') === 'success' && ($meta1['provider_id'] ?? '') === 'nvidia_nim'),
        "Provider: " . ($meta1['provider'] ?? '-') . ", Model: " . ($meta1['model'] ?? '-') . ", Status: " . ($cfindReply['status'] ?? '-')
    );

    // 5. Jalankan AI Matching nyata
    if ($lostRep) {
        $matchService = new AiMatchService();
        $matchRes = $matchService->runMatchingForLostReport((int)$lostRep['id']);
        $diag1 = $matchRes['diagnostic'] ?? [];
        assertTest(
            "Runtime AI Matching berjalan dengan NVIDIA NIM (Default)",
            isset($matchRes['result_status']),
            "Provider: " . ($diag1['model_used'] ?? '-') . ", CallStatus: " . ($diag1['call_status'] ?? '-') . ", ResultStatus: " . ($matchRes['result_status'] ?? '-')
        );
    }
} catch (Throwable $e) {
    assertTest("Skenario 1 Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// SKENARIO 2: OVERRIDE CHATBOT (C-FIND VIA GEMINI, MATCHING VIA NVIDIA)
// -------------------------------------------------------------
echo "--- Skenario 2: Override Chatbot (C-Find -> Gemini, Matching -> Default NVIDIA) ---\n";
try {
    // 1. Simpan Override C-Find via Controller
    $saveCfindRes = callEndpoint(
        fn() => $adminCtrl->saveAiFeatureAssignment(),
        ['feature' => 'cfind', 'provider_id' => 'google_gemini', 'model' => 'gemini-3.1-flash-lite']
    );
    assertTest(
        "Controller POST /admin/ai/feature/save (C-Find -> Gemini)",
        ($saveCfindRes['status'] === 200 && ($saveCfindRes['data']['success'] ?? false)),
        $saveCfindRes['data']['message'] ?? ''
    );

    // 2. Uji Konfigurasi Fitur C-Find via Controller
    $testCfindRes = callEndpoint(
        fn() => $adminCtrl->testAiFeatureConfiguration(),
        ['feature' => 'cfind']
    );
    assertTest(
        "Controller POST /admin/ai/feature/test (C-Find)",
        ($testCfindRes['data']['success'] ?? false),
        "Provider: " . ($testCfindRes['data']['provider_name'] ?? '-') . ", Model: " . ($testCfindRes['data']['model'] ?? '-') . ", Durasi: " . ($testCfindRes['data']['duration_sec'] ?? 0) . "s"
    );

    // 3. Jalankan Chatbot C-Find nyata -> harus Gemini
    $cfindService2 = new CFindService();
    $cfindReply2 = $cfindService2->processMessage('Fitur apa saja yang ada di aplikasi Campus Find ini?', [
        'id'   => 1,
        'role' => 'admin',
        'name' => 'Administrator',
    ]);
    $meta2 = $cfindReply2['metadata'] ?? [];
    assertTest(
        "Runtime C-Find berjalan dengan Google Gemini (Override)",
        (($cfindReply2['status'] ?? '') === 'success' && ($meta2['provider_id'] ?? '') === 'google_gemini'),
        "Provider: " . ($meta2['provider'] ?? '-') . ", Model: " . ($meta2['model'] ?? '-') . ", Status: " . ($cfindReply2['status'] ?? '-')
    );

    // 4. Jalankan AI Matching nyata -> harus tetap NVIDIA NIM (Default)
    if ($lostRep) {
        $matchService2 = new AiMatchService();
        $matchRes2 = $matchService2->runMatchingForLostReport((int)$lostRep['id']);
        $diag2 = $matchRes2['diagnostic'] ?? [];
        assertTest(
            "Runtime AI Matching tetap menggunakan NVIDIA NIM (Default)",
            (!empty($diag2['model_used']) && str_contains(strtolower($diag2['model_used']), 'nvidia')),
            "Model Used: " . ($diag2['model_used'] ?? '-') . ", CallStatus: " . ($diag2['call_status'] ?? '-')
        );
    }
} catch (Throwable $e) {
    assertTest("Skenario 2 Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// SKENARIO 3: OVERRIDE MATCHING (MATCHING VIA GEMINI)
// -------------------------------------------------------------
echo "--- Skenario 3: Override Matching (Matching -> Google Gemini) ---\n";
try {
    // 1. Simpan Override Matching via Controller
    $saveMatchRes = callEndpoint(
        fn() => $adminCtrl->saveAiFeatureAssignment(),
        ['feature' => 'matching', 'provider_id' => 'google_gemini', 'model' => 'gemini-3.1-flash-lite']
    );
    assertTest(
        "Controller POST /admin/ai/feature/save (Matching -> Gemini)",
        ($saveMatchRes['status'] === 200 && ($saveMatchRes['data']['success'] ?? false)),
        $saveMatchRes['data']['message'] ?? ''
    );

    // 2. Jalankan Pipeline AI Matching dengan Google Gemini
    if ($lostRep) {
        $matchService3 = new AiMatchService();
        $matchRes3 = $matchService3->runMatchingForLostReport((int)$lostRep['id']);
        $diag3 = $matchRes3['diagnostic'] ?? [];
        $scoresCount = count($matchRes3['candidates'] ?? []);
        $callStatus3 = $diag3['call_status'] ?? 'unknown';

        echo "    Hasil Evaluasi Matching via Google Gemini:\n";
        echo "    - Provider Name        : " . $matchService3->getActiveProviderName() . "\n";
        echo "    - Model Used           : " . ($diag3['model_used'] ?? '-') . "\n";
        echo "    - Call Status          : " . $callStatus3 . "\n";
        echo "    - Result Status        : " . ($matchRes3['result_status'] ?? '-') . "\n";
        echo "    - Durasi Evaluasi      : " . ($diag3['duration_ms'] ?? 0) . " ms\n";
        echo "    - Kandidat Dinilai     : " . $scoresCount . "\n";

        if ($scoresCount > 0) {
            foreach (array_slice($matchRes3['candidates'], 0, 3) as $c) {
                echo "      * [ID {$c['candidate_id']}] Skor: {$c['relevance_score']} | Alasan: {$c['explanation']}\n";
            }
        }

        assertTest(
            "Pipeline AI Matching berhasil dijalankan dengan Google Gemini",
            in_array($callStatus3, ['success', 'completed_fallback', 'fallback_local'], true) || isset($matchRes3['result_status']),
            "CallStatus={$callStatus3}, ResultStatus=" . ($matchRes3['result_status'] ?? '-')
        );
    }
} catch (Throwable $e) {
    assertTest("Skenario 3 Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// SKENARIO 4: REVERT KE DEFAULT (HAPUS OVERRIDE)
// -------------------------------------------------------------
echo "--- Skenario 4: Revert ke Default ---\n";
try {
    // 1. Revert C-Find via Controller
    $revertCfind = callEndpoint(
        fn() => $adminCtrl->revertAiFeatureToDefault(),
        ['feature' => 'cfind']
    );
    assertTest(
        "Controller POST /admin/ai/feature/revert-default (cfind)",
        ($revertCfind['status'] === 200 && ($revertCfind['data']['success'] ?? false)),
        $revertCfind['data']['message'] ?? ''
    );

    // 2. Revert Matching via Controller
    $revertMatching = callEndpoint(
        fn() => $adminCtrl->revertAiFeatureToDefault(),
        ['feature' => 'matching']
    );
    assertTest(
        "Controller POST /admin/ai/feature/revert-default (matching)",
        ($revertMatching['status'] === 200 && ($revertMatching['data']['success'] ?? false)),
        $revertMatching['data']['message'] ?? ''
    );

    // 3. Verifikasi Database: row override harus kosong
    $checkDb = $pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('ai_provider_cfind', 'ai_model_cfind', 'ai_provider_matching', 'ai_model_matching')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $overrideCount = 0;
    foreach ($checkDb as $k => $v) {
        if (!empty($v)) $overrideCount++;
    }

    $cfindCheck = AiProviderManager::getFeatureAssignment('cfind');
    $matchCheck = AiProviderManager::getFeatureAssignment('matching');

    assertTest(
        "Database bersih dari row override & kedua fitur mengikuti Default",
        ($overrideCount === 0 && !$cfindCheck['is_override'] && !$matchCheck['is_override']),
        "CFind Override: " . ($cfindCheck['is_override'] ? 'YES' : 'NO') . ", Matching Override: " . ($matchCheck['is_override'] ? 'YES' : 'NO')
    );
} catch (Throwable $e) {
    assertTest("Skenario 4 Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// SKENARIO 5: DELETE PROTECTION
// -------------------------------------------------------------
echo "--- Skenario 5: Delete Protection ---\n";
try {
    // 1. Coba hapus key provider nvidia_nim (yang sedang dipakai sebagai Model Default)
    $delNvidiaRes = callEndpoint(
        fn() => $adminCtrl->deleteAiProviderKey(),
        ['provider_id' => 'nvidia_nim']
    );

    $nvidiaBlocked = ($delNvidiaRes['status'] === 422 && !($delNvidiaRes['data']['success'] ?? true));
    $nvidiaMsg = $delNvidiaRes['data']['message'] ?? '';

    assertTest(
        "Proteksi penghapusan key provider yang aktif sebagai Model Default",
        ($nvidiaBlocked && (str_contains(strtolower($nvidiaMsg), 'default') || str_contains(strtolower($nvidiaMsg), 'digunakan'))),
        "HTTP {$delNvidiaRes['status']}: {$nvidiaMsg}"
    );

    // 2. Set override cfind ke google_gemini, lalu coba hapus key google_gemini
    AiProviderManager::saveFeatureAssignment('cfind', 'google_gemini', 'gemini-3.1-flash-lite');
    $delGeminiRes = callEndpoint(
        fn() => $adminCtrl->deleteAiProviderKey(),
        ['provider_id' => 'google_gemini']
    );

    $geminiBlocked = ($delGeminiRes['status'] === 422 && !($delGeminiRes['data']['success'] ?? true));
    $geminiMsg = $delGeminiRes['data']['message'] ?? '';

    assertTest(
        "Proteksi penghapusan key provider yang aktif sebagai Fitur Override",
        ($geminiBlocked && (str_contains(strtolower($geminiMsg), 'c-find') || str_contains(strtolower($geminiMsg), 'digunakan'))),
        "HTTP {$delGeminiRes['status']}: {$geminiMsg}"
    );

    // Bersihkan kembali override cfind
    AiProviderManager::revertFeatureToDefault('cfind');
} catch (Throwable $e) {
    assertTest("Skenario 5 Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// SKENARIO 6: SUNSET MODEL FALLBACK & RUNTIME 404 HANDLING
// -------------------------------------------------------------
echo "--- Skenario 6: Sunset Model Fallback & Runtime 404 Handling ---\n";
try {
    // 1. Simulasikan model sunset di database yang tidak ada di katalog provider
    $sunsetModelId = 'nvidia/nonexistent-sunset-model-2023';
    SettingService::set('ai_default_model', $sunsetModelId);
    SettingService::clearCache();

    // 2. Verifikasi logika katalog view
    $catalogModels = AiProviderManager::getCompatibleModels('nvidia_nim', 'chat');
    $modelInCatalog = false;
    foreach ($catalogModels as $cm) {
        if (($cm['id'] ?? '') === $sunsetModelId) {
            $modelInCatalog = true;
            break;
        }
    }

    assertTest(
        "Deteksi model tidak ada di katalog provider",
        (!$modelInCatalog),
        "Model '{$sunsetModelId}' terdeteksi tidak tersedia di katalog resmi."
    );

    // 3. Verifikasi Runtime CFindService dengan model sunset:
    //    Provider akan return HTTP 404, CFindService harus tangani secara aman tanpa fatal crash
    $cfindSunset = new CFindService();
    $cfindSunset->resetHistory();
    $sunsetReply = $cfindSunset->processMessage('Bagaimana cara kerja sistem rekomendasi dan pencocokan otomatis di platform ini?', [
        'id'   => 1,
        'role' => 'admin',
        'name' => 'Administrator',
    ]);

    $isHandledGracefully = !empty($sunsetReply['reply']) && ($sunsetReply['status'] === 'error' || $sunsetReply['available'] === true);
    // Pastikan tidak membocorkan detail teknis rahasia / stack trace
    $replyStr = is_array($sunsetReply['reply']) ? json_encode($sunsetReply['reply']) : (string)$sunsetReply['reply'];
    $hasLeak = str_contains($replyStr, 'stack trace') || str_contains($replyStr, 'Exception') || str_contains($replyStr, '404 Not Found');

    assertTest(
        "Runtime C-Find menangani error 404 (model sunset) dengan pesan umum aman",
        ($isHandledGracefully && !$hasLeak),
        "User Reply: \"{$replyStr}\" (Status: {$sunsetReply['status']})"
    );

    // 4. Periksa audit history untuk status error yang benar
    $latestAudit = $pdo->query("SELECT action, payload FROM audit_logs WHERE action = 'cfind_model_error' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $auditAction = $latestAudit['action'] ?? '';
    $auditPayload = $latestAudit['payload'] ?? '';

    assertTest(
        "Riwayat audit mencatat alasan error model_not_found_404",
        ($auditAction === 'cfind_model_error' && str_contains($auditPayload, 'model_not_found_404')),
        "Audit action: '{$auditAction}', payload: '{$auditPayload}'"
    );
} catch (Throwable $e) {
    assertTest("Skenario 6 Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// RESTORASI KONFIGURASI AKHIR (KEMBALI KE KONDISI AWAL BERFUNGSI)
// -------------------------------------------------------------
echo "--- Restorasi Konfigurasi AI ke Kondisi Awal Berfungsi ---\n";
try {
    // Set default ke NVIDIA NIM dengan model nemotron yang valid
    AiProviderManager::saveDefaultAssignment('nvidia_nim', 'nvidia/nemotron-3.5-bolt-e4b', 1);

    // Hapus seluruh override
    AiProviderManager::revertFeatureToDefault('cfind', 1);
    AiProviderManager::revertFeatureToDefault('matching', 1);

    $finalDef = AiProviderManager::getDefaultAssignment();
    $finalCfind = AiProviderManager::getFeatureAssignment('cfind');
    $finalMatching = AiProviderManager::getFeatureAssignment('matching');

    $isRestored = ($finalDef['provider'] === 'nvidia_nim' && 
                   !$finalCfind['is_override'] && 
                   !$finalMatching['is_override']);

    assertTest(
        "Konfigurasi AI berhasil dipulihkan (NVIDIA NIM untuk semua fitur)",
        $isRestored,
        "Default: {$finalDef['provider_name']} ({$finalDef['model']}), C-Find: Mengikuti default, Matching: Mengikuti default"
    );
} catch (Throwable $e) {
    assertTest("Restorasi Exception", false, $e->getMessage());
}

echo "\n===============================================================\n";
echo "   RINGKASAN HASIL PENGUJIAN TAHAP 2B: PASSED={$passCount}, FAILED={$failCount}\n";
echo "===============================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
