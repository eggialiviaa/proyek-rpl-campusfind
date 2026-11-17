<?php

declare(strict_types=1);

/**
 * database/migrate.php â€” CLI Migration & Seeder Runner
 *
 * Menjalankan pembuatan schema database dan seeder data awal untuk Campus Find.
 * Cara eksekusi: php database/migrate.php [--seed] [--fresh]
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;

// 1. Load Environment
$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad(); if (!file_exists(BASE_PATH . "/.env")) { echo "PERINGATAN: File .env tidak ditemukan. Menggunakan konfigurasi default.\n"; }

$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port   = (int) ($_ENV['DB_PORT'] ?? 3306);
$dbname = $_ENV['DB_NAME'] ?? 'campus_find';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

echo "====================================================\n";
echo "   Campus Find â€” Database Migration & Seeder Tool   \n";
echo "====================================================\n";
echo "Target Host : {$host}:{$port}\n";
echo "Database    : {$dbname}\n";
echo "User        : {$user}\n";
echo "----------------------------------------------------\n";

// 2. Connect to Server (tanpa db name dulu untuk memastikan database ada)
try {
    $serverPdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Buat database jika belum ada
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "âœ“ Database `{$dbname}` siap.\n";
} catch (PDOException $e) {
    echo "âœ— Gagal koneksi ke server MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Connect ke database spesifik
try {
    $dbPdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    echo "âœ— Gagal memilih database `{$dbname}`: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Eksekusi Schema
$schemaFile = BASE_PATH . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    echo "âœ— File schema `{$schemaFile}` tidak ditemukan!\n";
    exit(1);
}

echo "\n[1/2] Mengeksekusi database schema (24 entitas)...\n";
try {
    $schemaSql = file_get_contents($schemaFile);
    $dbPdo->exec($schemaSql);
    echo "âœ“ Seluruh tabel, foreign keys, dan index berhasil dibuat!\n";
} catch (PDOException $e) {
    echo "âœ— Error saat eksekusi schema.sql: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Eksekusi Seeder Data (Master Data)
$seedFile = BASE_PATH . '/database/seeds.sql';
if (file_exists($seedFile)) {
    echo "\n[2/2] Mengisi seed master data awal...\n";
    try {
        $seedSql = file_get_contents($seedFile);
        $dbPdo->exec($seedSql);
        echo "âœ“ Master data berhasil dimasukkan:\n";
        echo "   - 3 Roles (Admin, Petugas, Civitas)\n";
        echo "   - 7 Kategori Barang\n";
        echo "   - 9 Lokasi Kampus\n";
        echo "   - 5 Storage Locations (L&F Center)
   - Baseline System Settings & Canonical Email Templates\n";
    } catch (PDOException $e) {
        echo "âœ— Error saat eksekusi seeds.sql: " . $e->getMessage() . "\n";
        exit(1);
    }
}

$appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production'))));

if ($appEnv === 'production') {
    echo "\n[i] Environment: production. Seeder akun demo DILEWATI demi keamanan.\n";
    echo "    Untuk membuat akun Administrator pertama, jalankan perintah:\n";
    echo "    php database/create_admin.php\n";
} else {
    echo "\n[i] Menjalankan seeder akun demo (local/testing: {$appEnv})...\n";
    require_once BASE_PATH . '/database/seed_demo.php';
}


// 6. Verifikasi & Rekap Tabel
echo "\n----------------------------------------------------\n";
echo "   Rekapitulasi Tabel Database `{$dbname}`\n";
echo "----------------------------------------------------\n";

$tablesStmt = $dbPdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $countStmt = $dbPdo->query("SELECT COUNT(*) FROM `{$table}`");
    $rowCount  = $countStmt->fetchColumn();
    printf(" - %-25s : %3d baris data\n", $table, $rowCount);
}

echo "----------------------------------------------------\n";
echo "âœ“ Migration & Seeding selesai sukses tanpa error!\n";
echo "====================================================\n";

