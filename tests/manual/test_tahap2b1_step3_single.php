<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\GoogleGeminiService;
use App\Services\AiMatchService;
use App\Services\AiProviderInterface;
use App\Services\AiProviderManager;
use App\Services\SettingService;

$pdo = Database::getInstance()->getPdo();

echo "======================================================================\n";
echo "STEP 3: CONSOLIDATED VERIFICATION RUNNER\n";
echo "======================================================================\n\n";

// =====================================================================
// PART 1: Gemini Matching with 3 Candidates
// =====================================================================
echo "----------------------------------------------------------------------\n";
echo "PART 1: Gemini Matching with 3 Candidates\n";
echo "----------------------------------------------------------------------\n";

$targetReport = [
    'id'            => 100,
    'title'         => 'Dompet Kulit Pria Hitam Eiger',
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
        'id'            => 102, // Same-category different item/color
        'title'         => 'Dompet Pria Kulit Cokelat Eiger',
        'category_name' => 'Aksesoris & Dompet',
        'location_name' => 'Perpustakaan Lantai 2',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan dompet kulit pria lipat warna cokelat tua merk Eiger di rak buku perpustakaan lantai 2.',
    ],
    [
        'id'            => 103, // Unrelated
        'title'         => 'Helm Bogo Hitam Doff',
        'category_name' => 'Kendaraan & Helm',
        'location_name' => 'Parkiran Sepeda Motor Barat',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan helm bogo warna hitam doff tergantung di spion motor parkiran barat.',
    ],
];

$gemini = new GoogleGeminiService();
$geminiRes = $gemini->evaluateCandidates($targetReport, $candidates, 'lost');

echo "Gemini Status: " . ($geminiRes['status'] ?? '-') . "\n";
echo "Gemini Parse Status: " . ($geminiRes['parse_status'] ?? '-') . "\n";
echo "Gemini Model Used: " . ($geminiRes['model'] ?? '-') . "\n";
echo "Gemini Duration: " . ($geminiRes['duration_ms'] ?? 0) . "ms\n";
echo "Gemini Raw Model Output (Redacted):\n" . ($geminiRes['raw_content'] ?? '') . "\n\n";
echo "Gemini Parsed Scores:\n";
print_r($geminiRes['scores'] ?? []);

$scoreMatch = (float)($geminiRes['scores'][101]['score'] ?? 0);
$scoreSimilar = (float)($geminiRes['scores'][102]['score'] ?? 0);
$scoreUnrelated = (float)($geminiRes['scores'][103]['score'] ?? 0);

echo "Score Hierarchy Check:\n";
echo " - Clearly Matching (ID 101): {$scoreMatch}\n";
echo " - Same-Category Different (ID 102): {$scoreSimilar}\n";
echo " - Clearly Unrelated (ID 103): {$scoreUnrelated}\n";

$passPart1 = (
    ($geminiRes['parse_status'] ?? '') === 'parsed' &&
    $scoreMatch > $scoreSimilar &&
    $scoreSimilar > $scoreUnrelated
);

echo "Assertion Part 1 (parse_status === 'parsed' AND score(match) > score(similar) > score(unrelated)): "
    . ($passPart1 ? "[PASS]" : "[FAIL]") . "\n\n";

// =====================================================================
// PART 2: Mock a Malformed Model Response
// =====================================================================
echo "----------------------------------------------------------------------\n";
echo "PART 2: Negative Test (Mocked Malformed Model Response)\n";
echo "----------------------------------------------------------------------\n";

class MockMalformedProvider implements AiProviderInterface
{
    public function getProviderId(): string { return 'mock_malformed'; }
    public function getProviderName(): string { return 'Mock Malformed'; }
    public function isAvailable(): bool { return true; }
    public function isChatAvailable(): bool { return true; }
    public function getEffectiveApiKey(?string $feature = null): string { return 'mock_key'; }
    public function getBaseUrl(): string { return 'http://mock.test'; }
    public function getModel(): string { return 'mock-model'; }
    public function setModel(string $model): void {}
    public function getCurrentModel(string $feature = 'cfind'): string { return 'mock-model'; }
    public function getEmbedding(string $text): ?array { return null; }
    public function matchReports(array $lostReport, array $foundReport): array {
        return ['score' => 0.0, 'explanation' => 'mock', 'provider' => 'mock_malformed'];
    }
    public function chat(array $messages, array $options = []): array {
        return ['success' => true, 'content' => 'INVALID_JSON_RESPONSE'];
    }
    public function evaluateCandidates(array $targetReport, array $candidateReports, string $targetType = 'lost'): array {
        return [
            'status'           => 'failed',
            'parse_status'     => 'failed',
            'model'            => 'mock-model',
            'duration_ms'      => 30,
            'scores'           => [],
            'public_text_sent' => [],
            'raw_content'      => 'BROKEN_NON_JSON_CONTENT',
            'error'            => 'JSON parsing failed',
        ];
    }
}

// Ambil laporan untuk testing
$lost = $pdo->query("SELECT * FROM lost_reports LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$found = $pdo->query("SELECT * FROM found_reports LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$lostId = (int)$lost['id'];
$foundId = (int)$found['id'];
$origLostStatus = $lost['status'];
$origFoundStatus = $found['status'];

$pdo->exec("UPDATE lost_reports SET status = 'published' WHERE id = {$lostId}");
$pdo->exec("UPDATE found_reports SET status = 'published', category_id = {$lost['category_id']} WHERE id = {$foundId}");

$matchesCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates")->fetchColumn();
$notifsCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

$mockProvider = new MockMalformedProvider();
$matchService = new AiMatchService($mockProvider);
$negativeResult = $matchService->runMatchingForLostReport($lostId);

$matchesCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates")->fetchColumn();
$notifsCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

// Restore status
$pdo->exec("UPDATE lost_reports SET status = '{$origLostStatus}' WHERE id = {$lostId}");
$pdo->exec("UPDATE found_reports SET status = '{$origFoundStatus}', category_id = {$found['category_id']} WHERE id = {$foundId}");

$diag = $negativeResult['diagnostic'] ?? [];
$passCallNotSuccess = ($diag['call_status'] ?? '') !== 'success';
$passParseNotParsed = ($diag['parse_status'] ?? '') !== 'parsed';
$passNothingStored  = ($matchesCountAfter === $matchesCountBefore);
$passNoNotification = ($notifsCountAfter === $notifsCountBefore);

echo "Negative Test Results:\n";
echo " - Call status not success: " . ($passCallNotSuccess ? "[PASS] ({$diag['call_status']})" : "[FAIL]") . "\n";
echo " - Parse status not parsed: " . ($passParseNotParsed ? "[PASS] ({$diag['parse_status']})" : "[FAIL]") . "\n";
echo " - match_candidates count delta: " . ($matchesCountAfter - $matchesCountBefore) . " " . ($passNothingStored ? "[PASS]" : "[FAIL]") . "\n";
echo " - notifications count delta: " . ($notifsCountAfter - $notifsCountBefore) . " " . ($passNoNotification ? "[PASS]" : "[FAIL]") . "\n";

$passPart2 = ($passCallNotSuccess && $passParseNotParsed && $passNothingStored && $passNoNotification);
echo "Assertion Part 2 (not success, nothing stored, no notification): " . ($passPart2 ? "[PASS]" : "[FAIL]") . "\n\n";

// =====================================================================
// PART 3: Final Configuration Enforcement (NVIDIA NIM for all features)
// =====================================================================
echo "----------------------------------------------------------------------\n";
echo "PART 3: Final Configuration State\n";
echo "----------------------------------------------------------------------\n";

// Set default to NVIDIA NIM
AiProviderManager::saveDefaultAssignment('nvidia_nim', 'nvidia/nemotron-3.5-bolt-e4b', 1);

// Revert feature overrides so all features follow default NVIDIA NIM
AiProviderManager::revertFeatureToDefault('cfind', 1);
AiProviderManager::revertFeatureToDefault('matching', 1);

$defaultConfig = AiProviderManager::getDefaultAssignment();
$cfindConfig = AiProviderManager::getFeatureAssignment('cfind');
$matchingConfig = AiProviderManager::getFeatureAssignment('matching');

echo "Current System AI Configuration:\n";
echo " - Default Provider: {$defaultConfig['provider']} ({$defaultConfig['provider_name']})\n";
echo " - Default Model:    {$defaultConfig['model']}\n";
echo " - C-Find Feature:   {$cfindConfig['provider']} ({$cfindConfig['model']}) [Override: " . ($cfindConfig['is_override'] ? 'YES' : 'NO') . "]\n";
echo " - Matching Feature: {$matchingConfig['provider']} ({$matchingConfig['model']}) [Override: " . ($matchingConfig['is_override'] ? 'YES' : 'NO') . "]\n";

$nvLast4 = substr(AiProviderManager::resolveEffectiveApiKey('nvidia_nim'), -4);
$gemLast4 = substr(AiProviderManager::resolveEffectiveApiKey('google_gemini'), -4);
echo " - NVIDIA Key last4: {$nvLast4}\n";
echo " - Gemini Key last4: {$gemLast4}\n";

$passPart3 = (
    $defaultConfig['provider'] === 'nvidia_nim' &&
    $cfindConfig['provider'] === 'nvidia_nim' &&
    !$cfindConfig['is_override'] &&
    $matchingConfig['provider'] === 'nvidia_nim' &&
    !$matchingConfig['is_override']
);
echo "Assertion Part 3 (Final config is NVIDIA for all features): " . ($passPart3 ? "[PASS]" : "[FAIL]") . "\n\n";

// =====================================================================
// OVERALL STATUS
// =====================================================================
if ($passPart1 && $passPart2 && $passPart3) {
    echo "======================================================================\n";
    echo "ALL STEP 3 VERIFICATIONS PASSED SUCCESSFULLY (100%)\n";
    echo "======================================================================\n";
    exit(0);
} else {
    echo "======================================================================\n";
    echo "SOME VERIFICATIONS FAILED\n";
    echo "======================================================================\n";
    exit(1);
}

