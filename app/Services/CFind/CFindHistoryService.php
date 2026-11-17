<?php

declare(strict_types=1);

namespace App\Services\CFind;

use Core\Database;
use App\Services\SettingService;
use PDO;
use Throwable;

/**
 * CFindHistoryService — Penyimpanan dan Pemantauan Riwayat Chatbot C-Find untuk Administrator (Prompt 36C)
 *
 * Menggantikan desain lama Prompt 27 ("usage log tanpa isi pesan").
 * Fitur Utama:
 * 1. Simpan: pertanyaan, jawaban, role, user_id (tamu: session_hash), intent, status (dijawab, ditolak, tidak bisa dijawab, gagal), waktu, durasi, token.
 * 2. Tabel/daftar hasil: HANYA simpan ringkasan (judul, kolom, jumlah baris), BUKAN isi baris agar data pribadi tidak digandakan.
 * 3. Samarkan kontak: email dan nomor telepon otomatis disamarkan.
 * 4. Proteksi rahasia: tidak pernah menyimpan reasoning_content, API key, atau password.
 * 5. Retensi otomatis: CFIND_HISTORY_RETENTION_DAYS (default 30 hari).
 */
class CFindHistoryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
    }

    /**
     * Catat interaksi percakapan C-Find ke database
     *
     * @param int|null $userId ID Pengguna terdaftar (null jika tamu)
     * @param string $role Peran pengguna (admin, petugas, civitas, guest)
     * @param string $rawQuestion Teks pertanyaan mentah dari pengguna
     * @param string|array $rawReply Teks atau blok jawaban dari chatbot
     * @param string $intent Intent terdeteksi atau kunci metrik
     * @param string $systemStatus Status teknis awal ('success', 'denied', 'error', 'rate_limited', dll)
     * @param float $startTime Timestamp mikro saat pemrosesan pesan dimulai
     * @param int $tokensUsed Jumlah token yang terpakai
     * @return int ID percakapan yang tersimpan
     */
    public function recordConversation(
        ?int $userId,
        string $role,
        string $rawQuestion,
        string|array $rawReply,
        string $intent,
        string $systemStatus,
        float $startTime,
        int $tokensUsed = 0
    ): int {
        try {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // 1. Penanda sesi yang di-hash (terutama untuk tamu)
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
            $rawSessionId = session_id();
            if (empty($rawSessionId)) {
                $rawSessionId = $userId ? "user_{$userId}" : 'guest_' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            }
            $sessionHash = hash('sha256', $rawSessionId);

            // 2. Pemaskeran Kontak pada Pertanyaan & Jawaban
            $maskedQuestion = self::maskContacts($rawQuestion);
            $summarizedAnswer = self::summarizeAndMaskReply($rawReply);

            // 3. Klasifikasi Status Prompt 36C: (dijawab, ditolak, tidak bisa dijawab, gagal)
            $status = self::mapStatus($systemStatus, $intent, $summarizedAnswer);

            // 4. Pembersihan Otomatis Berdasarkan Batas Retensi
            $this->cleanExpiredHistory();

            // 5. Simpan ke Tabel Database cfind_chat_history
            $sql = "
                INSERT INTO cfind_chat_history (
                    user_id,
                    session_hash,
                    role,
                    question,
                    answer,
                    intent,
                    status,
                    duration_ms,
                    tokens_used,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $sessionHash,
                $role,
                $maskedQuestion,
                $summarizedAnswer,
                $intent,
                $status,
                $durationMs,
                $tokensUsed,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('[CFindHistoryService::recordConversation] Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Samarkan email dan nomor telepon pada teks yang disimpan
     */
    public static function maskContacts(string $text): string
    {
        // 1. Samarkan Email: misal budi.santoso@campus.ac.id -> b***o@***.ac.id
        $text = preg_replace_callback('/([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/u', function ($matches) {
            $user = $matches[1];
            $domain = $matches[2];
            $uLen = mb_strlen($user);
            $maskedUser = $uLen > 2 
                ? mb_substr($user, 0, 1) . '***' . mb_substr($user, -1) 
                : mb_substr($user, 0, 1) . '***';

            // Ambil ekstensi domain misal ac.id atau com
            $parts = explode('.', $domain);
            $ext = count($parts) > 1 ? implode('.', array_slice($parts, -2)) : $domain;
            return "{$maskedUser}@***.{$ext}";
        }, $text);

        // 2. Samarkan Nomor Telepon (format Indonesia: 08xx, +62xx, 62xx)
        $text = preg_replace_callback('/(?:\+?62|08)[0-9\s\-]{7,14}[0-9]/u', function ($matches) {
            $digits = preg_replace('/[\s\-]/', '', $matches[0]);
            $len = mb_strlen($digits);
            if ($len >= 10) {
                return mb_substr($digits, 0, 4) . '****' . mb_substr($digits, -3);
            }
            return '[NOMOR TELEPON DISAMARKAN]';
        }, $text);

        // 3. Proteksi Rahasia: Bersihkan API Key dan Hash Password jika ada
        $text = preg_replace('/nvapi-[A-Za-z0-9_-]{10,}/u', '[API KEY DISAMARKAN]', $text);
        $text = preg_replace('/\$2[ayb]\$[0-9]{2}\$[A-Za-z0-9\.\/]{53}/u', '[HASH DISAMARKAN]', $text);

        // 4. Hapus reasoning_content / tag think model AI
        $text = preg_replace('/<think>[\s\S]*?<\/think>/iu', '', $text);
        $text = preg_replace('/\[reasoning[\s\S]*?\]/iu', '', $text);

        return trim($text);
    }

    /**
     * Ringkas jawaban: untuk tabel/daftar simpan RINGKASAN saja (judul, kolom, jumlah baris),
     * BUKAN isi baris, agar data pribadi civitas tidak digandakan.
     */
    public static function summarizeAndMaskReply(string|array $reply): string
    {
        if (is_array($reply)) {
            $parts = [];
            foreach ($reply as $block) {
                if (!is_array($block)) {
                    $parts[] = self::maskContacts((string)$block);
                    continue;
                }

                $type = $block['type'] ?? 'text';
                if ($type === 'text') {
                    $c = (string)($block['content'] ?? '');
                    if (!empty($c)) {
                        $parts[] = self::maskContacts($c);
                    }
                } elseif ($type === 'table') {
                    // Ekstrak nama kolom
                    $colNames = [];
                    if (!empty($block['columns']) && is_array($block['columns'])) {
                        foreach ($block['columns'] as $k => $c) {
                            if (is_array($c)) {
                                $colNames[] = $c['label'] ?? ($c['key'] ?? (string)$k);
                            } elseif (is_string($c)) {
                                $colNames[] = $c;
                            }
                        }
                    }
                    $colsStr = !empty($colNames) ? implode(', ', $colNames) : 'Standar';
                    
                    // Hitung jumlah baris data
                    $rowCount = (int)($block['total'] ?? count($block['rows'] ?? $block['all_rows'] ?? []));
                    $title = !empty($block['title']) ? $block['title'] : 'Daftar Data';

                    $parts[] = "[Tabel: {$title} | Kolom: {$colsStr} | Total: {$rowCount} baris]";
                }
            }
            return implode("\n\n", $parts);
        }

        // Teks biasa
        return self::maskContacts((string)$reply);
    }

    /**
     * Petakan status teknis ke 4 status Prompt 36C:
     * - dijawab
     * - ditolak
     * - tidak bisa dijawab
     * - gagal
     */
    public static function mapStatus(string $systemStatus, string $intent, string $replyText): string
    {
        $statusLower = mb_strtolower($systemStatus);
        $intentLower = mb_strtolower($intent);
        $textLower = mb_strtolower($replyText);

        // 1. Gagal teknis
        if (str_contains($statusLower, 'error') || 
            str_contains($statusLower, 'unavailable') || 
            str_contains($textLower, 'sedang sibuk') ||
            str_contains($textLower, 'tidak tersedia saat ini')) {
            return 'gagal';
        }

        // 2. Ditolak (RBAC, Guardrail, Rate Limit, Out of Scope)
        $deniedIntents = [
            'prompt_injection',
            'out_of_scope',
            'rate_limit',
            'role_disabled',
            'civitas_rbac_denied',
            'guest_restricted',
            'budget_check',
            'budget_exceeded'
        ];

        if ($statusLower === 'denied' ||
            $statusLower === 'rate_limited' ||
            $statusLower === 'rejected_prompt_injection' ||
            $statusLower === 'rejected_out_of_scope' ||
            in_array($intentLower, $deniedIntents, true) ||
            str_contains($textLower, 'di luar hak akses') ||
            str_contains($textLower, 'hanya melayani pertanyaan seputar sistem lost & found') ||
            str_contains($textLower, 'batasan keamanan ketat') ||
            str_contains($textLower, 'batas penggunaan')) {
            return 'ditolak';
        }

        // 3. Tidak bisa dijawab (Entity not found, Out of FAQ honest fallback, Zero match)
        if ($statusLower === 'guest_faq_honest_fallback' ||
            $intentLower === 'guest_faq_honest_fallback' ||
            $intentLower === 'entity_not_found' ||
            str_contains($textLower, 'tidak menemukan barang bernama') ||
            str_contains($textLower, 'tidak menemukan laporan bernama') ||
            str_contains($textLower, 'tidak menemukan jawaban untuk pertanyaan tersebut') ||
            str_contains($textLower, 'tidak ditemukan di posko')) {
            return 'tidak bisa dijawab';
        }

        // 4. Dijawab dengan sukses
        return 'dijawab';
    }

    /**
     * Pembersihan otomatis data percakapan yang melewati batas retensi CFIND_HISTORY_RETENTION_DAYS (default 30)
     */
    public function cleanExpiredHistory(): int
    {
        try {
            $days = (int)($_ENV['CFIND_HISTORY_RETENTION_DAYS'] ?? getenv('CFIND_HISTORY_RETENTION_DAYS') ?: 30);
            if ($days <= 0) {
                $days = 30;
            }

            $stmt = $this->pdo->prepare("DELETE FROM cfind_chat_history WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('[CFindHistoryService::cleanExpiredHistory] Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Ambil data percakapan berpaginasi dengan filter lengkap untuk Admin
     *
     * @param array<string, mixed> $filters
     * @param int $page
     * @param int $limit
     * @return array{rows: array, total: int, page: int, limit: int, total_pages: int}
     */
    public function getPaginatedHistory(array $filters, int $page = 1, int $limit = 25): array
    {
        $page = max(1, $page);
        $limit = max(5, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        // Filter Role
        $role = trim((string)($filters['role'] ?? ''));
        if (!empty($role) && $role !== 'all') {
            $where[] = "h.role = ?";
            $params[] = $role;
        }

        // Filter Status (dijawab, ditolak, tidak bisa dijawab, gagal)
        $status = trim((string)($filters['status'] ?? ''));
        if (!empty($status) && $status !== 'all') {
            $where[] = "h.status = ?";
            $params[] = $status;
        }

        // Filter Tanggal Dari
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if (!empty($dateFrom)) {
            $where[] = "h.created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }

        // Filter Tanggal Sampai
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if (!empty($dateTo)) {
            $where[] = "h.created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }

        // Filter Pengguna (Nama / Email / User ID / NIM)
        $userSearch = trim((string)($filters['user'] ?? ''));
        if (!empty($userSearch)) {
            if (is_numeric($userSearch)) {
                $where[] = "h.user_id = ?";
                $params[] = (int)$userSearch;
            } else {
                $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.identity_number LIKE ?)";
                $kw = '%' . $userSearch . '%';
                $params[] = $kw;
                $params[] = $kw;
                $params[] = $kw;
            }
        }

        // Filter Kata Kunci (Pertanyaan / Jawaban / Intent)
        $keyword = trim((string)($filters['keyword'] ?? ''));
        if (!empty($keyword)) {
            $where[] = "(h.question LIKE ? OR h.answer LIKE ? OR h.intent LIKE ?)";
            $kw = '%' . $keyword . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = implode(' AND ', $where);

        // Hitung total
        $countSql = "
            SELECT COUNT(*) 
            FROM cfind_chat_history h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE {$whereSql}
        ";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Ambil data
        $dataSql = "
            SELECT h.*, 
                   u.name AS user_name, 
                   u.email AS user_email,
                   u.identity_number AS user_nim
            FROM cfind_chat_history h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE {$whereSql}
            ORDER BY h.created_at DESC, h.id DESC
            LIMIT ? OFFSET ?
        ";
        $dataStmt = $this->pdo->prepare($dataSql);
        
        $bindIdx = 1;
        foreach ($params as $p) {
            $dataStmt->bindValue($bindIdx++, $p);
        }
        $dataStmt->bindValue($bindIdx++, $limit, PDO::PARAM_INT);
        $dataStmt->bindValue($bindIdx++, $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;

        return [
            'rows'        => $rows,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Ambil detail percakapan berdasarkan ID
     */
    public function getDetailById(int $id): ?array
    {
        $sql = "
            SELECT h.*, 
                   u.name AS user_name, 
                   u.email AS user_email,
                   u.identity_number AS user_nim
            FROM cfind_chat_history h
            LEFT JOIN users u ON h.user_id = u.id
            WHERE h.id = ?
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}

