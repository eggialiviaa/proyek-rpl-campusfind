<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\CFindService;
use App\Services\AiProviderManager;

$pdo = Database::getInstance()->getPdo();

$maxChatId = (int)$pdo->query("SELECT IFNULL(MAX(id), 0) FROM cfind_chat_history")->fetchColumn();
$maxAuditId = (int)$pdo->query("SELECT IFNULL(MAX(id), 0) FROM audit_logs")->fetchColumn();

$cfind = new CFindService();
$user = [
    'id'   => 1,
    'role' => 'civitas',
    'nama' => 'Budi Test'
];

$start = microtime(true);
$res = $cfind->processMessage("Bagaimana cara melaporkan barang hilang di CampusFind?", $user);
$duration = round(microtime(true) - $start, 3);

echo "Duration: {$duration}s\n";
echo "Status: " . ($res['status'] ?? 'unknown') . "\n";
echo "Metadata:\n";
print_r($res['metadata'] ?? []);
echo "Reply:\n";
print_r($res['reply'] ?? '');
echo "\n";

// Cleanup
$pdo->prepare("DELETE FROM cfind_chat_history WHERE id > ?")->execute([$maxChatId]);
$pdo->prepare("DELETE FROM audit_logs WHERE id > ?")->execute([$maxAuditId]);

