<?php
/**
 * Run Migration 010 — Internal Chat System (via PHP PDO)
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

echo "\n=== Migration 010: Internal Chat System ===\n\n";

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

// 1. Create chat_rooms
if (!tableExists($pdo, 'chat_rooms')) {
    $pdo->exec("
        CREATE TABLE `chat_rooms` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_type` ENUM('claim', 'lost_report', 'found_report') NOT NULL DEFAULT 'claim',
            `claim_id` INT UNSIGNED NULL,
            `lost_report_id` INT UNSIGNED NULL,
            `found_report_id` INT UNSIGNED NULL,
            `title` VARCHAR(255) NOT NULL,
            `created_by_user_id` INT UNSIGNED NOT NULL,
            `status` ENUM('active', 'closed') NOT NULL DEFAULT 'active',
            `last_message_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,

            CONSTRAINT `fk_cr_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cr_lost_report` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_reports` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cr_found_report` FOREIGN KEY (`found_report_id`) REFERENCES `found_reports` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cr_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'chat_rooms' created successfully\n";
} else {
    echo "  Table 'chat_rooms' already exists\n";
}

// 2. Create chat_participants
if (!tableExists($pdo, 'chat_participants')) {
    $pdo->exec("
        CREATE TABLE `chat_participants` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `role_in_room` ENUM('claimant', 'reporter', 'petugas', 'admin') NOT NULL,
            `last_read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,

            CONSTRAINT `fk_cp_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `uk_room_user` UNIQUE (`room_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'chat_participants' created successfully\n";
} else {
    echo "  Table 'chat_participants' already exists\n";
}

// 3. Create chat_messages
if (!tableExists($pdo, 'chat_messages')) {
    $pdo->exec("
        CREATE TABLE `chat_messages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `room_id` INT UNSIGNED NOT NULL,
            `sender_id` INT UNSIGNED NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `read_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,

            CONSTRAINT `fk_cm_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'chat_messages' created successfully\n";
} else {
    echo "  Table 'chat_messages' already exists\n";
}

// Ensure read_at exists on chat_messages
$colStmt = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() AND table_name = 'chat_messages' AND column_name = 'read_at'
");
$colStmt->execute();
if ((int) $colStmt->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE `chat_messages` ADD COLUMN `read_at` DATETIME NULL AFTER `is_read`");
    echo "✓ Column 'read_at' added to 'chat_messages'\n";
}

echo "\nMigration 010 completed successfully!\n\n";

foreach (['chat_rooms', 'chat_participants', 'chat_messages'] as $t) {
    echo "=== {$t} ===\n";
    $stmt = $pdo->query("DESCRIBE `{$t}`");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}
