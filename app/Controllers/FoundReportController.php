<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\FoundReportModel;
use App\Models\CategoryModel;
use App\Models\LocationModel;
use App\Services\FileUploadService;
use App\Services\AuditLogService;
use App\Services\StatusHistoryService;

/**
 * FoundReportController — Pengelolaan Laporan Barang Ditemukan (Civitas & Publik)
 */
class FoundReportController extends BaseController
{
    private FoundReportModel $foundReportModel;
    private CategoryModel $categoryModel;
    private LocationModel $locationModel;

    public function __construct()
    {
        $this->foundReportModel = new FoundReportModel();
        $this->categoryModel    = new CategoryModel();
        $this->locationModel    = new LocationModel();
    }

    /**
     * GET /reports/found
     * Katalog publik laporan barang ditemukan
     */
    public function index(array $params = []): void
    {
        $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
        $locationId = isset($_GET['location']) ? (int) $_GET['location'] : null;
        $search     = isset($_GET['q'])        ? trim((string) $_GET['q']) : null;
        $dateFrom   = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo     = trim((string) ($_GET['date_to']   ?? ''));
        $condition  = trim((string) ($_GET['condition'] ?? ''));
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
        if ($condition !== '' && !in_array($condition, ['sangat_baik', 'baik', 'rusak_ringan', 'rusak_berat'], true)) {
            $condition = '';
        }

        $reports    = $this->foundReportModel->getPublicReports(
            $categoryId, $search, $limit, $offset,
            $locationId ?: null,
            $dateFrom   ?: null,
            $dateTo     ?: null,
            $sortBy,
            $condition  ?: null
        );
        $totalCount = $this->foundReportModel->countPublicReports(
            $categoryId, $search,
            $locationId ?: null,
            $dateFrom   ?: null,
            $dateTo     ?: null,
            $condition  ?: null
        );
        $totalPages = (int) ceil($totalCount / $limit);

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        $layout = AuthMiddleware::isLoggedIn() ? 'app_shell' : 'main';

        $this->render('reports/found/index', [
            'pageTitle'        => 'Daftar Barang Ditemukan — Campus Find',
            'metaDescription'  => 'Telusuri pengumuman barang temuan di lingkungan kampus.',
            'reports'          => $reports,
            'categories'       => $categories,
            'locations'        => $locations,
            'selectedCategory' => $categoryId,
            'selectedLocation' => $locationId,
            'searchQuery'      => $search,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'selectedCondition'=> $condition,
            'sortBy'           => $sortBy,
            'currentPage'      => $page,
            'totalPages'       => $totalPages,
            'totalCount'       => $totalCount,
            'user'             => AuthMiddleware::user(),
        ], $layout);
    }

    /**
     * GET /reports/found/create
     * Formulir pembuatan laporan penemuan barang
     */
    public function create(array $params = []): void
    {
        AuthMiddleware::require();

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        $this->render('reports/found/create', [
            'pageTitle'       => 'Laporkan Barang Temuan — Campus Find',
            'metaDescription' => 'Laporkan barang yang Anda temukan di lingkungan kampus agar pemilik dapat menemukannya.',
            'categories'      => $categories,
            'locations'       => $locations,
            'csrfToken'       => CsrfMiddleware::getToken(),
            'user'            => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /reports/found atau POST /reports/found/create
     * Simpan laporan penemuan baru
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
        $locationId     = (int) ($_POST['location_id'] ?? 0);
        $locationDetail = trim((string) ($_POST['location_detail'] ?? ''));
        $itemCondition  = trim((string) ($_POST['item_condition'] ?? 'baik'));
        $foundDate      = trim((string) ($_POST['found_date'] ?? ''));
        $foundTime      = trim((string) ($_POST['found_time'] ?? ''));
        $description    = trim((string) ($_POST['description'] ?? ''));
        $privateDetails = trim((string) ($_POST['private_details'] ?? ''));

        // 2. Server-side Validation
        $errors = [];
        if (empty($title) || strlen($title) < 3 || strlen($title) > 150) {
            $errors[] = 'Nama / judul barang harus diisi (3 - 150 karakter).';
        }

        if ($categoryId <= 0 || !$this->categoryModel->findById($categoryId)) {
            $errors[] = 'Silakan pilih kategori barang yang valid.';
        }

        if ($locationId <= 0 || !$this->locationModel->findById($locationId)) {
            $errors[] = 'Silakan pilih lokasi penemuan yang valid.';
        }

        if (!in_array($itemCondition, ['sangat_baik', 'baik', 'rusak_ringan', 'rusak_berat'], true)) {
            $itemCondition = 'baik';
        }

        if (empty($foundDate)) {
            $errors[] = 'Tanggal ditemukan wajib diisi.';
        } elseif (strtotime($foundDate) > time()) {
            $errors[] = 'Tanggal penemuan tidak boleh di masa mendatang.';
        }

        if (empty($description) || strlen($description) < 10) {
            $errors[] = 'Deskripsi barang wajib diisi minimal 10 karakter.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            $this->redirect('/reports/found/create');
        }

        // 3. Upload File Gambar Opsional
        $imagePath = null;
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = FileUploadService::uploadImage($_FILES['primary_image'], 'found_reports');
            if (!$uploadResult['success']) {
                $this->setFlash('error', $uploadResult['error'] ?? 'Gagal mengunggah foto barang.');
                $this->redirect('/reports/found/create');
            }
            $imagePath = $uploadResult['path'];
        }

        // 4. Generate Report Number & Simpan ke DB
        $reportNumber = $this->foundReportModel->generateReportNumber();

        $reportId = $this->foundReportModel->create([
            'report_number'    => $reportNumber,
            'reporter_user_id' => $userId,
            'category_id'      => $categoryId,
            'location_id'      => $locationId,
            'title'            => $title,
            'description'      => $description,
            'location_detail'  => $locationDetail ?: null,
            'item_condition'   => $itemCondition,
            'private_details'  => $privateDetails ?: null,
            'found_date'       => $foundDate,
            'found_time'       => $foundTime ?: null,
            'primary_image'    => $imagePath,
            'status'           => 'published',
        ]);

        // 5. Catat Audit Log
        AuditLogService::log('CREATE_FOUND_REPORT', $userId, 'found_reports', $reportId, [
            'report_number' => $reportNumber,
            'title'         => $title,
        ]);

        // 6. Notifikasi: Laporan Temuan Baru Menunggu Penyerahan Fisik ke Petugas
        try {
            \App\Services\NotificationService::notifyNewFoundReportPending([
                'id'            => $reportId,
                'report_number' => $reportNumber,
                'title'         => $title,
            ]);
        } catch (\Throwable $e) {
            error_log('[FoundReportController] notifyNewFoundReportPending error: ' . $e->getMessage());
        }

        // 7. Evaluasi Pencocokan AI Otomatis (Bounded & Fail-Safe)
        try {
            $aiMatchService = new \App\Services\AiMatchService();
            $aiMatchService->runMatchingForFoundReport($reportId);
        } catch (\Throwable $e) {
            error_log('[FoundReportController] auto matching error: ' . $e->getMessage());
        }

        $this->setFlash('success', "Laporan barang temuan berhasil diterbitkan dengan nomor {$reportNumber}!");
        $this->redirect('/reports/found/' . $reportId);
    }

    /**
     * GET /reports/found/{id}
     * Detail laporan barang ditemukan
     */
    public function show(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $isLoggedIn  = AuthMiddleware::isLoggedIn();

        // Privasi: hanya Pemilik Laporan, Petugas, atau Admin yang boleh melihat data privat
        $isOwner = $isLoggedIn && ((int) $currentUser['id'] === (int) $report['reporter_user_id']);
        $isStaff = $isLoggedIn && in_array($currentUser['role_name'] ?? '', ['admin', 'petugas'], true);
        $canAccessPrivate = $isOwner || $isStaff;

        // Ambil riwayat status history
        $histories = StatusHistoryService::getHistories('found_report', $id);

        // Ambil klaim terkait (Prompt 34F: Detail laporan menampilkan klaim terkait)
        $claimModel = new \App\Models\ClaimModel();
        $relatedClaims = $claimModel->getClaimsForReport(
            'found', 
            $id, 
            $currentUser ? (int) $currentUser['id'] : null, 
            $isStaff
        );

        // Hak Tampil Kotak Klaim Kepemilikan (Prompt 35A):
        // 1. Hanya tampil untuk Civitas yang memenuhi syarat (Admin dan Petugas tidak melihatnya)
        // 2. Bukan pelapor temuan itu sendiri (!isOwner)
        // 3. Barang berstatus boleh diklaim (mis. Tersimpan di Center, bukan draft/completed/cancelled)
        // 4. Belum ada klaim aktif miliknya
        $userRole = $currentUser ? ($currentUser['role_name'] ?? '') : '';
        $isCivitas = ($userRole === 'civitas');
        $isHandedOver = !empty($report['is_handed_over_to_center']) || in_array($report['status'], ['handed_over_to_center', 'verified', 'claimed'], true);
        $isClaimableStatus = $isHandedOver && !in_array($report['status'], ['draft', 'completed', 'cancelled'], true);
        $hasActiveClaim = false;
        if ($isLoggedIn && $currentUser) {
            $hasActiveClaim = $claimModel->hasActiveClaimForFoundReport((int)$currentUser['id'], $id, !empty($report['item_id']) ? (int)$report['item_id'] : null);
        }
        $canSubmitClaim = $isCivitas && !$isOwner && $isClaimableStatus && !$hasActiveClaim;

        $candidates = [];
        if ($isStaff) {
            $candidateModel = new \App\Models\MatchCandidateModel();
            $candidates = $candidateModel->getCandidatesForFoundReport($id, 20.0);
        }

        $layout = AuthMiddleware::isLoggedIn() ? 'app_shell' : 'main';

        $this->render('reports/found/show', [
            'pageTitle'        => htmlspecialchars($report['title']) . ' — Campus Find',
            'metaDescription'  => 'Detail laporan barang temuan: ' . htmlspecialchars($report['title']),
            'report'           => $report,
            'canAccessPrivate' => $canAccessPrivate,
            'isOwner'          => $isOwner,
            'isStaff'          => $isStaff,
            'histories'        => $histories,
            'relatedClaims'    => $relatedClaims,
            'canSubmitClaim'   => $canSubmitClaim,
            'candidates'       => $candidates,
            'user'             => $currentUser,
            'csrfToken'        => CsrfMiddleware::getToken(),
        ], $layout);
    }

    /**
     * GET /reports/found/{id}/edit
     * Formulir edit laporan penemuan
     */
    public function edit(array $params = []): void
    {
        AuthMiddleware::require();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        // Ownership authorization check
        if ((int) $report['reporter_user_id'] !== $userId && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk mengedit laporan ini.');
            $this->redirect('/reports/found/' . $id);
        }

        // Lock check: jika status sudah masuk tahap penyerahan/verifikasi, user tidak boleh mengubah
        if (!in_array($report['status'], ['draft', 'published'], true) && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Laporan ini sudah masuk tahap penyerahan/verifikasi dan tidak dapat diedit kembali.');
            $this->redirect('/reports/found/' . $id);
        }

        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        $this->render('reports/found/edit', [
            'pageTitle'  => 'Edit Laporan Temuan — ' . htmlspecialchars($report['report_number']),
            'report'     => $report,
            'categories' => $categories,
            'locations'  => $locations,
            'csrfToken'  => CsrfMiddleware::getToken(),
            'user'       => $currentUser,
        ], 'app_shell');
    }

    /**
     * POST /reports/found/{id}/edit
     * Proses perbarui laporan temuan
     */
    public function update(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        // Ownership authorization check
        if ((int) $report['reporter_user_id'] !== $userId && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk memperbarui laporan ini.');
            $this->redirect('/reports/found/' . $id);
        }

        // Lock check
        if (!in_array($report['status'], ['draft', 'published'], true) && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Laporan ini sudah terkunci dari pengeditan karena dalam proses verifikasi/serah terima.');
            $this->redirect('/reports/found/' . $id);
        }

        // Input
        $title          = trim((string) ($_POST['title'] ?? ''));
        $categoryId     = (int) ($_POST['category_id'] ?? 0);
        $locationId     = (int) ($_POST['location_id'] ?? 0);
        $locationDetail = trim((string) ($_POST['location_detail'] ?? ''));
        $itemCondition  = trim((string) ($_POST['item_condition'] ?? 'baik'));
        $foundDate      = trim((string) ($_POST['found_date'] ?? ''));
        $foundTime      = trim((string) ($_POST['found_time'] ?? ''));
        $description    = trim((string) ($_POST['description'] ?? ''));
        $privateDetails = trim((string) ($_POST['private_details'] ?? ''));

        // Validation
        $errors = [];
        if (empty($title) || strlen($title) < 3 || strlen($title) > 150) {
            $errors[] = 'Nama / judul barang harus diisi (3 - 150 karakter).';
        }
        if ($categoryId <= 0 || !$this->categoryModel->findById($categoryId)) {
            $errors[] = 'Silakan pilih kategori barang yang valid.';
        }
        if ($locationId <= 0 || !$this->locationModel->findById($locationId)) {
            $errors[] = 'Silakan pilih lokasi yang valid.';
        }
        if (empty($foundDate)) {
            $errors[] = 'Tanggal penemuan wajib diisi.';
        }
        if (empty($description) || strlen($description) < 10) {
            $errors[] = 'Deskripsi barang wajib diisi minimal 10 karakter.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            $this->redirect('/reports/found/' . $id . '/edit');
        }

        // Upload foto baru jika ada
        $imagePath = null;
        if (isset($_FILES['primary_image']) && $_FILES['primary_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = FileUploadService::uploadImage($_FILES['primary_image'], 'found_reports');
            if (!$uploadResult['success']) {
                $this->setFlash('error', $uploadResult['error'] ?? 'Gagal mengunggah foto baru.');
                $this->redirect('/reports/found/' . $id . '/edit');
            }
            $imagePath = $uploadResult['path'];

            if (!empty($report['primary_image'])) {
                FileUploadService::deleteFile($report['primary_image']);
            }
        }

        $this->foundReportModel->updateReport($id, [
            'category_id'     => $categoryId,
            'location_id'     => $locationId,
            'title'           => $title,
            'description'     => $description,
            'location_detail' => $locationDetail ?: null,
            'item_condition'  => $itemCondition,
            'private_details' => $privateDetails ?: null,
            'found_date'      => $foundDate,
            'found_time'      => $foundTime ?: null,
            'primary_image'   => $imagePath,
        ]);

        AuditLogService::log('UPDATE_FOUND_REPORT', $userId, 'found_reports', $id, [
            'report_number' => $report['report_number'],
            'title'         => $title,
        ]);

        $this->setFlash('success', 'Laporan barang temuan berhasil diperbarui!');
        $this->redirect('/reports/found/' . $id);
    }

    /**
     * POST /reports/found/{id}/handover
     * Pelapor mengajukan/menandai kesiapan penyerahan fisik barang ke Lost & Found Center
     */
    public function handover(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        // Hanya pelapor atau admin/petugas yang dapat mengajukan serah terima
        $isOwner = ((int) $report['reporter_user_id'] === $userId);
        $isStaff = in_array($currentUser['role_name'] ?? '', ['admin', 'petugas'], true);

        if (!$isOwner && !$isStaff) {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk tindakan ini.');
            $this->redirect('/reports/found/' . $id);
        }

        if ($report['status'] !== 'published') {
            $this->setFlash('error', 'Laporan ini sudah berada pada tahap penyerahan/verifikasi.');
            $this->redirect('/reports/found/' . $id);
        }

        $this->foundReportModel->updateStatus(
            $id,
            'pending_handover',
            $userId,
            'Pelapor menandai siap menyerahkan barang temuan ke Posko Lost & Found Center.'
        );

        AuditLogService::log('HANDOVER_FOUND_REPORT', $userId, 'found_reports', $id, [
            'report_number' => $report['report_number'],
            'new_status'    => 'pending_handover',
        ]);

        $this->setFlash('success', 'Status diperbarui: Menunggu penyerahan ke Lost & Found Center. Silakan bawa barang ke posko layanan.');
        $this->redirect('/reports/found/' . $id);
    }

    /**
     * POST /reports/found/{id}/delete
     * Hapus laporan temuan (hanya jika masih berstatus published dan milik sendiri)
     */
    public function delete(array $params = []): void
    {
        AuthMiddleware::require();
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $userId = (int) $currentUser['id'];

        if ((int) $report['reporter_user_id'] !== $userId && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Anda tidak memiliki izin untuk menghapus laporan ini.');
            $this->redirect('/reports/found/' . $id);
        }

        if (!in_array($report['status'], ['draft', 'published'], true) && ($currentUser['role_name'] ?? '') !== 'admin') {
            $this->setFlash('error', '⛔ Laporan yang sudah masuk tahap verifikasi/gudang tidak dapat dihapus.');
            $this->redirect('/reports/found/' . $id);
        }

        if (!empty($report['primary_image'])) {
            FileUploadService::deleteFile($report['primary_image']);
        }

        $this->foundReportModel->deleteReport($id);

        AuditLogService::log('DELETE_FOUND_REPORT', $userId, 'found_reports', $id, [
            'report_number' => $report['report_number'],
        ]);

        $this->setFlash('success', "Laporan {$report['report_number']} berhasil dihapus.");
        $this->redirect('/civitas/my-reports');
    }
}
