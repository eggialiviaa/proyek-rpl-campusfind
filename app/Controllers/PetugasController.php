<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\FoundReportModel;
use App\Models\ItemModel;
use App\Models\StorageLocationModel;
use App\Services\AuditLogService;
use App\Services\StatusHistoryService;
use App\Services\NotificationService;

/**
 * PetugasController — Modul Lost & Found Center untuk Petugas & Admin
 */
class PetugasController extends BaseController
{
    private FoundReportModel $foundReportModel;
    private ItemModel $itemModel;
    private StorageLocationModel $storageModel;

    public function __construct()
    {
        // Guard ketat backend: hanya petugas (atau admin) yang diizinkan mengelola fisik barang
        AuthMiddleware::requireRole(['petugas', 'admin']);
        $this->foundReportModel = new FoundReportModel();
        $this->itemModel        = new ItemModel();
        $this->storageModel     = new StorageLocationModel();
    }

    /**
     * GET /petugas/handover
     * Dialihkan ke tampilan terfilter Laporan > Temuan (status 'pending_handover') (Prompt 34F)
     */
    public function pendingHandover(array $params = []): void
    {
        $user = AuthMiddleware::user();
        if (!$user) {
            AuthMiddleware::handleUnauthenticated('/reports/manage?tab=found&status=pending_handover');
            return;
        }
        if (!in_array($user['role_name'] ?? '', ['petugas', 'admin'], true)) {
            $this->abort(403);
            return;
        }

        $statusFilter = isset($_GET['status']) && $_GET['status'] !== 'all' ? trim((string) $_GET['status']) : 'pending_handover';
        if (isset($_GET['status']) && $_GET['status'] === 'all') {
            $this->redirect('/reports/manage?tab=found');
            return;
        }

        $this->redirect('/reports/manage?tab=found&status=' . urlencode($statusFilter));
    }

    /**
     * GET /petugas/found-reports
     * Dialihkan ke tampilan terfilter Laporan > Temuan (Prompt 34F)
     */
    public function foundReports(array $params = []): void
    {
        $user = AuthMiddleware::user();
        if (!$user) {
            AuthMiddleware::handleUnauthenticated('/reports/manage?tab=found');
            return;
        }
        if (!in_array($user['role_name'] ?? '', ['petugas', 'admin'], true)) {
            $this->abort(403);
            return;
        }

        $statusFilter = isset($_GET['status']) && $_GET['status'] !== 'all' ? trim((string) $_GET['status']) : null;
        if ($statusFilter) {
            $this->redirect('/reports/manage?tab=found&status=' . urlencode($statusFilter));
            return;
        }

        $this->redirect('/reports/manage?tab=found');
    }

    /**
     * GET /petugas/intake/{id} atau GET /petugas/found-reports/{id}/verify
     * Form penerimaan barang fisik, pemeriksaan awal, penentuan kode item, dan alokasi rak
     */
    public function intakeForm(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        if ($report['status'] === 'verified' && $report['item_id'] !== null) {
            $this->setFlash('info', 'Barang temuan ini sudah diterima sebelumnya dan terdaftar di inventaris Center.');
            $this->redirect('/petugas/items/' . $report['item_id']);
        }

        $storageLocations = $this->storageModel->getAllWithCapacity();
        $suggestedCode    = $this->itemModel->generateItemCode();

        $this->render('petugas/intake', [
            'pageTitle'        => 'Penerimaan Fisik Barang — ' . htmlspecialchars($report['report_number']),
            'report'           => $report,
            'storageLocations' => $storageLocations,
            'suggestedCode'    => $suggestedCode,
            'csrfToken'        => CsrfMiddleware::getToken(),
            'user'             => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * Alias method untuk verifyForm
     */
    public function verifyForm(array $params = []): void
    {
        $this->intakeForm($params);
    }

    /**
     * POST /petugas/intake/{id} atau POST /petugas/found-reports/{id}/verify
     * Eksekusi penerimaan barang fisik ke gudang Center
     */
    public function intakeStore(array $params = []): void
    {
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $report = $this->foundReportModel->findDetail($id);

        if (!$report) {
            $this->abort(404);
        }

        $currentUser = AuthMiddleware::user();
        $verifierId  = (int) $currentUser['id'];

        $itemCode          = strtoupper(trim((string) ($_POST['item_code'] ?? '')));
        $storageLocationId = (int) ($_POST['storage_location_id'] ?? 0);
        $physicalCondition = trim((string) ($_POST['physical_condition'] ?? 'baik'));
        $physicalStatus    = trim((string) ($_POST['physical_status'] ?? 'stored'));
        $internalNotes     = trim((string) ($_POST['internal_notes'] ?? ''));

        // Validasi input
        $errors = [];
        if (empty($itemCode) || strlen($itemCode) < 3) {
            $errors[] = 'Kode barang wajib diisi.';
        } elseif ($this->itemModel->isCodeExists($itemCode)) {
            $errors[] = "Kode barang '{$itemCode}' sudah digunakan di inventaris! Gunakan kode unik.";
        }

        if ($storageLocationId <= 0 || !$this->storageModel->findById($storageLocationId)) {
            $errors[] = 'Silakan pilih lokasi rak/lemari penyimpanan yang valid.';
        }

        if (!in_array($physicalCondition, ['sangat_baik', 'baik', 'rusak_ringan', 'rusak_berat'], true)) {
            $physicalCondition = 'baik';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            $this->redirect('/petugas/intake/' . $id);
        }

        // 1. Buat record kanonik di tabel items
        $itemId = $this->itemModel->createItem([
            'item_code'           => $itemCode,
            'category_id'         => (int) $report['category_id'],
            'storage_location_id' => $storageLocationId,
            'verified_by_user_id' => $verifierId,
            'title'               => $report['title'],
            'description'         => $report['description'],
            'internal_notes'      => $internalNotes ?: null,
            'primary_image'       => $report['primary_image'],
            'physical_condition'  => $physicalCondition,
            'status'              => $physicalStatus,
        ]);

        // 2. Update status found_reports ke verified & set item_id
        $pdo = \Core\Database::getInstance()->getPdo();
        $oldReportStatus = $report['status'];
        $stmt = $pdo->prepare("
            UPDATE found_reports SET
                item_id = ?,
                is_handed_over_to_center = 1,
                handed_over_at = IFNULL(handed_over_at, NOW()),
                status = 'verified',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$itemId, $id]);

        // 3. Catat history transisi laporan
        StatusHistoryService::record(
            'found_report',
            $id,
            $oldReportStatus,
            'verified',
            $verifierId,
            "Barang fisik diterima di posko L&F Center (Kode Fisik: {$itemCode})"
        );

        // 4. Audit Log
        AuditLogService::log('INTAKE_PHYSICAL_ITEM', $verifierId, 'items', $itemId, [
            'item_code'        => $itemCode,
            'found_report_id'  => $id,
            'storage_id'       => $storageLocationId,
            'condition'        => $physicalCondition,
        ]);

        // Notifikasi in-app ke pelapor barang temuan
        NotificationService::notifyFoundReportReceived(
            $report,
            ['item_code' => $itemCode, 'id' => $itemId]
        );

        // Catatan: Evaluasi AI Matching sengaja dipisahkan dari alur kritis intake
        // agar transaksi penerimaan barang fisik selesai seketika (sub-second) tanpa terhambat latensi AI.
        // AI Matching dijalankan secara asynchronous / on-demand melalui antarmuka detail barang.
        $this->setFlash('success', "Barang fisik berhasil diterima. Kode inventaris: {$itemCode}");
        $this->redirect('/petugas/items/' . $itemId . '?auto_match=1');
    }

    /**
     * Alias method untuk verifyStore
     */
    public function verifyStore(array $params = []): void
    {
        $this->intakeStore($params);
    }

    /**
     * GET /petugas/inventory
     * Center Inventory — Katalog barang fisik L&F Center (Inventaris Aktif vs Riwayat Barang)
     */
    public function inventory(array $params = []): void
    {
        $tab       = (isset($_GET['tab']) && $_GET['tab'] === 'history') ? 'history' : 'active';
        $status    = isset($_GET['status']) && $_GET['status'] !== '' ? trim((string) $_GET['status']) : null;
        $storageId = isset($_GET['storage']) ? (int) $_GET['storage'] : null;
        $search    = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $limit     = 15;
        $offset    = ($page - 1) * $limit;

        // Reset filter status jika tidak cocok dengan tab yang sedang aktif
        $poskoStatuses = \App\Services\MetricDefinitionService::getPoskoItemStatuses();
        $historyStatuses = \App\Models\ItemModel::HISTORICAL_STATUSES;
        if ($status !== null) {
            if ($tab === 'active' && !in_array($status, $poskoStatuses, true)) {
                $status = null;
            } elseif ($tab === 'history' && !in_array($status, $historyStatuses, true)) {
                $status = null;
            }
        }

        $items            = $this->itemModel->getAllItems($limit, $offset, $status, $storageId, $search, $tab);
        $totalCount       = $this->itemModel->countAllItems($status, $storageId, $search, $tab);
        $activeCount      = $this->itemModel->countAllItems(null, null, null, 'active');
        $historyCount     = $this->itemModel->countAllItems(null, null, null, 'history');
        $totalPages       = (int) ceil($totalCount / $limit);
        $storageLocations = $this->storageModel->getAllWithCapacity();

        $this->render('petugas/inventory', [
            'pageTitle'        => ($tab === 'history') ? 'Riwayat Barang — L&F Center' : 'Center Inventory — Gudang Fisik L&F',
            'tab'              => $tab,
            'items'            => $items,
            'storageLocations' => $storageLocations,
            'selectedStatus'   => $status,
            'selectedStorage'  => $storageId,
            'searchQuery'      => $search,
            'currentPage'      => $page,
            'totalPages'       => $totalPages,
            'totalCount'       => $totalCount,
            'activeCount'      => $activeCount,
            'historyCount'     => $historyCount,
            'user'             => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * GET /petugas/items/{id}
     * Detail barang fisik kanonik di L&F Center
     */
    public function itemDetail(array $params = []): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->itemModel->findDetail($id);

        if (!$item) {
            $this->abort(404);
        }

        // Ambil riwayat status history item fisik
        $itemHistories = StatusHistoryService::getHistories('item', $id);

        // Ambil riwayat laporan temuan jika ada
        $reportHistories = [];
        if (!empty($item['found_report_id'])) {
            $reportHistories = StatusHistoryService::getHistories('found_report', (int) $item['found_report_id']);
        }

        $storageLocations = $this->storageModel->getAllWithCapacity();

        // Ambil kandidat kecocokan AI untuk laporan temuan ini jika ada
        $matchingCandidates = [];
        if (!empty($item['found_report_id'])) {
            $candidateModel = new \App\Models\MatchCandidateModel();
            $matchingCandidates = $candidateModel->getCandidatesForFoundReport((int) $item['found_report_id']);
        }

        $this->render('petugas/item_detail', [
            'pageTitle'          => 'Detail Barang Fisik — ' . htmlspecialchars($item['item_code']),
            'item'               => $item,
            'itemHistories'      => $itemHistories,
            'reportHistories'    => $reportHistories,
            'storageLocations'   => $storageLocations,
            'matchingCandidates' => $matchingCandidates,
            'autoMatch'          => !empty($_GET['auto_match']),
            'csrfToken'          => CsrfMiddleware::getToken(),
            'user'               => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /petugas/items/{id}/status
     * Ubah status fisik barang
     */
    public function updatePhysicalStatus(array $params = []): void
    {
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $item = $this->itemModel->findDetail($id);

        if (!$item) {
            $this->abort(404);
        }

        if (in_array($item['status'], ['returned', 'donated', 'disposed'], true)) {
            $this->setFlash('error', 'Barang ini telah berstatus final (' . ItemModel::getPhysicalStatusLabel($item['status']) . ') dan tidak dapat diubah status fisiknya kembali.');
            $this->redirect('/petugas/items/' . $id);
            return;
        }

        $currentUser = AuthMiddleware::user();
        $userId      = (int) $currentUser['id'];

        $newStatus = trim((string) ($_POST['physical_status'] ?? ''));
        $notes     = trim((string) ($_POST['status_notes'] ?? ''));

        try {
            $this->itemModel->updatePhysicalStatus($id, $newStatus, $userId, $notes);
            $this->setFlash('success', "Status fisik barang berhasil diubah menjadi: " . ItemModel::getPhysicalStatusLabel($newStatus));
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
        }

        $this->redirect('/petugas/items/' . $id);
    }

    /**
     * POST /petugas/items/{id}/relocate
     * Pindahkan barang ke rak / lemari penyimpanan lain
     */
    public function relocateStorage(array $params = []): void
    {
        CsrfMiddleware::validate();

        $id = (int) ($params['id'] ?? 0);
        $item = $this->itemModel->findDetail($id);

        if (!$item) {
            $this->abort(404);
        }

        if (in_array($item['status'], ['returned', 'donated', 'disposed'], true)) {
            $this->setFlash('error', 'Barang ini telah berstatus final (' . ItemModel::getPhysicalStatusLabel($item['status']) . ') dan sudah keluar dari penyimpanan rak posko.');
            $this->redirect('/petugas/items/' . $id);
            return;
        }

        $storageLocationId = (int) ($_POST['storage_location_id'] ?? 0);
        $notes = trim((string) ($_POST['relocate_notes'] ?? ''));

        $currentUser = AuthMiddleware::user();
        $userId      = (int) $currentUser['id'];

        if ($storageLocationId <= 0 || !$this->storageModel->findById($storageLocationId)) {
            $this->setFlash('error', 'Pilih lokasi rak tujuan yang valid.');
            $this->redirect('/petugas/items/' . $id);
            return;
        }

        try {
            $this->itemModel->updateStorageLocation($id, $storageLocationId, $userId, $notes);
            $this->setFlash('success', 'Lokasi penyimpanan barang berhasil dipindahkan.');
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
        }
        $this->redirect('/petugas/items/' . $id);
    }

    /**
     * GET /petugas/storage-locations
     * Manajemen rak / lemari gudang L&F Center
     */
    public function storageLocations(array $params = []): void
    {
        $locations = $this->storageModel->getAllWithCapacity();

        $this->render('petugas/storage_locations', [
            'pageTitle' => 'Manajemen Lokasi Rak Penyimpanan — L&F Center',
            'locations' => $locations,
            'csrfToken' => CsrfMiddleware::getToken(),
            'user'      => AuthMiddleware::user(),
        ], 'app_shell');
    }

    /**
     * POST /petugas/storage-locations/create
     * Tambah lokasi rak penyimpanan baru
     */
    public function storageStore(array $params = []): void
    {
        CsrfMiddleware::validate();

        $code        = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $name        = trim((string) ($_POST['name'] ?? ''));
        $zone        = trim((string) ($_POST['zone'] ?? ''));
        $capacity    = max(1, (int) ($_POST['capacity'] ?? 50));
        $description = trim((string) ($_POST['description'] ?? ''));

        if (empty($code) || empty($name)) {
            $this->setFlash('error', 'Kode rak dan nama lokasi wajib diisi.');
            $this->redirect('/petugas/storage-locations');
        }

        try {
            $newId = $this->storageModel->create([
                'code'        => $code,
                'name'        => $name,
                'zone'        => $zone,
                'capacity'    => $capacity,
                'description' => $description,
            ]);

            $currentUser = AuthMiddleware::user();
            AuditLogService::log('CREATE_STORAGE_LOCATION', (int) $currentUser['id'], 'storage_locations', $newId, [
                'code' => $code,
                'name' => $name,
            ]);

            $this->setFlash('success', "Lokasi rak penyimpanan '{$code}' berhasil ditambahkan!");
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('error', $e->getMessage());
        }

        $this->redirect('/petugas/storage-locations');
    }

    /**
     * GET /petugas/claims
     * Antrean verifikasi klaim barang
     */
    public function claims(array $params = []): void
    {
        $this->redirect('/petugas/claims');
    }
}
