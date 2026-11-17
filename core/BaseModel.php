<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOStatement;

/**
 * BaseModel — Abstract base untuk semua Model
 * Menyediakan helper query PDO yang aman via prepared statements
 */
abstract class BaseModel
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getPdo();
    }

    /**
     * Jalankan query dengan prepared statement, kembalikan PDOStatement
     *
     * @param string $sql   SQL query dengan placeholder ?
     * @param array  $params Parameter untuk binding
     */
    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Ambil semua baris dari query
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Ambil satu baris dari query
     *
     * @return array<string, mixed>|false
     */
    protected function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Jalankan INSERT/UPDATE/DELETE, kembalikan jumlah baris terpengaruh
     */
    protected function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Ambil ID terakhir yang di-insert
     */
    protected function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
