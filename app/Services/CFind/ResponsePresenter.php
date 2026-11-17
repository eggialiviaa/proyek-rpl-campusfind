<?php

declare(strict_types=1);

namespace App\Services\CFind;

/**
 * ResponsePresenter — Penyaji Jawaban Deterministik C-Find Data Agent (Prompt 29)
 *
 * Mengonversi hasil query database menjadi format jawaban singkat & tepat:
 * 1. Satu angka: Satu kalimat yang menyebut metriknya ("Total pengguna terdaftar: 12.").
 * 2. Daftar/tabel: Tabel HTML semantik (di-escape) dengan scroll horizontal dalam widget.
 * 3. Pertanyaan gabungan: Angka terlebih dahulu kemudian tabel/daftar.
 * 4. Kosong: "Tidak ada data untuk <permintaan>."
 * 5. Tolak karena hak: Sebutkan bahwa data di luar hak role.
 * 6. Tidak ada basa-basi atau ringkasan yang tidak diminta.
 */
class ResponsePresenter
{
    /**
     * Sajikan respons akhir dari rencana dan hasil eksekusi query
     *
     * @param array $plan
     * @param array $queryResults
     * @param string $userInput
     * @param string $role
     * @return string
     */
    public function present(array $plan, array $queryResults, string $userInput, string $role): array|string
    {
        if (empty($queryResults)) {
            return "Tidak ada data untuk permintaan tersebut.";
        }

        $normInput = mb_strtolower($userInput);

        // Kasus Khusus: Agregasi ganda yang merupakan total laporan (Kehilangan + Temuan)
        $allCounts = true;
        $totalSum = 0;
        $countParts = [];
        foreach ($queryResults as $res) {
            if (!($res['aggregate'] === 'count' && isset($res['count']))) {
                $allCounts = false;
                break;
            }
            $c = (int)$res['count'];
            $totalSum += $c;
            $countParts[] = "{$res['label']}: {$c}";
        }

        if ($allCounts && count($queryResults) > 1 && (str_contains($normInput, 'total laporan') || str_contains($normInput, 'laporan hari ini') || str_contains($normInput, 'laporan'))) {
            if (str_contains($normInput, 'per status') || str_contains($normInput, 'rekap') || str_contains($normInput, 'rincian') || str_contains($normInput, 'bandingkan') || str_contains($normInput, 'vs') || str_contains($normInput, 'komparasi')) {
                $statusSummary = implode(', ', $countParts);
                if (str_contains($normInput, 'hari ini')) {
                    return "Total laporan masuk hari ini: **{$totalSum}** ({$statusSummary}).";
                }
                return "Total laporan terdaftar: **{$totalSum}** ({$statusSummary}).";
            }
            if (str_contains($normInput, 'hari ini')) {
                return "Total laporan masuk hari ini: **{$totalSum}**.";
            }
            return "Total laporan terdaftar: **{$totalSum}**.";
        }

        $blocks = [];
        $hasTable = false;

        foreach ($queryResults as $res) {
            $label = $res['label'] ?? 'Data';
            $isCount = ($res['aggregate'] === 'count' && isset($res['count']));
            $rows = $res['rows'] ?? [];

            if ($isCount) {
                $count = (int)$res['count'];
                $blocks[] = [
                    'type'    => 'text',
                    'content' => $this->formatCountSentence($label, $count),
                ];
                continue;
            }

            if (empty($rows)) {
                $blocks[] = [
                    'type'    => 'text',
                    'content' => "Tidak ada data untuk {$label}.",
                ];
                continue;
            }

            // Prefix jika kueri laporan terakhir
            if (str_contains($normInput, 'laporan terakhir') && count($rows) === 1) {
                $blocks[] = [
                    'type'    => 'text',
                    'content' => "Berikut laporan terakhir yang tercatat di sistem:",
                ];
            }

            // Bangun struktur data tabel terstruktur
            $tableBlock = $this->buildTableBlock($res, $normInput);
            if ($tableBlock !== null) {
                $blocks[] = $tableBlock;
                $hasTable = true;
            }
        }

        // Jika tidak ada tabel sama sekali, kembalikan teks biasa
        if (!$hasTable) {
            $textParts = [];
            foreach ($blocks as $b) {
                if (isset($b['content'])) {
                    $textParts[] = $b['content'];
                }
            }
            return implode("\n\n", $textParts);
        }

        return self::validateReplyConsistency($blocks);
    }

    /**
     * Validator konsistensi teks dan tabel (Prompt 35C):
     * Jawaban "tidak ada" atau "belum ada laporan" TIDAK BOLEH disertai tabel berisi baris!
     * Begitu pula tabel kosong tidak boleh dimunculkan jika teks menyatakan data kosong.
     *
     * @param array|string $reply
     * @return array|string
     */
    public static function validateReplyConsistency(array|string $reply): array|string
    {
        if (is_string($reply)) {
            return $reply;
        }

        if (!is_array($reply)) {
            return $reply;
        }

        $hasEmptyStatement = false;
        $tableRowCounts = 0;
        $textBlockIndexes = [];

        foreach ($reply as $idx => $block) {
            if (isset($block['type']) && $block['type'] === 'text' && isset($block['content'])) {
                $textLower = mb_strtolower((string)$block['content']);
                if (preg_match('/\b(tidak ada data|tidak ada laporan|belum ada laporan|tidak ada yang|tidak ada)\b/iu', $textLower)) {
                    $hasEmptyStatement = true;
                    $textBlockIndexes[] = $idx;
                }
            } elseif (isset($block['type']) && $block['type'] === 'table' && !empty($block['rows'])) {
                $tableRowCounts += count($block['rows']);
            }
        }

        // VALIDATOR 1: Jawaban "tidak ada" TIDAK BOLEH disertai tabel berisi baris
        if ($tableRowCounts > 0 && $hasEmptyStatement) {
            // Hapus blok teks kontradiktif "tidak ada..."
            foreach ($textBlockIndexes as $delIdx) {
                unset($reply[$delIdx]);
            }
            $reply = array_values($reply);
        }

        // VALIDATOR 2: Hapus blok tabel kosong (0 baris)
        foreach ($reply as $idx => $block) {
            if (isset($block['type']) && $block['type'] === 'table' && empty($block['rows'])) {
                unset($reply[$idx]);
            }
        }

        $reply = array_values($reply);
        if (count($reply) === 1 && isset($reply[0]['type']) && $reply[0]['type'] === 'text') {
            return $reply[0]['content'];
        }

        return $reply;
    }

    /**
     * Format kalimat metrik tunggal tepat 1 kalimat berisi angka (Prompt 29D)
     * "Ada berapa X?" → HANYA satu kalimat berisi angka ("Total klaim: 37.").
     * Tanpa tabel, tanpa rincian per status, tanpa penjelasan, kecuali diminta.
     */
    private function formatCountSentence(string $label, int $count): string
    {
        $norm = mb_strtolower($label);
        $metricService = new \App\Services\MetricDefinitionService();

        // Status Filter Khusus Klaim / Entitas Lain (Prompt 29E)
        if (str_contains($norm, 'berstatus') || str_contains($norm, 'status')) {
            return "{$label}: **{$count}**.";
        }

        // 1. Barang Tersimpan di Rak Gudang (HANYA status stored)
        if (str_contains($norm, 'tersimpan') || str_contains($norm, 'rak gudang') || str_contains($norm, 'tersimpan di rak')) {
            $storedStats = $metricService->getStoredOnlyStats();
            $cnt = $storedStats['total'] ?? $count;
            return "Total barang tersimpan di rak: **{$cnt}** barang.";
        }

        // 2. Barang di Posko Center (HANYA kalimat angka tunggal, tanpa teks ganda)
        if (str_contains($norm, 'posko') || str_contains($norm, 'rak center')) {
            $poskoStats = $metricService->getCenterItemsStats();
            $cnt = $poskoStats['total'] ?? $count;
            return "Total barang di Posko Center: **{$cnt}** barang.";
        }

        // 2. Klaim Selesai
        if (str_contains($norm, 'klaim selesai') || str_contains($norm, 'selesai klaim')) {
            $claimsStats = $metricService->getClaimsStats();
            $cnt = $claimsStats['completed'] ?? $count;
            return "Total klaim selesai: **{$cnt}**.";
        }

        // 3. Total Pengajuan Klaim (HANYA angka tunggal, tanpa rincian kurung ganda)
        if (str_contains($norm, 'pengajuan klaim') || str_contains($norm, 'total klaim')) {
            $claimsStats = $metricService->getClaimsStats();
            $cnt = $claimsStats['total'] ?? $count;
            return "Total klaim: **{$cnt}**.";
        }

        if (str_contains($norm, 'antrean klaim') || str_contains($norm, 'menunggu verifikasi')) {
            $claimsStats = $metricService->getClaimsStats();
            $cnt = $claimsStats['pending'] ?? $count;
            return "Total antrean klaim yang menunggu verifikasi: **{$cnt}** klaim.";
        }

        if (str_contains($norm, 'lemari') || str_contains($norm, 'rak penyimpanan') || str_contains($norm, 'tempat simpan')) {
            return "Total lemari dan rak penyimpanan: **{$count}**.";
        }

        if (str_contains($norm, 'pengguna sistem') || str_contains($norm, 'pengguna terdaftar') || str_contains($norm, 'total pengguna')) {
            return "Total pengguna terdaftar: **{$count}**.";
        }
        if (str_contains($norm, 'lokasi') || str_contains($norm, 'gedung kampus')) {
            return "Total lokasi kampus: **{$count}**.";
        }
        if (str_contains($norm, 'kategori')) {
            return "Total kategori barang: **{$count}**.";
        }
        if (str_contains($norm, 'katalog publik')) {
            return "Total barang di katalog publik: **{$count}**.";
        }
        if (str_contains($norm, 'klaim hari ini')) {
            return "Total klaim hari ini: **{$count}**.";
        }

        if (str_starts_with($norm, 'total') || str_starts_with($norm, 'jumlah')) {
            return "{$label}: **{$count}**.";
        }

        return "Total {$label}: **{$count}**.";
    }

    /**
     * Sajikan tabel rekap pelapor (nama, jumlah laporan hilang, jumlah temuan, total, laporan terakhir)
     *
     * @param array $reporters
     * @return array
     */
    public function presentReportersTable(array $reporters): array
    {
        $rows = [];
        $rowNo = 1;
        foreach ($reporters as $r) {
            $latestDate = !empty($r['latest_report_date']) ? $this->formatDateIndonesian((string)$r['latest_report_date']) : '-';
            $rows[] = [
                'row_no'             => (string)$rowNo++,
                'reporter_name'      => $r['reporter_name'],
                'lost_count'         => (string)$r['lost_count'],
                'found_count'        => (string)$r['found_count'],
                'total_count'        => (string)$r['total_count'],
                'latest_report_date' => $latestDate,
            ];
        }

        $total = count($rows);
        $shown = min(50, $total);

        return [
            [
                'type'    => 'text',
                'content' => "Berikut daftar pengguna yang melakukan pelaporan barang di sistem:",
            ],
            [
                'type'    => 'table',
                'columns' => [
                    ['key' => 'row_no',             'label' => 'No'],
                    ['key' => 'reporter_name',      'label' => 'Nama Pelapor'],
                    ['key' => 'lost_count',         'label' => 'Lap. Hilang'],
                    ['key' => 'found_count',        'label' => 'Lap. Temuan'],
                    ['key' => 'total_count',        'label' => 'Total Laporan'],
                    ['key' => 'latest_report_date', 'label' => 'Laporan Terakhir'],
                ],
                'rows'    => array_slice($rows, 0, $shown),
                'all_rows'=> $rows,
                'total'   => $total,
                'shown'   => $shown,
            ],
        ];
    }

    /**
     * Bangun blok struktur tabel terstruktur (Prompt 29D)
     * - Kolom pertama 'No' (1, 2, 3...)
     * - Sembunyikan kolom ID internal kecuali diminta
     * - Format ramah baca (1/0 -> Ya/Tidak, tanggal singkat Indonesia, enum terjemahan)
     * - Tampilkan semua baris sampai 50 dalam kontainer
     */
    public function buildTableBlock(array $queryResult, string $userInput = ''): array
    {
        $columns = $queryResult['columns'] ?? [];
        $rows = $queryResult['rows'] ?? [];
        $totalRows = $queryResult['total'] ?? $queryResult['total_count'] ?? count($rows);

        $askId = str_contains(mb_strtolower($userInput), 'id') || str_contains(mb_strtolower($userInput), 'kode');
        $hiddenKeys = ['id', 'user_id', 'category_id', 'location_id', 'item_id', 'storage_location_id', 'reporter_user_id', 'claimant_user_id', 'admin_user_id', 'verified_by'];

        $formattedColumns = [
            ['key' => 'row_no', 'label' => 'No'],
        ];

        $activeColKeys = [];
        foreach ($columns as $cKey => $cLabel) {
            $keyStr = (string)$cKey;
            // Sembunyikan kolom ID internal kecuali eksplisit diminta
            if (!$askId && in_array($keyStr, $hiddenKeys, true)) {
                continue;
            }
            $activeColKeys[] = $keyStr;
            $formattedColumns[] = [
                'key'   => $keyStr,
                'label' => (string)$cLabel,
            ];
        }

        $formattedRows = [];
        $rowNo = 1;
        foreach ($rows as $r) {
            $rowObj = ['row_no' => (string)$rowNo++];
            foreach ($activeColKeys as $cKey) {
                $rawVal = $r[$cKey] ?? null;
                $isExplicitBool = is_bool($rawVal) || $cKey === 'is_active' || str_starts_with($cKey, 'is_') || str_starts_with($cKey, 'has_');
                if (is_bool($rawVal)) {
                    $valStr = ($cKey === 'is_active') ? ($rawVal ? 'Aktif' : 'Nonaktif') : ($rawVal ? 'Ya' : 'Tidak');
                } elseif ($rawVal === null || $rawVal === '') {
                    $valStr = '-';
                } else {
                    $valStr = (string)$rawVal;
                    if ($cKey === 'is_active' && ($valStr === '1' || $valStr === '0')) {
                        $valStr = ($valStr === '1') ? 'Aktif' : 'Nonaktif';
                    } elseif ($isExplicitBool && ($valStr === '1' || $valStr === '0')) {
                        $valStr = ($valStr === '1') ? 'Ya' : 'Tidak';
                    }
                }

                // Terjemahkan tanggal ke format singkat Indonesia jika cocok pola tanggal
                if (preg_match('/^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}(:\d{2})?)?$/', $valStr)) {
                    $valStr = $this->formatDateIndonesian($valStr);
                }

                // Terjemahkan label status/peran/aksi ke Bahasa Indonesia UI
                if (in_array($cKey, ['status', 'role_name', 'item_condition', 'physical_condition', 'is_active', 'action'], true)) {
                    $valStr = $this->translateStatusLabel($cKey, $valStr);
                }

                $rowObj[$cKey] = $valStr;
            }
            $formattedRows[] = $rowObj;
        }

        $shownCount = min(50, count($formattedRows));

        $tableBlock = [
            'type'        => 'table',
            'columns'     => $formattedColumns,
            'rows'        => array_slice($formattedRows, 0, $shownCount),
            'all_rows'    => $formattedRows,
            'total'       => $totalRows,
            'shown'       => $shownCount,
            'is_partial'  => $totalRows > $shownCount,
            'is_complete' => $totalRows <= $shownCount,
            'note'        => $totalRows > $shownCount ? "Menampilkan {$shownCount} dari total {$totalRows} data." : null,
        ];

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['cfind_last_table'] = $tableBlock;

        return $tableBlock;
    }

    /**
     * Format tanggal singkat berbahasa Indonesia (contoh: 27 Okt 2026 14:30) (Prompt 29D)
     */
    private function formatDateIndonesian(string $dateStr): string
    {
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $dateStr;
        }
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $m = (int)date('n', $ts);
        $monthName = $months[$m] ?? date('M', $ts);
        if (strlen(trim($dateStr)) <= 10) {
            return date('j', $ts) . ' ' . $monthName . ' ' . date('Y', $ts);
        }
        return date('j', $ts) . ' ' . $monthName . ' ' . date('Y H:i', $ts);
    }

    /**
     * Terjemahkan label status/peran ke Bahasa Indonesia resmi yang sama dengan UI
     */
    private function translateStatusLabel(string $columnKey, string $value): string
    {
        $norm = strtolower(trim($value));

        if ($columnKey === 'role_name') {
            return match ($norm) {
                'admin'   => 'Admin',
                'petugas' => 'Petugas',
                'civitas' => 'Civitas',
                default   => ucfirst($norm),
            };
        }

        if ($columnKey === 'is_active') {
            return match ($norm) {
                '1', 'true', 'ya', 'active' => 'Aktif',
                '0', 'false', 'tidak'       => 'Nonaktif',
                default                     => $value,
            };
        }

        if ($columnKey === 'action') {
            return match ($norm) {
                'login_success', 'auth_login', 'login', 'user_login' => 'Login berhasil',
                'login_failed', 'auth_failed', 'failed_login'        => 'Login gagal',
                'logout', 'auth_logout'                              => 'Keluar (Logout)',
                default                                              => ucwords(str_replace('_', ' ', $norm)),
            };
        }

        if ($columnKey === 'physical_condition' || $columnKey === 'item_condition') {
            return match ($norm) {
                'sangat_baik'  => 'Sangat Baik',
                'baik'         => 'Baik',
                'rusak_ringan' => 'Rusak Ringan',
                'rusak_berat'  => 'Rusak Berat',
                default        => ucwords(str_replace('_', ' ', $norm)),
            };
        }

        return match ($norm) {
            // Item & Inventaris
            'registered'             => 'Terdaftar',
            'pending_inspection'     => 'Menunggu Pemeriksaan',
            'in_inspection'          => 'Dalam Pemeriksaan',
            'stored'                 => 'Tersimpan di Posko',
            'in_claim'               => 'Sedang Diklaim',
            'ready_for_handover'     => 'Siap Diserahkan',
            'returned'               => 'Sudah Dikembalikan',
            'donated'                => 'Didonasikan',
            'disposed'               => 'Dimusnahkan',

            // Klaim
            'pending'                => 'Menunggu Verifikasi',
            'pending_verification'   => 'Menunggu Verifikasi',
            'under_review'           => 'Sedang Ditinjau',
            'need_more_info'         => 'Perlu Info Tambahan',
            'approved'               => 'Disetujui',
            'rejected'               => 'Ditolak',
            'completed'              => 'Selesai',
            'closed_duplicate'       => 'Ditutup (Duplikat)',
            'cancelled'              => 'Dibatalkan',

            // Laporan Hilang & Temuan
            'draft'                  => 'Draf',
            'published'              => 'Dipublikasikan',
            'pending_handover'       => 'Menunggu Penyerahan',
            'handed_over_to_center'  => 'Diserahkan ke Posko',
            'verified'               => 'Terverifikasi',
            'investigating'          => 'Dalam Penyelidikan',
            'matched'                => 'Cocok',
            'claimed'                => 'Diklaim',
            'resolved'               => 'Selesai',
            'expired'                => 'Kedaluwarsa',

            default                  => ucwords(str_replace('_', ' ', $norm)),
        };
    }

    /**
     * Format respons penolakan hak akses
     */
    public function presentDenied(string $role, string $reason = ''): string
    {
        return "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
    }

    /**
     * Format respons klarifikasi / di luar skema dengan 3-5 contoh
     */
    public function presentUnsupported(string $role): string
    {
        $examples = match ($role) {
            'admin' => [
                'Siapa saja yang mengajukan klaim?',
                'Ada berapa pengguna terdaftar?',
                'List barang yang ada di posko',
                'Ada berapa laporan hari ini?',
                'Tabel pengguna per role',
            ],
            'petugas' => [
                'Ada berapa barang di rak?',
                'Klaim hari ini?',
                'Laporan hari ini?',
                'List barang yang ada di posko',
                'Antrean verifikasi klaim',
            ],
            'civitas' => [
                'Klaim saya berapa dan statusnya?',
                'Status laporan kehilangan saya',
                'Ada berapa barang di katalog publik?',
                'Kategori barang apa saja yang ada?',
                'Diskusi internal gimana?',
            ],
            default => [
                'Cara lapor barang hilang',
                'Cara mengajukan klaim',
                'Lokasi posko Lost and Found',
                'Jam operasional posko',
            ],
        };

        $lines = ["Saya belum bisa menjawab pertanyaan tersebut saat ini. Contoh pertanyaan yang dapat Anda ajukan:"];
        foreach ($examples as $ex) {
            $lines[] = "• {$ex}";
        }

        return implode("\n", $lines);
    }
}
