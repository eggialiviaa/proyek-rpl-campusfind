<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\LostReportModel;
use App\Models\CategoryModel;
use App\Models\LocationModel;
use App\Services\FileUploadService;
use App\Services\AuditLogService;

/**
 * LostReportController — Pengelolaan Laporan Kehilangan Barang (Civitas)
 */
class LostReportController extends BaseController
{
    private LostReportModel $lostReportModel;
    private CategoryModel $categoryModel;
    private LocationModel $locationModel;

    public function __construct()
    {
        $this->lostReportModel = new LostReportModel();
        $this->categoryModel   = new CategoryModel();
        $this->locationModel   = new LocationModel();
    }

    /**
     * GET /reports/lost
     * Daftar katalog laporan kehilangan publik
     */
    public function index(array $params = []): void
    {
        $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
        $locationId = isset($_GET['location']) ? (int) $_GET['location'] : null;
        $search     = isset($_GET['q'])        ? trim((string) $_GET['q']) : null;
        $dateFrom   = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo     = trim((string) ($_GET['date_to']   ?? ''));
        $sortBy     = in_array($_GET['sort'] ?? '', ['newest','oldest','date_asc','date_desc'], true)
                      ? $_GET['sort'] : 'newest';
        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $limit      = 12;
        $offset     = ($page - 1) * $limit;

        if ($categoryId !== null && $categoryId <= 0) {
            $categoryId = null;
        }
        if ($locationId !== null && $locationId <= 0) {
            $locationId = null;
        }
        if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = '';
        }

        $reports    = $this->lostReportModel->getPublicReports(
            $categoryId, $search, $limit, $offset,
            $locationId ?: null,
            $dateFrom   ?: null,
            $dateTo     ?: null,
            $sortBy
        );
        $totalCount = $this->lostReportModel->countPublicReports(
            $categoryId, $search,
            $locationId ?: null,
            $dateFrom   ?: null,
            $dateTo     ?: null
        );
        $totalPages = (int) ceil($totalCount / $limit);

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        $layout = AuthMiddleware::isLoggedIn() ? 'app_shell' : 'main';

        $this->render('reports/lost/index', [
            'pageTitle'        => 'Daftar Barang Hilang — Campus Find',
            'metaDescription'  => 'Telusuri pengumuman barang hilang di lingkungan kampus.',
            'reports'          => $reports,
            'categories'       => $categories,
            'locations'        => $locations,
            'selectedCategory' => $categoryId,
            'selectedLocation' => $locationId,
            'searchQuery'      => $search,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'sortBy'           => $sortBy,
            'currentPage'      => $page,
            'totalPages'       => $totalPages,
            'totalCount'       => $totalCount,
            'user'             => AuthMiddleware::user(),
        ], $layout);
    }

    /**
     * GET /reports/lost/create
     * Formulir pembuatan laporan barang hilang
     */
    public function create(array $params = []): void
    {
        AuthMiddleware::require();

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        $this->render('reports/lost/create', [
            'pageTitle'       => 'Buat Laporan Barang Hilang — Campus Find',
            'metaDescription' => 'Laporkan barang hilang Anda agar dicocokkan oleh sistem dan petugas.',
            'categories'      => $categories,
            'locations'       => $locations,
            'csrfToken'       => CsrfMiddleware::getToken(),
            'user'            => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /reports/lost/create
     * Simpan laporan kehilangan baru
     */
    public function store(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];

        // 1. Ambil input
        $title          = trim((string) ($_POST['title'] ?? ''));
        $categoryId     = (int) ($_POST['category_id'] ?? 0);
        $locationId     = (int) ($_POST['last_seen_location_id'] ?? 0);
        $locationDetail = trim((string) ($_POST['location_detail'] ?? ''));
        $lostDate       = trim((string) ($_POST['lost_date'] ?? ''));
        $lostTime       = trim((string) ($_POST['lost_time'] ?? ''));
        $description    = trim((string) ($_POST['description'] ?? ''));
        $privateDetails = trim((string) ($_POST['private_details'] ?? ''));

        // 2. Server-side Validation
        $errors = [];
        if (empty($title) || strlen($title) < 3 || strlen($title) > 150) {
            $errors[] = 'Judul / nama barang harus diisi (3 - 150 karakter).';
        }

        if ($categoryId <= 0 || !$this->categoryModel->findById($categoryId)) {
            $errors[] = 'Silakan pilih kategori barang yang valid.';
        }

        if ($locationId <= 0 || !$this->locationModel->findById($locationId)) {
            $errors[] = 'Silakan pilih lokasi terakhir terlihat yang valid.';
        }

        if (empty($lostDate)) {
            $errors[] = 'Tanggal kehilangan wajib diisi.';
        } elseif (strtotime($lostDate) > time()) {
            $errors[] = 'Tanggal kehilangan tidak boleh di masa mendatang.';
        }

        if (empty($description) || strlen($description) < 10) {
            $errors[] = 'Deskripsi barang wajib diisi minimal 10 karakter agar memudahkan pencarian.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            $this->redirect('/reports/lost/create');
        }

        // 3. Upload File Gambar Opsional
        $imagePath = null;
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = FileUploadService::uploadImage($_FILES['primary_image'], 'lost_reports');
            if (!$uploadResult['success']) {
                $this->setFlash('error', $uploadResult['error'] ?? 'Gagal mengunggah foto.');
                $this->redirect('/reports/lost/create');
            }
            $imagePath = $uploadResult['path'];
        }

        // 4. Generate Report Number & Simpan ke DB
        $reportNumber = $this->lostReportModel->generateReportNumber();

        $reportId = $this->lostReportModel->create([
            'report_number'         => $reportNumber,
            'reporter_user_id'      => $userId,
            'category_id'           => $categoryId,
            'last_seen_location_id' => $locationId,
            'title'                 => $title,
            'description'           => $description,
            'location_detail'       => $locationDetail ?: null,
            'private_details'       => $privateDetails ?: null,
            'lost_date'             => $lostDate,
            'lost_time'             => $lostTime ?: null,
            'primary_image'         => $imagePath,
            'status'                => 'published',
        ]);

        // 5. Catat Audit Log
        AuditLogService::log('CREATE_LOST_REPORT', $userId, 'lost_reports', $reportId, [
            'report_number' => $reportNumber,
            'title'         => $title,
        ]);

        // 6. Notifikasi: Laporan Kehilangan Baru ke Petugas (In-App selalu; Email opsional via pengaturan)
        try {
            \App\Services\NotificationService::notifyNewLostReportCreated([
                'id'            => $reportId,
                'report_number' => $reportNumber,
                'title'         => $title,
            ]);
        } catch (\Throwable $e) {
            error_log('[LostReportController] notifyNewLostReportCreated error: ' . $e->getMessage());
        }

        // 7. Evaluasi Pencocokan AI Otomatis (Bounded & Fail-Safe)
        try {
            $aiMatchService = new \App\Services\AiMatchService();
            $aiMatchService->runMatchingForLostReport($reportId);
        } catch (\Throwable $e) {
            error_log('[LostReportController] auto matching error: ' . $e->getMessage());
        }

        $this->setFlash('success', "Laporan kehilangan berhasil diterbitkan dengan nomor {$reportNumber}!");
        $this->redirect('/reports/lost/' . $reportId);
    }

    /**
     * GET /reports/lost/{id}
     * Detail laporan kehilangan
     */
    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $report = $this->lostReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $isLoggedIn  = AuthMiddleware::isLoggedIn();

        // Cek izin akses data privat: hanya Pemilik Laporan, Petugas, atau Admin
        $isOwner   = $isLoggedIn && ((int) $currentUser['id'] === (int) $report['reporter_user_id']);
        $isStaff   = $isLoggedIn && in_array($currentUser['role_name'] ?? '', ['admin', 'petugas'], true);
        $canAccessPrivate = $isOwner || $isStaff;
        $histories = \App\Services\StatusHistoryService::getHistories('lost_report', $id);
        $candidates = [];
        if ($isStaff || $isOwner) {
            $candidateModel = new \App\Models\MatchCandidateModel();
            $candidates = $candidateModel->getCandidatesForLostReport($id, 20.0);
        }

        // Ambil klaim terkait (Prompt 34F: Detail laporan menampilkan klaim terkait)
        $claimModel = new \App\Models\ClaimModel();
        $relatedClaims = $claimModel->getClaimsForReport(
            'lost',
            $id,
            $currentUser ? (int) $currentUser['id'] : null,
            $isStaff
        );

        $layout = AuthMiddleware::isLoggedIn() ? 'app_shell' : 'main';

        $this->render('reports/lost/show', [
            'pageTitle'        => htmlspecialchars($report['title']) . ' — Campus Find',
            'metaDescription'  => 'Detail laporan barang hilang: ' . htmlspecialchars($report['title']),
            'report'           => $report,
            'canAccessPrivate' => $canAccessPrivate,
            'isOwner'          => $isOwner,
            'isStaff'          => $isStaff,
            'user'             => $currentUser,
            'histories'        => $histories,
            'candidates'       => $candidates,
            'relatedClaims'    => $relatedClaims,
            'csrfToken'        => CsrfMiddleware::getToken(),
        ], $layout);
    }

    /**
     * GET /reports/lost/{id}/edit
     * Formulir edit laporan kehilangan (Hanya Pemilik)
     */
    public function edit(array $params = []): void
    {
        AuthMiddleware::require();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->lostReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        // Ownership authorization check
        if ((int) $report['reporter_user_id'] !== $userId && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk mengedit laporan milik pengguna lain.');
            $this->redirect('/reports/lost/' . $id);
        }

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        $this->render('reports/lost/edit', [
            'pageTitle'  => 'Edit Laporan Kehilangan — ' . htmlspecialchars($report['report_number']),
            'report'     => $report,
            'categories' => $categories,
            'locations'  => $locations,
            'csrfToken'  => CsrfMiddleware::getToken(),
            'user'       => $currentUser,
        ], 'app_shell');
    }

    /**
     * POST /reports/lost/{id}/edit
     * Proses perbarui laporan kehilangan
     */
    public function update(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->lostReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        // Ownership authorization check
        if ((int) $report['reporter_user_id'] !== $userId && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk memperbarui laporan ini.');
            $this->redirect('/reports/lost/' . $id);
        }

        // Input data
        $title          = trim((string) ($_POST['title'] ?? ''));
        $categoryId     = (int) ($_POST['category_id'] ?? 0);
        $locationId     = (int) ($_POST['last_seen_location_id'] ?? 0);
        $locationDetail = trim((string) ($_POST['location_detail'] ?? ''));
        $lostDate       = trim((string) ($_POST['lost_date'] ?? ''));
        $lostTime       = trim((string) ($_POST['lost_time'] ?? ''));
        $description    = trim((string) ($_POST['description'] ?? ''));
        $privateDetails = trim((string) ($_POST['private_details'] ?? ''));

        // Validation
        $errors = [];
        if (empty($title) || strlen($title) < 3 || strlen($title) > 150) {
            $errors[] = 'Judul / nama barang harus diisi (3 - 150 karakter).';
        }
        if ($categoryId <= 0 || !$this->categoryModel->findById($categoryId)) {
            $errors[] = 'Silakan pilih kategori barang yang valid.';
        }
        if ($locationId <= 0 || !$this->locationModel->findById($locationId)) {
            $errors[] = 'Silakan pilih lokasi yang valid.';
        }
        if (empty($lostDate)) {
            $errors[] = 'Tanggal kehilangan wajib diisi.';
        }
        if (empty($description) || strlen($description) < 10) {
            $errors[] = 'Deskripsi barang wajib diisi minimal 10 karakter.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            $this->redirect('/reports/lost/' . $id . '/edit');
        }

        // Upload foto baru jika ada
        $imagePath = null;
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = FileUploadService::uploadImage($_FILES['primary_image'], 'lost_reports');
            if (!$uploadResult['success']) {
                $this->setFlash('error', $uploadResult['error'] ?? 'Gagal mengunggah foto baru.');
                $this->redirect('/reports/lost/' . $id . '/edit');
            }
            $imagePath = $uploadResult['path'];

            // Hapus foto lama jika foto baru berhasil diunggah
            if (!empty($report['primary_image'])) {
                FileUploadService::deleteFile($report['primary_image']);
            }
        }

        $this->lostReportModel->updateReport($id, [
            'category_id'           => $categoryId,
            'last_seen_location_id' => $locationId,
            'title'                 => $title,
            'description'           => $description,
            'location_detail'       => $locationDetail ?: null,
            'private_details'       => $privateDetails ?: null,
            'lost_date'             => $lostDate,
            'lost_time'             => $lostTime ?: null,
            'primary_image'         => $imagePath,
        ]);

        AuditLogService::log('UPDATE_LOST_REPORT', $userId, 'lost_reports', $id, [
            'report_number' => $report['report_number'],
            'title'         => $title,
        ]);

        $this->setFlash('success', 'Laporan kehilangan berhasil diperbarui!');
        $this->redirect('/reports/lost/' . $id);
    }

    /**
     * POST /reports/lost/{id}/delete
     * Hapus / batalkan laporan kehilangan (Hanya Pemilik)
     */
    public function delete(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->lostReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        // Ownership authorization check
        if ((int) $report['reporter_user_id'] !== $userId && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk menghapus laporan milik pengguna lain.');
            $this->redirect('/reports/lost/' . $id);
        }

        // Hapus foto jika ada
        if (!empty($report['primary_image'])) {
            FileUploadService::deleteFile($report['primary_image']);
        }

        $this->lostReportModel->deleteReport($id);

        AuditLogService::log('DELETE_LOST_REPORT', $userId, 'lost_reports', $id, [
            'report_number' => $report['report_number'],
        ]);

        $this->setFlash('success', "Laporan {$report['report_number']} berhasil dihapus.");
        $this->redirect('/civitas/my-reports');
    }
}
