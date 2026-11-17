<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;
use DateTimeImmutable;
use DateTimeZone;

/**
 * MetricDefinitionService — Sumber Kebenaran Tunggal Metrik dan Definisi Sistem (Prompt 29C-2)
 *
 * Mengelola definisi, formula query, rincian breakdown status, dan penjelasan metadata
 * yang dipakai bersama oleh Dashboard Admin, Controller terkait, dan Chatbot C-Find.
 */
class MetricDefinitionService
{
    private PDO $pdo;
    private DateTimeZone $timezone;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
        $this->timezone = new DateTimeZone('Asia/Jakarta');
    }

    /**
     * Single Source of Truth Status & Aksi Metrik Sistem
     */
    public const POSKO_ITEM_STATUSES = ['stored', 'registered', 'in_inspection', 'in_claim', 'ready_for_handover'];
    public const LOGIN_ACTIVITY_ACTIONS = ['LOGIN_SUCCESS', 'auth_login', 'login', 'user_login'];

    /**
     * Dapatkan daftar status resmi barang yang dianggap berada di Posko Center
     *
     * @return array<string>
     */
    public static function getPoskoItemStatuses(): array
    {
        return self::POSKO_ITEM_STATUSES;
    }

    /**
     * Dapatkan potongan SQL filter status barang posko
     *
     * @param string $tableAlias
     * @return string
     */
    public static function getPoskoItemFilterSql(string $tableAlias = 'items'): string
    {
        $quoted = implode("', '", self::POSKO_ITEM_STATUSES);
        $prefix = !empty($tableAlias) ? "{$tableAlias}." : "";
        return "{$prefix}status IN ('{$quoted}')";
    }

    /**
     * Dapatkan daftar aksi resmi yang dianggap sebagai aktivitas login berhasil
     *
     * @return array<string>
     */
    public static function getLoginActivityActions(): array
    {
        return self::LOGIN_ACTIVITY_ACTIONS;
    }

    /**
     * Dapatkan potongan SQL filter aksi login berhasil
     *
     * @param string $tableAlias
     * @return string
     */
    public static function getLoginActivityFilterSql(string $tableAlias = 'audit_logs'): string
    {
        $quoted = implode("', '", self::LOGIN_ACTIVITY_ACTIONS);
        $prefix = !empty($tableAlias) ? "{$tableAlias}." : "";
        return "{$prefix}action IN ('{$quoted}')";
    }

    /**
     * Definisi resmi kamus metrik
     */
    public const DEFINITIONS = [
        'posko_items' => [
            'name'       => 'Barang di Posko (Center)',
            'short_name' => 'Barang di Posko',
            'dashboard_label' => 'Total Barang di Posko',
            'filter_sql' => "status IN ('stored', 'registered', 'in_inspection', 'in_claim', 'ready_for_handover')",
            'explanation'=> "Barang di Posko Center adalah seluruh barang fisik temuan yang saat ini masih disimpan dan berada di bawah pengawasan Lost & Found Center (status: Tersimpan di Posko/stored, Sedang Diklaim/in_claim, Siap Diserahkan/ready_for_handover, Baru Didaftarkan/registered, atau Sedang Diperiksa/in_inspection). Barang yang sudah diambil pemilik (returned) tidak lagi dihitung berada di Posko.",
            'diff_note'  => "Dashboard Admin, Petugas, dan Chatbot C-Find menggunakan satu sumber kebenaran data yang sama: menghitung total seluruh barang fisik yang ada di Posko (mencakup status Tersimpan di Posko maupun Sedang Diklaim). Jika ingin melihat barang yang secara spesifik tersimpan di rak saja, gunakan metrik 'Barang Tersimpan di Rak'.",
        ],
        'stored_items' => [
            'name'       => 'Barang Tersimpan di Rak',
            'short_name' => 'Tersimpan di Rak',
            'dashboard_label' => 'Barang Tersimpan di Rak',
            'filter_sql' => "status = 'stored'",
            'explanation'=> "Barang Tersimpan di Rak adalah barang fisik temuan yang saat ini berada di rak penyimpanan gudang dan belum dalam proses serah terima atau pengembalian.",
        ],
        'claims' => [
            'name'       => 'Pengajuan Klaim',
            'short_name' => 'Klaim',
            'dashboard_label' => 'Total Pengajuan Klaim',
            'filter_sql' => "1=1",
            'explanation'=> "Pengajuan klaim mencakup seluruh riwayat tiket klaim kepemilikan barang temuan yang pernah diajukan oleh civitas. Istilah 'Antrean Klaim' secara spesifik hanya merujuk pada klaim yang sedang menunggu tindakan petugas (status: Menunggu Verifikasi/pending_verification atau Sedang Ditinjau/under_review).",
            'diff_note'  => "Total pengajuan klaim mencakup seluruh status (37 klaim), terdiri dari 18 Selesai, 2 Disetujui, dan 17 Ditutup Otomatis.",
        ],
        'reports' => [
            'name'       => 'Laporan Sistem',
            'short_name' => 'Laporan',
            'dashboard_label' => 'Total Laporan',
            'filter_sql' => "1=1",
            'explanation'=> "Total laporan sistem merupakan akumulasi dari Laporan Kehilangan (lost_reports) dan Laporan Temuan (found_reports) yang terdaftar di basis data.",
        ],
        'users' => [
            'name'       => 'Pengguna Terdaftar',
            'short_name' => 'Pengguna',
            'dashboard_label' => 'Total Pengguna',
            'filter_sql' => "1=1",
            'explanation'=> "Jumlah total akun pengguna yang terdaftar di sistem, mencakup Administrator, Petugas L&F, dan Civitas Kampus.",
        ],
        'login_activity' => [
            'name'       => 'Aktivitas Login',
            'short_name' => 'Login Hari Ini',
            'dashboard_label' => 'Aktivitas Login Pengguna',
            'filter_sql' => "action IN ('LOGIN_SUCCESS', 'auth_login', 'login', 'user_login')",
            'explanation'=> "Aktivitas login mencatat setiap keberhasilan autentikasi pengguna ke dalam sistem melalui tabel audit_logs dengan aksi LOGIN_SUCCESS berdasarkan zona waktu Asia/Jakarta (WIB).",
        ],
        'categories' => [
            'name'       => 'Kategori Barang',
            'short_name' => 'Kategori',
            'dashboard_label' => 'Kategori Barang',
            'filter_sql' => "is_active = 1",
            'explanation'=> "Klasifikasi kategori resmi barang kampus (misalnya Elektronik, Dokumen, Dompet, Kunci, dll.).",
        ],
        'locations' => [
            'name'       => 'Lokasi Kampus',
            'short_name' => 'Lokasi',
            'dashboard_label' => 'Lokasi Kampus',
            'filter_sql' => "1=1",
            'explanation'=> "Titik lokasi dan gedung kampus terdaftar sebagai referensi tempat barang hilang atau ditemukan.",
        ],
    ];

    /**
     * Hitung statistik barang fisik di Posko Center
     *
     * @return array{total: int, breakdown: array<string, int>, summary_text: string}
     */
    public function getCenterItemsStats(): array
    {
        $filterSql = self::getPoskoItemFilterSql();
        $stmt = $this->pdo->query("
            SELECT status, COUNT(*) AS cnt 
            FROM items 
            WHERE {$filterSql}
            GROUP BY status
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $breakdown = [];
        $total = 0;
        foreach ($rows as $r) {
            $st = (string)$r['status'];
            $c = (int)$r['cnt'];
            $breakdown[$st] = $c;
            $total += $c;
        }

        $parts = [];
        if (!empty($breakdown['stored'])) {
            $parts[] = "{$breakdown['stored']} Tersimpan di Posko";
        }
        if (!empty($breakdown['in_claim'])) {
            $parts[] = "{$breakdown['in_claim']} Sedang Diklaim";
        }
        if (!empty($breakdown['ready_for_handover'])) {
            $parts[] = "{$breakdown['ready_for_handover']} Siap Diserahkan";
        }
        if (!empty($breakdown['registered'])) {
            $parts[] = "{$breakdown['registered']} Baru Didaftarkan";
        }
        if (!empty($breakdown['in_inspection'])) {
            $parts[] = "{$breakdown['in_inspection']} Dalam Pemeriksaan";
        }

        $partsText = !empty($parts) ? implode(', ', $parts) : '';
        $summaryText = "{$total} barang di Posko Center" . (!empty($partsText) ? ": " . $partsText : ".");

        return [
            'total'        => $total,
            'breakdown'    => $breakdown,
            'parts_text'   => $partsText,
            'summary_text' => $summaryText,
        ];
    }

    /**
     * Hitung statistik barang temuan yang tersimpan aktif di rak gudang (status = 'stored')
     *
     * @return array{total: int, summary_text: string}
     */
    public function getStoredOnlyStats(): array
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS cnt FROM items WHERE status = 'stored'");
        $total = (int) $stmt->fetchColumn();

        return [
            'total'        => $total,
            'summary_text' => "{$total} barang tersimpan di rak",
        ];
    }

    /**
     * Hitung statistik klaim secara mendalam
     *
     * @return array{total: int, completed: int, approved: int, pending: int, closed_duplicate: int, breakdown: array<string, int>, summary_text: string}
     */
    public function getClaimsStats(): array
    {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) AS cnt FROM claims GROUP BY status");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $breakdown = [];
        $total = 0;
        foreach ($rows as $r) {
            $st = (string)$r['status'];
            $c = (int)$r['cnt'];
            $breakdown[$st] = $c;
            $total += $c;
        }

        $completed = $breakdown['completed'] ?? 0;
        $approved = $breakdown['approved'] ?? 0;
        $pending = ($breakdown['pending_verification'] ?? 0) + ($breakdown['under_review'] ?? 0);
        $closedDuplicate = $breakdown['closed_duplicate'] ?? 0;

        $parts = [];
        if ($completed > 0) $parts[] = "{$completed} Selesai";
        if ($approved > 0) $parts[] = "{$approved} Disetujui";
        if (!empty($breakdown['handover_scheduled'])) $parts[] = "{$breakdown['handover_scheduled']} Terjadwal Serah Terima";
        if ($pending > 0) $parts[] = "{$pending} Menunggu Verifikasi";
        if (!empty($breakdown['need_more_info'])) $parts[] = "{$breakdown['need_more_info']} Butuh Info Tambahan";
        if (!empty($breakdown['rejected'])) $parts[] = "{$breakdown['rejected']} Ditolak";
        if ($closedDuplicate > 0) $parts[] = "{$closedDuplicate} Ditutup Otomatis";

        $summaryText = "Total pengajuan klaim: {$total}" . (!empty($parts) ? " (" . implode(', ', $parts) . ")" : "");

        return [
            'total'            => $total,
            'completed'        => $completed,
            'approved'         => $approved,
            'pending'          => $pending,
            'closed_duplicate' => $closedDuplicate,
            'breakdown'        => $breakdown,
            'summary_text'     => $summaryText,
        ];
    }

    /**
     * Hitung statistik laporan hilang dan temuan
     *
     * @return array{total: int, lost: int, found: int, summary_text: string}
     */
    public function getReportsStats(): array
    {
        $lost = (int)$this->pdo->query("SELECT COUNT(*) FROM lost_reports")->fetchColumn();
        $found = (int)$this->pdo->query("SELECT COUNT(*) FROM found_reports")->fetchColumn();
        $total = $lost + $found;

        $summaryText = "Total laporan sistem: {$total} laporan ({$lost} Laporan Kehilangan, {$found} Laporan Temuan).";

        return [
            'total'        => $total,
            'lost'         => $lost,
            'found'        => $found,
            'summary_text' => $summaryText,
        ];
    }

    /**
     * Hitung statistik pengguna terdaftar
     */
    public function getUsersStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT r.name AS role_name, COUNT(*) AS cnt 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.deleted_at IS NULL
            GROUP BY r.name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $breakdown = [];
        $total = 0;
        foreach ($rows as $r) {
            $rn = (string)$r['role_name'];
            $c = (int)$r['cnt'];
            $breakdown[$rn] = $c;
            $total += $c;
        }

        $parts = [];
        if (!empty($breakdown['admin'])) $parts[] = "{$breakdown['admin']} Administrator";
        if (!empty($breakdown['petugas'])) $parts[] = "{$breakdown['petugas']} Petugas";
        if (!empty($breakdown['civitas'])) $parts[] = "{$breakdown['civitas']} Civitas";

        $summaryText = "Total pengguna terdaftar: {$total} akun" . (!empty($parts) ? " (" . implode(', ', $parts) . ")" : "");

        return [
            'total'        => $total,
            'breakdown'    => $breakdown,
            'summary_text' => $summaryText,
        ];
    }

    /**
     * Rekap pelapor (nama, jumlah hilang, jumlah temuan, total, laporan terakhir)
     *
     * @param int $limit
     * @return array<int, array{reporter_name: string, lost_count: int, found_count: int, total_count: int, latest_report_date: string}>
     */
    public function getReportersSummary(int $limit = 20): array
    {
        $sql = "
            SELECT 
                u.name AS reporter_name,
                COALESCE(lost_agg.cnt, 0) AS lost_count,
                COALESCE(found_agg.cnt, 0) AS found_count,
                (COALESCE(lost_agg.cnt, 0) + COALESCE(found_agg.cnt, 0)) AS total_count,
                GREATEST(
                    COALESCE(lost_agg.max_date, '1970-01-01 00:00:00'),
                    COALESCE(found_agg.max_date, '1970-01-01 00:00:00')
                ) AS latest_report_date
            FROM users u
            LEFT JOIN (
                SELECT reporter_user_id, COUNT(*) AS cnt, MAX(created_at) AS max_date 
                FROM lost_reports 
                GROUP BY reporter_user_id
            ) AS lost_agg ON u.id = lost_agg.reporter_user_id
            LEFT JOIN (
                SELECT reporter_user_id, COUNT(*) AS cnt, MAX(created_at) AS max_date 
                FROM found_reports 
                WHERE reporter_user_id IS NOT NULL 
                GROUP BY reporter_user_id
            ) AS found_agg ON u.id = found_agg.reporter_user_id
            WHERE (COALESCE(lost_agg.cnt, 0) + COALESCE(found_agg.cnt, 0)) > 0
            ORDER BY total_count DESC, latest_report_date DESC
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $dStr = $r['latest_report_date'] ?? null;
            $formattedDate = '-';
            if ($dStr && $dStr !== '1970-01-01 00:00:00') {
                $dt = new DateTimeImmutable($dStr, $this->timezone);
                $formattedDate = $dt->format('d/m/Y H:i') . ' WIB';
            }

            $result[] = [
                'reporter_name'      => (string)$r['reporter_name'],
                'lost_count'         => (int)$r['lost_count'],
                'found_count'        => (int)$r['found_count'],
                'total_count'        => (int)$r['total_count'],
                'latest_report_date' => $formattedDate,
            ];
        }

        return $result;
    }

    /**
     * Hitung aktivitas login hari ini (zona Asia/Jakarta)
     */
    public function getLoginStatsToday(): array
    {
        $now = new DateTimeImmutable('now', $this->timezone);
        $startToday = $now->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $endToday = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            SELECT a.id, COALESCE(u.name, 'Sistem') AS actor_name, u.email, a.created_at, a.action
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.action IN ('LOGIN_SUCCESS', 'auth_login', 'login', 'user_login')
            AND a.created_at BETWEEN ? AND ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$startToday, $endToday]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formattedRows = [];
        foreach ($rows as $r) {
            $dt = new DateTimeImmutable((string)$r['created_at']);
            $timeWib = $dt->setTimezone($this->timezone)->format('H:i') . ' WIB';
            $formattedRows[] = [
                'actor_name' => (string)$r['actor_name'],
                'email'      => (string)($r['email'] ?? '-'),
                'time'       => $timeWib,
                'status'     => 'Berhasil Masuk',
            ];
        }

        return [
            'total' => count($rows),
            'rows'  => $formattedRows,
            'date'  => $now->format('d/m/Y'),
        ];
    }

    /**
     * Jawab pertanyaan "kenapa/beda/maksudnya/dari mana angkanya" dari metadata definisi metrik
     *
     * @param string $norm
     * @return string|null
     */
    public function explainMetric(string $norm): ?string
    {
        // 1. Pertanyaan perbedaan angka barang di posko vs tersimpan di rak
        if (preg_match('/(?:kenapa|mengapa|kenape|kok).*(?:beda|berbeda).*(?:barang|posko|dashboard|rak)/iu', $norm) ||
            preg_match('/(?:kenapa|mengapa).*(?:dashboard).*(?:bukan|beda)/iu', $norm) ||
            str_contains($norm, 'kenapa barang di posko')) {
            $stats = $this->getCenterItemsStats();
            return "**Penjelasan Perbedaan & Definisi Metrik Barang Posko:**\n\n" .
                self::DEFINITIONS['posko_items']['diff_note'] . "\n\n" .
                "Kini Chatbot C-Find dan Dashboard Admin menggunakan **satu sumber kebenaran data yang sama** yaitu: **" . $stats['summary_text'] . "**.";
        }

        // 2. "Apa maksudnya barang di posko?" / "apa definisi barang di posko?"
        if (preg_match('/(?:apa maksud|maksudnya|definisi|pengertian|dari mana angkanya).*(?:barang di posko|posko center|barang posko)/iu', $norm)) {
            $stats = $this->getCenterItemsStats();
            return "**Definisi Metrik Barang di Posko (Center):**\n\n" .
                self::DEFINITIONS['posko_items']['explanation'] . "\n\n" .
                "Saat ini tercatat **" . $stats['summary_text'] . "**.";
        }

        // 3. "Apa maksudnya antrean klaim?" / "dari mana angka klaim?"
        if (preg_match('/(?:apa maksud|maksudnya|definisi|pengertian|dari mana angkanya).*(?:klaim|antrean klaim)/iu', $norm)) {
            $claimsStats = $this->getClaimsStats();
            return "**Definisi Metrik Pengajuan Klaim:**\n\n" .
                self::DEFINITIONS['claims']['explanation'] . "\n\n" .
                "Data saat ini: **" . $claimsStats['summary_text'] . "**.";
        }

        // 4. "Dari mana angka laporan?" / "apa maksudnya total laporan?"
        if (preg_match('/(?:apa maksud|maksudnya|definisi|pengertian|dari mana angkanya).*(?:laporan)/iu', $norm)) {
            $repStats = $this->getReportsStats();
            return "**Definisi Metrik Laporan Sistem:**\n\n" .
                self::DEFINITIONS['reports']['explanation'] . "\n\n" .
                "Data saat ini: **" . $repStats['summary_text'] . "**.";
        }

        return null;
    }

    /**
     * Dapatkan ringkasan statistik yang kompatibel dengan AdminController::dashboard()
     */
    public function getDashboardSummary(): array
    {
        $users = $this->getUsersStats()['total'];
        $reports = $this->getReportsStats();
        $items = $this->getCenterItemsStats()['total'];
        $claims = $this->getClaimsStats();

        return [
            'total_users'          => $users,
            'total_lost_reports'   => $reports['lost'],
            'total_found_reports'  => $reports['found'],
            'total_stored_items'   => $items, // 6 barang fisik di posko
            'total_claims'         => $claims['total'], // 37 total pengajuan klaim
            'total_returned_items' => $claims['completed'], // 18 klaim selesai
        ];
    }
}
