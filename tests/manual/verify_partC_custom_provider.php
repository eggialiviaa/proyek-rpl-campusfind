<?php

declare(strict_types=1);

define('CAMPUS_FIND_TESTING', true);
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$adminUser = \Core\Database::getInstance()->getPdo()->query("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name = 'admin' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
\App\Middleware\AuthMiddleware::login($adminUser);
$_SESSION['user'] = $adminUser;

use App\Services\AiProviderManager;
use App\Services\SettingService;
use App\Services\OpenAICompatibleService;
use App\Services\AiMatchService;
use App\Services\AuditLogService;
use Core\Database;

echo "====================================================================\n";
echo " PART C — VERIFICATION OF CUSTOM AI PROVIDERS & SSRF PROTECTION\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] {$desc}\n";
        if ($detail !== '') echo "        -> {$detail}\n";
    } else {
        $failCount++;
        echo " [FAIL] {$desc}\n";
        if ($detail !== '') echo "        -> {$detail}\n";
    }
}

// -------------------------------------------------------------------------
// 1. SSRF TABLE TESTS
// -------------------------------------------------------------------------
echo "--------------------------------------------------------------------\n";
echo "1. SSRF VALIDATION TESTS\n";
echo "--------------------------------------------------------------------\n";

$ssrfTestCases = [
    [
        'url'      => 'http://api.openai.com',
        'expected' => false,
        'label'    => 'Reject: not HTTPS (plain HTTP)'
    ],
    [
        'url'      => 'https://api.openai.com:8443',
        'expected' => false,
        'label'    => 'Reject: non-443 port (:8443)'
    ],
    [
        'url'      => 'https://user:pass@api.openai.com',
        'expected' => false,
        'label'    => 'Reject: userinfo in URL'
    ],
    [
        'url'      => 'https://127.0.0.1/v1',
        'expected' => false,
        'label'    => 'Reject: IPv4 loopback literal'
    ],
    [
        'url'      => 'https://169.254.169.254/latest',
        'expected' => false,
        'label'    => 'Reject: link-local cloud metadata'
    ],
    [
        'url'      => 'https://10.0.0.1/v1',
        'expected' => false,
        'label'    => 'Reject: RFC 1918 private IPv4'
    ],
    [
        'url'      => 'https://[::1]/v1',
        'expected' => false,
        'label'    => 'Reject: IPv6 loopback literal'
    ],
    [
        'url'      => 'https://api.groq.com/openai/v1',
        'expected' => true,
        'label'    => 'Accept: valid public HTTPS endpoint (Groq)'
    ],
    [
        'url'      => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'expected' => true,
        'label'    => 'Accept: valid public HTTPS endpoint (Gemini)'
    ],
];

printf("%-55s | %-10s | %-10s | %-6s\n", "Target URL / Test", "Expected", "Actual", "Status");
echo str_repeat("-", 90) . "\n";

foreach ($ssrfTestCases as $tc) {
    $res = AiProviderManager::validateCustomProviderUrl($tc['url']);
    $actual = (bool)$res['valid'];
    $ok = ($actual === $tc['expected']);
    $status = $ok ? "PASS" : "FAIL";
    if ($ok) $passCount++; else $failCount++;
    printf("%-55s | %-10s | %-10s | %-6s\n", substr($tc['url'], 0, 55), $tc['expected'] ? 'ACCEPT' : 'REJECT', $actual ? 'ACCEPT' : 'REJECT', $status);
}

// Mock DNS resolution tests
echo "\n--- Mocked DNS Resolution Tests ---\n";

// Mock 1: Neutral hostname resolving ONLY to private IP (192.168.1.100)
$mockPrivateDns = fn(string $h) => ['192.168.1.100'];
$resMock1 = AiProviderManager::validateCustomProviderUrl('https://api.example-provider.com/v1', $mockPrivateDns);
$mock1Ok = ($resMock1['valid'] === false 
    && str_contains($resMock1['error'] ?? '', 'Private network') 
    && !str_contains(strtolower($resMock1['error'] ?? ''), 'localhost'));
assertTest("Mock DNS: Neutral hostname (api.example-provider.com) resolves to 192.168.1.100 -> Rejected with private range reason (not 'localhost')", $mock1Ok, $resMock1['error'] ?? '');

// Mock 2: Hostname resolving to 1 public and 1 private IP
$mockMixedDns = fn(string $h) => ['93.184.216.34', '10.0.0.5'];
$resMock2 = AiProviderManager::validateCustomProviderUrl('https://mixed-ip.example.com/v1', $mockMixedDns);
$mock2Ok = ($resMock2['valid'] === false);
assertTest("Mock DNS: Hostname resolves to 1 public + 1 private IP -> Rejected (ANY private rejected)", $mock2Ok, $resMock2['error'] ?? '');

// -------------------------------------------------------------------------
// 2. TEMPORARY CUSTOM PROVIDER INTEGRATION TEST
// -------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "2. TEMPORARY CUSTOM PROVIDER INTEGRATION TEST\n";
echo "--------------------------------------------------------------------\n";

// Fetch existing stored Gemini key
$existingGeminiKey = AiProviderManager::resolveEffectiveApiKey('google_gemini');
if (empty($existingGeminiKey)) {
    echo " [WARN] Google Gemini key is empty via AiProviderManager. Checking SettingService...\n";
    $existingGeminiKey = SettingService::get('ai_provider_google_gemini_key') ?: SettingService::get('ai_api_key');
}

$last4 = substr($existingGeminiKey, -4);
echo "Using stored Gemini key (last4: ••••{$last4}) for custom provider test.\n";

// Pre-cleanup of any lingering test providers
foreach (AiProviderManager::getCustomProviders() as $cp) {
    if (str_starts_with($cp['id'] ?? '', 'gemini_openai_compat_')) {
        AiProviderManager::deleteCustomProvider($cp['id'], 1);
    }
}

$addResult = AiProviderManager::addCustomProvider(
    "Gemini OpenAI Compat",
    "https://generativelanguage.googleapis.com/v1beta/openai",
    $existingGeminiKey,
    1
);

assertTest("Add custom provider 'Gemini OpenAI Compat'", $addResult['success'] === true, $addResult['message'] ?? '');
$testProviderId = $addResult['provider']['id'] ?? '';
echo "Generated custom provider ID: {$testProviderId}\n";

$customService = new OpenAICompatibleService($testProviderId);

// 2a. Test GET /models
echo "\n--- 2a. Test GET /models ---\n";
$models = $customService->fetchModels(true);
$modelsCount = count($models);
assertTest("fetchModels() via custom provider path returns models", $modelsCount > 0, "Found {$modelsCount} models.");
if ($modelsCount > 0) {
    echo "        Top models: ";
    $sample = array_slice($models, 0, 3);
    foreach ($sample as $s) echo "[" . ($s['id'] ?? '') . "] ";
    echo "\n";
}

// 2b. Test chat through custom provider path
echo "\n--- 2b. Test chat completions ---\n";
$chatStart = microtime(true);
$chatPrompt = [['role' => 'user', 'content' => 'Sebutkan 1 warna buah pisang matang dalam satu kata:']];
$chatRes = $customService->chat($chatPrompt, ['temperature' => 0.1, 'max_tokens' => 100]);
$chatDuration = round(microtime(true) - $chatStart, 3);

$chatSuccess = ($chatRes['success'] === true && !empty($chatRes['content']));
assertTest("chat() via custom provider returns valid response", $chatSuccess, "Duration: {$chatDuration}s, Provider ID: {$chatRes['provider_id']}, Model: {$chatRes['model']}");
echo "        Response: " . trim((string)$chatRes['content']) . "\n";

// 2c. Test 3-candidate matching
echo "\n--- 2c. Test 3-candidate matching ---\n";
$targetReport = [
    'id'            => 101,
    'title'         => 'Kunci Motor Honda Vario Hitam',
    'category_name' => 'Kunci',
    'location_name' => 'Perpustakaan Pusat',
    'lost_date'     => '2026-11-10',
    'description'   => 'Gantungan kunci bertuliskan Honda warna merah, ada remote alarm.',
];

$candidate1 = [
    'id'            => 201,
    'title'         => 'Kunci Motor Honda Vario Hitam Remote',
    'category_name' => 'Kunci',
    'location_name' => 'Perpustakaan Pusat',
    'found_date'    => '2026-11-10',
    'description'   => 'Ditemukan kunci motor Vario hitam ada gantungan merah dan remote di meja baca perpustakaan.',
];

$candidate2 = [
    'id'            => 202,
    'title'         => 'Kunci Motor Yamaha NMAX Biru',
    'category_name' => 'Kunci',
    'location_name' => 'Parkiran Motor Gedung C',
    'found_date'    => '2026-11-11',
    'description'   => 'Kunci motor Yamaha ada gantungan kulit cokelat.',
];

$candidate3 = [
    'id'            => 203,
    'title'         => 'Botol Minum Tupperware Hijau',
    'category_name' => 'Lainnya',
    'location_name' => 'Kantin Pusat',
    'found_date'    => '2026-11-12',
    'description'   => 'Tumbler warna hijau ukuran 500ml.',
];

$evalStart = microtime(true);
$evalRes = $customService->evaluateCandidates($targetReport, [$candidate1, $candidate2, $candidate3], 'lost');
$evalDuration = round(microtime(true) - $evalStart, 3);

$parseStatus = $evalRes['parse_status'] ?? 'unknown';
$scoreMatch   = (float)($evalRes['scores'][201]['score'] ?? 0);
$scoreSimilar = (float)($evalRes['scores'][202]['score'] ?? 0);
$scoreUnrel   = (float)($evalRes['scores'][203]['score'] ?? 0);

echo "Matching results (Duration: {$evalDuration}s):\n";
echo " - Candidate 201 (Match):   {$scoreMatch}% - " . ($evalRes['scores'][201]['reason'] ?? '') . "\n";
echo " - Candidate 202 (Similar): {$scoreSimilar}% - " . ($evalRes['scores'][202]['reason'] ?? '') . "\n";
echo " - Candidate 203 (Unrel):   {$scoreUnrel}% - " . ($evalRes['scores'][203]['reason'] ?? '') . "\n";

assertTest("evaluateCandidates parse_status === 'parsed'", $parseStatus === 'parsed', "Actual parse_status: '{$parseStatus}'");
assertTest("score(match) > score(similar)", $scoreMatch > $scoreSimilar, "{$scoreMatch}% > {$scoreSimilar}%");
assertTest("score(similar) > score(unrelated)", $scoreSimilar > $scoreUnrel, "{$scoreSimilar}% > {$scoreUnrel}%");

// -------------------------------------------------------------------------
// 3. NEGATIVE TEST (MALFORMED AI RESPONSE)
// -------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "3. NEGATIVE TEST (MALFORMED AI RESPONSE)\n";
echo "--------------------------------------------------------------------\n";

// Create mock provider that returns malformed output
$mockService = new class implements \App\Services\AiProviderInterface {
    public function getProviderId(): string { return 'mock_malformed'; }
    public function getProviderName(): string { return 'Mock Malformed Provider'; }
    public function getEffectiveApiKey(?string $feature = null): string { return 'mock'; }
    public function isAvailable(): bool { return true; }
    public function isChatAvailable(): bool { return true; }
    public function getCurrentModel(string $feature = 'cfind'): string { return 'mock-model'; }
    public function getEmbedding(string $text): ?array { return null; }
    public function matchReports(array $lostReport, array $foundReport): array {
        return ['score' => 0.0, 'explanation' => '', 'provider' => 'mock'];
    }
    public function chat(array $messages, array $options = []): array {
        return ['success' => false, 'content' => null, 'error' => 'Malformed mock'];
    }
    public function evaluateCandidates(array $targetReport, array $candidateReports, string $targetType = 'lost'): array {
        return [
            'status'           => 'invalid_json',
            'parse_status'     => 'failed',
            'model'            => 'mock-model',
            'duration_ms'      => 10,
            'scores'           => [],
            'error'            => 'Format JSON tidak valid dari model.',
        ];
    }
};

$pdo = Database::getInstance()->getPdo();
$reportNumber = 'LOST-TEST-' . time();
$pdo->exec("INSERT INTO lost_reports (report_number, reporter_user_id, title, description, category_id, last_seen_location_id, lost_date, status, created_at, updated_at) VALUES ('{$reportNumber}', 1, 'Test Lost Part C Negative', 'Deskripsi uji', 1, 1, '2026-11-15', 'published', NOW(), NOW())");
$tempLostId = (int)$pdo->lastInsertId();

$foundReportNumber = 'FOUND-TEST-' . time();
$pdo->exec("INSERT INTO found_reports (report_number, reporter_user_id, title, description, category_id, location_id, found_date, status, created_at, updated_at) VALUES ('{$foundReportNumber}', 1, 'Test Found Part C Negative', 'Deskripsi uji temuan', 1, 1, '2026-11-15', 'published', NOW(), NOW())");
$tempFoundId = (int)$pdo->lastInsertId();

$matchService = new AiMatchService($mockService);

// Count before
$countBefore = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates")->fetchColumn();

// Execute matching with mock
$matchRes = $matchService->runMatchingForLostReport($tempLostId);

$countAfter = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates")->fetchColumn();
$countForThisReport = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates WHERE lost_report_id = {$tempLostId}")->fetchColumn();

// Cleanup temporary test reports
$pdo->exec("DELETE FROM lost_reports WHERE id = {$tempLostId}");
$pdo->exec("DELETE FROM found_reports WHERE id = {$tempFoundId}");

assertTest("evaluateCandidates called and parse_status === 'failed'", ($matchRes['diagnostic']['parse_status'] ?? '') === 'failed', "Diagnostic parse_status: " . ($matchRes['diagnostic']['parse_status'] ?? 'none'));
assertTest("No matching rows saved to database when parse_status === 'failed'", $countBefore === $countAfter && $countForThisReport === 0, "Rows before: {$countBefore}, rows after: {$countAfter}");
assertTest("No candidates returned as matched when parse_status === 'failed'", empty($matchRes['candidates']), "Candidates count: " . count($matchRes['candidates']));

// -------------------------------------------------------------------------
// 4. CONTROLLER ACTIONS & LIFECYCLE FOR TEMPORARY CUSTOM PROVIDER
// -------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "4. CONTROLLER ACTIONS & LIFECYCLE FOR TEMPORARY CUSTOM PROVIDER\n";
echo "--------------------------------------------------------------------\n";

$csrfToken = \App\Middleware\CsrfMiddleware::getToken();

function invokeAiAction(string $method, array $post): array {
    global $csrfToken;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $post;
    if (!isset($_POST['_csrf_token'])) {
        $_POST['_csrf_token'] = $csrfToken;
    }
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];

    ob_start();
    $caughtJson = null;
    try {
        $ctrl = new \App\Controllers\AdminController();
        $ctrl->$method();
    } catch (\RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'JSON:')) {
            $caughtJson = substr($e->getMessage(), 5);
        } else {
            echo "RUNTIME_ERROR: " . $e->getMessage() . "\n";
        }
    } catch (\Throwable $e) {
        echo "THROWABLE: " . $e->getMessage() . "\n";
    }
    $raw = ob_get_clean();
    if ($caughtJson !== null) {
        $raw = $caughtJson;
    }
    $decoded = json_decode($raw, true);
    return ['raw' => $raw, 'data' => $decoded];
}

// 4a. Action: Save Key (Fake)
echo "\n--- 4a. Action: Save Key (Fake: ••••8888) ---\n";
$res4a = invokeAiAction('saveAiProviderConnection', [
    'provider_id' => $testProviderId,
    'api_key'     => 'test_fake_api_key_8888',
    'base_url'    => 'https://generativelanguage.googleapis.com/v1beta/openai',
]);
echo "Raw JSON 4a: " . $res4a['raw'] . "\n";
assertTest("saveAiProviderConnection returns valid JSON", $res4a['data'] !== null);
assertTest("saveAiProviderConnection contains fields read by JS", 
    isset($res4a['data']['success'], $res4a['data']['message'], $res4a['data']['key_last4'], $res4a['data']['source'], $res4a['data']['status']));
assertTest("Key updated to fake key (last4: 8888)", ($res4a['data']['key_last4'] ?? '') === '8888');

// 4b. Action: Change Name
echo "\n--- 4b. Action: Change Custom Provider Name ---\n";
$res4b = invokeAiAction('saveAiProviderConnection', [
    'provider_id' => $testProviderId,
    'name'        => 'Gemini OpenAI Compat Renamed',
    'base_url'    => 'https://generativelanguage.googleapis.com/v1beta/openai',
]);
echo "Raw JSON 4b: " . $res4b['raw'] . "\n";
assertTest("Custom provider name changed via saveAiProviderConnection", ($res4b['data']['name'] ?? '') === 'Gemini OpenAI Compat Renamed');

// 4c. Action: Save with Unchanged URL (key kept, robust normalization)
echo "\n--- 4c. Action: Save with Unchanged URL (with trailing slash) -> Key KEPT ---\n";
$res4c = invokeAiAction('saveAiProviderConnection', [
    'provider_id' => $testProviderId,
    'base_url'    => 'https://generativelanguage.googleapis.com/v1beta/openai/', // trailing slash
    'api_key'     => '', // empty key: should KEEP active key
]);
echo "Raw JSON 4c: " . $res4c['raw'] . "\n";
assertTest("Saving unchanged normalized URL does NOT clear key", 
    empty($res4c['data']['key_cleared']) && ($res4c['data']['key_last4'] ?? '') === '8888');

// 4d. Action: Change Base URL (Key cleared, as designed for security)
echo "\n--- 4d. Action: Change Base URL -> Key CLEARED (Security Design) ---\n";
$res4d = invokeAiAction('saveAiProviderConnection', [
    'provider_id' => $testProviderId,
    'base_url'    => 'https://generativelanguage.googleapis.com/v1beta/openai', // switch to another endpoint
    'api_key'     => '',
]);
// To genuinely change URL, let's pass a different allowed path
$res4dChange = invokeAiAction('saveAiProviderConnection', [
    'provider_id' => $testProviderId,
    'base_url'    => 'https://api.groq.com/openai/v1',
    'api_key'     => '',
]);
echo "Raw JSON 4d: " . $res4dChange['raw'] . "\n";
assertTest("Changing Base URL clears key and notifies admin", 
    !empty($res4dChange['data']['key_cleared']) && ($res4dChange['data']['key_last4'] ?? '') === '');

// 4e. Action: Save Key (Restore Original Gemini Key)
echo "\n--- 4e. Action: Save Key (Restore) ---\n";
$res4e = invokeAiAction('saveAiProviderConnection', [
    'provider_id' => $testProviderId,
    'base_url'    => 'https://generativelanguage.googleapis.com/v1beta/openai',
    'api_key'     => $existingGeminiKey,
]);
echo "Raw JSON 4e: " . $res4e['raw'] . "\n";
assertTest("Restore key via saveAiProviderConnection", ($res4e['data']['key_last4'] ?? '') === $last4);

// 4f. Action: Test Provider Connection (Uji Koneksi)
echo "\n--- 4f. Action: Uji Koneksi Live via Controller ---\n";
$res4f = invokeAiAction('testAiProviderConnection', [
    'provider_id' => $testProviderId,
]);
echo "Raw JSON 4f: " . $res4f['raw'] . "\n";
assertTest("testAiProviderConnection returns valid JSON", $res4f['data'] !== null);
assertTest("testAiProviderConnection succeeds with HTTP 200", 
    ($res4f['data']['success'] ?? false) === true && ($res4f['data']['http_status'] ?? 0) === 200);

// 4g. Action: Delete Protection & Delete Custom Provider
echo "\n--- 4g. Action: Delete Protection & Hapus Provider ---\n";
// Set as default
SettingService::set('ai_default_provider', $testProviderId);
SettingService::clearCache();

$resDelBlocked = invokeAiAction('deleteCustomAiProvider', [
    'provider_id' => $testProviderId,
]);
echo "Raw JSON Delete Blocked: " . $resDelBlocked['raw'] . "\n";
assertTest("deleteCustomAiProvider blocked when provider is Default", 
    ($resDelBlocked['data']['success'] ?? true) === false);

// Revert default to nvidia_nim
SettingService::set('ai_default_provider', 'nvidia_nim');
SettingService::clearCache();

// Now delete custom provider
$resDelSuccess = invokeAiAction('deleteCustomAiProvider', [
    'provider_id' => $testProviderId,
]);
echo "Raw JSON Delete Success: " . $resDelSuccess['raw'] . "\n";
assertTest("deleteCustomAiProvider succeeds when provider is unassigned", 
    ($resDelSuccess['data']['success'] ?? false) === true);

// Verify it is gone from ai_custom_providers
$customsAfter = AiProviderManager::getCustomProviders();
$foundInCustoms = false;
foreach ($customsAfter as $cp) {
    if ($cp['id'] === $testProviderId) $foundInCustoms = true;
}
assertTest("Custom provider entry removed from ai_custom_providers JSON", !$foundInCustoms);

// Verify encrypted key row is removed from system_settings
$keyRowStmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE `key` = ?");
$keyRowStmt->execute(["ai_provider_{$testProviderId}_key"]);
$keyRowCount = (int)$keyRowStmt->fetchColumn();
assertTest("Encrypted key row 'ai_provider_{$testProviderId}_key' deleted from database", $keyRowCount === 0, "Key row count: {$keyRowCount}");

// -------------------------------------------------------------------------
// 5. AUDIT LOG VALIDATION
// -------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "5. AUDIT LOG VALIDATION\n";
echo "--------------------------------------------------------------------\n";

$auditStmt = $pdo->prepare("
    SELECT id, action, entity_type, payload, created_at 
    FROM audit_logs 
    WHERE action IN ('add_custom_ai_provider', 'delete_custom_ai_provider')
    ORDER BY id DESC LIMIT 5
");
$auditStmt->execute();
$auditRows = $auditStmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Recent audit logs for custom providers:\n";
$noSecretLeaked = true;
foreach ($auditRows as $row) {
    echo " - ID #{$row['id']} | Action: {$row['action']} | Payload: {$row['payload']}\n";
    if (strpos((string)$row['payload'], $existingGeminiKey) !== false) {
        $noSecretLeaked = false;
    }
}
assertTest("Audit logs contain no raw secrets (only masked last4 / non-sensitive metadata)", $noSecretLeaked);

// -------------------------------------------------------------------------
// 6. FINAL CLEANUP & STATE VERIFICATION
// -------------------------------------------------------------------------
echo "\n--------------------------------------------------------------------\n";
echo "6. FINAL CLEANUP & SYSTEM STATE CHECK\n";
echo "--------------------------------------------------------------------\n";

SettingService::set('ai_default_provider', 'nvidia_nim');
SettingService::clearCache();

$defaultProvFinal = SettingService::get('ai_default_provider');
$cfindProvFinal = SettingService::get('ai_provider_cfind', '');
$matchProvFinal = SettingService::get('ai_provider_matching', '');

$nvidiaKey = AiProviderManager::resolveEffectiveApiKey('nvidia_nim');
$geminiKey = AiProviderManager::resolveEffectiveApiKey('google_gemini');

$nvidiaLast4 = substr($nvidiaKey, -4);
$geminiLast4 = substr($geminiKey, -4);

echo "Final System State:\n";
echo " - Default Provider: {$defaultProvFinal}\n";
echo " - C-Find Provider Override: " . ($cfindProvFinal ?: '(follows default)') . "\n";
echo " - Matching Provider Override: " . ($matchProvFinal ?: '(follows default)') . "\n";
echo " - NVIDIA NIM Key in DB: ••••{$nvidiaLast4}\n";
echo " - Google Gemini Key in DB: ••••{$geminiLast4}\n";

assertTest("Default provider is 'nvidia_nim'", $defaultProvFinal === 'nvidia_nim');
assertTest("NVIDIA NIM key is intact", !empty($nvidiaKey) && strlen($nvidiaLast4) === 4);
assertTest("Google Gemini key is intact", !empty($geminiKey) && strlen($geminiLast4) === 4);

echo "\n====================================================================\n";
echo " VERIFICATION SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
