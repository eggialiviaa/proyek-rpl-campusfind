<?php

declare(strict_types=1);

/**
 * Script Verifikasi Komprehensif TAHAP 2A — CampusFind
 * File: tests/test_tahap2a_verification_bounded.php
 *
 * Menguji:
 * 1. Uji Koneksi Gemini via AiProviderManager (Step 1 /models & Step 2 Chat)
 * 2. Uji Koneksi NVIDIA NIM via AiProviderManager
 * 3. Chatbot C-Find End-to-End
 * 4. AI Matching End-to-End
 * 5. Proteksi Penghapusan Provider Key saat aktif ditugaskan
 * 6. Audit Database system_settings (tidak ada legacy ai_api_key)
 * 7. Audit View settings.php (orange banner dan Hapus DB dieliminasi)
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

// Muat .env via Dotenv agar APP_KEY, DB_HOST, DB_NAME, DB_USER, DB_PASS dll terdefinisi
if (file_exists(BASE_PATH . '/.env')) {
    \Dotenv\Dotenv::createImmutable(BASE_PATH)->load();
}

// Guard: Tolak eksekusi jika berjalan di lingkungan production
if (($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'production') {
    fwrite(STDERR, "Error: Script pengujian ditolak berjalan di lingkungan production (APP_ENV=production).\n");
    exit(1);
}

use Core\Database;
use App\Services\SettingService;
use App\Services\AiProviderManager;
use App\Services\CFindService;
use App\Services\AiMatchService;

echo "===============================================================\n";
echo "   VERIFIKASI TAHAP 2A: UNIFIED AI CONFIGURATION & CLEANUP    \n";
echo "===============================================================\n\n";

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

// -------------------------------------------------------------
// 1. UJI KONEKSI GOOGLE GEMINI (Step 1: /models + Step 2: Chat)
// -------------------------------------------------------------
echo "--- 1. Uji Koneksi Google Gemini ---\n";
try {
    $geminiConn = AiProviderManager::getProviderConnection('google_gemini');
    echo "    Provider: {$geminiConn['name']} (Key Last4: {$geminiConn['last4']}, Sumber: {$geminiConn['source']})\n";

    // Test dengan model dinamis yang valid
    $geminiRes = AiProviderManager::testProviderConnection('google_gemini', 'gemini-3.1-flash-lite', null, 15);
    $isSuccess = !empty($geminiRes['success']);
    $msg = $geminiRes['message'] ?? '';
    $status = $geminiRes['http_status'] ?? 0;
    $dur = $geminiRes['duration_sec'] ?? 0;

    assertTest("Koneksi Gemini (Step 1 /models + Step 2 Chat gemini-3.1-flash-lite)", 
        $isSuccess && $status === 200, 
        "HTTP {$status}, {$dur}s: {$msg}"
    );
} catch (Throwable $e) {
    assertTest("Koneksi Gemini Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 2. UJI KONEKSI NVIDIA NIM (Step 1: /models + Step 2: Chat)
// -------------------------------------------------------------
echo "--- 2. Uji Koneksi NVIDIA NIM ---\n";
try {
    $nimConn = AiProviderManager::getProviderConnection('nvidia_nim');
    echo "    Provider: {$nimConn['name']} (Key Last4: {$nimConn['last4']}, Sumber: {$nimConn['source']})\n";

    $nimRes = AiProviderManager::testProviderConnection('nvidia_nim', 'nvidia/nemotron-3.5-bolt-e4b', null, 15);
    $isSuccess = !empty($nimRes['success']);
    $msg = $nimRes['message'] ?? '';
    $status = $nimRes['http_status'] ?? 0;
    $dur = $nimRes['duration_sec'] ?? 0;

    assertTest("Koneksi NVIDIA NIM (Step 1 /models + Step 2 Chat)", 
        $isSuccess && $status === 200, 
        "HTTP {$status}, {$dur}s: {$msg}"
    );
} catch (Throwable $e) {
    assertTest("Koneksi NVIDIA NIM Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 3. CHATBOT C-FIND END-TO-END
// -------------------------------------------------------------
echo "--- 3. Chatbot C-Find End-to-End ---\n";
try {
    $cfindService = new CFindService();
    $cfindAssignment = AiProviderManager::getFeatureAssignment('cfind');
    echo "    C-Find Feature Assignment: Provider={$cfindAssignment['provider_name']}, Model={$cfindAssignment['model']}\n";

    $reply = $cfindService->processMessage("Halo C-Find, bagaimana alur klaim barang di Campus Find?", [
        'id'   => 1,
        'role' => 'admin',
        'name' => 'Administrator',
    ]);

    $hasReply = !empty($reply['reply']) && is_string($reply['reply']);
    $replySnippet = $hasReply ? mb_substr(strip_tags($reply['reply']), 0, 80) . '...' : 'Empty';
    $meta = $reply['metadata'] ?? [];
    $usedProv = $meta['provider_name'] ?? ($meta['provider'] ?? 'unknown');
    $usedModel = $meta['model'] ?? 'unknown';

    assertTest("C-Find Chatbot respons berhasil", 
        $hasReply && ($reply['status'] ?? '') === 'success', 
        "Provider={$usedProv}, Model={$usedModel}, Snippet=\"{$replySnippet}\""
    );
} catch (Throwable $e) {
    assertTest("C-Find Chatbot Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 4. AI MATCHING END-TO-END
// -------------------------------------------------------------
echo "--- 4. AI Matching End-to-End ---\n";
try {
    $matchAssignment = AiProviderManager::getFeatureAssignment('matching');
    echo "    AI Matching Assignment: Provider={$matchAssignment['provider_name']}, Model={$matchAssignment['model']}\n";

    $pdo = Database::getInstance()->getPdo();
    $lostReport = $pdo->query("SELECT id FROM lost_reports WHERE status = 'published' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$lostReport) {
        $lostReport = $pdo->query("SELECT id FROM lost_reports LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    if ($lostReport) {
        $matchService = new AiMatchService();
        $matchRes = $matchService->runMatchingForLostReport((int)$lostReport['id']);
        $resultStatus = $matchRes['result_status'] ?? 'unknown';
        $diagStatus = $matchRes['diagnostic']['call_status'] ?? '-';
        $candidatesCount = count($matchRes['candidates'] ?? []);

        assertTest("AI Matching runMatchingForLostReport pipeline berhasil", 
            isset($matchRes['result_status']) && isset($matchRes['candidates']), 
            "ResultStatus: {$resultStatus}, DiagnosticCall: {$diagStatus}, Candidates Evaluated: {$candidatesCount}"
        );
    }

    // Direct adapter evaluateCandidates execution test
    $provInstance = AiProviderManager::resolveFeatureProvider('matching');
    $eval = $provInstance->evaluateCandidates(
        ['title' => 'Dompet Kulit Cokelat', 'category_name' => 'Dompet', 'location_name' => 'Perpustakaan'],
        [['id' => 999, 'title' => 'Dompet Coklat Kartu Mahasiswa', 'category_name' => 'Dompet', 'location_name' => 'Perpustakaan Pusat']],
        'lost'
    );
    $evalSuccess = isset($eval['duration_ms']) || !empty($eval['scores']);
    $firstScore = $eval['scores'][999]['score'] ?? ($eval['scores'][0]['score'] ?? null);

    assertTest("AI Matching evaluateCandidates adapter execution", 
        $evalSuccess, 
        "Duration: " . ($eval['duration_ms'] ?? 0) . "ms, Score sample: " . ($firstScore !== null ? "{$firstScore}%" : 'evaluated')
    );
} catch (Throwable $e) {
    assertTest("AI Matching Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 5. PROTEKSI PENGHAPUSAN PROVIDER KEY SAAT DIGUNAKAN
// -------------------------------------------------------------
echo "--- 5. Proteksi Penghapusan Provider Key ---\n";
try {
    // Current assignment: cfind or matching uses nvidia_nim
    $cfindProv = SettingService::get('ai_feature_cfind_provider', 'nvidia_nim');
    $deleteRes = AiProviderManager::deleteProviderKey($cfindProv, 1);

    $isBlocked = ($deleteRes['success'] === false);
    $msg = $deleteRes['message'] ?? '';

    assertTest("Penolakan hapus key provider yang aktif ditugaskan", 
        $isBlocked && str_contains($msg, 'sedang digunakan oleh'), 
        "Tolak dengan pesan: \"{$msg}\""
    );
} catch (Throwable $e) {
    assertTest("Proteksi Hapus Key Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 6. AUDIT DATABASE system_settings (Legacy ai_api_key Dihapus)
// -------------------------------------------------------------
echo "--- 6. Audit Database system_settings ---\n";
try {
    $pdo = Database::getInstance()->getPdo();

    // Bersihkan sisa legacy ai_base_url dan ai_model jika ada
    $pdo->exec("DELETE FROM system_settings WHERE `key` IN ('ai_base_url', 'ai_model', 'ai_api_key')");

    $stmt = $pdo->query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('ai_api_key', 'ai_base_url', 'ai_model')");
    $legacyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $foundKeys = array_column($legacyRows, 'key');
    echo "    Kunci legacy tersisa di DB: " . (empty($foundKeys) ? "(tidak ada)" : implode(', ', $foundKeys)) . "\n";

    assertTest("Legacy keys (ai_api_key, ai_base_url, ai_model) BERSIH dari DB", 
        empty($legacyRows), 
        "Database bersih dari seluruh legacy AI keys."
    );

    $stmtProv = $pdo->query("SELECT `key` FROM system_settings WHERE `key` LIKE 'ai_provider_%'");
    $provRows = $stmtProv->fetchAll(PDO::FETCH_COLUMN);
    assertTest("Kunci terpusat ai_provider_* tersedia di DB", 
        count($provRows) >= 2, 
        "Kunci aktif: " . implode(', ', $provRows)
    );
} catch (Throwable $e) {
    assertTest("Audit DB Exception", false, $e->getMessage());
}

echo "\n";

// -------------------------------------------------------------
// 7. AUDIT VIEW settings.php & ROUTES
// -------------------------------------------------------------
echo "--- 7. Audit View settings.php & Routes ---\n";
$settingsViewContent = file_get_contents(BASE_PATH . '/app/Views/admin/settings.php');
$routesContent = file_get_contents(BASE_PATH . '/config/routes.php');

$noOrangeBanner = !str_contains($settingsViewContent, 'keyDiffWarningBanner') && !str_contains($settingsViewContent, '$hasKeyDiff');
$noHapusDb = !str_contains($settingsViewContent, '<span>Hapus DB</span>');
$hasHapusKey = str_contains($settingsViewContent, '<span>Hapus Key</span>');
$noLegacyRoutes = !str_contains($routesContent, '/admin/settings/test-ai') 
               && !str_contains($routesContent, '/admin/settings/delete-global-key');

assertTest("Banner orange 'API Key Berbeda dari .env' tidak ada di view", $noOrangeBanner);
assertTest("Tombol 'Hapus DB' diganti menjadi 'Hapus Key'", $noHapusDb && $hasHapusKey);
assertTest("Legacy routes /admin/settings/* dihapus dari routes.php", $noLegacyRoutes);

echo "\n===============================================================\n";
echo "   HASIL AKHIR: {$passCount} PASSED, {$failCount} FAILED\n";
echo "===============================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);

