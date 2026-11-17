<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\LostReportModel;
use App\Models\FoundReportModel;
use App\Models\CategoryModel;
use App\Models\LocationModel;
use App\Services\AuditLogService;
use App\Services\FileUploadService;
use App\Services\StatusHistoryService;

/**
 * ReportManagementController — Manajemen Laporan Kehilangan & Temuan (Petugas & Admin)
 * Sesuai Spesifikasi Prompt 33B & PRD
 */
class ReportManagementController extends BaseController
{
    private LostReportModel $lostReportModel;
    private FoundReportModel $foundReportModel;
    private CategoryModel $categoryModel;
    private LocationModel $locationModel;

    public function __construct()
    {
        $this->lostReportModel  = new LostReportModel();
        $this->foundReportModel = new FoundReportModel();
        $this->categoryModel    = new CategoryModel();
        $this->locationModel    = new LocationModel();
    }

    /**
     * GET /reports/manage (atau /reports)
     * Daftar seluruh laporan kehilangan & temuan dengan tab, filter, pencarian, dan paginasi
     */
    public function index(array $params = []): void
    {
        // Guard otorisasi: Hanya Petugas & Admin
        $user = AuthMiddleware::user();
        if (!$user) {
            AuthMiddleware::handleUnauthenticated('/reports/manage');
            return;
        }

        $role = $user['role_name'] ?? '';
        if (!in_array($role, ['petugas', 'admin'], true)) {
            $this->setFlash('error', '⛔ Anda tidak memiliki akses ke konsol manajemen laporan.');
            $this->redirect('/civitas/my-reports');
            return;
        }

        $activeTab   = trim((string) ($_GET['tab'] ?? 'lost'));
        if (!in_array($activeTab, ['lost', 'found'], true)) {
            $activeTab = 'lost';
        }

        $status      = isset($_GET['status']) && $_GET['status'] !== '' ? trim((string) $_GET['status']) : null;
        $categoryId  = isset($_GET['category_id']) && (int) $_GET['category_id'] > 0 ? (int) $_GET['category_id'] : null;
        $locationId  = isset($_GET['location_id']) && (int) $_GET['location_id'] > 0 ? (int) $_GET['location_id'] : null;
        $dateFrom    = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim((string) $_GET['date_from']) : null;
        $dateTo      = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim((string) $_GET['date_to']) : null;
        $search      = isset($_GET['q']) && $_GET['q'] !== '' ? trim((string) $_GET['q']) : null;
        $reporter    = isset($_GET['reporter']) && $_GET['reporter'] !== '' ? trim((string) $_GET['reporter']) : null;

        $page        = max(1, (int) ($_GET['page'] ?? 1));
        $limit       = 15;
        $offset      = ($page - 1) * $limit;

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        // Hitung counter tab
        $totalLostCount  = $this->lostReportModel->countStaffReports();
        $totalFoundCount = $this->foundReportModel->countStaffReports();

        if ($activeTab === 'lost') {
            $reports    = $this->lostReportModel->getStaffReports($status, $categoryId, $locationId, $dateFrom, $dateTo, $search, $reporter, $limit, $offset);
            $totalCount = $this->lostReportModel->countStaffReports($status, $categoryId, $locationId, $dateFrom, $dateTo, $search, $reporter);

            // Counter status lost
            $lostCounts = [
                'published'     => $this->lostReportModel->countStaffReports('published'),
                'investigating' => $this->lostReportModel->countStaffReports('investigating'),
                'matched'       => $this->lostReportModel->countStaffReports('matched'),
                'claimed'       => $this->lostReportModel->countStaffReports('claimed'),
                'resolved'      => $this->lostReportModel->countStaffReports('resolved'),
                'closed'        => $this->lostReportModel->countStaffReports('closed'),
            ];
            $tabCounts = $lostCounts;
        } else {
            $reports    = $this->foundReportModel->getStaffReports($status, $categoryId, $locationId, $dateFrom, $dateTo, $search, $reporter, $limit, $offset);
            $totalCount = $this->foundReportModel->countStaffReports($status, $categoryId, $locationId, $dateFrom, $dateTo, $search, $reporter);

            // Counter status found
            $foundCounts = [
                'published'        => $this->foundReportModel->countStaffReports('published'),
                'pending_handover' => $this->foundReportModel->countStaffReports('pending_handover'),
                'verified'         => $this->foundReportModel->countStaffReports('verified'),
                'claimed'          => $this->foundReportModel->countStaffReports('claimed'),
                'completed'        => $this->foundReportModel->countStaffReports('completed'),
                'cancelled'        => $this->foundReportModel->countStaffReports('cancelled'),
            ];
            $tabCounts = $foundCounts;
        }

        $totalPages = (int) ceil($totalCount / $limit);

        $this->render('reports/manage', [
            'pageTitle'        => 'Kelola Laporan Kehilangan & Temuan — Campus Find',
            'metaDescription'  => 'Pusat pengawasan dan verifikasi laporan kehilangan dan penemuan barang kampus.',
            'activeTab'        => $activeTab,
            'reports'          => $reports,
            'totalCount'       => $totalCount,
            'totalLostCount'   => $totalLostCount,
            'totalFoundCount'  => $totalFoundCount,
            'tabCounts'        => $tabCounts,
            'currentPage'      => $page,
            'totalPages'       => $totalPages,
            'limit'            => $limit,
            'selectedStatus'   => $status,
            'selectedCategory' => $categoryId,
            'selectedLocation' => $locationId,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'searchQuery'      => $search,
            'reporterQuery'    => $reporter,
            'categories'       => $categories,
            'locations'        => $locations,
            'user'             => $user,
            'csrfToken'        => CsrfMiddleware::getToken(),
        ], 'app_shell');
    }

    /**
     * POST /reports/lost/{id}/status
     * Petugas / Admin mengubah status laporan kehilangan dengan catatan internal
     */
    public function updateLostStatus(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->lostReportModel->findDetail($id);

        if (!$report) {
            $this->setFlash('error', 'Laporan kehilangan tidak ditemukan.');
            $this->redirect('/reports/manage?tab=lost');
            return;
        }

        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $notes     = trim((string) ($_POST['notes'] ?? ''));

        $allowedStatuses = ['published', 'investigating', 'matched', 'claimed', 'resolved', 'closed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $this->setFlash('error', 'Status transisi laporan kehilangan tidak sah.');
            $this->redirect('/reports/lost/' . $id);
            return;
        }

        if (empty($notes) || strlen($notes) < 5) {
            $this->setFlash('error', 'Catatan alasan perubahan status wajib diisi secara jelas (minimal 5 karakter).');
            $this->redirect('/reports/lost/' . $id);
            return;
        }

        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];

        $success = $this->lostReportModel->updateStatus($id, $newStatus, $userId, $notes);

        if ($success) {
            $statusLabel = LostReportModel::getStatusLabel($newStatus);
            AuditLogService::log('UPDATE_LOST_REPORT_STATUS', $userId, 'lost_reports', $id, [
                'report_number' => $report['report_number'],
                'old_status'    => $report['status'],
                'new_status'    => $newStatus,
                'notes'         => $notes,
            ]);

            $this->setFlash('success', "Status laporan kehilangan {$report['report_number']} berhasil diubah menjadi '{$statusLabel}'. Catatan internal telah tercatat.");
        } else {
            $this->setFlash('error', 'Gagal memperbarui status laporan kehilangan.');
        }

        $this->redirect('/reports/lost/' . $id);
    }

    /**
     * POST /admin/reports/{type}/{id}/delete
     * HANYA Administrator: Menghapus laporan bermasalah / spam dengan alasan wajib
     */
    public function adminDelete(array $params = []): void
    {
        AuthMiddleware::requireRole('admin');
        CsrfMiddleware::validate();

        $type = trim((string) ($params['type'] ?? ''));
        $id   = (int) ($params['id'] ?? 0);

        if (!in_array($type, ['lost', 'found'], true) || $id <= 0) {
            $this->setFlash('error', 'Tipe laporan atau ID tidak valid.');
            $this->redirect('/reports/manage');
            return;
        }

        $reason = trim((string) ($_POST['deletion_reason'] ?? ''));
        if (empty($reason) || strlen($reason) < 5) {
            $this->setFlash('error', 'Alasan penghapusan laporan bermasalah/spam wajib diisi (minimal 5 karakter).');
            $redirectUrl = ($type === 'lost') ? '/reports/lost/' . $id : '/reports/found/' . $id;
            $this->redirect($redirectUrl);
            return;
        }

        $adminUser = AuthMiddleware::user();
        $adminId = (int) $adminUser['id'];

        if ($type === 'lost') {
            $report = $this->lostReportModel->findDetail($id);
            if (!$report) {
                $this->setFlash('error', 'Laporan kehilangan tidak ditemukan.');
                $this->redirect('/reports/manage?tab=lost');
                return;
            }

            if (!empty($report['primary_image'])) {
                FileUploadService::deleteFile($report['primary_image']);
            }

            $this->lostReportModel->deleteReport($id);

            AuditLogService::log('ADMIN_DELETE_LOST_REPORT', $adminId, 'lost_reports', $id, [
                'report_number'   => $report['report_number'],
                'reporter_email'  => $report['reporter_email'] ?? '',
                'title'           => $report['title'],
                'deletion_reason' => $reason,
            ]);

            $this->setFlash('success', "Laporan kehilangan {$report['report_number']} berhasil dihapus dari sistem oleh Administrator. Alasan tercatat di audit log.");
            $this->redirectBack('/reports/manage?tab=lost');
        } else {
            $report = $this->foundReportModel->findDetail($id);
            if (!$report) {
                $this->setFlash('error', 'Laporan temuan tidak ditemukan.');
                $this->redirectBack('/reports/manage?tab=found');
                return;
            }

            if (!empty($report['primary_image'])) {
                FileUploadService::deleteFile($report['primary_image']);
            }

            $this->foundReportModel->deleteReport($id);

            AuditLogService::log('ADMIN_DELETE_FOUND_REPORT', $adminId, 'found_reports', $id, [
                'report_number'   => $report['report_number'],
                'reporter_email'  => $report['reporter_email'] ?? '',
                'title'           => $report['title'],
                'deletion_reason' => $reason,
            ]);

            $this->setFlash('success', "Laporan temuan {$report['report_number']} berhasil dihapus dari sistem oleh Administrator. Alasan tercatat di audit log.");
            $this->redirectBack('/reports/manage?tab=found');
        }
    }
}
