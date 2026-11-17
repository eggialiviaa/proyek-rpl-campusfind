<?php

declare(strict_types=1);

namespace App\Services\CFind;

use PDO;
use RuntimeException;
use Throwable;

/**
 * AbstractRoleProfile — Pondasi profil chatbot per role (Guest, Civitas, Petugas, Admin)
 * Memastikan otorisasi berlapis (Layer 2) pada setiap pemanggilan query/tool.
 */
abstract class AbstractRoleProfile
{
    protected PDO $pdo;
    protected string $role;
    protected ?int $userId;
    protected ?array $sessionUser;

    public function __construct(PDO $pdo, string $role, ?int $userId, ?array $sessionUser)
    {
        $this->pdo = $pdo;
        $this->role = strtolower(trim($role));
        $this->userId = $userId;
        $this->sessionUser = $sessionUser;
    }

    /**
     * Dapatkan nama profil role resmi
     */
    abstract public function getRoleName(): string;

    /**
     * Dapatkan System Prompt khusus untuk profil role ini
     */
    abstract public function getSystemPrompt(): string;

    /**
     * Daftar intent/fitur yang diizinkan untuk role ini
     *
     * @return array<string>
     */
    abstract public function getAllowedIntents(): array;

    /**
     * Pesan penolakan sopan saat user menanyakan hal di luar hak akses role-nya
     */
    abstract public function getDeniedResponse(string $intent): string;

    /**
     * Tangani pertanyaan langsung dengan template backend tanpa AI (Tier 1 / Tier 2 fast path)
     * Mengembalikan string jika terjawab langsung, atau null jika perlu diproses AI.
     */
    abstract public function handleDirectTemplate(string $text, string $intent): ?string;

    /**
     * Bangun konteks grounding database ter-parameter khusus role ini
     */
    abstract public function buildGroundingContext(string $userMessage): string;

    /**
     * Verifikasi Layer 2: Pastikan role server-side sesuai dengan hak akses fungsi
     *
     * @throws RuntimeException Jika role tidak cocok
     */
    protected function assertRole(string|array $allowedRoles, string $actionName): void
    {
        $allowed = (array) $allowedRoles;
        if (!in_array($this->role, $allowed, true)) {
            throw new RuntimeException("Akses ditolak: role '{$this->role}' tidak memiliki izin untuk '{$actionName}'.");
        }
    }

    /**
     * Verifikasi Layer 2: Pastikan user login valid (bukan tamu)
     *
     * @throws RuntimeException Jika user belum login
     */
    protected function assertAuthenticated(string $actionName): int
    {
        if ($this->userId === null || $this->userId <= 0) {
            throw new RuntimeException("Akses ditolak: '{$actionName}' memerlukan autentikasi login pengguna.");
        }
        return $this->userId;
    }

    /**
     * Helper pencarian barang publik (Tersedia untuk semua profil: Tamu, Civitas, Petugas, Admin)
     * Mengembalikan daftar publik maksimal 5 barang tanpa data privat.
     */
    protected function queryPublicItems(string $keyword): string
    {
        try {
            $sql = "
                SELECT f.report_number, f.title, c.name AS category_name, l.name AS location_name, f.found_date, f.status
                FROM found_reports f
                LEFT JOIN categories c ON f.category_id = c.id
                LEFT JOIN locations l ON f.location_id = l.id
                WHERE f.status IN ('open', 'stored', 'pending_handover')
            ";
            $params = [];

            if ($keyword !== '') {
                $sql .= " AND (f.title LIKE ? OR c.name LIKE ?)";
                $params[] = "%{$keyword}%";
                $params[] = "%{$keyword}%";
            }

            $sql .= " ORDER BY f.created_at DESC LIMIT 5";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return "Tidak ditemukan barang temuan publik yang cocok dengan pencarian '{$keyword}'.";
            }

            $lines = ["Daftar barang temuan publik (" . count($rows) . " item):"];
            foreach ($rows as $r) {
                $cleanTitle = DataMasker::wrapUntrusted('Judul', (string)($r['title'] ?? ''));
                $cat = (string)($r['category_name'] ?? 'Umum');
                $loc = (string)($r['location_name'] ?? 'Kampus');
                $date = date('d/m/Y', strtotime((string)$r['found_date']));
                $lines[] = "• [{$r['report_number']}] {$cleanTitle} | Kategori: {$cat} | Lokasi: {$loc} | Tgl: {$date}";
            }

            return implode("\n", $lines);
        } catch (Throwable $e) {
            error_log('[AbstractRoleProfile::queryPublicItems] Error: ' . $e->getMessage());
            return "Informasi pencarian barang tidak dapat diambil saat ini.";
        }
    }

    /**
     * Helper statistik agregat umum barang tersimpan & dikembalikan (Tersedia untuk semua profil)
     */
    protected function queryGeneralStats(): array
    {
        try {
            $stmtStored = $this->pdo->query("SELECT COUNT(*) FROM items WHERE status IN ('stored', 'ready_for_handover', 'in_claim')");
            $totalStored = (int) $stmtStored->fetchColumn();

            $stmtReturned = $this->pdo->query("SELECT COUNT(*) FROM items WHERE status = 'returned'");
            $totalReturned = (int) $stmtReturned->fetchColumn();

            return [
                'stored'   => $totalStored,
                'returned' => $totalReturned,
            ];
        } catch (Throwable $e) {
            error_log('[AbstractRoleProfile::queryGeneralStats] Error: ' . $e->getMessage());
            return ['stored' => 0, 'returned' => 0];
        }
    }
}
