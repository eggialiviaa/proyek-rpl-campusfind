<?php

declare(strict_types=1);

/**
 * Migration: Migrate AI settings to unified provider format (system_settings)
 * Idempotent: Can be run multiple times safely.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\SettingService;

$pdo = Database::getInstance()->getPdo();

echo "=== MIGRATION: UNIFIED AI CONFIGURATION STORAGE ===\n";

// Helper to mask key to last 4 chars only
function maskLast4(?string $decrypted): string {
    if (empty($decrypted)) return '(empty)';
    $clean = trim($decrypted);
    $len = strlen($clean);
    return '••••' . ($len >= 4 ? substr($clean, -4) : $clean);
}

// 1. Check existing ai_provider_nvidia_nim_key
$stmt = $pdo->prepare("SELECT `value`, `is_encrypted` FROM system_settings WHERE `key` = 'ai_provider_nvidia_nim_key'");
$stmt->execute();
$currentNimRow = $stmt->fetch(PDO::FETCH_ASSOC);

$currentNimKey = null;
if (!empty($currentNimRow['value'])) {
    $currentNimKey = ((int)$currentNimRow['is_encrypted'] === 1)
        ? SettingService::decrypt((string)$currentNimRow['value'])
        : (string)$currentNimRow['value'];
}

// Check legacy ai_api_key in DB
$stmtLegacy = $pdo->prepare("SELECT `value`, `is_encrypted` FROM system_settings WHERE `key` = 'ai_api_key'");
$stmtLegacy->execute();
$legacyRow = $stmtLegacy->fetch(PDO::FETCH_ASSOC);

$legacyDbKey = null;
if (!empty($legacyRow['value'])) {
    $legacyDbKey = ((int)$legacyRow['is_encrypted'] === 1)
        ? SettingService::decrypt((string)$legacyRow['value'])
        : (string)$legacyRow['value'];
}

// Check .env AI_API_KEY
$envKey = $_ENV['AI_API_KEY'] ?? getenv('AI_API_KEY') ?: ($_ENV['NVIDIA_API_KEY'] ?? getenv('NVIDIA_API_KEY') ?: null);

$actionTaken = 'NO_CHANGE';

if (empty($currentNimKey)) {
    if (!empty($legacyDbKey)) {
        // Copy encrypted value directly if already encrypted, or re-encrypt
        $enc = ((int)($legacyRow['is_encrypted'] ?? 0) === 1) ? $legacyRow['value'] : SettingService::encrypt($legacyDbKey);
        $ins = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
            VALUES ('ai_provider_nvidia_nim_key', ?, 1, 'ai', NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `is_encrypted` = 1, `updated_at` = NOW()
        ");
        $ins->execute([$enc]);
        $currentNimKey = $legacyDbKey;
        $actionTaken = 'COPIED_FROM_LEGACY_DB';
    } elseif (!empty($envKey)) {
        $enc = SettingService::encrypt($envKey);
        $ins = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
            VALUES ('ai_provider_nvidia_nim_key', ?, 1, 'ai', NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `is_encrypted` = 1, `updated_at` = NOW()
        ");
        $ins->execute([$enc]);
        $currentNimKey = $envKey;
        $actionTaken = 'COPIED_FROM_ENV';
    }
} else {
    $actionTaken = 'PRESERVED_EXISTING_NVIDIA_KEY';
}

// Ensure base URL for nvidia_nim
$stmtUrl = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'ai_provider_nvidia_nim_url'");
$stmtUrl->execute();
$nimUrl = $stmtUrl->fetchColumn();
if (empty($nimUrl)) {
    $legacyUrl = $pdo->query("SELECT `value` FROM system_settings WHERE `key` = 'ai_base_url'")->fetchColumn();
    $targetUrl = !empty($legacyUrl) ? $legacyUrl : ($_ENV['AI_BASE_URL'] ?? 'https://integrate.api.nvidia.com/v1');
    $pdo->prepare("
        INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
        VALUES ('ai_provider_nvidia_nim_url', ?, 0, 'ai', NOW(), NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
    ")->execute([rtrim($targetUrl, '/')]);
}

// Ensure base URL for google_gemini
$stmtGeminiUrl = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'ai_provider_google_gemini_url'");
$stmtGeminiUrl->execute();
$geminiUrl = $stmtGeminiUrl->fetchColumn();
if (empty($geminiUrl)) {
    $targetGeminiUrl = $_ENV['GEMINI_BASE_URL'] ?? 'https://generativelanguage.googleapis.com/v1beta/openai';
    $pdo->prepare("
        INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
        VALUES ('ai_provider_google_gemini_url', ?, 0, 'ai', NOW(), NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
    ")->execute([rtrim($targetGeminiUrl, '/')]);
}

// Verification step
SettingService::clearCache();
$checkStmt = $pdo->prepare("SELECT `value`, `is_encrypted` FROM system_settings WHERE `key` = 'ai_provider_nvidia_nim_key'");
$checkStmt->execute();
$verifiedRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
$verifiedKey = null;
if (!empty($verifiedRow['value'])) {
    $verifiedKey = SettingService::decrypt((string)$verifiedRow['value']);
}

$isVerified = !empty($verifiedKey);

if ($isVerified) {
    // Safely remove legacy ai_api_key, ai_api_key_cfind, ai_api_key_matching, ai_base_url
    $pdo->exec("DELETE FROM system_settings WHERE `key` IN ('ai_api_key', 'ai_api_key_cfind', 'ai_api_key_matching')");
}

echo "Action: {$actionTaken}\n";
echo "Verified NVIDIA NIM Key in DB: " . maskLast4($verifiedKey) . "\n";

// Check Gemini Key
$geminiStmt = $pdo->prepare("SELECT `value`, `is_encrypted` FROM system_settings WHERE `key` = 'ai_provider_google_gemini_key'");
$geminiStmt->execute();
$geminiRow = $geminiStmt->fetch(PDO::FETCH_ASSOC);
$geminiKey = !empty($geminiRow['value']) ? SettingService::decrypt((string)$geminiRow['value']) : null;
echo "Verified Google Gemini Key in DB: " . maskLast4($geminiKey) . "\n";
echo "Legacy DB Row 'ai_api_key' Status: " . ($isVerified ? 'REMOVED_SAFELY' : 'KEPT_DUE_TO_VERIFICATION_FAILURE') . "\n";
echo "Migration Status: " . ($isVerified ? 'SUCCESS' : 'FAILED') . "\n";

