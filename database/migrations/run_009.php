<?php
/**
 * Run Migration 009 — Claim Verification Schema (via PHP PDO)
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
define('BASE_PATH', $projectRoot);

require_once $projectRoot . '/vendor/autoload.php';

use Dotenv\Dotenv;
Dotenv::createImmutable(BASE_PATH)->load();

$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port   = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? 'campus_find';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Database connected\n";
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migration 009: Claim & Verification Schema Upgrade ===\n\n";

// Helper: check column
function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

// Helper: check index
function indexExists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND index_name = ?
    ");
    $stmt->execute([$table, $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

// 1. Update claims status enum
echo "[1] Updating claims status ENUM...\n";
$pdo->exec("
    ALTER TABLE `claims` 
    MODIFY COLUMN `status` ENUM(
        'pending', 
        'under_review', 
        'need_more_info', 
        'approved', 
        'rejected', 
        'completed', 
        'closed_duplicate', 
        'cancelled'
    ) NOT NULL DEFAULT 'pending'
");
echo "  ✓ Status ENUM updated to: pending, under_review, need_more_info, approved, rejected, completed, closed_duplicate, cancelled\n";

// 2. Add columns to claims
echo "[2] Adding columns to claims table...\n";
$columnsToAdd = [
    'claim_reason' => "TEXT NOT NULL AFTER `item_id`",
    'ownership_proof_description' => "TEXT NOT NULL AFTER `claim_reason`",
    'unique_features' => "TEXT NOT NULL AFTER `ownership_proof_description`",
    'additional_notes' => "TEXT NULL AFTER `unique_features`",
    'rejection_reason' => "TEXT NULL AFTER `reviewer_notes`",
    'handover_notes' => "TEXT NULL AFTER `handed_over_at`",
    'handed_over_by_user_id' => "INT UNSIGNED NULL AFTER `handover_notes`",
];

foreach ($columnsToAdd as $col => $def) {
    if (!columnExists($pdo, 'claims', $col)) {
        $pdo->exec("ALTER TABLE `claims` ADD COLUMN `{$col}` {$def}");
        echo "  + Added column `{$col}`\n";
    } else {
        echo "  ~ Column `{$col}` already exists — skipped\n";
    }
}

// 3. Add foreign key for handed_over_by_user_id if not exists
$fkStmt = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.key_column_usage
    WHERE table_schema = DATABASE()
      AND table_name = 'claims'
      AND constraint_name = 'fk_claims_handed_over_by'
");
$fkStmt->execute();
if ((int) $fkStmt->fetchColumn() === 0) {
    $pdo->exec("
        ALTER TABLE `claims`
        ADD CONSTRAINT `fk_claims_handed_over_by` 
        FOREIGN KEY (`handed_over_by_user_id`) REFERENCES `users` (`id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
    ");
    echo "  + Added FK constraint fk_claims_handed_over_by\n";
} else {
    echo "  ~ FK fk_claims_handed_over_by already exists — skipped\n";
}

// 4. Add composite performance indexes
echo "[3] Adding indexes...\n";
$indexes = [
    'idx_claims_item_status'     => '(`item_id`, `status`)',
    'idx_claims_found_status'    => '(`found_report_id`, `status`)',
    'idx_claims_claimant_status' => '(`claimant_user_id`, `status`)',
];

foreach ($indexes as $name => $cols) {
    if (!indexExists($pdo, 'claims', $name)) {
        $pdo->exec("ALTER TABLE `claims` ADD INDEX `{$name}` {$cols}");
        echo "  + Added index [{$name}] on {$cols}\n";
    } else {
        echo "  ~ Index [{$name}] already exists — skipped\n";
    }
}

echo "\n✓ Migration 009 completed successfully.\n";
