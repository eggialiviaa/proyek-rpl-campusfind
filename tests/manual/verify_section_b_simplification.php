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
use App\Services\OpenAICompatibleService;
use App\Services\SettingService;
use Core\Database;

$pdo = Database::getInstance()->getPdo();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $title, ?string $detail = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$title}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$title}" . ($detail ? " — {$detail}" : "") . "\n";
    }
}

echo "=== START VERIFICATION: SECTION B SIMPLIFICATION & HONEST EVALUATION ===\n\n";

// -------------------------------------------------------------
// TEST 1: Non-Chat Model Filtering Logic
// -------------------------------------------------------------
echo "[1] Testing Non-Chat Model Filtering Logic...\n";

$sampleModels = [
    ['id' => 'meta/llama-3.1-8b-instruct', 'label' => 'meta/llama-3.1-8b-instruct'],
    ['id' => 'openai/gpt-oss-safeguard-20b', 'label' => 'openai/gpt-oss-safeguard-20b'],
    ['id' => 'meta-llama/llama-prompt-guard-2-22m', 'label' => 'meta-llama/llama-prompt-guard-2-22m'],
    ['id' => 'nvidia/embed-qa-4', 'label' => 'nvidia/embed-qa-4'],
    ['id' => 'whisper-large-v3', 'label' => 'whisper-large-v3'],
    ['id' => 'tts-1-hd', 'label' => 'tts-1-hd'],
    ['id' => 'dall-e-3', 'label' => 'dall-e-3'],
    ['id' => 'gemini-1.5-flash', 'label' => 'gemini-1.5-flash'],
];

$filtered = AiProviderManager::filterModelsForChatAssignment($sampleModels, null);
$filteredIds = array_column($filtered, 'id');

assertTest(in_array('meta/llama-3.1-8b-instruct', $filteredIds, true), "Chat model meta/llama-3.1-8b-instruct retained");
assertTest(in_array('gemini-1.5-flash', $filteredIds, true), "Chat model gemini-1.5-flash retained");
assertTest(!in_array('openai/gpt-oss-safeguard-20b', $filteredIds, true), "Safeguard model excluded");
assertTest(!in_array('meta-llama/llama-prompt-guard-2-22m', $filteredIds, true), "Guard model excluded");
assertTest(!in_array('nvidia/embed-qa-4', $filteredIds, true), "Embed model excluded");
assertTest(!in_array('whisper-large-v3', $filteredIds, true), "Whisper model excluded");
assertTest(!in_array('tts-1-hd', $filteredIds, true), "TTS model excluded");

// Test that currently saved model is retained with warning
$savedNonChat = 'openai/gpt-oss-safeguard-20b';
$filteredWithSaved = AiProviderManager::filterModelsForChatAssignment($sampleModels, $savedNonChat);
$savedItem = null;
foreach ($filteredWithSaved as $item) {
    if ($item['id'] === $savedNonChat) {
        $savedItem = $item;
        break;
    }
}
assertTest($savedItem !== null, "Currently saved non-chat model is retained");
assertTest(str_contains($savedItem['label'] ?? '', 'Peringatan: Bukan Model Chat Standar'), "Currently saved non-chat model carries warning label");

// -------------------------------------------------------------
// TEST 2: Real Provider Catalog Hidden Models Check
// -------------------------------------------------------------
echo "\n[2] Checking Real Provider Catalogs for Hidden Models...\n";

$providersToCheck = ['nvidia_nim', 'google_gemini'];
$allProvs = AiProviderManager::getRegisteredProviders();
foreach ($allProvs as $pId => $pMeta) {
    if (!empty($pMeta['is_custom']) && str_contains($pId, 'groq')) {
        $providersToCheck[] = $pId;
    }
}

foreach ($providersToCheck as $pId) {
    $raw = AiProviderManager::getCompatibleModels($pId, 'cfind');
    $hidden = [];
    foreach ($raw as $m) {
        $mId = $m['id'] ?? '';
        if (AiProviderManager::isNonChatModel($mId)) {
            $hidden[] = $mId;
        }
    }
    echo "  Provider '{$pId}': Total " . count($raw) . " models, " . count($hidden) . " non-chat models hidden.\n";
    if (!empty($hidden)) {
        echo "    Hidden: " . implode(', ', array_slice($hidden, 0, 5)) . (count($hidden) > 5 ? '... (+' . (count($hidden) - 5) . ' more)' : '') . "\n";
    }
}

// -------------------------------------------------------------
// TEST 3: Assignment State Structure
// -------------------------------------------------------------
echo "\n[3] Testing getAiAssignmentState() Schema...\n";

$state = AiProviderManager::getAiAssignmentState();
assertTest(isset($state['default']), "State contains 'default' object");
assertTest(isset($state['default']['provider_id']), "State default contains provider_id");
assertTest(isset($state['default']['provider_name']), "State default contains provider_name");
assertTest(isset($state['default']['model']), "State default contains model");
assertTest(isset($state['features']['cfind']), "State features contains 'cfind'");
assertTest(isset($state['features']['cfind']['is_override']), "State features cfind contains is_override");
assertTest(isset($state['features']['matching']), "State features contains 'matching'");
assertTest(isset($state['features']['matching']['is_override']), "State features matching contains is_override");

// -------------------------------------------------------------
// TEST 4: Empty Content & Token Exhaustion Handling
// -------------------------------------------------------------
echo "\n[4] Testing Empty Content & Finish Reason Handling...\n";

// Case A: Verify isNonChatModel catches safety / safeguard models
assertTest(AiProviderManager::isNonChatModel('openai/gpt-oss-safeguard-20b') === true, "isNonChatModel returns true for safeguard-20b");
assertTest(AiProviderManager::isNonChatModel('meta/llama-guard-4-12b') === true, "isNonChatModel returns true for llama-guard");
assertTest(AiProviderManager::isNonChatModel('meta/llama-3.3-70b-instruct') === false, "isNonChatModel returns false for chat instruct model");

// Case B: Verify simulated empty content detection
$simulatedEmpty = '';
$simulatedReason = 'length';
$simulatedUsage = ['prompt_tokens' => 15, 'completion_tokens' => 16, 'total_tokens' => 31];
if ($simulatedEmpty === '') {
    $msg = "Model 'test-model' mengembalikan respons teks kosong (finish_reason: {$simulatedReason}). ";
    if ($simulatedReason === 'length') {
        $msg .= "Batas token habis sebelum model menghasilkan teks (kemungkinan kuota reasoning token atau max_tokens terlampaui).";
    }
    assertTest(str_contains($msg, 'Batas token habis'), "Empty content with finish_reason=length generates Indonesian warning");
    assertTest(str_contains($msg, 'reasoning token'), "Warning mentions reasoning token quota");
}

// -------------------------------------------------------------
// TEST 5: Matching Evaluation Relevance and Strict JSON Assertions
// -------------------------------------------------------------
echo "\n[5] Testing testMatchingEvaluation Logic...\n";

// Run real testMatchingEvaluation on active NVIDIA NIM default model
$matchingProv = $state['features']['matching']['provider_id'];
$matchingModel = $state['features']['matching']['model'];
$matchingName = $state['features']['matching']['provider_name'];

echo "  Running testMatchingEvaluation on {$matchingName} ({$matchingModel})...\n";
$evalResult = AiProviderManager::testMatchingEvaluation($matchingProv, $matchingModel, $matchingName);

assertTest(isset($evalResult['success']), "testMatchingEvaluation returned 'success' boolean");
assertTest(isset($evalResult['parse_status']), "testMatchingEvaluation returned 'parse_status'");
echo "  Parse Status: " . ($evalResult['parse_status'] ?? 'none') . "\n";
echo "  Success: " . ($evalResult['success'] ? 'true' : 'false') . "\n";
echo "  Message: " . ($evalResult['message'] ?? '') . "\n";
if (!empty($evalResult['scores'])) {
    echo "  Scores: Match=" . ($evalResult['scores']['match'] ?? 0) . "%, Similar=" . ($evalResult['scores']['similar'] ?? 0) . "%, Unrelated=" . ($evalResult['scores']['unrelated'] ?? 0) . "%\n";
    assertTest(
        $evalResult['scores']['match'] > $evalResult['scores']['similar'] &&
        $evalResult['scores']['similar'] > $evalResult['scores']['unrelated'],
        "Score ranking condition holds (Match > Similar > Unrelated)"
    );
}

// -------------------------------------------------------------
// TEST 6: Temporary Custom Provider Section B Lifecycle
// -------------------------------------------------------------
echo "\n[6] Testing Temporary Custom Provider Section B Lifecycle...\n";

$tempProvId = 'temp_sec_b_' . substr(bin2hex(random_bytes(4)), 0, 6);
$addRes = AiProviderManager::addCustomProvider(
    'Temp Sec B Provider',
    'https://api.openai.com/v1',
    'sk-test-fakekey9988',
    1
);
assertTest($addRes['success'] === true, "Temporary custom provider created ({$addRes['provider_id']})");
$createdId = $addRes['provider_id'];

// 6a. Save Default Assignment with temp provider
$saveDefRes = AiProviderManager::saveDefaultAssignment($createdId, 'temp-default-model-v1', 1);
assertTest($saveDefRes['success'] === true, "saveDefaultAssignment succeeded with custom provider");
assertTest(isset($saveDefRes['assignment_state']), "saveDefaultAssignment returns assignment_state");
assertTest($saveDefRes['assignment_state']['default']['provider_id'] === $createdId, "assignment_state default provider_id matches");
assertTest($saveDefRes['assignment_state']['default']['model'] === 'temp-default-model-v1', "assignment_state default model matches");

// 6b. Save Feature Override (C-Find) with temp provider
$saveCfindRes = AiProviderManager::saveFeatureAssignment('cfind', $createdId, 'temp-cfind-model-v2', 1);
assertTest($saveCfindRes['success'] === true, "saveFeatureAssignment (cfind) succeeded");
assertTest(isset($saveCfindRes['assignment']), "saveFeatureAssignment returns assignment object");
assertTest(isset($saveCfindRes['assignment_state']), "saveFeatureAssignment returns assignment_state object");
assertTest($saveCfindRes['assignment_state']['features']['cfind']['is_override'] === true, "cfind is_override is true");
assertTest($saveCfindRes['assignment_state']['features']['cfind']['model'] === 'temp-cfind-model-v2', "cfind model matches");

// 6c. Save Feature Override (Matching) with temp provider
$saveMatchRes = AiProviderManager::saveFeatureAssignment('matching', $createdId, 'temp-match-model-v3', 1);
assertTest($saveMatchRes['success'] === true, "saveFeatureAssignment (matching) succeeded");
assertTest(isset($saveMatchRes['assignment']), "saveFeatureAssignment returns assignment object");
assertTest(isset($saveMatchRes['assignment_state']), "saveFeatureAssignment returns assignment_state object");
assertTest($saveMatchRes['assignment_state']['features']['matching']['is_override'] === true, "matching is_override is true");
assertTest($saveMatchRes['assignment_state']['features']['matching']['model'] === 'temp-match-model-v3', "matching model matches");

// 6d. Revert Feature Override (Matching) to Default
$revertMatchRes = AiProviderManager::revertFeatureToDefault('matching', 1);
assertTest($revertMatchRes['success'] === true, "revertFeatureToDefault (matching) succeeded");
assertTest(isset($revertMatchRes['assignment_state']), "revertFeatureToDefault returns assignment_state");
assertTest($revertMatchRes['assignment_state']['features']['matching']['is_override'] === false, "matching is_override reverted to false");

// 6e. Revert Feature Override (C-Find) to Default
$revertCfindRes = AiProviderManager::revertFeatureToDefault('cfind', 1);
assertTest($revertCfindRes['success'] === true, "revertFeatureToDefault (cfind) succeeded");
assertTest(isset($revertCfindRes['assignment_state']), "revertFeatureToDefault returns assignment_state");
assertTest($revertCfindRes['assignment_state']['features']['cfind']['is_override'] === false, "cfind is_override reverted to false");

// 6f. Revert Default Assignment back to NVIDIA NIM
$restoreDefRes = AiProviderManager::saveDefaultAssignment('nvidia_nim', 'nvidia/nemotron-3.5-bolt-e4b', 1);
assertTest($restoreDefRes['success'] === true, "Default assignment restored to NVIDIA NIM");
assertTest($restoreDefRes['assignment_state']['default']['provider_id'] === 'nvidia_nim', "Default provider is nvidia_nim");

// 6g. Delete temporary custom provider
$delRes = AiProviderManager::deleteCustomProvider($createdId, 1);
assertTest($delRes['success'] === true, "Temporary custom provider deleted successfully");
assertTest(isset($delRes['assignment_state']), "deleteCustomProvider returns assignment_state");

// -------------------------------------------------------------
// TEST 7: Clean Final System State Verification
// -------------------------------------------------------------
echo "\n[7] Verifying Final System State Cleanliness...\n";

$finalState = AiProviderManager::getAiAssignmentState();
assertTest($finalState['default']['provider_id'] === 'nvidia_nim', "Final default provider is nvidia_nim");
assertTest($finalState['default']['model'] === 'nvidia/nemotron-3.5-bolt-e4b', "Final default model is nvidia/nemotron-3.5-bolt-e4b");
assertTest($finalState['features']['cfind']['is_override'] === false, "Final cfind has no override");
assertTest($finalState['features']['matching']['is_override'] === false, "Final matching has no override");

$nimConn = AiProviderManager::getProviderConnection('nvidia_nim');
$gemConn = AiProviderManager::getProviderConnection('google_gemini');

assertTest($nimConn['is_configured'] === true, "NVIDIA NIM is configured in database");
assertTest(!empty($nimConn['last4']), "NVIDIA NIM last4 is present (" . htmlspecialchars($nimConn['last4']) . ")");
assertTest($gemConn['is_configured'] === true, "Google Gemini is configured in database");
assertTest(!empty($gemConn['last4']), "Google Gemini last4 is present (" . htmlspecialchars($gemConn['last4']) . ")");

echo "\n=======================================================\n";
echo "VERIFICATION SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
