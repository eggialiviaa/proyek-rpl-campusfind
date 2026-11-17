<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\AiMatchService;
use App\Services\AiProviderInterface;
use App\Services\SettingService;

echo "======================================================================\n";
echo "VERIFICATION F.2: NEGATIVE TEST (Malformed Model Response)\n";
echo "======================================================================\n\n";

$pdo = Database::getInstance()->getPdo();

// 1. Definisikan Mock Provider yang menghasilkan keluaran malformed
class MalformedAiProviderMock implements AiProviderInterface
{
    public function getProviderId(): string { return 'mock_malformed'; }
    public function getProviderName(): string { return 'Mock Malformed Provider'; }
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
        return ['success' => true, 'content' => 'NOT_VALID_JSON_AT_ALL'];
    }

    public function evaluateCandidates(array $targetReport, array $candidateReports, string $targetType = 'lost'): array
    {
        // Mensimulasikan keluaran malformed dari provider
        return [
            'status'           => 'failed',
            'parse_status'     => 'failed',
            'model'            => 'mock-model',
            'duration_ms'      => 45,
            'scores'           => [],
            'public_text_sent' => ['target' => 'mock'],
            'raw_content'      => 'Internal server error or invalid string <<<>>>',
            'error'            => 'Keluaran model AI tidak dapat di-parse sebagai JSON array terstruktur',
        ];
    }
}

// 2. Ambil 1 target lost_report dan pastikan ada kandidat found_report
$lost = $pdo->query("SELECT * FROM lost_reports LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$lost) {
    echo "ERROR: Database lost_reports is empty.\n";
    exit(1);
}
$lostId = (int)$lost['id'];
$origLostStatus = $lost['status'];
$pdo->exec("UPDATE lost_reports SET status = 'published' WHERE id = {$lostId}");

$found = $pdo->query("SELECT * FROM found_reports LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$found) {
    echo "ERROR: Database found_reports is empty.\n";
    exit(1);
}
$foundId = (int)$found['id'];
$origFoundStatus = $found['status'];
$pdo->exec("UPDATE found_reports SET status = 'published', category_id = {$lost['category_id']} WHERE id = {$foundId}");

// Catat jumlah row match_candidates dan notifications sebelum eksekusi
$countMatchesBefore = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates")->fetchColumn();
$countNotifsBefore = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

echo "Initial State:\n";
echo " - Lost Report ID: {$lostId}\n";
echo " - Found Report ID: {$foundId}\n";
echo " - match_candidates count: {$countMatchesBefore}\n";
echo " - notifications count: {$countNotifsBefore}\n\n";

// 3. Inject Mock Provider ke AiMatchService
$mockProvider = new MalformedAiProviderMock();
$matchService = new AiMatchService($mockProvider);

echo "Executing AiMatchService with Malformed Provider...\n";
$result = $matchService->runMatchingForLostReport($lostId);

echo "Execution Result:\n";
echo " - Result Status: " . ($result['result_status'] ?? '-') . "\n";
echo " - Matches Count: " . ($result['matches_count'] ?? '-') . "\n";
$diag = $result['diagnostic'] ?? [];
echo " - Diagnostic Call Status: " . ($diag['call_status'] ?? '-') . "\n";
echo " - Diagnostic Parse Status: " . ($diag['parse_status'] ?? '-') . "\n";
echo " - Diagnostic Candidates Saved: " . count($result['candidates'] ?? []) . "\n\n";

// Catat jumlah row setelah eksekusi
$countMatchesAfter = (int)$pdo->query("SELECT COUNT(*) FROM match_candidates")->fetchColumn();
$countNotifsAfter = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();

echo "State After Execution:\n";
echo " - match_candidates count: {$countMatchesAfter} (Diff: " . ($countMatchesAfter - $countMatchesBefore) . ")\n";
echo " - notifications count: {$countNotifsAfter} (Diff: " . ($countNotifsAfter - $countNotifsBefore) . ")\n\n";

$assertions = [
    'parse_status is not parsed'     => ($diag['parse_status'] !== 'parsed'),
    'call is not reported as success' => ($diag['call_status'] !== 'success'),
    'No match candidate stored'      => ($countMatchesAfter === $countMatchesBefore),
    'No notification sent'           => ($countNotifsAfter === $countNotifsBefore),
    'Result candidates empty'        => empty($result['candidates']),
];

$allPass = true;
foreach ($assertions as $label => $pass) {
    echo ($pass ? " [PASS] " : " [FAIL] ") . $label . "\n";
    if (!$pass) $allPass = false;
}

// Revert statuses
$pdo->exec("UPDATE lost_reports SET status = '{$origLostStatus}' WHERE id = {$lostId}");
$pdo->exec("UPDATE found_reports SET status = '{$origFoundStatus}', category_id = {$found['category_id']} WHERE id = {$foundId}");

if ($allPass) {
    echo "\nVERIFICATION F.2 RESULT: 100% PASSED\n";
    exit(0);
} else {
    echo "\nVERIFICATION F.2 RESULT: FAILED\n";
    exit(1);
}
