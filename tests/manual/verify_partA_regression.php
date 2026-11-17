<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\CFindService;
use App\Services\NvidiaNimService;
use App\Services\AiProviderManager;
use App\Services\SettingService;

echo "======================================================================\n";
echo "PART A: REGRESSION TEST (NVIDIA FOR ALL FEATURES)\n";
echo "======================================================================\n\n";

$pdo = Database::getInstance()->getPdo();

// Record baseline max IDs for cleanup
$maxCfindHistoryId = (int)$pdo->query("SELECT IFNULL(MAX(id), 0) FROM cfind_chat_history")->fetchColumn();
$maxAuditId = (int)$pdo->query("SELECT IFNULL(MAX(id), 0) FROM audit_logs")->fetchColumn();

// ---------------------------------------------------------------------
// 1. C-FIND CHATBOT RUN (NVIDIA NIM)
// ---------------------------------------------------------------------
echo "--- 1. C-FIND CHATBOT REAL REQUEST ---\n";

$cfindProvider = AiProviderManager::resolveFeatureProvider('cfind');
$cfindProviderId = $cfindProvider->getProviderId();
$cfindProviderName = $cfindProvider->getProviderName();
$cfindModel = method_exists($cfindProvider, 'getCurrentModel') 
    ? $cfindProvider->getCurrentModel('cfind') 
    : SettingService::get('ai_default_model');

echo "Feature: Chatbot C-Find\n";
echo "Resolved Provider: {$cfindProviderName} ({$cfindProviderId})\n";
echo "Resolved Model: {$cfindModel}\n";

$cfindService = new CFindService($cfindProvider);
$testUser = [
    'id'       => 1,
    'username' => 'admin_test',
    'nama'     => 'Administrator Test',
    'role'     => 'admin',
];

$cfindStart = microtime(true);
$cfindResult = $cfindService->processMessage("Halo C-Find, fitur utama apa saja di Campus Find?", $testUser);
$cfindDuration = round(microtime(true) - $cfindStart, 3);

$cfindMeta = $cfindResult['metadata'] ?? [];
$cfindStatus = $cfindResult['status'] ?? 'unknown';
$cfindReply = is_array($cfindResult['reply'] ?? null) 
    ? json_encode($cfindResult['reply']) 
    : (string)($cfindResult['reply'] ?? '');

$cfindSnippet = mb_strlen($cfindReply) > 120 ? mb_substr($cfindReply, 0, 120) . '...' : $cfindReply;

echo "Duration: {$cfindDuration}s\n";
echo "Status: {$cfindStatus}\n";
echo "Execution Status: " . ($cfindMeta['execution_status'] ?? '-') . "\n";
echo "Fallback Active: " . (($cfindMeta['is_fallback'] ?? false) ? 'YES' : 'NO') . "\n";
echo "Reply Snippet: \"{$cfindSnippet}\"\n\n";

// ---------------------------------------------------------------------
// 2. NVIDIA AI-MATCHING RUN (NVIDIA NIM)
// ---------------------------------------------------------------------
echo "--- 2. NVIDIA AI-MATCHING REAL REQUEST ---\n";

$nvidiaService = new NvidiaNimService();
$matchingProviderId = $nvidiaService->getProviderId();
$matchingProviderName = $nvidiaService->getProviderName();
$matchingModel = $nvidiaService->getCurrentModel('matching');

echo "Feature: Pencocokan AI\n";
echo "Resolved Provider: {$matchingProviderName} ({$matchingProviderId})\n";
echo "Resolved Model: {$matchingModel}\n";

$targetReport = [
    'id'            => 100,
    'title'         => 'Dompet Kulit Hitam Eiger',
    'category_name' => 'Aksesoris & Dompet',
    'location_name' => 'Perpustakaan Lantai 2',
    'lost_date'     => '2026-11-14',
    'description'   => 'Dompet kulit pria lipat hitam merk Eiger hilang tercecer di meja baca perpustakaan lantai 2.',
];

$candidates = [
    [
        'id'            => 101, // Clearly matching
        'title'         => 'Dompet Pria Kulit Hitam Eiger',
        'category_name' => 'Aksesoris & Dompet',
        'location_name' => 'Perpustakaan Lantai 2',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan dompet kulit pria lipat warna hitam ada logo Eiger di meja baca perpustakaan lantai 2.',
    ],
    [
        'id'            => 102, // Same category different item
        'title'         => 'Dompet Pria Kulit Cokelat Eiger',
        'category_name' => 'Aksesoris & Dompet',
        'location_name' => 'Perpustakaan Lantai 2',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan dompet kulit pria lipat warna cokelat tua merk Eiger di area rak buku perpustakaan lantai 2.',
    ],
    [
        'id'            => 103, // Clearly unrelated
        'title'         => 'Helm Bogo Hitam Doff',
        'category_name' => 'Kendaraan & Helm',
        'location_name' => 'Parkiran Sepeda Motor Barat',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan helm bogo warna hitam doff tergantung di spion sepeda motor parkiran barat.',
    ],
];

$matchStart = microtime(true);
$matchResult = $nvidiaService->evaluateCandidates($targetReport, $candidates, 'lost');
$matchDuration = round(microtime(true) - $matchStart, 3);

$matchStatus = $matchResult['status'] ?? 'unknown';
$matchParseStatus = $matchResult['parse_status'] ?? 'unknown';
$scores = $matchResult['scores'] ?? [];

$score101 = (float)($scores[101]['score'] ?? 0);
$score102 = (float)($scores[102]['score'] ?? 0);
$score103 = (float)($scores[103]['score'] ?? 0);

echo "Duration: {$matchDuration}s\n";
echo "Status: {$matchStatus}\n";
echo "Parse Status: {$matchParseStatus}\n";
echo "Candidate #101 (Match) Score: {$score101}\n";
echo "Candidate #102 (Similar) Score: {$score102}\n";
echo "Candidate #103 (Unrelated) Score: {$score103}\n\n";

// ---------------------------------------------------------------------
// 3. CLEANUP TEST ROWS
// ---------------------------------------------------------------------
echo "--- 3. CLEANUP TEST DATA ---\n";
$stmtDelCfind = $pdo->prepare("DELETE FROM cfind_chat_history WHERE id > ?");
$stmtDelCfind->execute([$maxCfindHistoryId]);
$cfindDeleted = $stmtDelCfind->rowCount();

$stmtDelAudit = $pdo->prepare("DELETE FROM audit_logs WHERE id > ?");
$stmtDelAudit->execute([$maxAuditId]);
$auditDeleted = $stmtDelAudit->rowCount();

echo "Deleted {$cfindDeleted} temporary C-Find conversation row(s).\n";
echo "Deleted {$auditDeleted} temporary C-Find audit log row(s).\n\n";

// ---------------------------------------------------------------------
// 4. REGRESSION SUMMARY
// ---------------------------------------------------------------------
echo "======================================================================\n";
echo "REGRESSION TEST SUMMARY TABLE\n";
echo "======================================================================\n";
printf("%-20s | %-12s | %-32s | %-10s | %-12s | %-8s\n", "Feature", "Provider", "Model", "Status", "Parse Status", "Duration");
echo str_repeat("-", 105) . "\n";
printf("%-20s | %-12s | %-32s | %-10s | %-12s | %-8s\n", "Chatbot C-Find", $cfindProviderId, $cfindModel, $cfindStatus, "N/A", "{$cfindDuration}s");
printf("%-20s | %-12s | %-32s | %-10s | %-12s | %-8s\n", "Pencocokan AI", $matchingProviderId, $matchingModel, $matchStatus, $matchParseStatus, "{$matchDuration}s");
echo str_repeat("-", 105) . "\n";

$cfindPassed = ($cfindStatus === 'success' && !($cfindMeta['is_fallback'] ?? false));
$matchPassed = ($matchStatus === 'success' && $matchParseStatus === 'parsed' && $score101 > $score102 && $score102 > $score103);

if ($cfindPassed && $matchPassed) {
    echo "PART A REGRESSION: 100% PASSED [SUCCESS]\n";
    exit(0);
} else {
    echo "PART A REGRESSION: FAILED\n";
    exit(1);
}
