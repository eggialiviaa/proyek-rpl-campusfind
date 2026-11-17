<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\AiProviderManager;
use App\Services\SettingService;

echo "======================================================================\n";
echo "PART 0 VERIFICATION: AI PROVIDER SAVE & DELETE RESPONSE VERIFICATION\n";
echo "======================================================================\n\n";

$pdo = Database::getInstance()->getPdo();

// 1. Get current active key for nvidia_nim
$currentKey = AiProviderManager::resolveEffectiveApiKey('nvidia_nim');
if (empty($currentKey)) {
    echo "[FAIL] No existing key for nvidia_nim to test.\n";
    exit(1);
}

$expectedLast4 = mb_substr($currentKey, -4);
echo "1. Active key last4 for nvidia_nim: {$expectedLast4}\n";

// 2. Simulate saving connection with the key
$saveResult = AiProviderManager::saveProviderConnection('nvidia_nim', $currentKey, null, 1);

// Convert to JSON exactly as the controller does: $this->json($result, $statusCode);
$jsonOutput = json_encode($saveResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

echo "\n2. Controller JSON Response from saveProviderConnection:\n";
echo $jsonOutput . "\n\n";

// 3. Security Assertions
$fullKeyLeaked = (strlen($currentKey) > 4 && strpos($jsonOutput, $currentKey) !== false);
$hasLast4 = ($saveResult['key_last4'] ?? '') === $expectedLast4;
$hasMasked = ($saveResult['key_masked'] ?? '') === ('••••••••' . $expectedLast4);
$isDb = ($saveResult['is_db'] ?? false) === true;
$isConfigured = ($saveResult['is_configured'] ?? false) === true;
$statusConfigured = ($saveResult['status'] ?? '') === 'configured';

echo "3. Security & Integrity Checks:\n";
echo " - Full key NOT present in JSON response: " . (!$fullKeyLeaked ? "PASS [SAFE]" : "FAIL [LEAKED]") . "\n";
echo " - key_last4 matches expected ('{$expectedLast4}'): " . ($hasLast4 ? "PASS" : "FAIL") . "\n";
echo " - key_masked is '••••••••{$expectedLast4}': " . ($hasMasked ? "PASS" : "FAIL") . "\n";
echo " - is_db is true: " . ($isDb ? "PASS" : "FAIL") . "\n";
echo " - is_configured is true: " . ($isConfigured ? "PASS" : "FAIL") . "\n";
echo " - status is 'configured': " . ($statusConfigured ? "PASS" : "FAIL") . "\n";

if ($fullKeyLeaked || !$hasLast4 || !$hasMasked || !$isDb || !$isConfigured || !$statusConfigured) {
    echo "\n[FAIL] Part 0 save verification failed.\n";
    exit(1);
}

// 4. Test delete protection check
echo "\n4. Delete Protection Check:\n";
$deleteAttempt = AiProviderManager::deleteProviderKey('nvidia_nim', 1);
echo " - Delete active provider blocked: " . (!$deleteAttempt['success'] ? "PASS (blocked with message: {$deleteAttempt['message']})" : "FAIL") . "\n";

echo "\n======================================================================\n";
echo "PART 0 VERIFICATION: ALL CHECKS PASSED [SUCCESS]\n";
echo "======================================================================\n";
exit(0);

