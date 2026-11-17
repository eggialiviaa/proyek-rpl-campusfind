<?php

declare(strict_types=1);

/**
 * cli/notifications_worker.php — Direct CLI Script untuk Windows Task Scheduler
 *
 * Contoh command Task Scheduler:
 *   C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe C:\laragon\www\Campus-Find\cli\notifications_worker.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
Dotenv::createImmutable(BASE_PATH)->safeLoad();

use App\Services\Notifications\NotificationOutboxService;

$batchSize = 20;
$nowStr = date('Y-m-d H:i:s');

echo "[{$nowStr}] Memulai pemrosesan notification_outbox...\n";
$res = NotificationOutboxService::processOutbox($batchSize);

if ($res['circuit_breaker']) {
    echo "[{$nowStr}] [CIRCUIT BREAKER AKTIF] Pengiriman ditangguhkan sementara karena kegagalan beruntun.\n";
    exit(2);
}

echo "[{$nowStr}] Hasil: Diproses={$res['processed']}, Sukses={$res['sent']}, Gagal={$res['failed']}, Ditunda={$res['postponed']}\n";
exit(0);
