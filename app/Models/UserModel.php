<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use PDO;

/**
 * UserModel — Pengelolaan data tabel users untuk autentikasi dan manajemen user Admin
 */
class UserModel extends BaseModel
{
    /**
     * Cari user berdasarkan ID beserta relasi rolenya
     */
    public function findWithRole(int $id): array|false
    {
        $sql = "
            SELECT u.id, u.role_id, u.name, u.email, u.phone, u.identity_number, u.study_program, u.batch_year, 
                   u.avatar, u.is_active, u.deleted_at, u.email_verified_at, u.email_notifications_enabled, u.created_at, u.updated_at,
                   r.name AS role_name, r.label AS role_label
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ";
        return $this->fetchOne($sql, [$id]);
    }

    /**
     * Cari user berdasarkan email (untuk login / autentikasi)
     */
    public function findByEmail(string $email): array|false
    {
        $sql = "
            SELECT u.*, r.name AS role_name, r.label AS role_label
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ?
            LIMIT 1
        ";
        return $this->fetchOne($sql, [trim($email)]);
    }

    /**
     * Cari user berdasarkan identitas (Email ATAU NIM/NIP/identity_number)
     */
    public function findByIdentity(string $identity): array|false
    {
        $clean = trim($identity);
        $sql = "
            SELECT u.*, r.name AS role_name, r.label AS role_label
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? OR u.identity_number = ?
            LIMIT 1
        ";
        return $this->fetchOne($sql, [$clean, $clean]);
    }


    /**
     * Ambil semua user aktif
     */
    public function getAllActive(): array
    {
        $sql = "
            SELECT u.id, u.name, u.email, u.phone, u.identity_number, u.department, u.status, r.label AS role_label, u.created_at
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.is_active = 1 AND u.deleted_at IS NULL
            ORDER BY u.id ASC
        ";
        return $this->fetchAll($sql);
    }

    /**
     * Pencarian & Filter User untuk Manajemen Admin
     */
    public function searchAndFilter(
        string $keyword = '',
        ?int $roleId = null,
        ?string $status = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($keyword)) {
            $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.identity_number LIKE ? OR u.phone LIKE ?)";
            $kw = '%' . trim($keyword) . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        if ($roleId !== null && $roleId > 0) {
            $where[] = "u.role_id = ?";
            $params[] = $roleId;
        }

        if ($status === 'deleted') {
            $where[] = "u.deleted_at IS NOT NULL";
        } else {
            $where[] = "u.deleted_at IS NULL";
            if (!empty($status)) {
                $where[] = "u.is_active = ?";
                $params[] = ($status === 'active') ? 1 : 0;
            }
        }

        $whereClause = implode(' AND ', $where);

        $sql = "
            SELECT u.id, u.role_id, u.name, u.email, u.phone, u.identity_number,
                   u.study_program, u.batch_year,
                   IF(u.deleted_at IS NOT NULL, 'deleted', IF(u.is_active = 1, 'active', 'inactive')) AS status,
                   u.is_active, u.deleted_at, u.created_at,
                   r.name AS role_name, r.label AS role_label
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE {$whereClause}
            ORDER BY u.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        return $this->fetchAll($sql, $params);
    }

    /**
     * Hitung total user sesuai pencarian & filter
     */
    public function countSearchAndFilter(
        string $keyword = '',
        ?int $roleId = null,
        ?string $status = null
    ): int {
        $where = ["1=1"];
        $params = [];

        if (!empty($keyword)) {
            $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.identity_number LIKE ? OR u.phone LIKE ?)";
            $kw = '%' . trim($keyword) . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        if ($roleId !== null && $roleId > 0) {
            $where[] = "u.role_id = ?";
            $params[] = $roleId;
        }

        if ($status === 'deleted') {
            $where[] = "u.deleted_at IS NOT NULL";
        } else {
            $where[] = "u.deleted_at IS NULL";
            if (!empty($status)) {
                $where[] = "u.is_active = ?";
                $params[] = ($status === 'active') ? 1 : 0;
            }
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM users u WHERE {$whereClause}";
        $stmt = $this->query($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Ambil semua opsi Role dari database
     */
    public function getAllRoles(): array
    {
        $sql = "SELECT * FROM roles ORDER BY id ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Buat user baru oleh Admin
     */
    public function createByAdmin(array $data): int
    {
        $email = strtolower(trim($data['email']));
        if ($this->findByEmail($email)) {
            throw new \InvalidArgumentException("Email '{$email}' sudah terdaftar dalam sistem.");
        }

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        $roleId = (int) ($data['role_id'] ?? 3); // default mahasiswa/civitas
        $status = $data['status'] ?? 'active';
        $isActive = ($status === 'active') ? 1 : 0;

        $sql = "
            INSERT INTO users (
                role_id,
                name,
                email,
                password_hash,
                identity_number,
                phone,
                is_active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        $this->query($sql, [
            $roleId,
            trim($data['name']),
            $email,
            $passwordHash,
            !empty($data['identity_number']) ? trim($data['identity_number']) : null,
            !empty($data['phone']) ? trim($data['phone']) : null,
            $isActive,
        ]);

        return (int) $this->lastInsertId();
    }

    /**
     * Update Role pengguna oleh Admin
     */
    public function updateRole(int $userId, int $roleId): bool
    {
        $sql = "UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?";
        return $this->query($sql, [$roleId, $userId])->rowCount() > 0;
    }

    /**
     * Update Status pengguna (active, inactive, suspended)
     */
    public function updateStatus(int $userId, string $status): bool
    {
        $isActive = ($status === 'active') ? 1 : 0;
        $sql = "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?";
        return $this->query($sql, [$isActive, $userId])->rowCount() > 0;
    }

    /**
     * Dapatkan Statistik Lengkap untuk Dashboard Admin
     */
    public function getDashboardStats(): array
    {
        $metricService = new \App\Services\MetricDefinitionService($this->pdo);
        return $metricService->getDashboardSummary();
    }
}
