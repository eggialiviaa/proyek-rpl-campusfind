<?php

declare(strict_types=1);

namespace App\Services\CFind;

use App\Services\AiProviderInterface;
use App\Services\NvidiaNimService;
use App\Services\SettingService;
use Throwable;

/**
 * QueryPlanner — Perencana Rencana Query Semantik C-Find (Prompt 29)
 *
 * Menerjemahkan pertanyaan pengguna menjadi 1-3 query semantik terstruktur (JSON).
 * Memiliki fast-path regex untuk pola umum (0 token) dan AI Planner jika diperlukan.
 */
class QueryPlanner
{
    private SemanticSchema $schema;
    private ?AiProviderInterface $nimService;

    public function __construct(SemanticSchema $schema, ?AiProviderInterface $nimService = null)
    {
        $this->schema = $schema;
        $this->nimService = $nimService;
    }

    /**
     * Rencanakan query dari input pengguna
     *
     * @param string $userInput
     * @param string $role
     * @param array $recentHistory
     * @return array{success: bool, plan: ?array, error?: string, fast_path: bool, tokens_used: int}
     */
    public function plan(string $userInput, string $role, array $recentHistory = []): array
    {
        $clean = trim($userInput);
        $stripped = \App\Services\CFindService::stripGreetingPrefix($clean);
        $norm = mb_strtolower($stripped);

        // 1. FAST-PATH RULE & PATTERN MATCHING (0 TOKEN, CEPAT)
        $fastPlan = $this->matchFastPath($norm, $role);
        if ($fastPlan !== null) {
            return [
                'success'     => true,
                'plan'        => $fastPlan,
                'fast_path'   => true,
                'tokens_used' => 0,
                'branch'      => 'fast_path_rule',
            ];
        }

        // 2. AI PLANNER (Dengan retry 1x untuk timeout/5xx)
        $aiPlanResult = $this->planWithAi($clean, $role, $recentHistory);
        if ($aiPlanResult['success'] && !empty($aiPlanResult['plan'])) {
            return $aiPlanResult;
        }

        // 3. JATUH KE PENCOCOKAN ATURAN (FALLBACK RULE MATCHING)
        $fallbackPlan = $this->matchFallbackRule($norm, $role, $recentHistory);
        if ($fallbackPlan !== null) {
            return [
                'success'       => true,
                'plan'          => $fallbackPlan,
                'fast_path'     => true,
                'fallback_rule' => true,
                'tokens_used'   => $aiPlanResult['tokens_used'] ?? 0,
                'branch'        => 'fallback_rule_applied',
                'ai_failure'    => $aiPlanResult['branch'] ?? 'unknown_ai_failure',
            ];
        }

        // 4. HANYA BILA KEDUANYA GAGAL
        return $aiPlanResult;
    }

    /**
     * Fast-path deterministic rule matching
     */
    private function matchFastPath(string $norm, string $role): ?array
    {
        // ──────────────────────────────────────────────
        // GABUNGAN & TABEL
        // ──────────────────────────────────────────────
        if (str_contains($norm, 'berapa klaim hari ini dan siapa') || str_contains($norm, 'berapa klaim') && str_contains($norm, 'siapa saja')) {
            return [
                'format'      => 'combined',
                'explanation' => 'Jumlah klaim hari ini dan daftar pengajunya',
                'queries'     => [
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today']],
                        'aggregate' => 'count',
                        'label'     => 'Total klaim hari ini',
                    ],
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['claimant_name', 'claim_number', 'status'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today']],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                    ],
                ],
            ];
        }

        if (str_contains($norm, 'tabel barang di rak per kategori') || str_contains($norm, 'barang di rak per kategori')) {
            return [
                'format'      => 'table',
                'explanation' => 'Tabel jumlah barang di rak per kategori',
                'queries'     => [
                    [
                        'entity'    => 'posko_items',
                        'select'    => ['category_name'],
                        'aggregate' => 'count',
                        'group_by'  => ['category_name'],
                        'order'     => [['field' => 'count', 'direction' => 'DESC']],
                    ],
                ],
            ];
        }

        if (str_contains($norm, 'tabel pengguna per role') || str_contains($norm, 'pengguna per role')) {
            return [
                'format'      => 'table',
                'explanation' => 'Tabel pengguna per role',
                'queries'     => [
                    [
                        'entity'    => 'users',
                        'select'    => ['role_name'],
                        'aggregate' => 'count',
                        'group_by'  => ['role_name'],
                        'order'     => [['field' => 'count', 'direction' => 'DESC']],
                    ],
                ],
            ];
        }

        if (str_contains($norm, 'bandingkan laporan hilang vs temuan minggu ini') || str_contains($norm, 'laporan hilang vs temuan')) {
            return [
                'format'      => 'combined',
                'explanation' => 'Perbandingan laporan hilang dan temuan minggu ini',
                'queries'     => [
                    [
                        'entity'    => 'lost_reports',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'this_week']],
                        'aggregate' => 'count',
                        'label'     => 'Total Laporan Kehilangan Minggu Ini',
                    ],
                    [
                        'entity'    => 'found_reports',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'this_week']],
                        'aggregate' => 'count',
                        'label'     => 'Total Laporan Temuan Minggu Ini',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // METRIK KLAIM
        // ──────────────────────────────────────────────

        // 1. Berapa yang selesai klaim (klaim selesai / tuntas)
        if (preg_match('/(?:berapa|jumlah|total).*(?:selesai|tuntas).*(?:klaim)/u', $norm) ||
            preg_match('/(?:berapa|jumlah|total).*(?:klaim).*(?:selesai|tuntas)/u', $norm) ||
            str_contains($norm, 'selesai klaim') ||
            str_contains($norm, 'klaim selesai')) {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah klaim yang berstatus selesai',
                'queries'     => [
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'status', 'operator' => '=', 'value' => 'completed']],
                        'aggregate' => 'count',
                        'label'     => 'Klaim Selesai',
                    ],
                ],
            ];
        }

        // 2. Klaim hari ini
        if (preg_match('/(?:klaim|pengajuan).*(?:hari ini)/u', $norm) || preg_match('/(?:berapa|jumlah).*(?:klaim).*hari ini/u', $norm)) {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah pengajuan klaim hari ini',
                'queries'     => [
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today']],
                        'aggregate' => 'count',
                        'label'     => 'Klaim Hari Ini',
                    ],
                ],
            ];
        }

        // 3. Total seluruh klaim
        if (preg_match('/(?:total|berapa|jumlah|hitung).*(?:pengajuan klaim|total klaim|semua klaim|seluruh.*klaim|klaim)/u', $norm) || 
            str_contains($norm, 'total pengajuan klaim') || 
            str_contains($norm, 'total klaim') ||
            str_contains($norm, 'seluruh pengajuan klaim')) {
            return [
                'format'      => 'number',
                'explanation' => 'Total pengajuan klaim di sistem',
                'queries'     => [
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Total Pengajuan Klaim',
                    ],
                ],
            ];
        }

        // 4. Daftar pengaju klaim
        if (preg_match('/^(?:siapa saja|siapa aja|daftar|list).*(?:klaim|mengajukan)/u', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar pengaju klaim',
                'queries'     => [
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['claim_number', 'claimant_name', 'status', 'created_at'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                        'label'     => 'Daftar Pengajuan Klaim',
                    ],
                ],
            ];
        }

        if (preg_match('/(?:klaim saya|status klaim saya|status pengajuan klaim)/u', $norm)) {
            return [
                'format'      => 'combined',
                'explanation' => 'Status dan jumlah klaim milik saya',
                'queries'     => [
                    [
                        'entity'    => 'my_claims',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                    ],
                    [
                        'entity'    => 'my_claims',
                        'select'    => ['claim_number', 'status', 'created_at'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // MASTER RAK & LEMARI PENYIMPANAN (storage_locations)
        // ──────────────────────────────────────────────
        if (preg_match('/(?:berapa|jumlah|total).*(?:lemari|rak simpan|rak penyimpanan|tempat simpan)/u', $norm) || 
            str_contains($norm, 'berapa lemari') || 
            str_contains($norm, 'berapa rak') ||
            str_contains($norm, 'lemari dan rak')) {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah lemari dan rak penyimpanan posko',
                'queries'     => [
                    [
                        'entity'    => 'storage_locations',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Lemari dan Rak Penyimpanan',
                    ],
                ],
            ];
        }

        if (preg_match('/(?:apa saja|daftar|list|rincian).*(?:lemari|rak simpan|rak penyimpanan|tempat simpan)/u', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar lemari dan rak penyimpanan posko',
                'queries'     => [
                    [
                        'entity'    => 'storage_locations',
                        'select'    => ['code', 'name', 'zone', 'capacity', 'is_active'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'code', 'direction' => 'ASC']],
                        'limit'     => 50,
                        'label'     => 'Daftar Lemari dan Rak Penyimpanan',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // BARANG TERSIMPAN DI RAK (STORED ONLY)
        // ──────────────────────────────────────────────
        if (preg_match('/(?:list|daftar|apa saja|apa aja).*barang.*(?:tersimpan|rak)/u', $norm) && !str_contains($norm, 'posko')) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar barang tersimpan di rak gudang',
                'queries'     => [
                    [
                        'entity'    => 'stored_items',
                        'select'    => ['item_code', 'title', 'category_name', 'status'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'registered_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                        'label'     => 'Barang Tersimpan di Rak',
                    ],
                ],
            ];
        }

        if (preg_match('/(?:berapa).*(?:barang).*(?:tersimpan|rak)/u', $norm) || 
            str_contains($norm, 'tersimpan di rak') || 
            $norm === 'barang tersimpan di rak' || 
            $norm === 'barang di rak') {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah barang tersimpan di rak gudang',
                'queries'     => [
                    [
                        'entity'    => 'stored_items',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Barang Tersimpan di Rak',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // BARANG DI POSKO / TOTAL PHYSICAL INVENTORY
        // ──────────────────────────────────────────────
        if (preg_match('/(?:list|daftar|apa saja|apa aja).*barang.*(?:posko|center)/u', $norm) || $norm === 'list barang yang ada di posko' || $norm === 'daftar barang posko') {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar barang di posko center',
                'queries'     => [
                    [
                        'entity'    => 'posko_items',
                        'select'    => ['item_code', 'title', 'category_name', 'status'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'registered_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                        'label'     => 'Barang di Posko Center',
                    ],
                ],
            ];
        }

        if (preg_match('/(?:berapa).*(?:barang).*(?:posko|center)/u', $norm) || 
            str_contains($norm, 'barang di posko') || 
            $norm === 'barang posko' || 
            $norm === 'total barang di posko') {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah barang fisik di posko center',
                'queries'     => [
                    [
                        'entity'    => 'posko_items',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Barang di Posko Center',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // KATALOG PUBLIK (Cari Barang)
        // ──────────────────────────────────────────────
        if (preg_match('/(?:berapa).*(?:katalog publik|barang.*katalog|laporan publik)/u', $norm)) {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah laporan aktif di katalog publik',
                'queries'     => [
                    [
                        'entity'    => 'public_catalog',
                        'select'    => ['title'],
                        'aggregate' => 'count',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // PENGGUNA TERDAFTAR (ADMIN)
        // ──────────────────────────────────────────────
        if (preg_match('/(?:total|berapa|jumlah|hitung).*(?:pengguna|user)/iu', $norm) ||
            str_contains($norm, 'total pengguna') || str_contains($norm, 'total user') ||
            $norm === 'total pengguna' || $norm === 'total pengguna?') {
            return [
                'format'      => 'number',
                'explanation' => 'Total pengguna terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'users',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'pengguna terdaftar',
                    ],
                ],
            ];
        }

        if (preg_match('/(?:siapa saja|siapa aja|daftar|list).*(?:terdaftar|pengguna|user)/u', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar pengguna terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'users',
                        'select'    => ['name', 'role_name', 'email', 'is_active', 'created_at'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                    ],
                ],
            ];
        }

        if (preg_match('/(?:siapa saja|siapa aja|daftar).*(?:login.*hari ini)/u', $norm) || str_contains($norm, 'login hari ini')) {
            return [
                'format'      => 'table',
                'explanation' => 'Aktivitas pengguna yang login hari ini',
                'queries'     => [
                    [
                        'entity'    => 'login_activity',
                        'select'    => ['actor_name', 'action', 'created_at'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today']],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                        'limit'     => 50,
                        'label'     => 'Aktivitas Login Hari Ini',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // LAPORAN
        // ──────────────────────────────────────────────
        if (preg_match('/(?:total|berapa|jumlah|hitung).*(?:total laporan|semua laporan|seluruh laporan|laporan)/iu', $norm) ||
            str_contains($norm, 'total laporan') || $norm === 'total laporan' || $norm === 'total laporan?') {
            return [
                'format'      => 'combined',
                'explanation' => 'Total semua laporan',
                'queries'     => [
                    [
                        'entity'    => 'lost_reports',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Kehilangan',
                    ],
                    [
                        'entity'    => 'found_reports',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Temuan',
                    ],
                ],
            ];
        }

        if (preg_match('/(?:apa.*laporan terakhir|laporan terakhir)/u', $norm)) {
            return [
                'format'      => 'list',
                'explanation' => 'Laporan terakhir',
                'queries'     => [
                    [
                        'entity'    => 'lost_reports',
                        'select'    => ['report_number', 'title', 'category_name', 'created_at'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                        'limit'     => 1,
                    ],
                ],
            ];
        }

        if (preg_match('/(?:berapa).*(?:laporan hari ini)/u', $norm) || $norm === 'laporan hari ini?' || $norm === 'laporan hari ini') {
            return [
                'format'      => 'combined',
                'explanation' => 'Laporan masuk hari ini',
                'queries'     => [
                    [
                        'entity'    => 'lost_reports',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today']],
                        'aggregate' => 'count',
                        'label'     => 'Kehilangan',
                    ],
                    [
                        'entity'    => 'found_reports',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today']],
                        'aggregate' => 'count',
                        'label'     => 'Temuan',
                    ],
                ],
            ];
        }

        if (str_contains($norm, 'perlu ditinjau') || str_contains($norm, 'menunggu verifikasi')) {
            return [
                'format'      => 'combined',
                'explanation' => 'Laporan dan klaim yang menunggu peninjauan',
                'queries'     => [
                    [
                        'entity'    => 'claims_queue',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'status', 'operator' => '=', 'value' => 'pending_verification']],
                        'aggregate' => 'count',
                        'label'     => 'Klaim Menunggu Verifikasi',
                    ],
                    [
                        'entity'    => 'found_reports',
                        'select'    => ['id'],
                        'filters'   => [['field' => 'status', 'operator' => '=', 'value' => 'pending_verification']],
                        'aggregate' => 'count',
                        'label'     => 'Laporan Temuan Menunggu Verifikasi',
                    ],
                ],
            ];
        }

        // ──────────────────────────────────────────────
        // MASTER DATA (Kategori & Lokasi) (Prompt 29D)
        // ──────────────────────────────────────────────
        // 1. Lokasi Majemuk (Berapa dan apa saja)
        if ((str_contains($norm, 'berapa') && (str_contains($norm, 'apa saja') || str_contains($norm, 'siapa saja') || str_contains($norm, 'daftar'))) && str_contains($norm, 'lokasi')) {
            return [
                'format'      => 'combined',
                'explanation' => 'Jumlah dan daftar lokasi kampus terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'locations',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Lokasi Kampus',
                    ],
                    [
                        'entity'    => 'locations',
                        'select'    => ['name', 'building_name', 'is_active'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                        'label'     => 'Daftar Lokasi Kampus',
                    ],
                ],
            ];
        }

        // 2. Lokasi Hitungan Saja ("ada berapa lokasi?") -> HANYA angka, tanpa tabel
        if (preg_match('/(?:berapa|jumlah|total).*(?:lokasi|gedung kampus)/u', $norm) || str_contains($norm, 'ada berapa lokasi')) {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah lokasi kampus terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'locations',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Lokasi Kampus',
                    ],
                ],
            ];
        }

        // 3. Lokasi Daftar Saja ("apa saja lokasi?", "daftar lokasi kampus") -> Tabel 50 baris
        if (preg_match('/(?:apa saja|daftar|list|sebutkan).*(?:lokasi|gedung kampus)/u', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar lokasi kampus terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'locations',
                        'select'    => ['name', 'building_name', 'is_active'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                        'label'     => 'Daftar Lokasi Kampus',
                    ],
                ],
            ];
        }

        // 4. Kategori Majemuk (Berapa dan apa saja)
        if ((str_contains($norm, 'berapa') && (str_contains($norm, 'apa saja') || str_contains($norm, 'daftar'))) && str_contains($norm, 'kategori')) {
            return [
                'format'      => 'combined',
                'explanation' => 'Jumlah dan daftar kategori barang terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'categories',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Kategori Barang',
                    ],
                    [
                        'entity'    => 'categories',
                        'select'    => ['name', 'description', 'is_active'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                        'label'     => 'Daftar Kategori Barang',
                    ],
                ],
            ];
        }

        // 5. Kategori Hitungan Saja ("ada berapa kategori?") -> HANYA angka, tanpa tabel
        if (preg_match('/(?:berapa|jumlah|total).*(?:kategori)/u', $norm) || str_contains($norm, 'ada berapa kategori')) {
            return [
                'format'      => 'number',
                'explanation' => 'Jumlah kategori barang terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'categories',
                        'select'    => ['id'],
                        'aggregate' => 'count',
                        'label'     => 'Kategori Barang',
                    ],
                ],
            ];
        }

        // 6. Kategori Daftar Saja ("apa saja kategori?", "daftar kategori") -> Tabel 50 baris
        if (preg_match('/(?:apa saja|daftar|list|sebutkan).*(?:kategori)/u', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar kategori barang terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'categories',
                        'select'    => ['name', 'description', 'is_active'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                        'label'     => 'Daftar Kategori Barang',
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * AI Planner: Panggil AI kecil hanya untuk menyusun rencana JSON tanpa data baris
     * Didukung retry 1x bila timeout atau status 5xx.
     */
    private function planWithAi(string $userInput, string $role, array $recentHistory = []): array
    {
        if ($this->nimService === null || !$this->nimService->isChatAvailable()) {
            return [
                'success'     => false,
                'plan'        => null,
                'error'       => 'Layanan AI perencana tidak tersedia.',
                'branch'      => 'provider_unavailable',
                'fast_path'   => false,
                'tokens_used' => 0,
            ];
        }

        $schemaDesc = $this->schema->getRoleSchemaPromptDescription($role);

        $systemPrompt = <<<PROMPT
Anda adalah "C-Find Semantic Query Planner".
Tugas Anda HANYA SATU: Menganalisis pertanyaan pengguna dan menerjemahkannya menjadi JSON rencana query terstruktur.
DILARANG menulis SQL. DILARANG mengarang entitas atau kolom di luar skema.
JANGAN mengirim data hasil. JANGAN menjawab dalam teks bebas. Output HANYA JSON valid.

SKEMA ROLE ("{$role}"):
{$schemaDesc}

FORMAT JSON OUTPUT:
{
  "format": "number" | "list" | "table" | "combined",
  "explanation": "penjelasan singkat",
  "queries": [
    {
      "entity": "nama_entity",
      "select": ["field1", "field2"],
      "filters": [
        {"field": "field_name", "operator": "=" | "!=" | "IN" | "date_range", "value": "nilai" | "today" | "this_week"}
      ],
      "aggregate": "count" | "none",
      "group_by": ["field1"],
      "order": [{"field": "field1", "direction": "ASC" | "DESC"}],
      "limit": 10
    }
  ]
}
Maksimal 3 query. Jika pertanyaan tidak dapat dijawab dari skema, hasilkan: {"queries":[], "format":"unsupported", "explanation":"di luar skema"}.
PROMPT;

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // Masukkan 4 pesan terakhir riwayat dengan sanitasi (mencegah PHP Warning Array to string conversion)
        $slice = array_slice($recentHistory, -4);
        foreach ($slice as $h) {
            $contentStr = '';
            $rawC = $h['content'] ?? '';
            if (is_array($rawC)) {
                $parts = [];
                if (isset($rawC['type']) && $rawC['type'] === 'table') {
                    $parts[] = "[Tabel data: " . count($rawC['rows'] ?? []) . " baris]";
                } else {
                    foreach ($rawC as $item) {
                        if (is_array($item) && ($item['type'] ?? '') === 'table') {
                            $parts[] = "[Tabel data: " . count($item['rows'] ?? []) . " baris]";
                        } elseif (is_array($item) && isset($item['content'])) {
                            $parts[] = (string)$item['content'];
                        } elseif (is_string($item)) {
                            $parts[] = $item;
                        }
                    }
                }
                $contentStr = implode(' ', $parts);
            } else {
                $contentStr = (string)$rawC;
            }

            $messages[] = [
                'role'    => ($h['role'] ?? '') === 'user' ? 'user' : 'assistant',
                'content' => mb_substr($contentStr, 0, 150),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userInput];

        // Panggilan AI dengan Retry 1x untuk timeout atau status 5xx
        $aiRes = null;
        $attempt = 1;
        $maxAttempts = 2;
        $tokensUsed = 0;
        $lastBranch = 'unknown_failure';

        while ($attempt <= $maxAttempts) {
            try {
                $aiRes = $this->nimService->chat($messages, [
                    'max_tokens'  => 400,
                    'temperature' => 0.0,
                ]);
            } catch (Throwable $e) {
                error_log("[QueryPlanner] Exception during AI chat: " . $e->getMessage());
                $aiRes = [
                    'success'       => false,
                    'content'       => null,
                    'finish_reason' => 'exception',
                    'diagnostic'    => 'provider_exception',
                    'error'         => $e->getMessage(),
                    'status_code'   => 500,
                ];
            }

            $tokensUsed += (int)($aiRes['usage']['total_tokens'] ?? 0);
            $statusCode = (int)($aiRes['status_code'] ?? 0);
            $isTimeout = (($aiRes['finish_reason'] ?? '') === 'timeout' || str_contains(strtolower($aiRes['error'] ?? ''), 'timeout'));
            $is5xx = ($statusCode >= 500 && $statusCode <= 599);

            if (!empty($aiRes['success']) && !empty($aiRes['content'])) {
                break;
            }

            // Klasifikasi diagnosa
            if ($isTimeout) {
                $lastBranch = ($attempt === 1) ? 'planner_retry_timeout' : 'planner_timeout';
            } elseif ($is5xx) {
                $lastBranch = ($attempt === 1) ? 'planner_retry_5xx' : "planner_http_{$statusCode}";
            } elseif ($statusCode === 429) {
                $lastBranch = 'planner_http_429';
                break; // Rate limit jangan di-retry langsung tanpa jeda
            } elseif ($statusCode === 403) {
                $lastBranch = 'planner_http_403';
                break; // Auth error
            } else {
                $lastBranch = $aiRes['diagnostic'] ?? ($aiRes['finish_reason'] ?? 'planner_error');
                break;
            }

            if ($attempt < $maxAttempts && ($isTimeout || $is5xx)) {
                $attempt++;
                usleep(200000); // 200ms jeda sebelum retry
                continue;
            }

            break;
        }

        if (empty($aiRes) || empty($aiRes['content'])) {
            return [
                'success'     => false,
                'plan'        => null,
                'error'       => 'AI Planner tidak memberikan respons.',
                'branch'      => $lastBranch,
                'fast_path'   => false,
                'tokens_used' => $tokensUsed,
            ];
        }

        $rawContent = trim($aiRes['content']);

        // Ekstrak JSON
        $jsonStr = $rawContent;
        if (preg_match('/\{[\s\S]*\}/', $rawContent, $m)) {
            $jsonStr = $m[0];
        }

        $decoded = json_decode($jsonStr, true);
        if (!is_array($decoded) || !isset($decoded['queries'])) {
            return [
                'success'     => false,
                'plan'        => null,
                'error'       => 'Format respons AI Planner tidak valid JSON.',
                'branch'      => 'planner_invalid_json',
                'fast_path'   => false,
                'tokens_used' => $tokensUsed,
            ];
        }

        return [
            'success'     => true,
            'plan'        => $decoded,
            'fast_path'   => false,
            'tokens_used' => $tokensUsed,
            'branch'      => 'ai_planner_success',
        ];
    }

    /**
     * Fallback pencocokan aturan semantik jika perencana AI gagal (timeout/429/403/5xx/rusak)
     */
    private function matchFallbackRule(string $norm, string $role, array $recentHistory = []): ?array
    {
        // 1. Lokasi Kampus
        if (preg_match('/\b(lokasi|gedung|tempat|sebutkan.*lokasi|daftar.*lokasi)\b/iu', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar lokasi kampus terdaftar',
                'queries'     => [
                    [
                        'entity'    => 'locations',
                        'select'    => ['name', 'building_name'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                    ],
                ],
            ];
        }

        // 2. Siapa yang melapor / pelapor laporan
        if (preg_match('/\b(siapa.*lapor|yang melapor|melakukan laporan|pelapor|pembuat laporan)\b/iu', $norm)) {
            if ($role === 'admin' || $role === 'petugas') {
                return [
                    'format'      => 'table',
                    'explanation' => 'Daftar pelapor laporan hilang dan temuan',
                    'queries'     => [
                        [
                            'entity'    => 'lost_reports',
                            'select'    => ['reporter_name'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Nama Pelapor',
                        ],
                        [
                            'entity'    => 'found_reports',
                            'select'    => ['reporter_name'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Nama Penemu',
                        ],
                    ],
                ];
            }
        }

        // 3. Kategori barang
        if (preg_match('/\b(kategori|jenis barang)\b/iu', $norm)) {
            return [
                'format'      => 'table',
                'explanation' => 'Daftar kategori barang',
                'queries'     => [
                    [
                        'entity'    => 'categories',
                        'select'    => ['name', 'description'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                    ],
                ],
            ];
        }

        // 4. Pengguna / User (Admin)
        if (preg_match('/\b(pengguna|user|civitas|petugas|daftar akun)\b/iu', $norm)) {
            if ($role === 'admin') {
                return [
                    'format'      => 'table',
                    'explanation' => 'Daftar pengguna terdaftar',
                    'queries'     => [
                        [
                            'entity'    => 'users',
                            'select'    => ['name', 'email', 'role_name', 'is_active', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                        ],
                    ],
                ];
            }
        }

        // 5. Klaim
        if (preg_match('/\b(klaim|pengajuan)\b/iu', $norm)) {
            if ($role === 'admin' || $role === 'petugas') {
                return [
                    'format'      => 'table',
                    'explanation' => 'Daftar pengajuan klaim',
                    'queries'     => [
                        [
                            'entity'    => 'claims_queue',
                            'select'    => ['claim_number', 'claimant_name', 'status', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                        ],
                    ],
                ];
            } elseif ($role === 'civitas') {
                return [
                    'format'      => 'table',
                    'explanation' => 'Daftar pengajuan klaim saya',
                    'queries'     => [
                        [
                            'entity'    => 'my_claims',
                            'select'    => ['claim_number', 'status', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                        ],
                    ],
                ];
            }
        }

        // 6. Barang di Posko / Rak / Temuan
        if (preg_match('/\b(barang|posko|rak|temuan)\b/iu', $norm)) {
            if ($role === 'admin' || $role === 'petugas') {
                $targetEntity = (str_contains($norm, 'tersimpan') || (str_contains($norm, 'rak') && !str_contains($norm, 'posko'))) ? 'stored_items' : 'posko_items';
                $targetLabel = $targetEntity === 'stored_items' ? 'Barang Tersimpan di Rak' : 'Barang di Posko Center';
                return [
                    'format'      => 'table',
                    'explanation' => 'Daftar ' . strtolower($targetLabel),
                    'queries'     => [
                        [
                            'entity'    => $targetEntity,
                            'select'    => ['item_code', 'title', 'category_name', 'status'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'registered_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => $targetLabel,
                        ],
                    ],
                ];
            } elseif ($role === 'civitas' || $role === 'guest') {
                return [
                    'format'      => 'table',
                    'explanation' => 'Katalog barang temuan publik',
                    'queries'     => [
                        [
                            'entity'    => 'public_catalog',
                            'select'    => ['title', 'category_name', 'status', 'found_date'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                        ],
                    ],
                ];
            }
        }

        // 7. Pertanyaan Lanjutan Terbuka ("apa saja coba?", "apa saja", "coba apa saja")
        if (preg_match('/\b(apa saja|apa aja|coba sebutkan|sebutkan apa saja)\b/iu', $norm)) {
            if ($role === 'admin' || $role === 'petugas') {
                return [
                    'format'      => 'table',
                    'explanation' => 'Daftar barang di posko center',
                    'queries'     => [
                        [
                            'entity'    => 'posko_items',
                            'select'    => ['item_code', 'title', 'category_name', 'status'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'registered_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                        ],
                    ],
                ];
            }
            return [
                'format'      => 'table',
                'explanation' => 'Daftar kategori barang',
                'queries'     => [
                    [
                        'entity'    => 'categories',
                        'select'    => ['name', 'description'],
                        'aggregate' => 'none',
                        'order'     => [['field' => 'name', 'direction' => 'ASC']],
                        'limit'     => 50,
                    ],
                ],
            ];
        }

        return null;
    }
}
