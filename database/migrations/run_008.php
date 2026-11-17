<?php
/**
 * Run Migration 008 — Search Indexes (via PHP PDO)
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
require_once $projectRoot . '/vendor/autoload.php';

// Load .env
$envFile = $projectRoot . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

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

// Helper: add index only if it doesn't exist
function addIndexIfNotExists(PDO $pdo, string $table, string $indexName, string $columns): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND index_name = ?
    ");
    $stmt->execute([$table, $indexName]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columns})");
        echo "  + Added index [{$indexName}] on {$table}({$columns})\n";
    } else {
        echo "  ~ Index [{$indexName}] already exists on {$table} — skipped\n";
    }
}

echo "\n=== Migration 008: Search Performance Indexes ===\n\n";

echo "[lost_reports]\n";
addIndexIfNotExists($pdo, 'lost_reports', 'idx_lr_status',     '`status`');
addIndexIfNotExists($pdo, 'lost_reports', 'idx_lr_category',   '`category_id`');
addIndexIfNotExists($pdo, 'lost_reports', 'idx_lr_location',   '`last_seen_location_id`');
addIndexIfNotExists($pdo, 'lost_reports', 'idx_lr_lost_date',  '`lost_date`');
addIndexIfNotExists($pdo, 'lost_reports', 'idx_lr_created_at', '`created_at`');

echo "\n[found_reports]\n";
addIndexIfNotExists($pdo, 'found_reports', 'idx_fr_status',     '`status`');
addIndexIfNotExists($pdo, 'found_reports', 'idx_fr_category',   '`category_id`');
addIndexIfNotExists($pdo, 'found_reports', 'idx_fr_location',   '`location_id`');
addIndexIfNotExists($pdo, 'found_reports', 'idx_fr_found_date', '`found_date`');
addIndexIfNotExists($pdo, 'found_reports', 'idx_fr_created_at', '`created_at`');

echo "\n✓ Migration 008 completed successfully.\n";
