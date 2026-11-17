<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;

/**
 * Database — Singleton PDO connection
 * Membaca konfigurasi dari environment variables via config/database.php
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require BASE_PATH . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['dbname'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            // Log error, jangan expose detail ke browser
            error_log('[Database] Connection failed: ' . $e->getMessage());
            throw new \RuntimeException('Database connection failed. Check your .env configuration.');
        }
    }

    /** Singleton — ambil instance tunggal Database */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /** Ambil PDO instance */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /** Reset koneksi (terutama berguna saat pengujian untuk beralih database) */
    public static function resetConnection(): void
    {
        self::$instance = null;
    }

    // Prevent clone dan unserialize untuk menjaga singleton
    private function __clone() {}
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton.');
    }
}
