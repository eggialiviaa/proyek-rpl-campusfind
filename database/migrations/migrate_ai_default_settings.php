<?php

declare(strict_types=1);

/**
 * Migration: Idempotent Setup of AI Default Provider & Model (Task 2B)
 * File: database/migrations/migrate_ai_default_settings.php
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

if (file_exists(BASE_PATH . '/.env')) {
    \Dotenv\Dotenv::createImmutable(BASE_PATH)->load();
}

use Core\Database;
use App\Services\SettingService;
use App\Services\AiProviderManager;

$pdo = Database::getInstance()->getPdo();

echo "=== MIGRASI TAHAP 2B: MODEL DEFAULT & OVERRIDE FITUR AI ===\n\n";

// 1. Ambil penugasan default saat ini (atau ambil dari C-Find)
$stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'ai_default_provider'");
$stmt->execute();
$currentDefaultProv = trim((string)$stmt->fetchColumn());

$stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'ai_default_model'");
$stmt->execute();
$currentDefaultModel = trim((string)$stmt->fetchColumn());

if (empty($currentDefaultProv)) {
    // Ambil dari cfind jika ada, atau fallback nvidia_nim
    $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'ai_feature_cfind_provider'");
    $stmt->execute();
    $cfindProv = trim((string)$stmt->fetchColumn());
    $currentDefaultProv = !empty($cfindProv) ? $cfindProv : 'nvidia_nim';

    $stmtSave = $pdo->prepare("
        INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
        VALUES ('ai_default_provider', ?, 0, 'ai', NOW(), NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
    ");
    $stmtSave->execute([$currentDefaultProv]);
    echo " [+] Set ai_default_provider = '{$currentDefaultProv}'\n";
} else {
    echo " [i] ai_default_provider sudah ada: '{$currentDefaultProv}'\n";
}

if (empty($currentDefaultModel)) {
    $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = 'ai_model_cfind'");
    $stmt->execute();
    $cfindModel = trim((string)$stmt->fetchColumn());
    $currentDefaultModel = !empty($cfindModel) ? AiProviderManager::normalizeModelId($cfindModel) : 'nvidia/nemotron-3.5-bolt-e4b';

    $stmtSave = $pdo->prepare("
        INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `created_at`, `updated_at`)
        VALUES ('ai_default_model', ?, 0, 'ai', NOW(), NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
    ");
    $stmtSave->execute([$currentDefaultModel]);
    echo " [+] Set ai_default_model = '{$currentDefaultModel}'\n";
} else {
    echo " [i] ai_default_model sudah ada: '{$currentDefaultModel}'\n";
}

// 2. Evaluasi fitur cfind & matching: jika identik dengan default, kosongkan agar mengikuti default
foreach (['cfind', 'matching'] as $feat) {
    $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = ?");
    $stmt->execute(["ai_feature_{$feat}_provider"]);
    $provVal = trim((string)$stmt->fetchColumn());

    $stmt->execute(["ai_model_{$feat}"]);
    $modelVal = trim((string)$stmt->fetchColumn());
    $normModelVal = !empty($modelVal) ? AiProviderManager::normalizeModelId($modelVal) : '';

    $isIdenticalProv = empty($provVal) || ($provVal === $currentDefaultProv);
    $isIdenticalModel = empty($normModelVal) || ($normModelVal === $currentDefaultModel);

    if ($isIdenticalProv && $isIdenticalModel) {
        // Kosongkan agar otomatis mengikuti default
        $stmtDel = $pdo->prepare("DELETE FROM system_settings WHERE `key` IN (?, ?)");
        $stmtDel->execute(["ai_feature_{$feat}_provider", "ai_model_{$feat}"]);
        echo " [+] Fitur '{$feat}': Identik dengan default -> Override dikosongkan (status: Mengikuti default)\n";
    } else {
        echo " [i] Fitur '{$feat}': Memiliki override khusus (provider: '{$provVal}', model: '{$normModelVal}')\n";
    }
}

SettingService::clearCache();

echo "\n=== HASIL AKHIR MIGRASI DEFAULT & FITUR ===\n";
$conn = AiProviderManager::getProviderConnection($currentDefaultProv);
echo "Default Provider : {$conn['name']} ({$currentDefaultProv})\n";
echo "Default Model    : {$currentDefaultModel}\n";
echo "Default Key      : " . $conn['api_key_masked'] . "\n";

foreach (['cfind', 'matching'] as $feat) {
    $assignment = AiProviderManager::getFeatureAssignment($feat);
    $statusText = !empty($assignment['is_override']) ? 'Diatur terpisah (override)' : 'Mengikuti default';
    echo "Fitur {$assignment['feature_label']}: Provider={$assignment['provider_name']}, Model={$assignment['model']} [{$statusText}]\n";
}

echo "Migrasi berhasil.\n";

