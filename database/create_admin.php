<?php

declare(strict_types=1);

/**
 * database/create_admin.php — CLI Tool untuk Membuat Administrator Pertama (Production & Hosting)
 *
 * Digunakan pada saat deployment hosting / production di mana seeder akun demo dinonaktifkan.
 * Admin pertama yang dibuat lewat script ini selanjutnya dapat membuat akun Petugas
 * dan Civitas melalui antarmuka User Management di dashboard Admin.
 *
 * Penggunaan:
 *   php database/create_admin.php
 *   php database/create_admin.php --name="Admin Utama" --email="admin@kampus.ac.id" --password="PasswordKuat123!"
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;

// 1. Load Environment
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port   = (int) ($_ENV['DB_PORT'] ?? 3306);
$dbname = $_ENV['DB_NAME'] ?? 'campus_find';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

echo "========================================================================\n";
echo "   Campus Find — Pembuatan Akun Administrator Pertama (Hosting / Prod)  \n";
echo "========================================================================\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    echo "✗ Gagal koneksi database `{$dbname}`: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Parse CLI Arguments (bila dijalankan non-interaktif)
$options = getopt('', ['name::', 'email::', 'password::', 'identity::', 'phone::']) ?: [];

$name = trim((string)($options['name'] ?? ''));
$email = trim((string)($options['email'] ?? ''));
$password = (string)($options['password'] ?? '');
$identity = trim((string)($options['identity'] ?? ''));
$phone = trim((string)($options['phone'] ?? ''));

// Dukung juga parsing manual dari $argv (khususnya di Windows cmd / powershell)
if (isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--name=')) {
            $name = trim(trim(substr($arg, 7)), '"\'');
        }
        if (str_starts_with($arg, '--email=')) {
            $email = trim(trim(substr($arg, 8)), '"\'');
        }
        if (str_starts_with($arg, '--password=')) {
            $password = trim(trim(substr($arg, 11)), '"\'');
        }
        if (str_starts_with($arg, '--identity=')) {
            $identity = trim(trim(substr($arg, 11)), '"\'');
        }
        if (str_starts_with($arg, '--phone=')) {
            $phone = trim(trim(substr($arg, 8)), '"\'');
        }
    }
}

// Helper untuk membaca input dari CLI jika belum diberikan via options
function promptInput(string $prompt, string $default = ''): string {
    $defHint = $default !== '' ? " [{$default}]" : '';
    echo "{$prompt}{$defHint}: ";
    if (feof(STDIN)) {
        return $default;
    }
    $line = fgets(STDIN);
    if ($line === false) {
        return $default;
    }
    $input = trim((string)$line);
    return $input !== '' ? $input : $default;
}

if ($email === '') {
    $email = promptInput("Masukkan Email Administrator");
    while (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (feof(STDIN)) {
            echo "✗ Email tidak boleh kosong.\n";
            exit(1);
        }
        echo "✗ Format email tidak valid. Silakan coba lagi.\n";
        $email = promptInput("Masukkan Email Administrator");
    }
}

if ($name === '') {
    $name = promptInput("Masukkan Nama Lengkap", "Administrator Sistem");
}

if ($identity === '') {
    $identity = promptInput("Masukkan Nomor Identitas / NIP", "ADM-001");
}

if ($password === '') {
    echo "Masukkan Password (minimal 8 karakter): ";
    $line = fgets(STDIN);
    $password = $line !== false ? trim((string)$line) : '';
    while (strlen($password) < 8) {
        if (feof(STDIN)) {
            echo "✗ Password minimal 8 karakter.\n";
            exit(1);
        }
        echo "✗ Password terlalu pendek (minimal 8 karakter). Silakan coba lagi.\n";
        echo "Masukkan Password (minimal 8 karakter): ";
        $line = fgets(STDIN);
        $password = $line !== false ? trim((string)$line) : '';
    }
}


$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// 3. Simpan Administrator ke Database
try {
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    $existingId = $checkStmt->fetchColumn();

    if ($existingId) {
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET role_id = 1, name = ?, password_hash = ?, identity_number = ?, phone = ?, is_active = 1, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$name, $passwordHash, $identity, $phone ?: null, $existingId]);
        $adminId = (int)$existingId;
        echo "\n✓ Akun pengguna yang sudah ada berhasil diperbarui menjadi Administrator!\n";
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO users (role_id, name, email, password_hash, identity_number, phone, is_active, email_verified_at, created_at, updated_at) 
            VALUES (1, ?, ?, ?, ?, ?, 1, NOW(), NOW(), NOW())
        ");
        $insertStmt->execute([$name, $email, $passwordHash, $identity, $phone ?: null]);
        $adminId = (int)$pdo->lastInsertId();
        echo "\n✓ Akun Administrator baru berhasil dibuat!\n";
    }

    // Catat Audit Log
    try {
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, created_at) 
            VALUES (?, 'ADMIN_CREATED_CLI', 'users', ?, ?, '127.0.0.1', NOW())
        ");
        $auditStmt->execute([
            $adminId,
            $adminId,
            json_encode(['email' => $email, 'name' => $name, 'role' => 'admin']),
        ]);
    } catch (\Throwable $e) {
        // Abaikan jika audit_logs belum tersedia
    }

    echo "------------------------------------------------------------------------\n";
    echo "   Detail Kredensial Administrator:\n";
    echo "   Nama     : {$name}\n";
    echo "   Email    : {$email}\n";
    echo "   NIP/NIM  : {$identity}\n";
    echo "   Role     : Administrator (role_id: 1)\n";
    echo "------------------------------------------------------------------------\n";
    echo "✓ Anda sekarang dapat masuk melalui halaman login (/login).\n";
    echo "✓ Setelah masuk, Admin dapat membuat akun Petugas dan Civitas melalui\n";
    echo "  menu 'User Management' di Dashboard Admin.\n";
    echo "========================================================================\n\n";
    exit(0);
} catch (PDOException $e) {
    echo "\n✗ Gagal menyimpan akun Administrator: " . $e->getMessage() . "\n";
    exit(1);
}
