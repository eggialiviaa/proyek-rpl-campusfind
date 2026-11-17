<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use Core\Database;

/**
 * DashboardController — Pengarah dashboard dinamis berbasis role
 */
class DashboardController extends BaseController
{
    /**
     * GET /dashboard
     * Tampilkan dashboard sesuai role pengguna
     */
    public function index(array $params = []): void
    {
        AuthMiddleware::require();

        $user = AuthMiddleware::user();
        $role = $user['role_name'] ?? $user['role'] ?? 'civitas';

        $pdo = Database::getInstance()->getPdo();

        switch ($role) {
            case 'admin':
                // Redirect admin ke /admin untuk menghindari dashboard ganda
                $this->redirect('/admin');
                return;

            case 'petugas':
                // Ambil data agregat operasional petugas dari ItemCatalogService (single source of truth: POSKO_ITEM_STATUSES)
                $catalogService = new \App\Services\ItemCatalogService();
                $storedItems = $catalogService->getAllActiveInventoryCount();
                $pendingClaims = $catalogService->getPendingClaimsCount();
                $unverifiedFound = $catalogService->getPendingHandoverCount();
                $storageCount = (int) $pdo->query("SELECT COUNT(*) FROM storage_locations WHERE is_active = 1")->fetchColumn();

                $this->render('dashboard/petugas', [
                    'pageTitle' => 'Petugas Dashboard — Lost & Found Center',
                    'user'      => $user,
                    'stats'     => [
                        'stored_items'      => $storedItems,
                        'pending_claims'    => $pendingClaims,
                        'unverified_found'  => $unverifiedFound,
                        'storage_locations' => $storageCount,
                    ],
                ], 'app_shell');
                break;

            case 'civitas':
            default:
                // Ambil data laporan dan analitik pribadi civitas (PAGE-01 Reference Benchmark)
                $userId = (int) $user['id'];

                // 1. KPI & Hero Metrics
                // 1a. Laporan Kehilangan Aktif (Mencari, Investigasi, Potensi Cocok)
                $stmtActiveLost = $pdo->prepare("
                    SELECT COUNT(*) FROM lost_reports 
                    WHERE reporter_user_id = ? AND status IN ('published', 'investigating', 'matched')
                ");
                $stmtActiveLost->execute([$userId]);
                $activeLost = (int) $stmtActiveLost->fetchColumn();

                // 1b. Total Laporan Temuan yang dilaporkan civitas
                $stmtFound = $pdo->prepare("SELECT COUNT(*) FROM found_reports WHERE reporter_user_id = ?");
                $stmtFound->execute([$userId]);
                $myFound = (int) $stmtFound->fetchColumn();

                // 1c. Klaim yang sedang dalam proses verifikasi petugas
                $stmtClaimsInProcess = $pdo->prepare("
                    SELECT COUNT(*) FROM claims 
                    WHERE claimant_user_id = ? AND status IN ('pending', 'under_review', 'need_more_info', 'approved')
                ");
                $stmtClaimsInProcess->execute([$userId]);
                $claimsInProcess = (int) $stmtClaimsInProcess->fetchColumn();

                // 1d. Barang Berhasil Kembali (Lost report resolved/claimed + Klaim selesai diambil + Temuan selesai)
                $stmtReturned = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM lost_reports WHERE reporter_user_id = ? AND status IN ('claimed', 'resolved')) +
                        (SELECT COUNT(*) FROM claims WHERE claimant_user_id = ? AND status = 'completed') +
                        (SELECT COUNT(*) FROM found_reports WHERE reporter_user_id = ? AND status = 'completed')
                ");
                $stmtReturned->execute([$userId, $userId, $userId]);
                $returnedItems = (int) $stmtReturned->fetchColumn();

                // Total keseluruhan laporan & klaim civitas (sebagai denominator recovery rate)
                $stmtTotalLost = $pdo->prepare("SELECT COUNT(*) FROM lost_reports WHERE reporter_user_id = ?");
                $stmtTotalLost->execute([$userId]);
                $totalLost = (int) $stmtTotalLost->fetchColumn();

                $stmtTotalClaims = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE claimant_user_id = ?");
                $stmtTotalClaims->execute([$userId]);
                $totalClaims = (int) $stmtTotalClaims->fetchColumn();

                $totalReportsAndClaims = $totalLost + $myFound + $totalClaims;
                
                // Progress Ring Rate (Tingkat Pemulihan / Resolusi)
                $recoveryRate = null;
                if ($totalReportsAndClaims > 0) {
                    $recoveryRate = (int) round(($returnedItems / $totalReportsAndClaims) * 100);
                    if ($recoveryRate > 100) $recoveryRate = 100;
                }

                // 2. Scheduled Handovers (dari claims user dengan tanggal serah terima terjadwal)
                $stmtHandovers = $pdo->prepare("
                    SELECT c.id, c.claim_number, c.handover_scheduled_at, c.status,
                           COALESCE(i.title, lr.title, fr.title, 'Barang Temuan') as item_title
                    FROM claims c
                    LEFT JOIN items i ON c.item_id = i.id
                    LEFT JOIN lost_reports lr ON c.lost_report_id = lr.id
                    LEFT JOIN found_reports fr ON c.found_report_id = fr.id
                    WHERE c.claimant_user_id = ? AND c.handover_scheduled_at IS NOT NULL
                    ORDER BY c.handover_scheduled_at ASC
                ");
                $stmtHandovers->execute([$userId]);
                $scheduledHandovers = $stmtHandovers->fetchAll(\PDO::FETCH_ASSOC);

                // 3. Monthly Activity Trend (6 Bulan Terakhir)
                $trendMonths = [];
                for ($i = 5; $i >= 0; $i--) {
                    $ym = date('Y-m', strtotime("-$i months"));
                    $label = date('M', strtotime("-$i months"));
                    $trendMonths[$ym] = [
                        'ym'    => $ym,
                        'label' => $label,
                        'lost'  => 0,
                        'found' => 0,
                    ];
                }

                $stmtTrendLost = $pdo->prepare("
                    SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as c
                    FROM lost_reports
                    WHERE reporter_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY ym
                ");
                $stmtTrendLost->execute([$userId]);
                while ($row = $stmtTrendLost->fetch(\PDO::FETCH_ASSOC)) {
                    if (isset($trendMonths[$row['ym']])) {
                        $trendMonths[$row['ym']]['lost'] = (int) $row['c'];
                    }
                }

                $stmtTrendFound = $pdo->prepare("
                    SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as c
                    FROM found_reports
                    WHERE reporter_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY ym
                ");
                $stmtTrendFound->execute([$userId]);
                while ($row = $stmtTrendFound->fetch(\PDO::FETCH_ASSOC)) {
                    if (isset($trendMonths[$row['ym']])) {
                        $trendMonths[$row['ym']]['found'] = (int) $row['c'];
                    }
                }

                // 4. Category Breakdown
                $stmtCategories = $pdo->prepare("
                    SELECT c.name, COUNT(*) as count, c.icon
                    FROM (
                        SELECT category_id FROM lost_reports WHERE reporter_user_id = ?
                        UNION ALL
                        SELECT category_id FROM found_reports WHERE reporter_user_id = ?
                    ) rep
                    JOIN categories c ON rep.category_id = c.id
                    GROUP BY c.id, c.name, c.icon
                    ORDER BY count DESC
                ");
                $stmtCategories->execute([$userId, $userId]);
                $categoryDistribution = $stmtCategories->fetchAll(\PDO::FETCH_ASSOC);

                // 5. Recent Activity (5 Catatan Terkini)
                $stmtActivities = $pdo->prepare("
                    (SELECT 'lost' as type, id, report_number as ref_number, title, status, created_at 
                     FROM lost_reports WHERE reporter_user_id = ?)
                    UNION ALL
                    (SELECT 'found' as type, id, report_number as ref_number, title, status, created_at 
                     FROM found_reports WHERE reporter_user_id = ?)
                    UNION ALL
                    (SELECT 'claim' as type, c.id, c.claim_number as ref_number, 
                            COALESCE(i.title, lr.title, fr.title, 'Pengajuan Klaim') as title, 
                            c.status, c.created_at 
                     FROM claims c 
                     LEFT JOIN items i ON c.item_id = i.id 
                     LEFT JOIN lost_reports lr ON c.lost_report_id = lr.id 
                     LEFT JOIN found_reports fr ON c.found_report_id = fr.id 
                     WHERE c.claimant_user_id = ?)
                    ORDER BY created_at DESC 
                    LIMIT 5
                ");
                $stmtActivities->execute([$userId, $userId, $userId]);
                $recentActivities = $stmtActivities->fetchAll(\PDO::FETCH_ASSOC);

                // Enrich recent activities with URL
                foreach ($recentActivities as &$act) {
                    $act['url'] = match ($act['type']) {
                        'lost'  => '/reports/lost/' . $act['id'],
                        'found' => '/reports/found/' . $act['id'],
                        'claim' => '/claims/' . $act['id'],
                        default => '/dashboard',
                    };
                }
                unset($act);

                $dashboardData = [
                    'pageTitle' => 'Dashboard Civitas — Campus Find',
                    'user'      => $user,
                    'hero'      => [
                        'active_reports'    => $activeLost + ($myFound > 0 ? 1 : 0),
                        'claims_in_process' => $claimsInProcess,
                        'returned_items'    => $returnedItems,
                        'recovery_rate'     => $recoveryRate,
                        'total_activities'  => $totalReportsAndClaims,
                    ],
                    'kpis'      => [
                        'active_lost'       => $activeLost,
                        'my_found'          => $myFound,
                        'claims_in_process' => $claimsInProcess,
                        'returned_items'    => $returnedItems,
                    ],
                    'calendar'  => [
                        'handovers' => $scheduledHandovers,
                    ],
                    'trend'     => array_values($trendMonths),
                    'categories'=> $categoryDistribution,
                    'activities'=> $recentActivities,
                    'posko'     => [
                        'name'     => 'Pusat Layanan Lost & Found Center',
                        'location' => 'Gedung Rektorat Lt. 1 (Ruang Piket Layanan Terpadu)',
                        'hours'    => 'Senin – Jumat: 08.00 – 16.00 WIB',
                        'notes'    => 'Penyerahan barang temuan fisik, verifikasi kepemilikan, dan pengambilan barang.',
                    ],
                ];

                $this->render('dashboard/civitas', $dashboardData, 'app_shell');
                break;
        }
    }
}
