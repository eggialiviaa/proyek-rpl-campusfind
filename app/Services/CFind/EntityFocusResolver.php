<?php

declare(strict_types=1);

namespace App\Services\CFind;

use PDO;

/**
 * EntityFocusResolver — Penyelesaian Entitas dan Memori Fokus Percakapan (Prompt 29E)
 *
 * Mengelola memori fokus sesi (entitas tunggal terakhir yang dibahas),
 * pencocokan rujukan baris ("barang nomor 5"), pencarian nama/kode toleran,
 * dan penyusunan jawaban atribut tunggal dalam satu kalimat ringkas.
 */
class EntityFocusResolver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Resolusi entitas dari input pengguna
     *
     * @param string $userInput
     * @return array{status: 'single'|'multiple'|'not_found'|'none', entity?: array, items?: array, query?: string, row_no?: int}
     */
    public function resolve(string $userInput): array
    {
        $normalized = mb_strtolower(trim($userInput));

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // 1. Rujukan Nomor Baris Tabel Terakhir ("barang nomor 5", "yang no 3", "nomor 5 itu", "baris ke 5", "yang pertama", "yang ke-2")
        $rowNo = null;
        if (preg_match('/(?:barang|yang|baris)?\s*(?:nomor|no)\.?\s*(\d+)/iu', $normalized, $matches)) {
            $rowNo = (int)$matches[1];
        } elseif (preg_match('/\b(?:yang\s+)?(ke-?(\d+)|pertama|kesatu|kedua|ketiga|keempat|kelima)\b/iu', $normalized, $ordMatches)) {
            if (!empty($ordMatches[2])) {
                $rowNo = (int)$ordMatches[2];
            } else {
                $word = mb_strtolower($ordMatches[1]);
                $map = ['pertama' => 1, 'kesatu' => 1, 'kedua' => 2, 'ketiga' => 3, 'keempat' => 4, 'kelima' => 5];
                $rowNo = $map[$word] ?? null;
            }
        }

        if ($rowNo !== null && $rowNo > 0) {
            $lastTable = $_SESSION['cfind_last_table'] ?? null;
            $tableRows = $lastTable['rows'] ?? $_SESSION['cfind_last_context']['items'] ?? $_SESSION['cfind_last_context']['last_result'][0]['rows'] ?? [];
            if (!empty($tableRows)) {
                $currIdx = 1;
                foreach ($tableRows as $r) {
                    $matchedRowNo = (int)($r['row_no'] ?? $currIdx);
                    if ($matchedRowNo === $rowNo || $currIdx === $rowNo) {
                        // Temukan entitas item di DB berdasarkan item_code atau title
                        $itemCode = $r['item_code'] ?? null;
                        $title = $r['title'] ?? null;
                        $item = null;
                        if ($itemCode) {
                            $stmt = $this->pdo->prepare("SELECT i.*, sl.name as storage_location_name, sl.code as storage_location_code, c.name as category_name 
                                FROM items i 
                                LEFT JOIN storage_locations sl ON sl.id = i.storage_location_id 
                                LEFT JOIN categories c ON c.id = i.category_id 
                                WHERE i.item_code = ? LIMIT 1");
                            $stmt->execute([$itemCode]);
                            $item = $stmt->fetch(PDO::FETCH_ASSOC);
                        } elseif ($title) {
                            $stmt = $this->pdo->prepare("SELECT i.*, sl.name as storage_location_name, sl.code as storage_location_code, c.name as category_name 
                                FROM items i 
                                LEFT JOIN storage_locations sl ON sl.id = i.storage_location_id 
                                LEFT JOIN categories c ON c.id = i.category_id 
                                WHERE i.title = ? LIMIT 1");
                            $stmt->execute([$title]);
                            $item = $stmt->fetch(PDO::FETCH_ASSOC);
                        }

                        if ($item) {
                            $entityData = [
                                'type'                  => 'item',
                                'id'                    => (int)$item['id'],
                                'item_code'             => $item['item_code'],
                                'title'                 => $item['title'],
                                'status'                => $item['status'],
                                'physical_condition'    => $item['physical_condition'] ?? 'baik',
                                'registered_at'         => $item['registered_at'] ?? $item['created_at'],
                                'storage_location_name' => $item['storage_location_name'] ?? 'Posko Lost & Found',
                                'storage_location_code' => $item['storage_location_code'] ?? '-',
                                'category_name'         => $item['category_name'] ?? '-',
                                'row_no'                => $rowNo,
                            ];
                            $_SESSION['cfind_focus_entity'] = $entityData;
                            return [
                                'status' => 'single',
                                'entity' => $entityData,
                                'row_no' => $rowNo,
                            ];
                        }
                    }
                    $currIdx++;
                }
            }
        }

        // 2. Evaluasi apakah ini pertanyaan anaphora ke fokus lama ATAU pencarian subjek baru
        $keywords = $this->extractSubjectKeywords($userInput);
        $hasExplicitAnaphora = (bool)preg_match('/\b(tersebut|barang itu|barang ini|barang tadi|yang tadi)\b/iu', $normalized);
        $hasFocusInSession = !empty($_SESSION['cfind_focus_entity']);

        if ($hasFocusInSession) {
            if ($hasExplicitAnaphora) {
                // Rujukan anaphora eksplisit ("tersebut", "barang ini", dll)
                return [
                    'status' => 'single',
                    'entity' => $_SESSION['cfind_focus_entity'],
                ];
            }
            if (empty($keywords)) {
                // Pertanyaan atribut tanpa subjek eksplisit (misal "kapan waktu registrasinya?", "status fisiknya?")
                return [
                    'status' => 'single',
                    'entity' => $_SESSION['cfind_focus_entity'],
                ];
            }
        }

        // 3. Pencarian Berdasarkan Nama / Kode Toleran
        $searchRes = $this->searchItemTolerant($userInput, $keywords);
        if ($searchRes['status'] === 'single') {
            $item = $searchRes['items'][0];
            $entityData = [
                'type'                  => 'item',
                'id'                    => (int)$item['id'],
                'item_code'             => $item['item_code'],
                'title'                 => $item['title'],
                'status'                => $item['status'],
                'physical_condition'    => $item['physical_condition'] ?? 'baik',
                'registered_at'         => $item['registered_at'] ?? $item['created_at'],
                'storage_location_name' => $item['storage_location_name'] ?? 'Posko Lost & Found',
                'storage_location_code' => $item['storage_location_code'] ?? '-',
                'category_name'         => $item['category_name'] ?? '-',
            ];
            $_SESSION['cfind_focus_entity'] = $entityData;
            return [
                'status' => 'single',
                'entity' => $entityData,
            ];
        }

        return $searchRes;
    }

    /**
     * Ekstrak kata kunci subjek pencarian dengan membersihkan stop words & atribut
     */
    public function extractSubjectKeywords(string $userInput): array
    {
        $clean = mb_strtolower(trim($userInput));
        $clean = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $clean);
        $words = array_filter(explode(' ', $clean), fn($w) => mb_strlen($w) >= 2);

        $stopWords = [
            'apa', 'kode', 'kodenya', 'barang', 'barangnya', 'untuk', 'dari', 'yang', 'ini', 'itu', 'tersebut', 'tadi',
            'bagaimana', 'status', 'statusnya', 'fisik', 'fisiknya', 'kapan', 'di', 'mana', 'dimana', 'lemari', 'lemarinya', 
            'rak', 'raknya', 'koleksi', 'lihat', 'bisa', 'tolong', 'tampilkan', 'info', 'tentang', 'data', 'pada',
            'waktu', 'registrasi', 'registrasinya', 'didaftarkan', 'dicatat', 'kondisi', 'kondisinya', 'kategori', 'kategorinya',
            'namanya', 'nama', 'ada', 'kah', 'tersimpan', 'disimpan', 'simpan', 'posisi', 'posisinya', 'lokasi',
            'lokasinya', 'letak', 'letaknya', 'punya', 'siapa', 'pengajunya', 'pemilik', 'pemiliknya',
            'tsb', 'coba', 'ke', 'ke-1', 'ke-2', 'ke-3', 'ke-4', 'ke-5', 'ke-13', 'mereka', 'semua', 'semuanya',
            'rincian', 'rinciannya', 'detail', 'detailnya', 'lengkap', 'lengkapnya', 'daftar', 'list',
            'laporan', 'klaim', 'pengguna', 'user', 'orang'
        ];
        // Abaikan juga kata yang murni angka atau pola ke-\d+
        return array_values(array_filter($words, function ($w) use ($stopWords) {
            if (in_array($w, $stopWords, true)) {
                return false;
            }
            if (preg_match('/^ke-?\d+$/i', $w) || is_numeric($w)) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Pencarian toleran item berdasarkan kata kunci
     */
    public function searchItemTolerant(string $userInput, ?array $keywords = null): array
    {
        if ($keywords === null) {
            $keywords = $this->extractSubjectKeywords($userInput);
        }

        if (empty($keywords)) {
            return ['status' => 'none'];
        }

        $allItems = $this->pdo->query("SELECT i.*, sl.name as storage_location_name, sl.code as storage_location_code, c.name as category_name 
            FROM items i 
            LEFT JOIN storage_locations sl ON sl.id = i.storage_location_id 
            LEFT JOIN categories c ON c.id = i.category_id")->fetchAll(PDO::FETCH_ASSOC);

        $scored = [];
        foreach ($allItems as $item) {
            $score = 0;
            $titleLower = mb_strtolower($item['title'] . ' ' . $item['item_code'] . ' ' . ($item['description'] ?? ''));
            $itemWords = preg_split('/[\s\-_]+/', $titleLower);

            // Exact match item code
            foreach ($keywords as $kw) {
                if (strcasecmp($kw, $item['item_code']) === 0) {
                    $score += 120;
                }
            }

            // Keyword match with Levenshtein tolerance
            $matchedWords = 0;
            foreach ($keywords as $kw) {
                $kwLen = mb_strlen($kw);
                $wordMatched = false;
                foreach ($itemWords as $iw) {
                    if ($iw === $kw || str_contains($iw, $kw) || str_contains($kw, $iw)) {
                        $score += 25;
                        $wordMatched = true;
                        break;
                    }
                    if ($kwLen >= 4) {
                        $dist = levenshtein($kw, $iw);
                        if ($dist <= 1 || ($kwLen >= 6 && $dist <= 2)) {
                            $score += 20;
                            $wordMatched = true;
                            break;
                        }
                    }
                }
                if ($wordMatched) {
                    $matchedWords++;
                }
            }

            // Pastikan seluruh kata pencarian cocok
            if ($matchedWords < count($keywords)) {
                continue;
            }

            $score += 50;

            if ($score > 0) {
                $scored[] = ['item' => $item, 'score' => $score];
            }
        }

        if (empty($scored)) {
            return [
                'status' => 'not_found',
                'query'  => implode(' ', $keywords),
                'items'  => []
            ];
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $topScore = $scored[0]['score'];
        $topItems = array_map(fn($s) => $s['item'], array_filter($scored, fn($s) => $s['score'] >= $topScore * 0.85));

        if (count($topItems) === 1) {
            return [
                'status' => 'single',
                'items'  => $topItems,
            ];
        }

        return [
            'status' => 'multiple',
            'items'  => array_slice($topItems, 0, 5),
        ];
    }

    /**
     * Jawab pertanyaan atribut sebuah entitas dalam satu kalimat ringkas (Prompt 29E)
     */
    public function answerAttribute(array $entity, string $userInput): string
    {
        $norm = mb_strtolower(trim($userInput));
        $title = $entity['title'] ?? 'Barang';
        $code = $entity['item_code'] ?? '';
        $rowNo = $entity['row_no'] ?? null;

        // 1. Lokasi Rak Penyimpanan
        if (str_contains($norm, 'lemari') || str_contains($norm, 'rak') || str_contains($norm, 'di mana') || str_contains($norm, 'simpan') || str_contains($norm, 'posisi')) {
            $rackName = $entity['storage_location_name'] ?? 'Posko Lost & Found';
            if ($rowNo !== null) {
                return "Barang nomor {$rowNo} ({$title} / {$code}) tersimpan di {$rackName}.";
            }
            return "{$title} ({$code}) tersimpan di {$rackName}.";
        }

        // 2. Status Fisik / Status
        if (str_contains($norm, 'status')) {
            $statusRaw = $entity['status'] ?? 'stored';
            $statusLabel = match ($statusRaw) {
                'in_claim'  => 'Sedang Diklaim',
                'stored'    => 'Tersimpan di Posko',
                'returned'  => 'Sudah Diambil/Dikembalikan',
                'disposed'  => 'Dimusnahkan',
                'auctioned' => 'Dilelang',
                default     => ucfirst($statusRaw),
            };

            if ($rowNo !== null) {
                return "Barang nomor {$rowNo} ({$title} / {$code}) berstatus {$statusLabel}.";
            }
            return "Status fisik {$title} ({$code}) adalah {$statusLabel}.";
        }

        // 3. Kondisi Fisik
        if (str_contains($norm, 'kondisi')) {
            $condRaw = $entity['physical_condition'] ?? 'baik';
            $condLabel = match ($condRaw) {
                'sangat_baik'  => 'Sangat Baik',
                'baik'         => 'Baik',
                'rusak_ringan' => 'Rusak Ringan',
                'rusak_berat'  => 'Rusak Berat',
                default        => ucfirst($condRaw),
            };

            if ($rowNo !== null) {
                return "Kondisi fisik barang nomor {$rowNo} ({$title} / {$code}) adalah {$condLabel}.";
            }
            return "Kondisi fisik {$title} ({$code}) adalah {$condLabel}.";
        }

        // 4. Waktu Registrasi / Pendaftaran
        if (str_contains($norm, 'registrasi') || str_contains($norm, 'didaftarkan') || str_contains($norm, 'dicatat') || str_contains($norm, 'kapan')) {
            $dateRaw = $entity['registered_at'] ?? null;
            $formattedDate = '-';
            if ($dateRaw) {
                $ts = strtotime($dateRaw);
                if ($ts !== false) {
                    $months = [
                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
                        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
                    ];
                    $day = date('j', $ts);
                    $mNum = (int)date('n', $ts);
                    $year = date('Y', $ts);
                    $time = date('H:i', $ts);
                    $formattedDate = "{$day} {$months[$mNum]} {$year} {$time}";
                }
            }

            if ($rowNo !== null) {
                return "Barang nomor {$rowNo} ({$title} / {$code}) didaftarkan pada {$formattedDate}.";
            }
            return "{$title} ({$code}) didaftarkan pada {$formattedDate}.";
        }

        // 5. Kode Barang
        if (str_contains($norm, 'kode') || str_contains($norm, 'nomor barang')) {
            if ($rowNo !== null) {
                return "Kode barang untuk barang nomor {$rowNo} ({$title}) adalah **{$code}**.";
            }
            return "Kode barang untuk {$title} adalah **{$code}**.";
        }

        // 6. Kategori Barang
        if (str_contains($norm, 'kategori') || str_contains($norm, 'jenis')) {
            $cat = $entity['category_name'] ?? '-';
            if ($rowNo !== null) {
                return "Kategori barang nomor {$rowNo} ({$title} / {$code}) adalah {$cat}.";
            }
            return "Kategori {$title} ({$code}) adalah {$cat}.";
        }

        // Default: Rangkuman ringkas atribut entitas
        $rackName = $entity['storage_location_name'] ?? 'Posko Lost & Found';
        return "{$title} ({$code}) tersimpan di {$rackName}.";
    }
}
