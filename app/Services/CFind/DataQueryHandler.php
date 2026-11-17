<?php

declare(strict_types=1);

namespace App\Services\CFind;

use Core\Database;
use App\Services\AiProviderInterface;
use App\Services\CFindService;
use App\Services\ItemCatalogService;
use App\Services\MetricDefinitionService;
use App\Services\NvidiaNimService;
use App\Services\SettingService;
use PDO;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * DataQueryHandler — C-Find Data Agent Engine (Prompt 29)
 *
 * Mengimplementasikan C-Find Data Agent yang menggantikan Prompt 28, 28B, dan 28C.
 *
 * Prinsip Penting:
 * 1. AI TIDAK PERNAH menulis SQL dan tidak memiliki akses langsung ke database.
 * 2. Ke provider AI HANYA dikirim: pertanyaan, riwayat singkat, dan deskripsi skema semantik (TANPA data baris).
 * 3. Backend yang memegang kebenaran data; jawaban disajikan deterministik oleh backend (bukan ditulis AI).
 * 4. Isolasi role ketat: filter cakupan user_id & role dipaksa oleh backend dari server session.
 * 5. Field terlarang (password/hash, token, bukti klaim, catatan internal, kontak) tidak pernah ada di skema.
 * 6. Jawaban singkat dan tepat: single number = 1 kalimat, daftar/tabel = semantic HTML table ter-escape.
 * 7. Realtime: hasil query selalu dieksekusi langsung terhadap basis data tanpa stale cache.
 */
class DataQueryHandler
{
    private PDO $pdo;
    private ItemCatalogService $catalogService;
    private ?AiProviderInterface $nimService;
    private DateTimeZone $timezone;
    private MetricDefinitionService $metricService;

    private SemanticSchema $semanticSchema;
    private QueryPlanner $planner;
    private QueryValidator $validator;
    private QueryCompiler $compiler;
    private ResponsePresenter $presenter;
    private string $currentRole = 'guest';
    private ?int $currentUserId = null;

    public function __construct(
        ?PDO $pdo = null,
        ?ItemCatalogService $catalogService = null,
        ?AiProviderInterface $nimService = null,
        ?MetricDefinitionService $metricService = null
    ) {
        $this->pdo = $pdo ?? Database::getInstance()->getPdo();
        $this->catalogService = $catalogService ?? new ItemCatalogService($this->pdo);
        $this->nimService = $nimService;
        $this->timezone = new DateTimeZone('Asia/Jakarta');
        $this->metricService = $metricService ?? new MetricDefinitionService($this->pdo);

        $this->semanticSchema = new SemanticSchema();
        $this->planner = new QueryPlanner($this->semanticSchema, $this->nimService);
        $this->validator = new QueryValidator($this->semanticSchema);
        $this->compiler = new QueryCompiler($this->pdo, $this->semanticSchema, $this->catalogService);
        $this->presenter = new ResponsePresenter();
    }

    /**
     * Entry point: Proses pesan pengguna melalui pipeline C-Find Data Agent
     *
     * @param string $userInput Pesan dari pengguna
     * @param array<string, mixed>|null $sessionUser Sesi user server
     * @param string $role 'guest' | 'civitas' | 'petugas' | 'admin'
     * @param int|null $userId ID user jika login
     * @return array{handled: bool, reply: mixed, tokens: int, intent: string, status: string}|null
     */
    public function handle(string $userInput, ?array $sessionUser, string $role, ?int $userId): ?array
    {
        $this->currentRole = $role;
        $this->currentUserId = $userId;
        $clean = trim($userInput);
        $stripped = CFindService::stripGreetingPrefix($clean);
        $normalized = mb_strtolower($stripped);
        $startTime = microtime(true);

        // ══════════════════════════════════════════════════════════════
        // TAHAP 1: PRA-FILTER ATURAN (0 TOKEN AI, LATENCY < 5ms)
        // ══════════════════════════════════════════════════════════════

        // 1.0 Penanganan Langsung Kunci Metrik Deterministik (Chip Cepat Prompt 29C-3)
        if (str_starts_with($normalized, 'metric:')) {
            $metricKey = substr($normalized, 7);
            $chipResult = $this->handleDeterministicMetricKey($metricKey, $role, $userId, $sessionUser);
            if ($chipResult !== null) {
                return $chipResult;
            }
        }

        // 1.1 Penolakan Keamanan Mutlak (Password, Hash, Bukti Klaim, Catatan Internal, Chat Pribadi, Prompt Injection)
        $securityCheck = $this->checkSecurityViolation($normalized, $role);
        if ($securityCheck !== null) {
            return $securityCheck;
        }

        // 1.2 Penjelasan Definisi & Perbedaan Metrik ("kenapa dashboard 6 bukan 4?", "apa maksudnya...", "dari mana angkanya...")
        $metricExplanation = $this->metricService->explainMetric($normalized);
        if ($metricExplanation !== null) {
            return [
                'handled' => true,
                'reply'   => $metricExplanation,
                'tokens'  => 0,
                'intent'  => 'metric_explanation',
                'status'  => 'success',
            ];
        }

        // 1.3 Identitas User & Bot & Konfirmasi Login Diri Sendiri ("bukankah saya juga login?")
        $identityCheck = $this->checkIdentityQuestions($normalized, $role, $sessionUser, $userId);
        if ($identityCheck !== null) {
            return $identityCheck;
        }

        // 1.3.000 Pertanyaan Perkenalan Diri / Kemampuan Asisten AI (Delegasikan ke Tier 2 AI Reasoning untuk pengguna login)
        if (preg_match('/\b(perkenalkan dirimu|perkenalkan diri|kenalan dong|apa yang bisa kamu bantu|apa saja yang bisa kamu bantu|bisa bantu apa saja|kamu bisa apa|kemampuan kamu)\b/iu', $normalized)) {
            if ($role !== 'guest') {
                return null;
            }
        }

        // 1.3.00 Penanganan Status Laporan & Klaim Milik Pengguna Sendiri (Prompt 34E)
        $userStatusCheck = $this->checkUserOwnEntitiesStatus($normalized, $role, $userId, $sessionUser);
        if ($userStatusCheck !== null) {
            return $userStatusCheck;
        }

        // 1.3.001 Penanganan Laporan Hari Ini (Kehilangan & Temuan) Terpadu untuk Petugas & Admin (Prompt 35C)
        $todayReportsCheck = $this->handleTodayReportsQuery($normalized, $role, $userId, $sessionUser);
        if ($todayReportsCheck !== null) {
            return $todayReportsCheck;
        }

        // 1.3.002 Pertanyaan Lanjutan Kontekstual & Anafora Himpunan (Prompt 36A)
        // Mengecek apakah pertanyaan merupakan pertanyaan lanjutan atau rujukan himpunan sebelumnya
        // ("siapa saja rinciannya?", "rinciannya", "lengkap", "coba apa saja ke-13 laporan tsb?", "siapa saja yang mengajukan klaim tsb?")
        if ($this->isSetFollowUpQuery($normalized)) {
            $followUp = $this->handleFollowUp($normalized, $role, $userId, $clean);
            if ($followUp !== null) {
                return [
                    'handled' => true,
                    'reply'   => $followUp,
                    'tokens'  => 0,
                    'intent'  => 'contextual_follow_up',
                    'status'  => 'success',
                ];
            }
        }

        // 1.3.0 Resolusi Entitas Tunggal & Pertanyaan Atribut (Prompt 29E)
        // Mendeteksi rujukan nomor baris ("barang nomor 5"), anafora barang spesifik ("barang tersebut", "status fisik barang tersebut"),
        // atau pertanyaan atribut spesifik suatu barang ("apa kode barang untuk kacamata ray-ban?")
        $isCountQuery = preg_match('/(?:ada berapa|berapa banyak|jumlah|hitung total|total)/iu', $normalized) && !preg_match('/(?:nomor|no)\.?\s*\d+/iu', $normalized);
        $isEntityDetailQuery = !$isCountQuery && (
            preg_match('/(?:nomor|no)\.?\s*\d+/iu', $normalized) ||
            preg_match('/(?:barang tersebut|barang itu|barang ini|barang tadi|status fisiknya|kodenya apa|waktu registrasinya)/iu', $normalized) ||
            preg_match('/(?:apa kode barang|kode barang untuk|kode barang|kodenya apa|tersimpan di lemari mana|di mana tersimpan|di lemari mana|status fisik|waktu registrasi|kapan didaftarkan|kapan dicatat|statusnya apa|kondisinya apa)/iu', $normalized)
        );

        if ($isEntityDetailQuery && !str_starts_with($normalized, 'metric:')) {
            $focusResolver = new EntityFocusResolver($this->pdo);
            $res = $focusResolver->resolve($clean);
            if ($res['status'] === 'single') {
                $answer = $focusResolver->answerAttribute($res['entity'], $clean);
                return [
                    'handled' => true,
                    'reply'   => $answer,
                    'tokens'  => 0,
                    'intent'  => 'entity_attribute_single',
                    'status'  => 'success',
                ];
            } elseif ($res['status'] === 'multiple') {
                $optionsText = "Saya menemukan beberapa barang yang cocok. Maksud Anda yang mana?\n";
                $optNo = 1;
                foreach ($res['items'] as $it) {
                    $optionsText .= "{$optNo}. {$it['title']} ({$it['item_code']})\n";
                    $optNo++;
                }
                return [
                    'handled' => true,
                    'reply'   => trim($optionsText),
                    'tokens'  => 0,
                    'intent'  => 'entity_disambiguation',
                    'status'  => 'success',
                ];
            } elseif ($res['status'] === 'not_found') {
                $qName = $res['query'] ?? '';
                if (!empty($qName) && mb_strlen(trim($qName)) >= 2) {
                    $entityLabel = 'barang';
                    if (preg_match('/\b(laporan)\b/iu', $normalized)) {
                        $entityLabel = 'laporan';
                    } elseif (preg_match('/\b(klaim)\b/iu', $normalized)) {
                        $entityLabel = 'klaim';
                    } elseif (preg_match('/\b(pengguna|user|civitas)\b/iu', $normalized)) {
                        $entityLabel = 'pengguna';
                    }
                    return [
                        'handled' => true,
                        'reply'   => "Saya tidak menemukan {$entityLabel} bernama '{$qName}'.",
                        'tokens'  => 0,
                        'intent'  => 'entity_not_found',
                        'status'  => 'success',
                    ];
                }
            }
        }

        // 1.3.1 Civitas dengan Laporan Terbanyak ("siapa civitas dengan laporan terbanyak?") (Prompt 29D)
        if (preg_match('/(?:siapa|pengguna|civitas).*(?:laporan terbanyak|paling banyak lapor|terbanyak laporan)/iu', $normalized)) {
            if ($role === 'civitas') {
                return [
                    'handled' => true,
                    'reply'   => 'Data rekap pelapor lain berada di luar hak akses peran Civitas Anda.',
                    'tokens'  => 0,
                    'intent'  => 'civitas_rbac_denied',
                    'status'  => 'denied',
                ];
            }
            if ($role === 'guest') {
                return [
                    'handled' => true,
                    'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'tokens'  => 0,
                    'intent'  => 'guest_restricted',
                    'status'  => 'denied',
                ];
            }

            $sql = "SELECT u.name, COUNT(*) as cnt,
                    SUM(CASE WHEN r.source = 'lost' THEN 1 ELSE 0 END) as lost_cnt,
                    SUM(CASE WHEN r.source = 'found' THEN 1 ELSE 0 END) as found_cnt
                    FROM (
                        SELECT reporter_user_id as user_id, 'lost' as source FROM lost_reports WHERE reporter_user_id IS NOT NULL
                        UNION ALL
                        SELECT reporter_user_id as user_id, 'found' as source FROM found_reports WHERE reporter_user_id IS NOT NULL
                    ) r JOIN users u ON u.id = r.user_id
                    GROUP BY u.id, u.name
                    ORDER BY cnt DESC LIMIT 1";
            $top = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            if ($top) {
                if (str_contains($normalized, 'rincian') || str_contains($normalized, 'detail') || str_contains($normalized, 'jenis')) {
                    $reply = "{$top['name']}: {$top['cnt']} laporan ({$top['lost_cnt']} Kehilangan, {$top['found_cnt']} Temuan).";
                } else {
                    $reply = "{$top['name']}: {$top['cnt']} laporan.";
                }
            } else {
                $reply = "Belum ada data pelapor yang tercatat dalam sistem.";
            }

            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'top_reporter',
                'status'  => 'success',
            ];
        }

        // 1.3.2 Login Hari Ini: Hitungan ("ada berapa yang login hari ini?") (Prompt 29D & BugFix 01)
        if (preg_match('/(?:berapa.*(?:yang |orang |user |pengguna )?login|login.*berapa|berapa.*pengguna.*login)/iu', $normalized) || 
            $normalized === 'ada berapa yang login hari ini?' || 
            $normalized === 'ada berapa yang login?' ||
            $normalized === 'berapa yang login hari ini?' ||
            $normalized === 'berapa pengguna yang login hari ini?') {
            if ($role !== 'admin') {
                $reply = match ($role) {
                    'guest'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'civitas' => 'Data tersebut berada di luar hak akses peran Civitas Anda.',
                    'petugas' => 'Data tersebut berada di luar hak akses peran Petugas Anda.',
                    default   => 'Data tersebut berada di luar hak akses peran Anda.',
                };
                return [
                    'handled' => true,
                    'reply'   => $reply,
                    'tokens'  => 0,
                    'intent'  => 'login_activity_rbac_denied',
                    'status'  => 'denied',
                ];
            }

            $tz = new DateTimeZone('Asia/Jakarta');
            $todayStart = (new \DateTimeImmutable('today', $tz))->format('Y-m-d 00:00:00');
            $todayEnd = (new \DateTimeImmutable('today', $tz))->format('Y-m-d 23:59:59');

            $allowedActions = MetricDefinitionService::getLoginActivityActions();
            $actionPlaceholders = implode(',', array_fill(0, count($allowedActions), '?'));

            $sql = "SELECT 
                al.user_id,
                COALESCE(u.name, 'Pengguna') AS name,
                COALESCE(u.email, '-') AS email,
                COALESCE(r.name, 'civitas') AS role_name,
                COUNT(al.id) AS login_count,
                MAX(al.created_at) AS last_login_at
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE al.created_at >= ? AND al.created_at <= ?
              AND al.action IN ($actionPlaceholders)
            GROUP BY al.user_id, u.name, u.email, r.name
            ORDER BY last_login_at DESC";

            $params = array_merge([$todayStart, $todayEnd], $allowedActions);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $userCount = count($userRows);
            $totalLogins = array_sum(array_column($userRows, 'login_count'));

            if ($userCount === 0) {
                $reply = "Belum ada pengguna yang berhasil login hari ini.";
            } else {
                $reply = "Terdapat {$userCount} pengguna yang berhasil login hari ini ({$totalLogins} kali login).";
            }

            $plan = [
                'format'  => 'number',
                'queries' => [
                    [
                        'entity'    => 'login_activity',
                        'select'    => ['user_id', 'login_count'],
                        'filters'   => [
                            ['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today'],
                            ['field' => 'action', 'operator' => 'IN', 'value' => $allowedActions],
                        ],
                        'aggregate' => 'count',
                        'label'     => 'Aktivitas Login Hari Ini',
                    ],
                ],
            ];
            $results = [['total' => $userCount, 'total_logins' => $totalLogins, 'rows' => $userRows]];
            $this->storeSessionContext(
                $plan,
                $results,
                $clean,
                'login_activity',
                $userCount,
                'today',
                $role,
                [
                    'initial_intent'   => 'count',
                    'filter_sql'       => "al.created_at >= '{$todayStart}' AND al.created_at <= '{$todayEnd}' AND " . MetricDefinitionService::getLoginActivityFilterSql('al'),
                    'structured_filter'=> ['date' => 'today', 'actions' => $allowedActions],
                    'date_range'       => ['type' => 'today', 'start' => $todayStart, 'end' => $todayEnd],
                    'status_list'      => $allowedActions,
                    'group_by'         => ['user_id'],
                    'order_by'         => 'last_login_at DESC',
                    'aggregation'      => ['type' => 'count', 'value' => $userCount],
                    'total_records'    => $userCount,
                    'record_ids'       => array_values(array_filter(array_column($userRows, 'user_id'))),
                ]
            );

            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'login_count_today',
                'status'  => 'success',
            ];
        }

        // 1.3.3 Login Hari Ini: Daftar Baris ("siapa saja yang login hari ini?") (Prompt 29D & BugFix 01)
        if (preg_match('/(?:siapa saja|siapa aja|siapa yang|daftar|list).*(?:yang |orang |user |pengguna )?login/iu', $normalized) ||
            $normalized === 'siapa yang login hari ini?' ||
            $normalized === 'siapa yang login?') {
            if ($role !== 'admin') {
                $reply = match ($role) {
                    'guest'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'civitas' => 'Data tersebut berada di luar hak akses peran Civitas Anda.',
                    'petugas' => 'Data tersebut berada di luar hak akses peran Petugas Anda.',
                    default   => 'Data tersebut berada di luar hak akses peran Anda.',
                };
                return [
                    'handled' => true,
                    'reply'   => $reply,
                    'tokens'  => 0,
                    'intent'  => 'login_activity_rbac_denied',
                    'status'  => 'denied',
                ];
            }

            $tz = new DateTimeZone('Asia/Jakarta');
            $todayStart = (new \DateTimeImmutable('today', $tz))->format('Y-m-d 00:00:00');
            $todayEnd = (new \DateTimeImmutable('today', $tz))->format('Y-m-d 23:59:59');

            $allowedActions = MetricDefinitionService::getLoginActivityActions();
            $actionPlaceholders = implode(',', array_fill(0, count($allowedActions), '?'));

            $sql = "SELECT 
                al.user_id,
                COALESCE(u.name, 'Pengguna') AS name,
                COALESCE(u.email, '-') AS email,
                COALESCE(r.name, 'civitas') AS role_name,
                COUNT(al.id) AS login_count,
                MAX(al.created_at) AS last_login_at
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE al.created_at >= ? AND al.created_at <= ?
              AND al.action IN ($actionPlaceholders)
            GROUP BY al.user_id, u.name, u.email, r.name
            ORDER BY last_login_at DESC";

            $params = array_merge([$todayStart, $todayEnd], $allowedActions);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $reply = "Belum ada pengguna yang tercatat login pada hari ini.";
                return [
                    'handled' => true,
                    'reply'   => $reply,
                    'tokens'  => 0,
                    'intent'  => 'login_list_today',
                    'status'  => 'success',
                ];
            }

            $queryResult = [
                'columns' => [
                    'name'          => 'Nama Pengguna',
                    'email'         => 'Email',
                    'role_name'     => 'Peran',
                    'login_count'   => 'Jumlah Login',
                    'last_login_at' => 'Waktu Terakhir',
                ],
                'rows'    => array_map(function($r) {
                    return [
                        'name'          => $r['name'],
                        'email'         => $r['email'],
                        'role_name'     => ucfirst($r['role_name'] ?? 'civitas'),
                        'login_count'   => (string)$r['login_count'],
                        'last_login_at' => $this->formatDateTimeIndonesian((string)$r['last_login_at']),
                    ];
                }, $rows),
                'total'   => count($rows),
            ];

            $tableBlock = $this->presenter->buildTableBlock($queryResult, $normalized);
            $plan = [
                'format'  => 'table',
                'queries' => [
                    [
                        'entity'    => 'login_activity',
                        'select'    => ['name', 'email', 'role_name', 'login_count', 'last_login_at'],
                        'filters'   => [
                            ['field' => 'created_at', 'operator' => 'date_range', 'value' => 'today'],
                            ['field' => 'action', 'operator' => 'IN', 'value' => $allowedActions],
                        ],
                        'aggregate' => 'none',
                        'label'     => 'Aktivitas Login Hari Ini',
                    ],
                ],
            ];
            $this->storeSessionContext(
                $plan,
                [$queryResult],
                $clean,
                'login_activity',
                count($rows),
                'today',
                $role,
                [
                    'initial_intent'   => 'list',
                    'filter_sql'       => "al.created_at >= '{$todayStart}' AND al.created_at <= '{$todayEnd}' AND " . MetricDefinitionService::getLoginActivityFilterSql('al'),
                    'structured_filter'=> ['date' => 'today', 'actions' => $allowedActions],
                    'date_range'       => ['type' => 'today', 'start' => $todayStart, 'end' => $todayEnd],
                    'status_list'      => $allowedActions,
                    'group_by'         => ['user_id'],
                    'order_by'         => 'last_login_at DESC',
                    'aggregation'      => ['type' => 'none'],
                    'total_records'    => count($rows),
                    'record_ids'       => array_values(array_filter(array_column($rows, 'user_id'))),
                ]
            );

            $reply = [
                [
                    'type'    => 'text',
                    'content' => "Berikut daftar pengguna yang berhasil login ke sistem hari ini:",
                ],
                $tableBlock,
            ];

            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'login_list_today',
                'status'  => 'success',
            ];
        }

        // 1.3.4 Laporan Terakhir: Kehilangan & Temuan Gabungan ("apa laporan terakhir?") (Prompt 29D)
        if (preg_match('/(?:laporan terakhir|laporan paling baru|laporan terbaru)/iu', $normalized)) {
            if ($role === 'guest') {
                return [
                    'handled' => true,
                    'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'tokens'  => 0,
                    'intent'  => 'guest_restricted',
                    'status'  => 'denied',
                ];
            }

            $sql = "
                SELECT 
                    'Kehilangan' AS report_type,
                    lr.id,
                    lr.report_number,
                    lr.title,
                    c.name AS category_name,
                    lr.status,
                    lr.created_at
                FROM lost_reports lr
                LEFT JOIN categories c ON c.id = lr.category_id
                UNION ALL
                SELECT 
                    'Temuan' AS report_type,
                    fr.id,
                    fr.report_number,
                    fr.title,
                    c.name AS category_name,
                    fr.status,
                    fr.created_at
                FROM found_reports fr
                LEFT JOIN categories c ON c.id = fr.category_id
                ORDER BY created_at DESC, id DESC
                LIMIT 1
            ";
            $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return [
                    'handled' => true,
                    'reply'   => 'Belum ada laporan kehilangan atau temuan yang tercatat di sistem.',
                    'tokens'  => 0,
                    'intent'  => 'latest_report',
                    'status'  => 'success',
                ];
            }

            $queryResult = [
                'columns' => [
                    'report_type'   => 'Jenis Laporan',
                    'report_number' => 'Nomor Laporan',
                    'title'         => 'Nama Barang',
                    'category_name' => 'Kategori',
                    'status'        => 'Status',
                    'created_at'    => 'Tanggal Lapor',
                ],
                'rows'    => [$row],
            ];

            $tableBlock = $this->presenter->buildTableBlock($queryResult, $normalized);
            $reply = [
                [
                    'type'    => 'text',
                    'content' => "Berikut laporan terakhir yang tercatat di sistem:",
                ],
                $tableBlock,
            ];

            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'latest_report',
                'status'  => 'success',
            ];
        }

        // 1.3.4.1 Hitungan Pengajuan Klaim Berdasarkan Status (Prompt 29E)
        if (preg_match('/(?:berapa|jumlah|hitung).*(?:klaim|pengajuan).*(?:status|berstatus)/iu', $normalized) ||
            preg_match('/(?:klaim|pengajuan).*(?:berstatus)/iu', $normalized) ||
            preg_match('/(?:berapa|jumlah).*(?:klaim).*(?:selesai|ditutup|disetujui|pending|ditolak|dibatalkan)/iu', $normalized)) {

            // Map status
            $statusMap = [
                'closed_duplicate' => ['ditutup (duplikat)', 'ditutup duplikat', 'duplikat', 'ditutup', 'tutup'],
                'completed'        => ['selesai', 'sudah selesai', 'tuntas', 'sukses', 'berhasil'],
                'approved'         => ['disetujui', 'approved', 'diterima', 'setuju'],
                'pending'          => ['menunggu verifikasi', 'pending', 'menunggu', 'diajukan'],
                'rejected'         => ['ditolak', 'rejected', 'tolak'],
                'cancelled'        => ['dibatalkan', 'cancelled', 'batal'],
            ];

            $matchedEnum = null;
            $matchedLabel = null;
            $isSpecificDitutup = false;

            foreach ($statusMap as $enumVal => $synonyms) {
                foreach ($synonyms as $syn) {
                    if (str_contains($normalized, $syn)) {
                        $matchedEnum = $enumVal;
                        $matchedLabel = match ($enumVal) {
                            'completed'        => 'Selesai',
                            'closed_duplicate' => 'Ditutup (Duplikat)',
                            'approved'         => 'Disetujui',
                            'pending'          => 'Menunggu Verifikasi',
                            'rejected'         => 'Ditolak',
                            'cancelled'        => 'Dibatalkan',
                        };
                        if ($syn === 'ditutup' && !str_contains($normalized, 'duplikat')) {
                            $isSpecificDitutup = true;
                        }
                        break 2;
                    }
                }
            }

            if ($matchedEnum !== null) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM claims WHERE status = ?");
                $stmt->execute([$matchedEnum]);
                $count = (int)$stmt->fetchColumn();

                $this->storeSessionCount('claims_queue', [['field' => 'status', 'operator' => '=', 'value' => $matchedEnum]], $count);

                if ($isSpecificDitutup) {
                    $reply = "Status 'ditutup' cocok dengan Ditutup (Duplikat): **{$count}** klaim.";
                } else {
                    $reply = "Klaim berstatus {$matchedLabel}: **{$count}**.";
                }

                return [
                    'handled' => true,
                    'reply'   => $reply,
                    'tokens'  => 0,
                    'intent'  => 'claims_status_count',
                    'status'  => 'success',
                ];
            } else {
                // Ekstrak kata status yang tidak dikenal
                preg_match('/(?:status|berstatus)\s*([a-z0-9_\-]+)/iu', $normalized, $sm);
                $unknownWord = $sm[1] ?? 'tersebut';
                return [
                    'handled' => true,
                    'reply'   => "Saya tidak mengenali status klaim '{$unknownWord}'. Pilihan status klaim yang tersedia: Menunggu Verifikasi (pending), Disetujui (approved), Selesai (completed), Ditutup Duplikat (closed_duplicate), Ditolak (rejected), atau Dibatalkan (cancelled).",
                    'tokens'  => 0,
                    'intent'  => 'claims_status_unknown',
                    'status'  => 'success',
                ];
            }
        }

        // 1.3.4.2 Sisa Kapasitas Rak / Lemari / Zona Penyimpanan (Prompt 29E & Prompt 36B)
        if ($this->isRackCapacityQuery($normalized)) {
            $rackAnswer = $this->resolveRackCapacity($normalized);
            if ($rackAnswer !== null) {
                return [
                    'handled' => true,
                    'reply'   => $rackAnswer,
                    'tokens'  => 0,
                    'intent'  => 'rack_remaining_capacity',
                    'status'  => 'success',
                ];
            }
        }

        // 1.3.5 Lemari & Rak Penyimpanan: Hitungan ("ada berapa lemari dan rak penyimpanan?") (Prompt 29D)
        if (preg_match('/(?:berapa|jumlah|total).*(?:lemari|rak simpan|rak penyimpanan|tempat simpan)/iu', $normalized) ||
            $normalized === 'ada berapa lemari dan rak?' ||
            $normalized === 'ada berapa lemari dan rak penyimpanan?' ||
            $normalized === 'berapa lemari dan rak?' ||
            $normalized === 'ada berapa rak penyimpanan?') {
            if ($role === 'guest') {
                return [
                    'handled' => true,
                    'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'tokens'  => 0,
                    'intent'  => 'guest_restricted',
                    'status'  => 'denied',
                ];
            }
            if ($role === 'civitas') {
                return [
                    'handled' => true,
                    'reply'   => 'Data tersebut berada di luar hak akses peran Civitas Anda.',
                    'tokens'  => 0,
                    'intent'  => 'civitas_rbac_denied',
                    'status'  => 'denied',
                ];
            }

            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM storage_locations WHERE is_active = 1")->fetchColumn();
            $this->storeSessionCount('storage_locations', [], $count);
            return [
                'handled' => true,
                'reply'   => "Total lemari dan rak penyimpanan: **{$count}**.",
                'tokens'  => 0,
                'intent'  => 'storage_locations_count',
                'status'  => 'success',
            ];
        }

        // 1.3.6 Lemari & Rak Penyimpanan: Daftar ("apa saja lemari dan rak penyimpanan?") (Prompt 29D)
        if (preg_match('/(?:apa saja|daftar|list|rincian).*(?:lemari|rak simpan|rak penyimpanan|tempat simpan)/iu', $normalized) ||
            $normalized === 'apa saja lemari dan rak penyimpanan?' ||
            $normalized === 'daftar rak penyimpanan' ||
            $normalized === 'daftar lemari dan rak') {
            if ($role === 'guest') {
                return [
                    'handled' => true,
                    'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'tokens'  => 0,
                    'intent'  => 'guest_restricted',
                    'status'  => 'denied',
                ];
            }
            if ($role === 'civitas') {
                return [
                    'handled' => true,
                    'reply'   => 'Data tersebut berada di luar hak akses peran Civitas Anda.',
                    'tokens'  => 0,
                    'intent'  => 'civitas_rbac_denied',
                    'status'  => 'denied',
                ];
            }

            $rows = $this->pdo->query("SELECT code, name, zone, capacity, is_active FROM storage_locations WHERE is_active = 1 ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
            $queryResult = [
                'columns' => [
                    'code'      => 'Kode Rak',
                    'name'      => 'Nama Lemari / Rak',
                    'zone'      => 'Zona',
                    'capacity'  => 'Kapasitas',
                    'is_active' => 'Status',
                ],
                'rows'    => $rows,
            ];

            $tableBlock = $this->presenter->buildTableBlock($queryResult, $normalized);
            $reply = [
                [
                    'type'    => 'text',
                    'content' => "Berikut daftar lemari dan rak penyimpanan yang terdaftar di posko:",
                ],
                $tableBlock,
            ];

            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'storage_locations_list',
                'status'  => 'success',
            ];
        }

        // 1.3.7 Lokasi Kampus: Hitungan ("ada berapa lokasi?") (Prompt 29D)
        if (preg_match('/(?:berapa|jumlah|total).*(?:lokasi|gedung kampus)/iu', $normalized) && !str_contains($normalized, 'apa saja') && !str_contains($normalized, 'siapa saja') && !preg_match('/\bdaftar\b/iu', $normalized)) {
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM locations WHERE is_active = 1")->fetchColumn();
            $this->storeSessionCount('locations', [], $count);
            return [
                'handled' => true,
                'reply'   => "Total lokasi kampus: **{$count}**.",
                'tokens'  => 0,
                'intent'  => 'locations_count',
                'status'  => 'success',
            ];
        }

        // 1.3.8 Kategori Barang: Hitungan ("ada berapa kategori?") (Prompt 29D)
        if (preg_match('/(?:berapa|jumlah|total).*(?:kategori|jenis barang)/iu', $normalized) && !str_contains($normalized, 'apa saja') && !str_contains($normalized, 'siapa saja') && !preg_match('/\bdaftar\b/iu', $normalized)) {
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
            $this->storeSessionCount('categories', [], $count);
            return [
                'handled' => true,
                'reply'   => "Total kategori barang: **{$count}**.",
                'tokens'  => 0,
                'intent'  => 'categories_count',
                'status'  => 'success',
            ];
        }

        // 1.4 Rekap Siapa Saja yang Melakukan Laporan ("siapa saja yang melakukan laporan?")
        if (preg_match('/^(siapa saja yang (melakukan|buat|mengirim|punya) laporan|siapa saja yang melapor|siapa (saja )?pelapornya|daftar pelapor( laporan)?)\??$/iu', $normalized)) {
            if ($role === 'civitas') {
                return [
                    'handled' => true,
                    'reply'   => 'Data rekap pelapor lain berada di luar hak akses peran Civitas Anda.',
                    'tokens'  => 0,
                    'intent'  => 'civitas_rbac_denied',
                    'status'  => 'denied',
                ];
            }
            if ($role === 'guest') {
                return [
                    'handled' => true,
                    'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'tokens'  => 0,
                    'intent'  => 'guest_restricted',
                    'status'  => 'denied',
                ];
            }

            $reporters = $this->metricService->getReportersSummary(10);
            if (empty($reporters)) {
                return [
                    'handled' => true,
                    'reply'   => 'Belum ada data pelapor yang tercatat dalam sistem.',
                    'tokens'  => 0,
                    'intent'  => 'reporters_summary',
                    'status'  => 'success',
                ];
            }

            $reportersTable = $this->presenter->presentReportersTable($reporters);
            return [
                'handled' => true,
                'reply'   => $reportersTable,
                'tokens'  => 0,
                'intent'  => 'reporters_summary',
                'status'  => 'success',
            ];
        }

        // 1.5 Pertanyaan Lanjutan Kontekstual ("dari siapa?", "jam berapa?", "coba apa saja?", "tapi kan saya admin?", "bisa saya lihat rinciannya?")
        $followUp = $this->handleFollowUp($normalized, $role, $userId);
        if ($followUp !== null) {
            return [
                'handled' => true,
                'reply'   => $followUp,
                'tokens'  => 0,
                'intent'  => 'follow_up',
                'status'  => 'success',
            ];
        }

        // 1.6 Panduan & FAQ Resmi (Diskusi internal, lokasi posko, alur lapor, alur klaim)
        $faqCheck = $this->checkOfficialFaq($normalized);
        if ($faqCheck !== null) {
            return $faqCheck;
        }

        // 1.7 Pembatasan Role Tamu (Guest): Menjawab dari FAQ dengan aturan (tanpa AI),
        // maks 3 kalimat, dan jujur bila tidak ada jawabannya di panduan resmi (Prompt 35C).
        if ($role === 'guest' || $userId === null) {
            // Jika tamu bertanya data operasional, tolak sopan
            if (preg_match('/\b(klaim|pengajuan|barang di posko|barang di rak|daftar user|pengguna|audit log|laporan masuk|laporan hari ini)\b/iu', $normalized)) {
                return [
                    'handled' => true,
                    'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                    'tokens'  => 0,
                    'intent'  => 'guest_restricted',
                    'status'  => 'denied',
                ];
            }

            // Pertanyaan di luar FAQ resmi dijawab jujur maks 3 kalimat tanpa AI
            $honestReply = 'Maaf, saya tidak menemukan jawaban untuk pertanyaan tersebut dalam panduan resmi. Silakan hubungi Petugas di Posko Lost & Found Center (Gedung Rektorat Lt. 1) atau hubungi Administrator kampus untuk informasi lebih lanjut.';
            return [
                'handled' => true,
                'reply'   => $honestReply,
                'tokens'  => 0,
                'intent'  => 'guest_faq_honest_fallback',
                'status'  => 'success',
            ];
        }

        // ══════════════════════════════════════════════════════════════
        // TAHAP 2: PERENCANA (PLANNER)
        // Menghasilkan 1-3 query semantik (fast-path regex atau AI Planner)
        // ══════════════════════════════════════════════════════════════
        $recentHistory = $this->getRecentSessionHistory();
        $planResult = $this->planner->plan($clean, $role, $recentHistory);

        if (!$planResult['success'] || empty($planResult['plan'])) {
            $branch = $planResult['branch'] ?? ($planResult['error'] ?? 'unsupported');
            $tokensUsed = (int)($planResult['tokens_used'] ?? 0);
            $this->logUsageBranch($userId, $role, 'ambigu', $tokensUsed, "error [branch: {$branch}]");

            return [
                'handled' => true,
                'reply'   => 'C-Find sedang sibuk, coba lagi.',
                'tokens'  => $tokensUsed,
                'intent'  => 'unsupported',
                'status'  => 'error',
                'branch'  => $branch,
            ];
        }

        $plan = $planResult['plan'];
        $tokensUsed = (int)($planResult['tokens_used'] ?? 0);

        // ══════════════════════════════════════════════════════════════
        // TAHAP 3: VALIDATOR BACKEND
        // JSON Schema ketat: tolak entitas/field di luar role, batasi limit & time range
        // ══════════════════════════════════════════════════════════════
        $validation = $this->validator->validate($plan, $role);

        if (!$validation['valid']) {
            $reason = $validation['reason'] ?? 'invalid';

            if ($reason === 'denied_role' || $reason === 'forbidden_field' || $reason === 'denied_field') {
                $deniedReply = $this->presenter->presentDenied($role, $validation['error'] ?? '');
                return [
                    'handled' => true,
                    'reply'   => $deniedReply,
                    'tokens'  => $tokensUsed,
                    'intent'  => 'permission_denied',
                    'status'  => 'denied',
                ];
            }

            // Jika query tidak menghasilkan rencana data tabel (empty_queries / unsupported) dan pengguna bukan guest,
            // delegasikan ke Tier 2 AI reasoning di CFindService agar diproses secara kontekstual
            if ($role !== 'guest' && ($reason === 'empty_queries' || ($plan['format'] ?? '') === 'unsupported')) {
                return null;
            }

            $unsupportedReply = $this->presenter->presentUnsupported($role);
            return [
                'handled' => true,
                'reply'   => $unsupportedReply,
                'tokens'  => $tokensUsed,
                'intent'  => 'invalid_plan',
                'status'  => 'unsupported',
            ];
        }

        $validatedPlan = $validation['validated_plan'];

        // ══════════════════════════════════════════════════════════════
        // TAHAP 4: KOMPILATOR BACKEND & EKSEKUSI REALTIME
        // Eksekusi prepared statement SQL ter-parameter dengan isolasi user_id/role
        // ══════════════════════════════════════════════════════════════
        $queryResults = $this->compiler->executePlan($validatedPlan, $role, $userId);

        // ══════════════════════════════════════════════════════════════
        // TAHAP 5: PENYAJI BACKEND (PRESENTER)
        // Format jawaban singkat & tepat (single number, HTML table, combined)
        // ══════════════════════════════════════════════════════════════
        $finalReply = $this->presenter->present($validatedPlan, $queryResults, $clean, $role);

        // Simpan konteks untuk pertanyaan lanjutan di sesi server
        $this->storeSessionContext($validatedPlan, $queryResults, $clean);

        $statusStr = 'success';
        if (!empty($planResult['fallback_rule'])) {
            $statusStr = 'success [branch: fallback_rule_applied]';
            $this->logUsageBranch($userId, $role, 'fallback_rule', $tokensUsed, $statusStr);
        }
        $elapsed = round(microtime(true) - $startTime, 3);
        $this->logDataAgentExecution($userId, $role, $validatedPlan, $elapsed, $statusStr);

        return [
            'handled' => true,
            'reply'   => $finalReply,
            'tokens'  => $tokensUsed,
            'intent'  => 'data_agent_query',
            'status'  => 'success',
        ];
    }

    /**
     * Penanganan Langsung Kunci Metrik Deterministik (Chip Cepat Prompt 29C-3)
     */
    private function handleDeterministicMetricKey(string $key, string $role, ?int $userId, ?array $sessionUser): ?array
    {
        $key = trim(strtolower($key));

        switch ($key) {
            // 1. Total Pengguna Terdaftar (Hanya Admin)
            case 'users_count':
                if ($role !== 'admin') {
                    $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $usersCount = (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $reply = "Total pengguna terdaftar: **{$usersCount}**.";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'users']]], [['total' => $usersCount]], 'metric:users_count');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_users_count', 'status' => 'success'];

            // 2. Daftar Pengguna Sistem (Hanya Admin)
            case 'users_list':
                if ($role !== 'admin') {
                    $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $plan = [
                    'format'  => 'table',
                    'queries' => [
                        [
                            'entity'    => 'users',
                            'select'    => ['name', 'email', 'role_name', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Daftar Pengguna Sistem',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:users_list', $role);
                $this->storeSessionContext($plan, $res, 'metric:users_list', 'users');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_users_list', 'status' => 'success'];

            // 3. Aktivitas Login Hari Ini (Hanya Admin)
            case 'login_activity':
                if ($role !== 'admin') {
                    $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $plan = [
                    'format'  => 'table',
                    'queries' => [
                        [
                            'entity'    => 'login_activity',
                            'select'    => ['actor_name', 'action', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Aktivitas Login Pengguna',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:login_activity', $role);
                $this->storeSessionContext($plan, $res, 'metric:login_activity', 'login_activity');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_login_activity', 'status' => 'success'];

            // 4. Total Laporan Sistem (Admin & Petugas)
            case 'reports_count':
                if ($role === 'civitas' || $role === 'guest') {
                    $reply = $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Data rekap seluruh laporan berada di luar hak akses peran Civitas Anda.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $repStats = $this->metricService->getReportsStats();
                $reply = "Total laporan sistem: **{$repStats['total']}** (Kehilangan: {$repStats['lost']}, Temuan: {$repStats['found']}).";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'reports']]], [['total' => $repStats['total']]], 'metric:reports_count', 'reports', (int)$repStats['total']);
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_reports_count', 'status' => 'success'];

            // 5. Total Barang di Posko (Semua Role Boleh Tahu Jumlah Barang di Posko)
            case 'posko_items_count':
                $centerStats = $this->metricService->getCenterItemsStats();
                $parts = !empty($centerStats['parts_text']) ? " ({$centerStats['parts_text']})" : '';
                $reply = "Total barang di Posko Center: **{$centerStats['total']}** barang{$parts}.";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'posko_items']]], [['total' => $centerStats['total']]], 'metric:posko_items_count', 'posko_items', (int)$centerStats['total']);
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_posko_items_count', 'status' => 'success'];

            // 5b. Total Barang Tersimpan di Rak (Admin & Petugas)
            case 'stored_items_count':
                $storedStats = $this->metricService->getStoredOnlyStats();
                $reply = "Total barang tersimpan di rak: **{$storedStats['total']}** barang.";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'stored_items']]], [['total' => $storedStats['total']]], 'metric:stored_items_count', 'stored_items', (int)$storedStats['total']);
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_stored_items_count', 'status' => 'success'];

            // 6. Daftar Barang di Posko (Admin & Petugas)
            case 'posko_items_list':
                if ($role === 'civitas' || $role === 'guest') {
                    $reply = $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Daftar detail barang posko internal berada di luar hak akses peran Civitas Anda. Silakan lihat katalog publik.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $plan = [
                    'format'  => 'table',
                    'queries' => [
                        [
                            'entity'    => 'posko_items',
                            'select'    => ['item_code', 'title', 'category_name', 'status', 'registered_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'registered_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Barang di Posko Center',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:posko_items_list', $role);
                $this->storeSessionContext($plan, $res, 'metric:posko_items_list', 'posko_items');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_posko_items_list', 'status' => 'success'];

            // 7. Total Pengajuan Klaim (Admin & Petugas)
            case 'claims_count':
                if ($role === 'civitas' || $role === 'guest') {
                    $reply = $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Data seluruh pengajuan klaim berada di luar hak akses peran Civitas Anda.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $claimsStats = $this->metricService->getClaimsStats();
                $reply = "Total pengajuan klaim: **{$claimsStats['total']}** klaim ({$claimsStats['summary_text']}).";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'claims_queue']]], [['total' => $claimsStats['total']]], 'metric:claims_count', 'claims_queue', (int)$claimsStats['total']);
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_claims_count', 'status' => 'success'];

            // 8. Klaim Selesai (Admin & Petugas)
            case 'claims_completed':
                if ($role === 'civitas' || $role === 'guest') {
                    $reply = $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Data pengajuan klaim berada di luar hak akses peran Civitas Anda.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $claimsStats = $this->metricService->getClaimsStats();
                $reply = "Total Klaim Selesai: **{$claimsStats['completed']}** (Barang telah diserahkan kembali kepada pemiliknya).";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'claims_queue']]], [['total' => $claimsStats['completed']]], 'metric:claims_completed', 'claims_queue', (int)$claimsStats['completed']);
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_claims_completed', 'status' => 'success'];

            // 9. Daftar Pengajuan Klaim (Admin & Petugas)
            case 'claims_list':
                if ($role === 'civitas' || $role === 'guest') {
                    $reply = $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Data pengajuan klaim orang lain berada di luar hak akses peran Civitas Anda.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $plan = [
                    'format'  => 'table',
                    'queries' => [
                        [
                            'entity'    => 'claims_queue',
                            'select'    => ['claim_number', 'item_title', 'claimant_name', 'status', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Pengajuan Klaim',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:claims_list', $role);
                $this->storeSessionContext($plan, $res, 'metric:claims_list', 'claims_queue');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_claims_list', 'status' => 'success'];

            // 10. Rekap Pelapor (Admin & Petugas)
            case 'reporters_summary':
                if ($role === 'civitas' || $role === 'guest') {
                    $reply = $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Data rekap pelapor lain berada di luar hak akses peran Civitas Anda.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $reporters = $this->metricService->getReportersSummary(50);
                if (empty($reporters)) {
                    return ['handled' => true, 'reply' => 'Belum ada data pelapor yang tercatat dalam sistem.', 'tokens' => 0, 'intent' => 'reporters_summary', 'status' => 'success'];
                }
                $reply = $this->presenter->presentReportersTable($reporters);
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'reporters_summary', 'status' => 'success'];

            // 11. Status Klaim Saya (Civitas)
            case 'my_claims':
                if ($role !== 'civitas') {
                    if ($role === 'guest') {
                        return ['handled' => true, 'reply' => 'Silakan masuk (login) terlebih dahulu untuk melihat status pengajuan klaim Anda.', 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                    }
                    return $this->handleDeterministicMetricKey('claims_list', $role, $userId, $sessionUser);
                }
                $plan = [
                    'format'  => 'combined',
                    'queries' => [
                        [
                            'entity'    => 'my_claims',
                            'select'    => ['id'],
                            'aggregate' => 'count',
                            'label'     => 'Klaim Milik Saya',
                        ],
                        [
                            'entity'    => 'my_claims',
                            'select'    => ['claim_number', 'status', 'created_at'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'created_at', 'direction' => 'DESC']],
                            'limit'     => 50,
                            'label'     => 'Riwayat Klaim Saya',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:my_claims', $role);
                $this->storeSessionContext($plan, $res, 'metric:my_claims', 'my_claims');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_my_claims', 'status' => 'success'];
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_my_claims', 'status' => 'success'];

            // 12. Barang di Katalog Publik (Semua Role)
            case 'public_catalog':
                $count = (int)$this->pdo->query("SELECT COUNT(*) FROM items WHERE status = 'stored'")->fetchColumn();
                $reply = "Total barang temuan di katalog publik: **{$count}** barang.";
                $this->storeSessionContext(['format' => 'number', 'queries' => [['entity' => 'public_catalog']]], [['total' => $count]], 'metric:public_catalog');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_public_catalog', 'status' => 'success'];

            // 13. FAQ Cara Lapor Hilang (Semua Role)
            case 'faq_lost_report':
                $reply = "Masuk ke akun Anda, buka menu **Laporan** > **Lapor Barang Hilang** (`/reports/lost/create`), isi rincian dan lokasi barang, lalu klik **Terbitkan Laporan**.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_lost_report', 'status' => 'success'];

            // 14. FAQ Cara Klaim (Semua Role)
            case 'faq_claim_guide':
                $reply = "Buka detail barang temuan yang sesuai di katalog publik (`/reports/found/{id}`), lalu klik **Ini Barang Saya! (Ajukan Klaim)** dan sertakan bukti kepemilikan privat.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_claim_guide', 'status' => 'success'];

            // 15. FAQ Info Posko (Semua Role)
            case 'posko_info':
                $reply = "Posko Lost & Found Center berada di Gedung Rektorat Lt. 1, buka Senin–Jumat pukul 08:00–16:00 WIB.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_posko_info', 'status' => 'success'];

            // 16. Kategori Barang (Semua Role)
            case 'categories_list':
                $plan = [
                    'format'  => 'combined',
                    'queries' => [
                        [
                            'entity'    => 'categories',
                            'select'    => ['id'],
                            'aggregate' => 'count',
                            'label'     => 'Kategori Barang',
                        ],
                        [
                            'entity'    => 'categories',
                            'select'    => ['name', 'slug'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'name', 'direction' => 'ASC']],
                            'limit'     => 50,
                            'label'     => 'Daftar Kategori Barang',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:categories_list', $role);
                $this->storeSessionContext($plan, $res, 'metric:categories_list');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_categories_list', 'status' => 'success'];

            // 17. Lokasi Kampus (Semua Role)
            case 'locations_list':
                $plan = [
                    'format'  => 'combined',
                    'queries' => [
                        [
                            'entity'    => 'locations',
                            'select'    => ['id'],
                            'aggregate' => 'count',
                            'label'     => 'Lokasi Kampus',
                        ],
                        [
                            'entity'    => 'locations',
                            'select'    => ['name', 'building_name'],
                            'aggregate' => 'none',
                            'order'     => [['field' => 'name', 'direction' => 'ASC']],
                            'limit'     => 50,
                            'label'     => 'Daftar Lokasi Kampus',
                        ],
                    ],
                ];
                $res = $this->compiler->executePlan($plan, $role, $userId);
                $reply = $this->presenter->present($plan, $res, 'metric:locations_list', $role);
                $this->storeSessionContext($plan, $res, 'metric:locations_list');
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_locations_list', 'status' => 'success'];

            // 18. Lemari dan Rak Penyimpanan (Petugas & Admin)
            case 'storage_locations':
                if ($role === 'guest' || $role === 'civitas') {
                    $reply = ($role === 'guest')
                        ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.'
                        : 'Data tersebut berada di luar hak akses peran Civitas Anda.';
                    return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'rbac_denied', 'status' => 'denied'];
                }
                $count = (int)$this->pdo->query("SELECT COUNT(*) FROM storage_locations WHERE is_active = 1")->fetchColumn();
                $reply = "Total lemari dan rak penyimpanan: **{$count}**.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'metric_storage_locations', 'status' => 'success'];

            default:
                return null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // A. KEAMANAN & PENOLAKAN KREDENSIAL / INJECTION
    // ─────────────────────────────────────────────────────────────

    private function checkSecurityViolation(string $norm, string $role): ?array
    {
        // 1. Password, Hash, Token, Kredensial (Kecualikan pertanyaan panduan/FAQ lupa/ubah password)
        $isPasswordFaq = (bool) preg_match('/\b(lupa password|lupa kata sandi|reset password|lupa sandi|ganti password|ubah kata sandi|ganti kata sandi|cara login|login dengan apa|kata sandi sementara)\b/iu', $norm);
        if (!$isPasswordFaq && preg_match('/\b(password|kata sandi|hash password|password_hash|daftar password|kredensial|secret key)\b/iu', $norm)) {
            $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'forbidden_credentials', 'status' => 'denied'];
        }

        // 2. Berkas Bukti Kepemilikan Klaim
        if (preg_match('/\b(bukti klaim|isi bukti|tampilkan bukti|lihat bukti|berkas bukti|ownership proof)\b/iu', $norm)) {
            $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'forbidden_claim_evidence', 'status' => 'denied'];
        }

        // 3. Catatan Internal Petugas
        if (preg_match('/\b(catatan internal|internal notes|catatan rahasia petugas|reviewer notes)\b/iu', $norm)) {
            $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'forbidden_internal_notes', 'status' => 'denied'];
        }

        // 4. Isi Chat Pribadi Civitas - Petugas
        if (preg_match('/\b(isi chat civitas|semua pesan chat|baca chat orang|chat pribadi)\b/iu', $norm)) {
            $reply = "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'forbidden_chat_logs', 'status' => 'denied'];
        }

        // 5. Prompt Injection & SQL Injection Attempt
        if (preg_match('/(abaikan instruksi|abaikan aturan|ignore.*instruction|system[_\s-]?prompt|api[_\s-]?key|secret[_\s-]?key|jailbreak|developer mode|bocorkan.*key|bocorkan.*prompt|select\s+\*\s+from|drop\s+table|union\s+select)/iu', $norm)) {
            $reply = "Permintaan tersebut tidak dapat diproses karena melanggar batasan keamanan sistem.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'prompt_injection_blocked', 'status' => 'denied'];
        }

        // 6. Penolakan Role Civitas terhadap Data Pengguna, Klaim Orang Lain, dan Audit Log
        if ($role === 'civitas') {
            if (preg_match('/\b(daftar pengguna|daftar user|siapa saja yang terdaftar|audit log|log audit|klaim orang lain|pengguna terdaftar)\b/iu', $norm)) {
                $reply = "Data tersebut berada di luar hak akses peran Civitas Anda.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'civitas_rbac_denied', 'status' => 'denied'];
            }
        }

        // 7. Penolakan Role Petugas terhadap Aktivitas Login & Akun Sistem
        if ($role === 'petugas') {
            if (preg_match('/\b(aktivitas login|siapa saja yang login|log login|daftar user terdaftar)\b/iu', $norm)) {
                $reply = "Data tersebut berada di luar hak akses peran Petugas Anda.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'petugas_rbac_denied', 'status' => 'denied'];
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // B. IDENTITAS USER & BOT
    // ─────────────────────────────────────────────────────────────

    private function checkIdentityQuestions(string $norm, string $role, ?array $sessionUser, ?int $userId = null): ?array
    {
        // Konfirmasi Login Diri Sendiri ("bukankah saya juga login?", "kan saya juga login?", "apakah saya tercatat login?")
        if (preg_match('/\b(bukankah saya juga login|kan saya juga login|bukannya saya (sudah )?login|apakah saya (tercatat )?login|saya kan login( hari ini)?|saya login jam berapa)\b/iu', $norm)) {
            if ($role === 'guest' || empty($sessionUser) || $userId === null) {
                $reply = "Anda saat ini belum masuk (login) ke sistem dan mengakses sebagai **Pengunjung Tamu**.";
                return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'user_login_confirmation', 'status' => 'success'];
            }

            $name = $sessionUser['name'] ?? 'Pengguna';
            $roleLabel = match ($role) {
                'admin'   => 'Administrator Sistem',
                'petugas' => 'Petugas Lost & Found Center',
                'civitas' => 'Civitas Akademika',
                default   => ucfirst($role),
            };

            // Periksa catatan login di audit_logs
            $stmt = $this->pdo->prepare("
                SELECT created_at FROM audit_logs 
                WHERE user_id = ? AND action IN ('LOGIN_SUCCESS', 'auth_login', 'login', 'user_login')
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$userId]);
            $loginTime = $stmt->fetchColumn();

            if ($loginTime) {
                $dt = new DateTimeImmutable((string)$loginTime);
                $timeWib = $dt->setTimezone($this->timezone)->format('H:i') . ' WIB';
                $reply = "Ya, Anda tercatat aktif dan berhasil login pada hari ini ({$timeWib}) sebagai **{$name}** ({$roleLabel}). Sesi Anda saat ini terautentikasi resmi.";
            } else {
                $reply = "Benar, Anda sedang aktif dan terautentikasi dalam sesi resmi sebagai **{$name}** ({$roleLabel}).";
            }

            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'user_login_confirmation', 'status' => 'success'];
        }

        // "Saya siapa?" / "Siapa saya?" / "Profil saya"
        if (preg_match('/^(saya siapa|siapa saya|siapa aku|nama saya|nama aku|akun saya|profil saya|role saya|peran saya|siapakah saya)\??$/iu', $norm)) {
            if ($role === 'guest' || empty($sessionUser)) {
                $reply = "Anda saat ini mengakses sebagai **Pengunjung Tamu** (belum masuk). Silakan masuk (login) untuk mengakses layanan pelaporan dan klaim pribadi.";
            } else {
                $name = $sessionUser['name'] ?? 'Pengguna';
                $roleLabel = match ($role) {
                    'admin'   => 'Administrator Sistem',
                    'petugas' => 'Petugas Lost & Found Center',
                    'civitas' => 'Civitas Akademika',
                    default   => ucfirst($role),
                };
                $reply = "Anda terautentikasi sebagai **{$name}** (Peran: {$roleLabel}).";
            }
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'identitas_user', 'status' => 'success'];
        }

        // Identitas Bot C-Find
        if (preg_match('/^(siapa kamu|siapa anda|kamu siapa|anda siapa|bot apa|nama kamu|namamu|tentang kamu|apa itu c-find|kamu ini apa)\??$/iu', $norm)) {
            $reply = "Saya **" . CFindService::BOT_NAME . "**, agen data cerdas sistem Lost & Found **Campus Find**. Saya dapat menjawab data operasional posko, inventaris barang, status klaim, laporan, dan panduan resmi kampus sesuai hak akses Anda.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'identitas_bot', 'status' => 'success'];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // C. PANDUAN & FAQ RESMI
    // ─────────────────────────────────────────────────────────────

    private function checkOfficialFaq(string $norm): ?array
    {
        // FAQ Login & Cara Masuk (Prompt 35C)
        if (preg_match('/\b(cara login|bagaimana login|bagaimana cara login|login gimana|masuk akun|login dengan apa|cara masuk akun|cara masuk)\b/iu', $norm) || $norm === 'login' || $norm === 'cara login?' || $norm === 'cara masuk?') {
            $reply = "Anda dapat masuk menggunakan NIM atau alamat email terdaftar beserta kata sandi di halaman Masuk (`/login`). Pastikan akun Anda telah didaftarkan oleh Administrator kampus dan sudah diaktivasi sebelum login. Jika gagal 5 kali berturut-turut, akun terkunci sementara selama 5 menit.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_login_guide', 'status' => 'success'];
        }

        // FAQ Pendaftaran Akun / Belum Terdaftar (Prompt 35C)
        if (preg_match('/\b(cara daftar|bagaimana mendaftar|belum punya akun|daftar akun|registrasi akun|bikin akun|siapa yang mendaftarkan akun|belum terdaftar|buat akun baru)\b/iu', $norm)) {
            $reply = "Akun Campus Find didaftarkan secara resmi oleh Administrator kampus melalui impor data civitas atau penambahan akun manual. Pengguna tidak dapat mendaftar mandiri secara bebas di dalam aplikasi. Silakan hubungi Administrator kampus untuk pendaftaran data akun Anda.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_register_account', 'status' => 'success'];
        }

        // FAQ Aktivasi Akun (Prompt 35C)
        if (preg_match('/\b(cara aktivasi|bagaimana aktivasi|aktivasi akun|cara mengaktifkan akun|aktifkan akun|aktivasi gimana|aktivasi)\b/iu', $norm)) {
            $reply = "Akun baru yang telah didaftarkan Admin dapat diaktivasi mandiri melalui menu Aktivasi Akun (`/activate`) dengan memasukkan NIM atau email untuk menerima kode OTP 6 digit. Anda juga bisa diaktivasi dengan kata sandi sementara yang disetel oleh Admin dan wajib diganti saat login pertama. Setelah kata sandi ditetapkan, akun Anda otomatis aktif dan siap digunakan.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_activation_guide', 'status' => 'success'];
        }

        // FAQ Lupa Kata Sandi (Prompt 35C)
        if (preg_match('/\b(lupa password|lupa kata sandi|reset password|lupa sandi|bagaimana jika lupa password|cara reset kata sandi|lupa kata sandi gimana)\b/iu', $norm)) {
            $reply = "Buka halaman login lalu pilih tautan Lupa password (`/forgot-password`) dan masukkan NIM atau email terdaftar Anda. Sistem akan mengirimkan kode verifikasi OTP 6 digit ke alamat email kampus Anda untuk membuat kata sandi baru. Kode verifikasi OTP tersebut berlaku selama 5 menit.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_forgot_password', 'status' => 'success'];
        }

        // FAQ Aturan Kunci Akun (Prompt 35C)
        if (preg_match('/\b(akun terkunci|kenapa akun terkunci|aturan kunci akun|kunci akun|terlalu banyak percobaan gagal|akun diblokir sementara|kenapa terkunci)\b/iu', $norm)) {
            $reply = "Akun atau perangkat akan terkunci sementara selama 5 menit jika terjadi 5 kali percobaan login yang gagal berturut-turut. Sistem menampilkan pesan kredensial generik demi mencegah upaya penebakan akun terdaftar. Anda dapat mencoba masuk kembali secara normal setelah masa penguncian berakhir.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_account_lockout', 'status' => 'success'];
        }

        // FAQ Diskusi Internal
        if (preg_match('/\b(diskusi internal|diskusi internal gimana|chat dengan petugas|cara chat|fitur diskusi|pesan internal)\b/iu', $norm)) {
            $reply = "Ruang obrolan dibuat otomatis saat Anda menekan tombol **Diskusi** pada detail laporan atau klaim Anda. Percakapan dapat diakses melalui menu **Pesan** (`/chats`) antara pelapor/pengklaim dan Petugas.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_internal_discussion', 'status' => 'success'];
        }

        // FAQ Lokasi Posko & Jam Operasional
        if (preg_match('/^(posko|info posko)\??$/iu', $norm) || preg_match('/\b(lokasi posko|jam operasional|jam buka|buka jam berapa|alamat posko|dimana posko|gedung rektorat|posko buka|posko dimana)\b/iu', $norm)) {
            $reply = "Posko Lost & Found Center berada di Gedung Rektorat Lt. 1, buka Senin–Jumat pukul 08:00–16:00 WIB.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_posko_info', 'status' => 'success'];
        }

        // FAQ Cara Lapor Barang Hilang
        if (preg_match('/\b(cara lapor barang hilang|bagaimana lapor hilang|alur lapor hilang|lapor kehilangan)\b/iu', $norm)) {
            $reply = "Masuk ke akun Anda, buka menu **Laporan** > **Lapor Barang Hilang** (`/reports/lost/create`), isi rincian dan lokasi barang, lalu klik **Terbitkan Laporan**.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_lost_report', 'status' => 'success'];
        }

        // FAQ Cara Klaim Barang
        if (preg_match('/\b(cara (mengajukan )?klaim|bagaimana klaim|alur klaim|syarat klaim)\b/iu', $norm)) {
            $reply = "Buka detail barang temuan yang sesuai di katalog publik (`/reports/found/{id}`), lalu klik **Ini Barang Saya! (Ajukan Klaim)** dan sertakan bukti kepemilikan privat.";
            return ['handled' => true, 'reply' => $reply, 'tokens' => 0, 'intent' => 'faq_claim_guide', 'status' => 'success'];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // D. PERTANYAAN LANJUTAN KONTEKSTUAL (MULTI-TURN & HIMPUNAN PROMPT 36A)
    // ─────────────────────────────────────────────────────────────

    /**
     * Deteksi apakah kueri adalah pertanyaan lanjutan atau rujukan himpunan sebelumnya (Prompt 36A)
     */
    private function isSetFollowUpQuery(string $norm): bool
    {
        $cleanNorm = trim($norm);

        // 1. Rujukan nomor ke-N atau anafora kata ganti himpunan
        if (preg_match('/\b(ke-?\d+|tsb|tersebut|mereka)\b/iu', $cleanNorm)) {
            return true;
        }

        // 2. Pertanyaan rujukan peran / pengaju / siapa / rincian / detail
        if (preg_match('/\b(siapa saja|siapa aja|siapa yang|apa saja|apa aja|coba apa saja|apa saja rinciannya|siapa saja rinciannya|siapa saja yang mengajukan|siapa pengajunya|siapa pelapornya|siapa pemohonnya|yang mana saja)\b/iu', $cleanNorm)) {
            return true;
        }

        if (preg_match('/\b(rincian|rinciannya|detail|detailnya|lengkap|lengkapnya|selengkapnya|rincian lengkap|detail lengkap|bisa lebih detail|tampilkan detailnya|bagaimana dengan datanya|berikan informasi lebih lanjut|berikan daftar lengkap|tampilkan daftarnya|daftarnya)\b/iu', $cleanNorm)) {
            return true;
        }

        if (preg_match('/\b(tampilkan semua|lihat semua|tampilkan seluruhnya|lihat seluruhnya|muat semua|tampilkan lengkap|tampilkan secara lengkap|semua barang di posko|berikan seluruh daftar)\b/iu', $cleanNorm) || $cleanNorm === 'semua' || $cleanNorm === 'semuanya') {
            return true;
        }

        if (preg_match('/^(?:dari siapa|jam berapa|kapan|pukul berapa|waktunya)\??$/iu', $cleanNorm)) {
            return true;
        }

        if (preg_match('/\b(tapi kan saya admin|kan saya admin|saya kan admin|bukannya saya admin)\b/iu', $cleanNorm)) {
            return true;
        }

        // 3. Rujukan kata benda jamak + itu/tersebut/tsb (misal: "laporan itu", "klaim tersebut", "pengguna tadi")
        if (preg_match('/\b(laporan|klaim|pengguna|user|barang|item|civitas|pelapor|pengaju)\s+(?:itu|ini|tersebut|tsb|tadi|mereka)\b/iu', $cleanNorm)) {
            return true;
        }

        return false;
    }

    /**
     * Ambil data gabungan laporan kehilangan dan temuan (Prompt 36A)
     * Menggabungkan lost_reports dan found_reports ke dalam satu tabel terpadu hingga 50 baris default
     */
    private function getUnifiedReports(array $filters = [], string $mode = 'standard', int $limit = 50): array
    {
        $whereLost = [];
        $whereFound = [];
        $params = [];

        // Filter rentang hari ini jika ada
        foreach ($filters as $f) {
            if (($f['field'] ?? '') === 'created_at' && ($f['value'] ?? '') === 'today') {
                $tz = new DateTimeZone('Asia/Jakarta');
                $start = (new \DateTime('today', $tz))->format('Y-m-d 00:00:00');
                $end = (new \DateTime('today', $tz))->format('Y-m-d 23:59:59');
                $whereLost[] = "lr.created_at >= :start_lost AND lr.created_at <= :end_lost";
                $whereFound[] = "fr.created_at >= :start_found AND fr.created_at <= :end_found";
                $params['start_lost'] = $start;
                $params['end_lost'] = $end;
                $params['start_found'] = $start;
                $params['end_found'] = $end;
            }
        }

        $whereLostSql = !empty($whereLost) ? 'WHERE ' . implode(' AND ', $whereLost) : '';
        $whereFoundSql = !empty($whereFound) ? 'WHERE ' . implode(' AND ', $whereFound) : '';

        $sql = "
            SELECT 
                'Kehilangan' AS report_type,
                lr.report_number,
                lr.title,
                COALESCE(c.name, '-') AS category_name,
                COALESCE(l.name, '-') AS location_name,
                lr.status,
                lr.lost_date AS report_date,
                lr.created_at,
                COALESCE(u.name, 'Civitas') AS reporter_name
            FROM lost_reports lr
            LEFT JOIN categories c ON lr.category_id = c.id
            LEFT JOIN locations l ON lr.last_seen_location_id = l.id
            LEFT JOIN users u ON lr.reporter_user_id = u.id
            {$whereLostSql}
            UNION ALL
            SELECT 
                'Temuan' AS report_type,
                fr.report_number,
                COALESCE(i.title, fr.title) AS title,
                COALESCE(c.name, '-') AS category_name,
                COALESCE(l.name, '-') AS location_name,
                fr.status,
                fr.found_date AS report_date,
                fr.created_at,
                COALESCE(u.name, 'Penemu') AS reporter_name
            FROM found_reports fr
            LEFT JOIN items i ON fr.item_id = i.id
            LEFT JOIN categories c ON (COALESCE(fr.category_id, i.category_id) = c.id)
            LEFT JOIN locations l ON fr.location_id = l.id
            LEFT JOIN users u ON fr.reporter_user_id = u.id
            {$whereFoundSql}
            ORDER BY created_at DESC
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tentukan kolom berdasarkan mode pertanyaan (Prompt 36A)
        $columns = match ($mode) {
            'what' => [
                'report_number' => 'Nomor Laporan',
                'title'         => 'Judul / Nama Barang',
                'report_type'   => 'Jenis Laporan',
                'status'        => 'Status',
            ],
            'when' => [
                'report_number' => 'Nomor Laporan',
                'title'         => 'Judul / Nama Barang',
                'report_type'   => 'Jenis Laporan',
                'report_date'   => 'Waktu Kejadian',
                'created_at'    => 'Waktu Pencatatan',
            ],
            'full' => [
                'report_number' => 'Nomor Laporan',
                'title'         => 'Judul / Nama Barang',
                'report_type'   => 'Jenis Laporan',
                'category_name' => 'Kategori',
                'location_name' => 'Lokasi',
                'status'        => 'Status',
                'created_at'    => 'Waktu Dibuat',
            ],
            default => [
                'report_number' => 'Nomor Laporan',
                'title'         => 'Judul / Nama Barang',
                'report_type'   => 'Jenis Laporan',
                'category_name' => 'Kategori',
                'status'        => 'Status',
            ],
        };

        return [
            'columns' => $columns,
            'rows'    => array_map(function ($r) {
                return [
                    'report_type'   => $r['report_type'],
                    'report_number' => $r['report_number'],
                    'title'         => $r['title'],
                    'category_name' => $r['category_name'],
                    'location_name' => $r['location_name'],
                    'status'        => $this->translateReportStatus((string) $r['status']),
                    'report_date'   => !empty($r['report_date']) ? $this->formatDateOnlyIndonesian((string)$r['report_date']) : '-',
                    'created_at'    => $this->formatDateTimeIndonesian((string) $r['created_at']),
                    'reporter_name' => $r['reporter_name'],
                ];
            }, $rows),
            'total'   => count($rows),
        ];
    }

    private function handleFollowUp(string $norm, string $role, ?int $userId, string $rawQuery = ''): array|string|null
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // 0. Pastikan ini adalah pertanyaan lanjutan / anafora himpunan
        if (!$this->isSetFollowUpQuery($norm)) {
            return null;
        }

        // 1. "Tapi kan saya admin?"
        if (preg_match('/\b(tapi kan saya admin|kan saya admin|saya kan admin|bukannya saya admin)\b/iu', $norm)) {
            if ($role === 'admin') {
                return "Benar, sebagai **Administrator**, Anda memiliki akses penuh ke seluruh data operasional sistem, termasuk data pengguna terdaftar, antrean klaim posko, inventaris barang, dan jejak audit log keamanan. Silakan sebutkan data spesifik yang ingin Anda periksa.";
            }
            return "Peran aktif Anda saat ini bukan Administrator.";
        }

        $ctx = $_SESSION['cfind_context_' . $role] ?? null;
        if ($ctx === null && isset($_SESSION['cfind_last_context']) && is_array($_SESSION['cfind_last_context'])) {
            if (isset($_SESSION['cfind_last_context']['user_role']) && $_SESSION['cfind_last_context']['user_role'] === $role) {
                $ctx = $_SESSION['cfind_last_context'];
            }
        }

        $lastEntity = $ctx['entity'] ?? null;
        if (!$lastEntity) {
            return "Maaf, konteks percakapan sebelumnya tidak tersedia atau sudah kedaluwarsa untuk peran Anda. Silakan sebutkan data spesifik yang ingin Anda periksa (misalnya: laporan kehilangan, barang temuan, atau status klaim).";
        }

        // 2. Deteksi Jenis Pertanyaan (Prompt 36A):
        // "siapa" -> hanya nama (dan jumlah bila relevan; <= 10 nama cukup satu kalimat/daftar bernomor singkat, selebihnya tabel No + Nama + Jumlah)
        // "apa" -> nama/judul item
        // "kapan" -> waktu
        // "rinciannya" -> kolom ringkas standar
        // "lengkap" -> kolom tambahan (tidak pernah mengurangi baris)
        $isWho = (bool)preg_match('/\b(siapa|siapa saja|siapa aja|pengaju|pengklaim|pelapor|nama|namanya|dari siapa)\b/iu', $norm);
        $isWhat = (bool)preg_match('/\b(apa saja|apa aja|coba apa saja|apa|judul|nama barang|barang apa)\b/iu', $norm);
        $isWhen = (bool)preg_match('/\b(kapan|jam berapa|pukul berapa|waktu|waktunya|tanggal)\b/iu', $norm);
        $isFull = (bool)preg_match('/\b(lengkap|lengkapnya|detail|detailnya|selengkapnya|rincian lengkap|detail lengkap)\b/iu', $norm);
        $isShowAll = (bool)preg_match('/\b(tampilkan semua|lihat semua|semua|semuanya|muat semua)\b/iu', $norm);

        // ══════════════════════════════════════════════════════════════
        // ENTITAS A: KLAIM (claims_queue / my_claims)
        // ══════════════════════════════════════════════════════════════
        if ($lastEntity === 'claims_queue' || $lastEntity === 'my_claims' || str_contains((string)$lastEntity, 'claim')) {
            if ($role === 'civitas') {
                return "Klaim tersebut adalah pengajuan Anda sendiri.";
            }
            if ($role === 'guest') {
                return "Data tersebut berada di luar hak akses peran Tamu Anda.";
            }

            // Pertanyaan "siapa" (Prompt 36A: "siapa saja yang mengajukan klaim?" -> "Pengaju klaim (2): Fraa, Siti Nurhaliza.")
            if ($isWho && !$isFull) {
                $sql = "SELECT DISTINCT users.name 
                        FROM claims 
                        JOIN users ON claims.claimant_user_id = users.id 
                        WHERE users.name IS NOT NULL 
                        ORDER BY users.name ASC";
                $names = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
                $names = array_values(array_filter($names));
                $countNames = count($names);

                if ($countNames === 0) {
                    return "Tidak ada data pengajuan klaim dalam sistem.";
                }

                if ($countNames <= 10) {
                    $nameList = implode(', ', $names);
                    return "Pengaju klaim ({$countNames}): {$nameList}.";
                }

                // Jika > 10 nama: tabel No + Nama + Jumlah
                $sqlGrouped = "SELECT users.name, COUNT(claims.id) as claim_count 
                               FROM claims 
                               JOIN users ON claims.claimant_user_id = users.id 
                               GROUP BY users.id, users.name 
                               ORDER BY claim_count DESC, users.name ASC LIMIT 50";
                $rowsGrouped = $this->pdo->query($sqlGrouped)->fetchAll(PDO::FETCH_ASSOC);
                $qRes = [
                    'columns' => ['name' => 'Nama Pengaju', 'claim_count' => 'Jumlah Klaim'],
                    'rows'    => $rowsGrouped,
                    'total'   => count($rowsGrouped),
                ];
                $tbl = $this->presenter->buildTableBlock($qRes, $norm);
                return [$tbl];
            }

            // Pertanyaan waktu "kapan"
            if ($isWhen) {
                $sqlTimes = "SELECT claims.claim_number, COALESCE(items.title, found_reports.title) as item_title, users.name as claimant_name, claims.created_at 
                             FROM claims 
                             LEFT JOIN items ON claims.item_id = items.id 
                             LEFT JOIN found_reports ON claims.found_report_id = found_reports.id 
                             LEFT JOIN users ON claims.claimant_user_id = users.id 
                             ORDER BY claims.created_at DESC LIMIT 50";
                $rowsTimes = $this->pdo->query($sqlTimes)->fetchAll(PDO::FETCH_ASSOC);
                $qRes = [
                    'columns' => [
                        'claim_number'  => 'Nomor Klaim',
                        'item_title'    => 'Nama Barang',
                        'claimant_name' => 'Pengaju',
                        'created_at'    => 'Waktu Masuk',
                    ],
                    'rows'  => array_map(function($r) {
                        return [
                            'claim_number'  => $r['claim_number'],
                            'item_title'    => $r['item_title'] ?? '-',
                            'claimant_name' => $r['claimant_name'] ?? '-',
                            'created_at'    => $this->formatDateTimeIndonesian((string)$r['created_at']),
                        ];
                    }, $rowsTimes),
                    'total' => count($rowsTimes),
                ];
                $tbl = $this->presenter->buildTableBlock($qRes, $norm);
                return [$tbl];
            }

            // Pertanyaan Rincian / "apa saja rinciannya?" / "lengkap"
            $cols = [
                'claim_number'  => 'Nomor Klaim',
                'item_title'    => 'Nama Barang',
                'claimant_name' => 'Pengaju',
                'status'        => 'Status',
            ];
            if ($isFull) {
                // Lengkap menambah kolom
                $cols['created_at'] = 'Tanggal Pengajuan';
            } else {
                $cols['created_at'] = 'Waktu';
            }

            $sqlClaims = "SELECT claims.claim_number, COALESCE(items.title, found_reports.title) as item_title, users.name as claimant_name, claims.status, claims.created_at 
                          FROM claims 
                          LEFT JOIN items ON claims.item_id = items.id 
                          LEFT JOIN found_reports ON claims.found_report_id = found_reports.id 
                          LEFT JOIN users ON claims.claimant_user_id = users.id 
                          ORDER BY claims.created_at DESC LIMIT 50";
            $rowsClaims = $this->pdo->query($sqlClaims)->fetchAll(PDO::FETCH_ASSOC);
            $qRes = [
                'columns' => $cols,
                'rows'    => array_map(function($r) {
                    return [
                        'claim_number'  => $r['claim_number'],
                        'item_title'    => $r['item_title'] ?? '-',
                        'claimant_name' => $r['claimant_name'] ?? '-',
                        'status'        => $this->translateClaimStatus((string)$r['status']),
                        'created_at'    => $this->formatDateTimeIndonesian((string)$r['created_at']),
                    ];
                }, $rowsClaims),
                'total'   => count($rowsClaims),
            ];
            $this->updateFollowUpContext('claims_queue', count($rowsClaims), [$qRes]);
            $tbl = $this->presenter->buildTableBlock($qRes, $norm);
            return [$tbl];
        }

        // ══════════════════════════════════════════════════════════════
        // ENTITAS B: LAPORAN (reports / lost_reports / found_reports)
        // ══════════════════════════════════════════════════════════════
        if ($lastEntity === 'reports' || $lastEntity === 'lost_reports' || $lastEntity === 'found_reports') {
            if ($role === 'civitas' || $role === 'guest') {
                return $role === 'guest' ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' : 'Data rekap seluruh laporan berada di luar hak akses peran Civitas Anda.';
            }

            // Pertanyaan "siapa"
            if ($isWho && !$isFull) {
                $sql = "SELECT DISTINCT COALESCE(u.name, 'Civitas') as name 
                        FROM (
                            SELECT reporter_user_id as user_id FROM lost_reports WHERE reporter_user_id IS NOT NULL
                            UNION ALL
                            SELECT reporter_user_id as user_id FROM found_reports WHERE reporter_user_id IS NOT NULL
                        ) rep JOIN users u ON rep.user_id = u.id ORDER BY u.name ASC";
                $names = $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
                $names = array_values(array_filter($names));
                $countNames = count($names);
                if ($countNames === 0) {
                    return "Belum ada data pelapor yang tercatat dalam sistem.";
                }
                if ($countNames <= 10) {
                    $nameList = implode(', ', $names);
                    return "Pelapor ({$countNames}): {$nameList}.";
                }
                // Jika > 10: tabel
                $reporters = $this->metricService->getReportersSummary(50);
                return [$this->presenter->presentReportersTable($reporters)];
            }

            $mode = 'standard';
            if ($isFull) {
                $mode = 'full';
            } elseif ($isWhat) {
                $mode = 'what';
            } elseif ($isWhen) {
                $mode = 'when';
            }

            $filters = $ctx['filters'] ?? [];
            $qRes = $this->getUnifiedReports($filters, $mode, 50);
            $this->updateFollowUpContext('reports', $qRes['total'], [$qRes]);
            $tbl = $this->presenter->buildTableBlock($qRes, $norm);
            return [$tbl];
        }

        // ══════════════════════════════════════════════════════════════
        // ENTITAS C: PENGGUNA (users)
        // ══════════════════════════════════════════════════════════════
        if ($lastEntity === 'users') {
            if ($role !== 'admin') {
                return "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
            }

            $cols = [
                'name'      => 'Nama',
                'email'     => 'Email',
                'role_name' => 'Peran',
            ];
            if ($isFull) {
                // Menambah kolom
                $cols['is_active'] = 'Status Akun';
                $cols['created_at'] = 'Terdaftar Sejak';
            }

            $sql = "SELECT u.name, u.email, r.name as role_name, u.is_active, u.created_at 
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.id 
                    ORDER BY u.created_at DESC LIMIT 50";
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            // Jika murni "siapa" tanpa kata rincian dan user <= 10:
            if ($isWho && !$isFull && !str_contains($norm, 'rincian') && !str_contains($norm, 'detail') && count($rows) <= 10) {
                $names = array_column($rows, 'name');
                return "Pengguna (" . count($names) . "): " . implode(', ', $names) . ".";
            }

            $qRes = [
                'columns' => $cols,
                'rows'    => array_map(function($r) {
                    return [
                        'name'       => $r['name'],
                        'email'      => $r['email'],
                        'role_name'  => ucfirst($r['role_name'] ?? 'civitas'),
                        'is_active'  => (int)$r['is_active'] === 1 ? 'Aktif' : 'Nonaktif',
                        'created_at' => $this->formatDateTimeIndonesian((string)$r['created_at']),
                    ];
                }, $rows),
                'total'   => count($rows),
            ];
            $this->updateFollowUpContext('users', count($rows), [$qRes]);
            $tbl = $this->presenter->buildTableBlock($qRes, $norm);
            return [$tbl];
        }

        // ══════════════════════════════════════════════════════════════
        // ENTITAS D: BARANG DI POSKO (posko_items)
        // ══════════════════════════════════════════════════════════════
        if ($lastEntity === 'posko_items') {
            if ($role === 'civitas' || $role === 'guest') {
                return $role === 'guest' 
                    ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' 
                    : 'Daftar detail barang posko internal berada di luar hak akses peran Civitas Anda. Silakan lihat katalog publik.';
            }

            $cols = [
                'item_code'     => 'Kode Barang',
                'title'         => 'Nama Barang',
                'category_name' => 'Kategori',
                'status'        => 'Status',
            ];
            if ($isFull) {
                $cols['storage_location_name'] = 'Tempat Penyimpanan';
                $cols['registered_at']         = 'Waktu Registrasi';
            }

            $statuses = MetricDefinitionService::getPoskoItemStatuses();
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));

            $sql = "SELECT i.item_code, i.title, c.name as category_name, i.status, sl.name as storage_location_name, i.registered_at 
                    FROM items i 
                    LEFT JOIN categories c ON i.category_id = c.id 
                    LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id 
                    WHERE i.status IN ($placeholders) 
                    ORDER BY i.registered_at DESC LIMIT 50";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($statuses);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $qRes = [
                'columns' => $cols,
                'rows'    => array_map(function($r) {
                    return [
                        'item_code'             => $r['item_code'],
                        'title'                 => $r['title'],
                        'category_name'         => $r['category_name'] ?? '-',
                        'status'                => $this->translateItemStatus((string)$r['status']),
                        'storage_location_name' => $r['storage_location_name'] ?? 'Posko Lost & Found',
                        'registered_at'         => $this->formatDateTimeIndonesian((string)$r['registered_at']),
                    ];
                }, $rows),
                'total'   => count($rows),
            ];
            $this->updateFollowUpContext('posko_items', count($rows), [$qRes], $role);
            $tbl = $this->presenter->buildTableBlock($qRes, $norm);
            return [$tbl];
        }

        // ══════════════════════════════════════════════════════════════
        // ENTITAS D2: BARANG TERSIMPAN DI RAK (stored_items)
        // ══════════════════════════════════════════════════════════════
        if ($lastEntity === 'stored_items') {
            if ($role === 'civitas' || $role === 'guest') {
                return $role === 'guest' 
                    ? 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login).' 
                    : 'Daftar detail barang posko internal berada di luar hak akses peran Civitas Anda. Silakan lihat katalog publik.';
            }

            $cols = [
                'item_code'     => 'Kode Barang',
                'title'         => 'Nama Barang',
                'category_name' => 'Kategori',
                'status'        => 'Status',
            ];
            if ($isFull) {
                $cols['storage_location_name'] = 'Tempat Penyimpanan';
                $cols['registered_at']         = 'Waktu Registrasi';
            }

            $sql = "SELECT i.item_code, i.title, c.name as category_name, i.status, sl.name as storage_location_name, i.registered_at 
                    FROM items i 
                    LEFT JOIN categories c ON i.category_id = c.id 
                    LEFT JOIN storage_locations sl ON i.storage_location_id = sl.id 
                    WHERE i.status = 'stored' 
                    ORDER BY i.registered_at DESC LIMIT 50";
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $qRes = [
                'columns' => $cols,
                'rows'    => array_map(function($r) {
                    return [
                        'item_code'             => $r['item_code'],
                        'title'                 => $r['title'],
                        'category_name'         => $r['category_name'] ?? '-',
                        'status'                => $this->translateItemStatus((string)$r['status']),
                        'storage_location_name' => $r['storage_location_name'] ?? 'Posko Lost & Found',
                        'registered_at'         => $this->formatDateTimeIndonesian((string)$r['registered_at']),
                    ];
                }, $rows),
                'total'   => count($rows),
            ];
            $this->updateFollowUpContext('stored_items', count($rows), [$qRes], $role);
            $tbl = $this->presenter->buildTableBlock($qRes, $norm);
            return [$tbl];
        }

        // ══════════════════════════════════════════════════════════════
        // ENTITAS E: AKTIVITAS LOGIN (login_activity)
        // ══════════════════════════════════════════════════════════════
        if ($lastEntity === 'login_activity') {
            if ($role !== 'admin') {
                return "Data tersebut berada di luar hak akses peran " . ucfirst($role) . " Anda.";
            }

            $tz = new DateTimeZone('Asia/Jakarta');
            $todayStart = $ctx['date_range']['start'] ?? (new \DateTimeImmutable('today', $tz))->format('Y-m-d 00:00:00');
            $todayEnd = $ctx['date_range']['end'] ?? (new \DateTimeImmutable('today', $tz))->format('Y-m-d 23:59:59');

            $allowedActions = MetricDefinitionService::getLoginActivityActions();
            $actionPlaceholders = implode(',', array_fill(0, count($allowedActions), '?'));

            $sql = "SELECT 
                al.user_id,
                COALESCE(u.name, 'Pengguna') AS name,
                COALESCE(u.email, '-') AS email,
                COALESCE(r.name, 'civitas') AS role_name,
                COUNT(al.id) AS login_count,
                MAX(al.created_at) AS last_login_at
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE al.created_at >= ? AND al.created_at <= ?
              AND al.action IN ($actionPlaceholders)
            GROUP BY al.user_id, u.name, u.email, r.name
            ORDER BY last_login_at DESC
            LIMIT 50";

            $params = array_merge([$todayStart, $todayEnd], $allowedActions);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $userRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($userRows)) {
                return "Belum ada pengguna yang berhasil login hari ini.";
            }

            // Jika murni "siapa" tanpa kata rincian dan user <= 10:
            if ($isWho && !$isFull && !str_contains($norm, 'rincian') && !str_contains($norm, 'detail') && count($userRows) <= 10) {
                $names = array_column($userRows, 'name');
                return "Pengguna login hari ini (" . count($names) . "): " . implode(', ', $names) . ".";
            }

            $cols = [
                'name'          => 'Nama Pengguna',
                'email'         => 'Email',
                'login_count'   => 'Frekuensi Login',
                'last_login_at' => 'Waktu Login Terakhir',
            ];
            if ($isFull) {
                $cols['role_name'] = 'Peran (Role)';
            }

            $qRes = [
                'columns' => $cols,
                'rows'    => array_map(function($r) {
                    return [
                        'name'          => $r['name'],
                        'email'         => $r['email'],
                        'role_name'     => ucfirst($r['role_name'] ?? 'civitas'),
                        'login_count'   => (string)$r['login_count'],
                        'last_login_at' => $this->formatDateTimeIndonesian((string)$r['last_login_at']),
                    ];
                }, $userRows),
                'total'   => count($userRows),
            ];
            $this->updateFollowUpContext('login_activity', count($userRows), [$qRes], $role);
            $tbl = $this->presenter->buildTableBlock($qRes, $norm);
            return [$tbl];
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // E. PENYIMPANAN KONTEKS & RIWAYAT SESI
    // ─────────────────────────────────────────────────────────────

    /**
     * Simpan konteks percakapan komprehensif ke sesi (Prompt 36A & BugFix 01):
     * {entity, initial_intent, filter_sql, structured_filter, date_range, status_list, group_by, sorting, aggregation, total_records, user_role, created_at}
     */
    private function storeSessionContext(
        array $plan,
        array $results,
        string $rawQuery,
        ?string $explicitEntity = null,
        ?int $explicitTotal = null,
        string $range = 'all',
        string $role = '',
        array $extraMeta = []
    ): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $activeRole = !empty($role) ? $role : $this->currentRole;

        $_SESSION['cfind_last_plan'] = $plan;
        $_SESSION['cfind_last_result'] = $results;
        $_SESSION['cfind_last_query'] = $rawQuery;

        $entity = $explicitEntity ?? ($plan['queries'][0]['entity'] ?? 'unknown');
        if (count($plan['queries'] ?? []) > 1) {
            $entities = array_column($plan['queries'], 'entity');
            if (in_array('lost_reports', $entities, true) && in_array('found_reports', $entities, true)) {
                $entity = 'reports';
            }
        }
        $filters = $plan['queries'][0]['filters'] ?? [];
        $total = $explicitTotal;
        if ($total === null) {
            $totalSum = 0;
            foreach ($results as $r) {
                $totalSum += (int)($r['total'] ?? $r['total_count'] ?? $r['count'] ?? (isset($r['rows']) ? count($r['rows']) : 0));
            }
            $total = $totalSum;
        }

        $structured = [
            'entity'            => $entity,
            'initial_intent'    => $extraMeta['initial_intent'] ?? (($plan['format'] ?? '') === 'number' ? 'count' : 'list'),
            'filter_sql'        => $extraMeta['filter_sql'] ?? '',
            'structured_filter' => $extraMeta['structured_filter'] ?? $filters,
            'filters'           => $filters,
            'range'             => $range,
            'date_range'        => $extraMeta['date_range'] ?? ['type' => $range],
            'status_list'       => $extraMeta['status_list'] ?? [],
            'group_by'          => $extraMeta['group_by'] ?? ($plan['queries'][0]['group_by'] ?? []),
            'sorting'           => $extraMeta['order_by'] ?? ($plan['queries'][0]['order'] ?? []),
            'order_by'          => $extraMeta['order_by'] ?? '',
            'aggregation'       => $extraMeta['aggregation'] ?? ['type' => $plan['queries'][0]['aggregate'] ?? 'none', 'value' => $total],
            'total_records'     => $total,
            'total'             => $total,
            'record_ids'        => $extraMeta['record_ids'] ?? [],
            'user_role'         => $activeRole,
            'created_at'        => time(),
            'last_result'       => $results,
            'last_query'        => $rawQuery,
            'items'             => $results[0]['rows'] ?? [],
            'displayed_count'   => isset($results[0]['rows']) ? count($results[0]['rows']) : (isset($results[0]['total']) ? (int)$results[0]['total'] : $total),
        ];

        $_SESSION['cfind_context_' . $activeRole] = $structured;
        $_SESSION['cfind_last_context'] = $structured;
        $_SESSION['cfind_last_count_entity'] = $entity;
        $_SESSION['cfind_last_count_filters'] = $filters;
        $_SESSION['cfind_last_count_total'] = $total;
    }

    /**
     * Perbarui memori konteks percakapan setelah membalas pertanyaan lanjutan (Prompt 36A & BugFix 01)
     */
    private function updateFollowUpContext(string $entity, int $total, array $results, string $role = '', array $extra = []): void
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $activeRole = !empty($role) ? $role : $this->currentRole;
        $existing = $_SESSION['cfind_context_' . $activeRole] ?? $_SESSION['cfind_last_context'] ?? [];
        $existing['entity'] = $entity;
        $existing['total_records'] = $total;
        $existing['total'] = $total;
        $existing['last_result'] = $results;
        $existing['items'] = $results[0]['rows'] ?? [];
        $existing['displayed_count'] = isset($results[0]['rows']) ? count($results[0]['rows']) : $total;
        $existing['user_role'] = $activeRole;
        $existing['updated_at'] = time();
        foreach ($extra as $k => $v) {
            $existing[$k] = $v;
        }

        $_SESSION['cfind_context_' . $activeRole] = $existing;
        $_SESSION['cfind_last_context'] = $existing;
        $_SESSION['cfind_last_count_entity'] = $entity;
        $_SESSION['cfind_last_count_total'] = $total;
        $_SESSION['cfind_last_plan'] = [
            'format' => 'table',
            'queries' => [['entity' => $entity, 'limit' => 50]]
        ];
    }

    /**
     * Simpan konteks hitungan angka ke sesi untuk pertanyaan rincian lanjutan (Prompt 29E & 36A)
     */
    private function storeSessionCount(string $entity, array $filters, int $total, string $range = 'all'): void
    {
        $plan = [
            'format'  => 'number',
            'queries' => [
                [
                    'entity'    => $entity,
                    'select'    => ['id'],
                    'filters'   => $filters,
                    'aggregate' => 'count',
                    'label'     => $entity,
                ],
            ],
        ];
        $results = [['total_count' => $total, 'total' => $total, 'count' => $total]];
        $this->storeSessionContext($plan, $results, "metric:{$entity}_count", $entity, $total, $range);
    }

    private function translateItemStatus(string $status): string
    {
        $norm = strtolower(trim($status));
        return match ($norm) {
            'stored'    => 'Tersimpan di Posko',
            'claimed'   => 'Telah Diklaim',
            'returned'  => 'Dikembalikan',
            'disposed'  => 'Dimusnahkan',
            default     => ucwords(str_replace('_', ' ', $norm)),
        };
    }

    private function formatDateOnlyIndonesian(string $dateStr): string
    {
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $dateStr;
        }
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $m = (int) date('n', $ts);
        $monthName = $months[$m] ?? date('M', $ts);
        return date('d', $ts) . ' ' . $monthName . ' ' . date('Y', $ts);
    }

    /**
     * Deteksi apakah kueri menanyakan kapasitas / ketersediaan ruang rak atau zona (Prompt 29E & Prompt 36B)
     * Sinonim: stok, tempat, ruang, slot, kapasitas, tersisa, sisa, muat, daya tampung.
     */
    private function isRackCapacityQuery(string $norm): bool
    {
        // Abaikan jika pertanyaan hitungan total lemari atau daftar semua lemari
        if (preg_match('/^(?:ada berapa|berapa|jumlah|total)\s+(?:lemari|rak simpan|rak penyimpanan|tempat simpan|lemari dan rak)(\s+penyimpanan)?\??$/iu', $norm) ||
            $norm === 'ada berapa lemari dan rak?' ||
            $norm === 'ada berapa lemari dan rak penyimpanan?' ||
            $norm === 'berapa lemari dan rak?' ||
            $norm === 'ada berapa rak penyimpanan?') {
            return false;
        }
        if (preg_match('/^(?:apa saja|daftar|list|rincian)\s+(?:lemari|rak simpan|rak penyimpanan|tempat simpan|lemari dan rak)(\s+penyimpanan)?\??$/iu', $norm) ||
            $norm === 'apa saja lemari dan rak penyimpanan?' ||
            $norm === 'daftar rak penyimpanan' ||
            $norm === 'daftar lemari dan rak') {
            return false;
        }

        // 1. Sinonim kapasitas / ketersediaan ruang
        $hasCapSynonym = (bool)preg_match('/\b(kapasitas|daya tampung|stok|tempat|ruang|slot|muat|tersisa|sisa)\b/iu', $norm);

        // 2. Target zona atau rak
        $hasZoneTarget = (bool)preg_match('/\bzona\s+[a-z0-9_]+/iu', $norm);
        $hasRackTarget = (bool)preg_match('/\b(rak|lemari|gudang|kotak|brankas|storage|posko)\b/iu', $norm) ||
                         (bool)preg_match('/\b(stg-[a-z]{2}-\d{2}|rak-[a-z0-9\-]+|[a-z]{1,3}-\d{2})\b/iu', $norm) ||
                         (bool)preg_match('/\b(elektronik|gadget|dokumen|kartu|dompet|kunci|aksesoris|barang umum|baran umum|vip)\b/iu', $norm);

        // Jika ada sinonim kapasitas DAN target zona / rak
        if ($hasCapSynonym && ($hasZoneTarget || $hasRackTarget)) {
            return true;
        }

        // Jika menyebut "zona X" dan kata tanya berapa / ada / tersedia
        if ($hasZoneTarget && preg_match('/\b(berapa|tersedia|ada)\b/iu', $norm)) {
            return true;
        }

        return false;
    }

    /**
     * Resolusi sisa kapasitas rak dan lemari penyimpanan fisik (Prompt 29E & Prompt 36B)
     * Filter zona WAJIB dipetakan ke rak yang zonanya berawalan "Zona X".
     * Beberapa rak dalam satu zona -> tabel kecil (rak, kapasitas, terisi, sisa).
     * Zona tidak ada -> "Zona X tidak ditemukan. Zona yang ada: ...".
     * Definisi terisi disebut singkat dan sama dengan dasbor.
     */
    private function resolveRackCapacity(string $normalized): ?string
    {
        $sql = "
            SELECT sl.*, 
                   COUNT(i.id) AS current_items_count,
                   (sl.capacity - COUNT(i.id)) AS remaining_capacity
            FROM storage_locations sl
            LEFT JOIN items i ON sl.id = i.storage_location_id 
                 AND i.status IN ('stored', 'registered', 'in_inspection', 'in_claim', 'ready_for_handover')
            WHERE sl.is_active = 1
            GROUP BY sl.id
            ORDER BY sl.code ASC
        ";
        $allRacks = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Kumpulkan daftar nama zona unik berurutan alfabetis
        $zoneNames = [];
        foreach ($allRacks as $rack) {
            if (!empty($rack['zone']) && !in_array($rack['zone'], $zoneNames, true)) {
                $zoneNames[] = $rack['zone'];
            }
        }
        sort($zoneNames, SORT_NATURAL | SORT_FLAG_CASE);

        $defTerisi = "Definisi terisi: barang fisik berstatus tersimpan di posko, sedang diklaim, siap diserahkan, baru didaftarkan, atau dalam pemeriksaan sesuai dasbor.";

        // 1. FILTER ZONA ("zona X")
        if (preg_match('/\bzona\s+([a-z0-9_]+(?:\s+[a-z0-9_]+)*)\b/iu', $normalized, $mZone)) {
            $rawZone = trim($mZone[1]);
            // Potong kata pengiring pertanyaan bila menempel
            $stopWords = ['apakah', 'ada', 'yang', 'tersedia', 'tersisa', 'sisa', 'kapasitas', 'stok', 'tempat', 'ruang', 'slot', 'muat', 'berapa'];
            $parts = explode(' ', $rawZone);
            $cleanParts = [];
            foreach ($parts as $p) {
                if (in_array(mb_strtolower($p), $stopWords, true)) {
                    break;
                }
                $cleanParts[] = $p;
            }
            $targetZone = !empty($cleanParts) ? implode(' ', $cleanParts) : $parts[0];

            // Petakan ke rak yang zonanya berawalan "Zona X" (dari data nyata)
            $matchedRacks = [];
            $matchedZoneName = null;
            foreach ($allRacks as $rack) {
                $rackZone = $rack['zone'] ?? '';
                if (preg_match('/^zona\s+' . preg_quote($targetZone, '/') . '\b/iu', $rackZone) ||
                    preg_match('/^zona\s+' . preg_quote($targetZone, '/') . '/iu', $rackZone)) {
                    $matchedRacks[] = $rack;
                    $matchedZoneName = $rackZone;
                }
            }

            // Fallback kecocokan tepat nama zona bila awalan tidak kena
            if (empty($matchedRacks)) {
                foreach ($allRacks as $rack) {
                    $rackZone = $rack['zone'] ?? '';
                    if (mb_strtolower($rackZone) === mb_strtolower("zona " . $targetZone) ||
                        mb_strtolower($rackZone) === mb_strtolower($targetZone)) {
                        $matchedRacks[] = $rack;
                        $matchedZoneName = $rackZone;
                    }
                }
            }

            // Bila Zona TIDAK ADA:
            // "Zona X tidak ditemukan. Zona yang ada: ..." (daftar dari database)
            // Filter yang gagal dipetakan TIDAK BOLEH menghasilkan jawaban untuk rak lain!
            if (empty($matchedRacks)) {
                $existingList = implode(', ', $zoneNames);
                $displayZone = mb_strtoupper($targetZone);
                return "Zona {$displayZone} tidak ditemukan. Zona yang ada: {$existingList}.";
            }

            $this->storeSessionCount('storage_locations', ['zone' => $matchedZoneName], count($matchedRacks));

            // Bila HANYA 1 rak di zona tersebut
            if (count($matchedRacks) === 1) {
                $r = $matchedRacks[0];
                $cap = (int)$r['capacity'];
                $terisi = (int)$r['current_items_count'];
                $sisa = (int)$r['remaining_capacity'];
                return "{$r['name']}: kapasitas {$cap}, terisi {$terisi}, sisa {$sisa}. ({$defTerisi})";
            }

            // Beberapa rak dalam satu zona -> tabel kecil (rak, kapasitas, terisi, sisa)
            $tableLines = [
                "Berikut ketersediaan kapasitas di {$matchedZoneName}:",
                "",
                "| No | Rak / Tempat Penyimpanan | Kapasitas | Terisi | Sisa |",
                "|---|---|---|---|---|",
            ];
            $no = 1;
            foreach ($matchedRacks as $r) {
                $cap = (int)$r['capacity'];
                $terisi = (int)$r['current_items_count'];
                $sisa = (int)$r['remaining_capacity'];
                $tableLines[] = "| {$no} | {$r['name']} ({$r['code']}) | {$cap} | {$terisi} | {$sisa} |";
                $no++;
            }
            $tableLines[] = "";
            $tableLines[] = "*({$defTerisi})*";
            return implode("\n", $tableLines);
        }

        // 2. PENCOCOKAN TOLERAN NAMA RAK & KODE RAK (Bila bukan filter zona)

        // A. Cek kode rak lengkap (misal STG-GD-01, STG-EL-01, RAK-VIP-4C64)
        foreach ($allRacks as $rack) {
            if (preg_match('/\b' . preg_quote(mb_strtolower($rack['code']), '/') . '\b/iu', $normalized)) {
                $cap = (int)$rack['capacity'];
                $terisi = (int)$rack['current_items_count'];
                $sisa = (int)$rack['remaining_capacity'];
                $this->storeSessionCount('storage_locations', ['code' => $rack['code']], 1);
                return "{$rack['name']}: kapasitas {$cap}, terisi {$terisi}, sisa {$sisa}. ({$defTerisi})";
            }
        }

        // B. Cek suffix seperti E-01, A-01, B-01, C-01, D-01
        if (preg_match('/\b([a-z]{1,3}-\d{2})\b/i', $normalized, $mSuffix)) {
            $suf = mb_strtolower($mSuffix[1]);
            foreach ($allRacks as $rack) {
                if (str_contains(mb_strtolower($rack['code']), $suf) || str_contains(mb_strtolower($rack['name']), $suf)) {
                    $cap = (int)$rack['capacity'];
                    $terisi = (int)$rack['current_items_count'];
                    $sisa = (int)$rack['remaining_capacity'];
                    $this->storeSessionCount('storage_locations', ['code' => $rack['code']], 1);
                    return "{$rack['name']}: kapasitas {$cap}, terisi {$terisi}, sisa {$sisa}. ({$defTerisi})";
                }
            }
        }

        // C. Pencocokan toleran nama rak (dengan bobot kata pembeda vs kata umum)
        $bestRack = null;
        $bestScore = 0;
        $commonWords = ['rak', 'lemari', 'kotak', 'gudang', 'simpan', 'penyimpanan', 'dan', 'di', 'pada', 'yang', 'untuk', 'tempat', 'posko'];

        $normWords = preg_split('/[\s\-_]+/', $normalized);
        foreach ($allRacks as $rack) {
            $nameLower = mb_strtolower($rack['name']);
            $nameWords = preg_split('/[\s\-_]+/', $nameLower);
            $score = 0;

            foreach ($nameWords as $nw) {
                if (mb_strlen($nw) < 3) continue;
                $isCommon = in_array($nw, $commonWords, true);
                foreach ($normWords as $qw) {
                    if (mb_strlen($qw) < 3) continue;
                    if ($nw === $qw) {
                        $score += ($isCommon ? 5 : 30);
                        break;
                    }
                    if (mb_strlen($qw) >= 4 && levenshtein($nw, $qw) <= 1) {
                        $score += ($isCommon ? 3 : 25);
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRack = $rack;
            }
        }

        if ($bestRack && $bestScore >= 25) {
            $cap = (int)$bestRack['capacity'];
            $terisi = (int)$bestRack['current_items_count'];
            $sisa = (int)$bestRack['remaining_capacity'];
            $this->storeSessionCount('storage_locations', ['id' => $bestRack['id']], 1);
            return "{$bestRack['name']}: kapasitas {$cap}, terisi {$terisi}, sisa {$sisa}. ({$defTerisi})";
        }

        // Jika user bertanya kapasitas/stok tapi nama rak tidak ditemukan, jangan fallback ke rak lain
        $rackNames = array_column($allRacks, 'name');
        $existingRacksList = implode(', ', $rackNames);
        return "Rak atau tempat penyimpanan tersebut tidak ditemukan di posko. Rak yang terdaftar: {$existingRacksList}.";
    }

    private function getRecentSessionHistory(): array
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $hist = $_SESSION['cfind_history'] ?? [];
        return is_array($hist) ? array_slice($hist, -4) : [];
    }

    private function logDataAgentExecution(?int $userId, string $role, array $plan, float $durationSec, string $status): void
    {
        $logDir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logPath = $logDir . '/cfind_usage.log';
        $timestamp = date('Y-m-d H:i:s');
        $userTag = $userId !== null ? (string)$userId : 'guest';
        $entities = [];
        foreach ($plan['queries'] ?? [] as $q) {
            $entities[] = $q['entity'] ?? 'unknown';
        }
        $entitiesStr = implode(',', $entities);

        $line = sprintf(
            "[%s] [CFIND_DATA_AGENT] User: %s | Role: %s | Entities: %s | Duration: %.3fs | Status: %s\n",
            $timestamp,
            $userTag,
            $role,
            $entitiesStr,
            $durationSec,
            $status
        );

        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Penanganan Status Laporan & Klaim Milik Pengguna Sendiri (Prompt 34E)
     *
     * Pertanyaan seperti:
     * - "update laporan saya", "bagaimana status laporan saya", "laporan terakhir saya", "ada berapa laporan saya"
     * - "update klaim saya", "bagaimana status klaim saya", "klaim terakhir saya", "ada berapa klaim saya"
     *
     * Aturan Prompt 34E:
     * - satu laporan/klaim -> satu kalimat berisi nomor, nama barang, status terbaru, dan waktu pembaruan terakhir (dari riwayat status);
     * - beberapa laporan/klaim -> tabel bernomor;
     * - tidak ada -> "Anda belum mengajukan laporan." / "Anda belum mengajukan klaim."
     * - Tidak boleh memuat data pengguna lain.
     */
    private function checkUserOwnEntitiesStatus(string $normalized, string $role, ?int $userId, ?array $sessionUser): ?array
    {
        $norm = trim($normalized);

        // Pola pertanyaan Laporan Milik Sendiri
        $isReportQuery = (
            preg_match('/\b(update laporan saya|bagaimana status laporan saya|laporan terakhir saya|ada berapa laporan saya)\b/iu', $norm)
            || preg_match('/\b(status laporan saya|laporan saya|cek laporan saya|bagaimana laporan saya|laporan saya bagaimana|status laporan kehilangan saya|laporan kehilangan saya)\b/iu', $norm)
            || (str_contains($norm, 'laporan') && (str_contains($norm, 'saya') || str_contains($norm, 'milik saya') || str_contains($norm, 'punya saya')))
            || ($role === 'civitas' && (
                preg_match('/^(?:update laporan|bagaimana status laporan|status laporan|laporan terakhir|ada berapa laporan)\??$/iu', $norm)
                || preg_match('/^(?:laporan saya|cek laporan|status laporan)\??$/iu', $norm)
            ))
        );

        // Pola pertanyaan Klaim Milik Sendiri
        $isClaimQuery = (
            preg_match('/\b(update klaim saya|bagaimana status klaim saya|klaim terakhir saya|ada berapa klaim saya)\b/iu', $norm)
            || preg_match('/\b(status klaim saya|klaim saya|cek klaim saya|bagaimana klaim saya|klaim saya bagaimana|status pengajuan klaim saya|pengajuan klaim saya)\b/iu', $norm)
            || (str_contains($norm, 'klaim') && (str_contains($norm, 'saya') || str_contains($norm, 'milik saya') || str_contains($norm, 'punya saya')))
            || ($role === 'civitas' && (
                preg_match('/^(?:update klaim|bagaimana status klaim|status klaim|klaim terakhir|ada berapa klaim)\??$/iu', $norm)
                || preg_match('/^(?:klaim saya|cek klaim|status klaim)\??$/iu', $norm)
            ))
        );

        if (!$isReportQuery && !$isClaimQuery) {
            return null;
        }

        // Jika belum login (Guest)
        if (empty($userId) || $role === 'guest') {
            $entityName = $isReportQuery ? 'laporan' : 'klaim';
            return [
                'handled' => true,
                'reply'   => "Silakan masuk (login) terlebih dahulu untuk melihat status {$entityName} Anda.",
                'tokens'  => 0,
                'intent'  => 'user_entity_unauthenticated',
                'status'  => 'denied',
            ];
        }

        if ($isReportQuery) {
            return $this->handleUserReportsStatus((int) $userId);
        }

        return $this->handleUserClaimsStatus((int) $userId);
    }

    /**
     * Format status laporan milik pengguna sendiri (Prompt 34E)
     */
    private function handleUserReportsStatus(int $userId): array
    {
        // 1. Ambil semua laporan yang dimiliki pengguna
        $stmt = $this->pdo->prepare("
            SELECT id, report_number, title, status, created_at, 'lost_report' AS entity_type
            FROM lost_reports
            WHERE reporter_user_id = ?
            UNION ALL
            SELECT id, report_number, title, status, created_at, 'found_report' AS entity_type
            FROM found_reports
            WHERE reporter_user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Jika tidak ada laporan
        if (empty($reports)) {
            return [
                'handled' => true,
                'reply'   => 'Anda belum mengajukan laporan.',
                'tokens'  => 0,
                'intent'  => 'my_reports_status',
                'status'  => 'success',
            ];
        }

        // Ambil riwayat status pembaruan terakhir untuk setiap laporan dari status_histories
        foreach ($reports as &$r) {
            $stmtHist = $this->pdo->prepare("
                SELECT new_status, created_at
                FROM status_histories
                WHERE entity_type = ? AND entity_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtHist->execute([$r['entity_type'], (int) $r['id']]);
            $hist = $stmtHist->fetch(PDO::FETCH_ASSOC);

            $lastTime = !empty($hist['created_at']) ? $hist['created_at'] : $r['created_at'];
            $currentStatus = !empty($hist['new_status']) ? $hist['new_status'] : $r['status'];

            $r['latest_time'] = $lastTime;
            $r['latest_status'] = $currentStatus;
            $r['status_label'] = $this->translateReportStatus((string) $currentStatus);
            $r['formatted_time'] = $this->formatDateTimeIndonesian((string) $lastTime);
        }
        unset($r);

        // 3. Jika tepat 1 laporan: satu kalimat berisi nomor, nama barang, status terbaru, dan waktu pembaruan terakhir
        if (count($reports) === 1) {
            $r = $reports[0];
            $reply = "Laporan Anda dengan nomor {$r['report_number']} ({$r['title']}) saat ini berstatus \"{$r['status_label']}\", terakhir diperbarui pada {$r['formatted_time']}.";
            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'my_reports_status',
                'status'  => 'success',
            ];
        }

        // 4. Jika beberapa laporan: tabel bernomor
        $reply = $this->buildNumberedTableReply(
            "Berikut daftar laporan milik Anda:",
            ['Nomor Laporan', 'Nama Barang', 'Status Terbaru', 'Waktu Pembaruan Terakhir'],
            array_map(function ($idx, $r) {
                return [
                    'row_no'        => (string) ($idx + 1),
                    'report_number' => (string) $r['report_number'],
                    'title'         => (string) $r['title'],
                    'status'        => (string) $r['status_label'],
                    'updated_at'    => (string) $r['formatted_time'],
                ];
            }, array_keys($reports), $reports)
        );

        return [
            'handled' => true,
            'reply'   => $reply,
            'tokens'  => 0,
            'intent'  => 'my_reports_status',
            'status'  => 'success',
        ];
    }

    /**
     * Format status klaim milik pengguna sendiri (Prompt 34E)
     */
    private function handleUserClaimsStatus(int $userId): array
    {
        // 1. Ambil semua klaim yang diajukan oleh pengguna
        $stmt = $this->pdo->prepare("
            SELECT c.id, c.claim_number, c.status, c.created_at,
                   COALESCE(i.title, lr.title, fr.title, 'Barang Temuan') AS item_title
            FROM claims c
            LEFT JOIN items i ON c.item_id = i.id
            LEFT JOIN lost_reports lr ON c.lost_report_id = lr.id
            LEFT JOIN found_reports fr ON c.found_report_id = fr.id
            WHERE c.claimant_user_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$userId]);
        $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Jika tidak ada klaim
        if (empty($claims)) {
            return [
                'handled' => true,
                'reply'   => 'Anda belum mengajukan klaim.',
                'tokens'  => 0,
                'intent'  => 'my_claims_status',
                'status'  => 'success',
            ];
        }

        // Ambil riwayat status pembaruan terakhir untuk setiap klaim dari status_histories
        foreach ($claims as &$c) {
            $stmtHist = $this->pdo->prepare("
                SELECT new_status, created_at
                FROM status_histories
                WHERE entity_type = 'claim' AND entity_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtHist->execute([(int) $c['id']]);
            $hist = $stmtHist->fetch(PDO::FETCH_ASSOC);

            $lastTime = !empty($hist['created_at']) ? $hist['created_at'] : $c['created_at'];
            $currentStatus = !empty($hist['new_status']) ? $hist['new_status'] : $c['status'];

            $c['latest_time'] = $lastTime;
            $c['latest_status'] = $currentStatus;
            $c['status_label'] = $this->translateClaimStatus((string) $currentStatus);
            $c['formatted_time'] = $this->formatDateTimeIndonesian((string) $lastTime);
        }
        unset($c);

        // 3. Jika tepat 1 klaim: satu kalimat berisi nomor, nama barang, status terbaru, dan waktu pembaruan terakhir
        if (count($claims) === 1) {
            $c = $claims[0];
            $reply = "Klaim Anda dengan nomor {$c['claim_number']} ({$c['item_title']}) saat ini berstatus \"{$c['status_label']}\", terakhir diperbarui pada {$c['formatted_time']}.";
            return [
                'handled' => true,
                'reply'   => $reply,
                'tokens'  => 0,
                'intent'  => 'my_claims_status',
                'status'  => 'success',
            ];
        }

        // 4. Jika beberapa klaim: tabel bernomor
        $reply = $this->buildNumberedTableReply(
            "Berikut daftar pengajuan klaim milik Anda:",
            ['Nomor Klaim', 'Nama Barang', 'Status Terbaru', 'Waktu Pembaruan Terakhir'],
            array_map(function ($idx, $c) {
                return [
                    'row_no'        => (string) ($idx + 1),
                    'report_number' => (string) $c['claim_number'],
                    'title'         => (string) $c['item_title'],
                    'status'        => (string) $c['status_label'],
                    'updated_at'    => (string) $c['formatted_time'],
                ];
            }, array_keys($claims), $claims)
        );

        return [
            'handled' => true,
            'reply'   => $reply,
            'tokens'  => 0,
            'intent'  => 'my_claims_status',
            'status'  => 'success',
        ];
    }

    /**
     * Penanganan Laporan Hari Ini (Kehilangan & Temuan) Terpadu (Prompt 35C)
     *
     * Pertanyaan umum "laporan" mencakup kehilangan DAN temuan yang dibuat hari ini (batas hari Asia/Jakarta).
     * Teks dan tabel berasal dari SATU hasil query:
     * - "Hari ini ada {total} laporan: {lost} kehilangan, {found} temuan." diikuti tabel jika ada data.
     * - "Hari ini belum ada laporan." jika kosong.
     * Validator: jawaban "tidak ada" tidak boleh disertai tabel berisi baris.
     *
     * @param string $normalized
     * @param string $role
     * @param int|null $userId
     * @param array<string, mixed>|null $sessionUser
     * @return array{handled: bool, reply: mixed, tokens: int, intent: string, status: string}|null
     */
    private function handleTodayReportsQuery(string $normalized, string $role, ?int $userId, ?array $sessionUser): ?array
    {
        $norm = trim($normalized);

        // Pola pertanyaan laporan hari ini
        $isTodayReportsQuery = (
            preg_match('/\b(laporan (apa saja )?hari ini|ada laporan apa hari ini|apakah ada laporan hari ini|ada berapa laporan hari ini|cek laporan hari ini|daftar laporan hari ini|status laporan hari ini)\b/iu', $norm)
            || preg_match('/^(?:ada )?laporan (?:apa )?hari ini\??$/iu', $norm)
            || (str_contains($norm, 'laporan') && str_contains($norm, 'hari ini') && !str_contains($norm, 'saya'))
        );

        if (!$isTodayReportsQuery) {
            return null;
        }

        // RBAC: Guest tidak memiliki akses data operasional
        if ($role === 'guest' || empty($userId)) {
            return [
                'handled' => true,
                'reply'   => 'Maaf, data operasional hanya dapat diakses oleh civitas dan petugas yang telah masuk (login). Silakan login terlebih dahulu.',
                'tokens'  => 0,
                'intent'  => 'guest_restricted',
                'status'  => 'denied',
            ];
        }

        // RBAC: Civitas tidak memiliki akses ke antrean operasional seluruh laporan posko
        if ($role === 'civitas') {
            return [
                'handled' => true,
                'reply'   => 'Data operasional laporan posko hari ini berada di luar hak akses peran Civitas Anda.',
                'tokens'  => 0,
                'intent'  => 'civitas_rbac_denied',
                'status'  => 'denied',
            ];
        }

        // Peran Petugas & Admin: Ambil batas hari Asia/Jakarta
        $tz = new DateTimeZone('Asia/Jakarta');
        $todayStart = (new \DateTime('today', $tz))->format('Y-m-d 00:00:00');
        $todayEnd = (new \DateTime('today', $tz))->format('Y-m-d 23:59:59');

        $sql = "
            SELECT 
                'Kehilangan' AS report_type,
                lr.id,
                lr.report_number,
                lr.title,
                lr.status,
                lr.created_at
            FROM lost_reports lr
            WHERE lr.created_at >= :start_lost AND lr.created_at <= :end_lost
            UNION ALL
            SELECT 
                'Temuan' AS report_type,
                fr.id,
                fr.report_number,
                fr.title,
                fr.status,
                fr.created_at
            FROM found_reports fr
            WHERE fr.created_at >= :start_found AND fr.created_at <= :end_found
            ORDER BY created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'start_lost'  => $todayStart,
            'end_lost'    => $todayEnd,
            'start_found' => $todayStart,
            'end_found'   => $todayEnd,
        ]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Jika kosong: kembalikan teks persis sesuai Prompt 35C tanpa tabel
        if (empty($reports)) {
            return [
                'handled' => true,
                'reply'   => 'Hari ini belum ada laporan.',
                'tokens'  => 0,
                'intent'  => 'today_reports',
                'status'  => 'success',
            ];
        }

        $lostCount = 0;
        $foundCount = 0;
        foreach ($reports as $r) {
            if ($r['report_type'] === 'Kehilangan') {
                $lostCount++;
            } else {
                $foundCount++;
            }
        }
        $totalCount = $lostCount + $foundCount;

        $introText = "Hari ini ada {$totalCount} laporan: {$lostCount} kehilangan, {$foundCount} temuan.";

        $queryResult = [
            'columns' => [
                'report_type'   => 'Jenis',
                'report_number' => 'Nomor Laporan',
                'title'         => 'Nama / Judul Barang',
                'status'        => 'Status',
                'created_at'    => 'Waktu Pelaporan',
            ],
            'rows' => array_map(function ($r) {
                return [
                    'report_type'   => $r['report_type'],
                    'report_number' => $r['report_number'],
                    'title'         => $r['title'],
                    'status'        => $this->translateReportStatus((string) $r['status']),
                    'created_at'    => $this->formatDateTimeIndonesian((string) $r['created_at']),
                ];
            }, $reports),
        ];

        $tableBlock = $this->presenter->buildTableBlock($queryResult, $norm);
        $reply = [
            [
                'type'    => 'text',
                'content' => $introText,
            ],
            $tableBlock,
        ];

        $validatedReply = ResponsePresenter::validateReplyConsistency($reply);

        return [
            'handled' => true,
            'reply'   => $validatedReply,
            'tokens'  => 0,
            'intent'  => 'today_reports',
            'status'  => 'success',
        ];
    }

    /**
     * Membangun format tabel bernomor Markdown
     */
    private function buildNumberedTableReply(string $intro, array $headers, array $rows): string
    {
        $lines = [$intro, ''];

        // Header tabel
        $lines[] = '| No | ' . implode(' | ', $headers) . ' |';
        $lines[] = '|:---|' . str_repeat(':---|', count($headers));

        // Baris tabel
        foreach ($rows as $r) {
            $values = array_values($r);
            $lines[] = '| ' . implode(' | ', $values) . ' |';
        }

        return implode("\n", $lines);
    }

    private function translateReportStatus(string $status): string
    {
        $norm = strtolower(trim($status));
        return match ($norm) {
            'published'             => 'Dipublikasikan',
            'investigating'         => 'Dalam Penyelidikan',
            'matched'               => 'Cocok',
            'claimed'               => 'Diklaim',
            'resolved', 'completed' => 'Selesai',
            'closed'                => 'Ditutup',
            'cancelled'             => 'Dibatalkan',
            'draft'                 => 'Draf',
            'pending_handover'      => 'Menunggu Penyerahan',
            'handed_over_to_center' => 'Diserahkan ke Posko',
            'verified'              => 'Terverifikasi',
            default                 => ucwords(str_replace('_', ' ', $norm)),
        };
    }

    private function translateClaimStatus(string $status): string
    {
        $norm = strtolower(trim($status));
        return match ($norm) {
            'pending', 'pending_verification' => 'Menunggu Verifikasi',
            'under_review'                    => 'Sedang Ditinjau',
            'need_more_info'                  => 'Perlu Info Tambahan',
            'approved'                        => 'Disetujui',
            'rejected'                        => 'Ditolak',
            'completed'                       => 'Selesai',
            'closed_duplicate'                => 'Ditutup (Duplikat)',
            'cancelled'                       => 'Dibatalkan',
            default                           => ucwords(str_replace('_', ' ', $norm)),
        };
    }

    private function formatDateTimeIndonesian(string $dateStr): string
    {
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $dateStr;
        }
        $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $m = (int) date('n', $ts);
        $monthName = $months[$m] ?? date('M', $ts);
        return date('d', $ts) . ' ' . $monthName . ' ' . date('Y', $ts) . ', ' . date('H:i', $ts) . ' WIB';
    }

    private function logUsageBranch(?int $userId, string $role, string $intent, int $tokens, string $status): void
    {
        $logDir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logPath = $logDir . '/cfind_usage.log';
        $timestamp = date('Y-m-d H:i:s');
        $userTag = $userId !== null ? (string)$userId : 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $line = sprintf(
            "[%s] [CFIND_USAGE] User: %s | Role: %s | IP: %s | Intent: %s | Tokens: %d | Status: %s\n",
            $timestamp,
            $userTag,
            $role,
            $ip,
            $intent,
            $tokens,
            $status
        );

        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
