<?php

declare(strict_types=1);

/**
 * Migration 34A: Pembersihan Data Notifikasi, Outbox, dan Chat dari Entitas HTML Prematur
 * Sesuai aturan: simpan teks MENTAH (tervalidasi), escape dilakukan satu kali saat keluaran.
 * Dilengkapi dengan backup otomatis sebelum perubahan dilakukan.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

// Gunakan koneksi database aktif
$pdo = Core\Database::getInstance()->getPdo();
$dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

echo "========================================================================\n";
echo "   MIGRASI 34A: PEMBERSIHAN ENTITAS NOTIFIKASI & OUTBOX ({$dbName})\n";
echo "========================================================================\n\n";

// 1. Buat Berkas Backup Khusus Tabel Terdampak
$backupDir = BASE_PATH . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$timestamp = date('Ymd_His');
$backupFile = "{$backupDir}/backup_34a_notifications_{$dbName}_{$timestamp}.json";

echo "1. Membackup data tabel notifications, notification_outbox, dan chat_messages...\n";
$backupData = [
    'db_name'             => $dbName,
    'timestamp'           => date('c'),
    'notifications'       => $pdo->query("SELECT id, user_id, type, payload, created_at FROM notifications")->fetchAll(PDO::FETCH_ASSOC),
    'notification_outbox' => $pdo->query("SELECT id, user_id, template_key, payload, created_at FROM notification_outbox")->fetchAll(PDO::FETCH_ASSOC),
    'chat_messages'       => $pdo->query("SELECT id, room_id, sender_user_id, message, created_at FROM chat_messages")->fetchAll(PDO::FETCH_ASSOC),
];

file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "   -> Backup berhasil disimpan di: {$backupFile} (" . number_format(filesize($backupFile)) . " bytes)\n\n";

// Fungsi rekursif untuk membersihkan entitas HTML dalam array payload
function cleanEntitiesRecursive(mixed $data): mixed {
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            $data[$k] = cleanEntitiesRecursive($v);
        }
        return $data;
    }
    if (is_string($data)) {
        // Decode HTML entities jika mengandung &quot;, &#039;, &amp;, &lt;, &gt;
        if (preg_match('/&(?:quot|#039|amp|lt|gt|#x27);/i', $data)) {
            $prev = '';
            $current = $data;
            while ($prev !== $current && preg_match('/&(?:quot|#039|amp|lt|gt|#x27);/i', $current)) {
                $prev = $current;
                $current = html_entity_decode($current, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            return $current;
        }
        return $data;
    }
    return $data;
}

// 2. Bersihkan Tabel notifications
echo "2. Memproses pembersihan tabel notifications...\n";
$stmtNotifs = $pdo->query("SELECT id, payload FROM notifications WHERE payload LIKE '%&quot;%' OR payload LIKE '%&amp;%' OR payload LIKE '%&#039;%' OR payload LIKE '%&lt;%' OR payload LIKE '%&gt;%'");
$dirtyNotifs = $stmtNotifs->fetchAll(PDO::FETCH_ASSOC);

$updatedNotifs = 0;
$pdo->beginTransaction();
try {
    $stmtUpdateNotif = $pdo->prepare("UPDATE notifications SET payload = ? WHERE id = ?");
    foreach ($dirtyNotifs as $row) {
        $id = (int)$row['id'];
        $rawPayload = json_decode((string)$row['payload'], true);
        if (is_array($rawPayload)) {
            $cleaned = cleanEntitiesRecursive($rawPayload);
            $newJson = json_encode($cleaned, JSON_UNESCAPED_UNICODE);
            $stmtUpdateNotif->execute([$newJson, $id]);
            $updatedNotifs++;
        }
    }

    // 3. Bersihkan Tabel notification_outbox
    echo "3. Memproses pembersihan tabel notification_outbox...\n";
    $stmtOutbox = $pdo->query("SELECT id, payload FROM notification_outbox WHERE payload LIKE '%&quot;%' OR payload LIKE '%&amp;%' OR payload LIKE '%&#039;%' OR payload LIKE '%&lt;%' OR payload LIKE '%&gt;%'");
    $dirtyOutbox = $stmtOutbox->fetchAll(PDO::FETCH_ASSOC);

    $updatedOutbox = 0;
    $stmtUpdateOutbox = $pdo->prepare("UPDATE notification_outbox SET payload = ? WHERE id = ?");
    foreach ($dirtyOutbox as $row) {
        $id = (int)$row['id'];
        $rawPayload = json_decode((string)$row['payload'], true);
        if (is_array($rawPayload)) {
            $cleaned = cleanEntitiesRecursive($rawPayload);
            $newJson = json_encode($cleaned, JSON_UNESCAPED_UNICODE);
            $stmtUpdateOutbox->execute([$newJson, $id]);
            $updatedOutbox++;
        }
    }

    // 4. Bersihkan Tabel chat_messages
    echo "4. Memproses pembersihan tabel chat_messages...\n";
    $stmtChat = $pdo->query("SELECT id, message FROM chat_messages WHERE message LIKE '%&quot;%' OR message LIKE '%&amp;%' OR message LIKE '%&#039;%' OR message LIKE '%&lt;%' OR message LIKE '%&gt;%'");
    $dirtyChat = $stmtChat->fetchAll(PDO::FETCH_ASSOC);

    $updatedChat = 0;
    $stmtUpdateChat = $pdo->prepare("UPDATE chat_messages SET message = ? WHERE id = ?");
    foreach ($dirtyChat as $row) {
        $id = (int)$row['id'];
        $cleanedMsg = cleanEntitiesRecursive((string)$row['message']);
        $stmtUpdateChat->execute([$cleanedMsg, $id]);
        $updatedChat++;
    }

    $pdo->commit();
    echo "   -> notifications diperbarui      : {$updatedNotifs} baris\n";
    echo "   -> notification_outbox diperbarui: {$updatedOutbox} baris\n";
    echo "   -> chat_messages diperbarui      : {$updatedChat} baris\n";
    echo "\nMigrasi 34A berhasil selesai dengan aman!\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] Migrasi gagal dan di-rollback: " . $e->getMessage() . "\n";
    exit(1);
}
