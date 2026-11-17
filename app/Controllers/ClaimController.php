<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\ClaimModel;
use App\Models\ItemModel;
use App\Models\FoundReportModel;
use App\Models\LostReportModel;
use App\Services\FileUploadService;

/**
 * ClaimController — Menangani pengajuan klaim kepemilikan oleh civitas akademika
 */
class ClaimController extends BaseController
{
    private ClaimModel       $claimModel;
    private ItemModel        $itemModel;
    private FoundReportModel $foundReportModel;
    private LostReportModel  $lostReportModel;

    public function __construct()
    {
        $this->claimModel       = new ClaimModel();
        $this->itemModel        = new ItemModel();
        $this->foundReportModel = new FoundReportModel();
        $this->lostReportModel  = new LostReportModel();
    }

    /**
     * GET /claims/create
     * Formulir pengajuan klaim kepemilikan
     */
    public function create(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = AuthMiddleware::role();

        // Prompt 35A: Admin dan Petugas ditolak dari endpoint klaim
        if (in_array($userRole, ['petugas', 'admin'], true)) {
            $this->abort(403);
            return;
        }

        $itemId  = isset($_GET['item_id']) ? (int) $_GET['item_id'] : null;
        $foundId = isset($_GET['found_id']) ? (int) $_GET['found_id'] : null;

        $targetItem  = null;
        $foundReport = null;

        if ($itemId) {
            $targetItem = $this->itemModel->findDetail($itemId);
            if (!$targetItem) {
                $this->setFlash('error', 'Barang inventaris tidak ditemukan.');
                $this->redirect('/search');
                return;
            }
        } elseif ($foundId) {
            $foundReport = $this->foundReportModel->findById($foundId);
            if (!$foundReport) {
                $this->setFlash('error', 'Laporan penemuan tidak ditemukan.');
                $this->redirect('/search');
                return;
            }

            // Prompt 35A: Bukan pelapor temuan itu sendiri
            if ((int) $foundReport['reporter_user_id'] === $userId) {
                $this->setFlash('error', 'Anda tidak dapat mengajukan klaim atas barang temuan yang Anda laporkan sendiri.');
                $this->redirect('/reports/found/' . $foundReport['id']);
                return;
            }

            // Prompt 35A: Barang berstatus boleh diklaim (Tersimpan di Center, bukan draft/completed/cancelled)
            $isHandedOver = !empty($foundReport['is_handed_over_to_center']) || in_array($foundReport['status'] ?? '', ['handed_over_to_center', 'verified', 'claimed'], true);
            if (!$isHandedOver || in_array($foundReport['status'] ?? '', ['draft', 'completed', 'cancelled'], true)) {
                $this->setFlash('error', 'Barang temuan ini belum dapat diklaim karena belum diserahkan ke Posko L&F Center.');
                $this->redirect('/reports/found/' . $foundReport['id']);
                return;
            }

            // Cari apakah item sudah terdaftar di center
            if (!empty($foundReport['matched_item_id'])) {
                $targetItem = $this->itemModel->findDetail((int) $foundReport['matched_item_id']);
                $itemId = (int) $foundReport['matched_item_id'];
            }
        } else {
            $this->setFlash('error', 'Silakan pilih barang temuan yang ingin Anda klaim terlebih dahulu.');
            $this->redirect('/search');
            return;
        }

        // Cek apakah user sudah punya klaim aktif pada barang ini (Prompt 35A)
        $targetFoundId = $foundReport ? (int)$foundReport['id'] : null;
        if ($this->claimModel->hasActiveClaimForFoundReport($userId, $targetFoundId, $itemId)) {
            $this->setFlash('warning', 'Anda sudah memiliki pengajuan klaim aktif untuk barang ini. Silakan pantau status klaim Anda.');
            $this->redirect('/civitas/my-claims');
            return;
        }

        // Ambil daftar laporan kehilangan milik user sendiri untuk dropdown referensi pembantu
        $userLostReports = $this->lostReportModel->getUserReports($userId);

        $this->render('claims/create', [
            'pageTitle'       => 'Ajukan Klaim Kepemilikan — Campus Find',
            'metaDescription' => 'Ajukan klaim kepemilikan barang temuan di Lost & Found Center kampus dengan bukti privat.',
            'targetItem'      => $targetItem,
            'foundReport'     => $foundReport,
            'itemId'          => $itemId,
            'foundId'         => $foundId,
            'userLostReports' => $userLostReports,
            'csrfToken'       => CsrfMiddleware::getToken(),
            'user'            => $user,
        ], 'app_shell');
    }

    /**
     * POST /claims atau POST /claims/create
     * Simpan pengajuan klaim baru
     */
    public function store(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = AuthMiddleware::role();

        // Prompt 35A: Admin dan Petugas ditolak dari endpoint klaim
        if (in_array($userRole, ['petugas', 'admin'], true)) {
            $this->abort(403);
            return;
        }

        CsrfMiddleware::validate();

        $itemId          = !empty($_POST['item_id']) ? (int) $_POST['item_id'] : null;
        $foundReportId   = !empty($_POST['found_report_id']) ? (int) $_POST['found_report_id'] : null;
        $lostReportId    = !empty($_POST['lost_report_id']) ? (int) $_POST['lost_report_id'] : null;
        $claimReason     = trim((string) ($_POST['claim_reason'] ?? ''));
        $ownershipDesc   = trim((string) ($_POST['ownership_proof_description'] ?? ''));
        $uniqueFeatures  = trim((string) ($_POST['unique_features'] ?? ''));
        $additionalNotes = trim((string) ($_POST['additional_notes'] ?? ''));

        $errors = [];

        if (empty($itemId) && empty($foundReportId)) {
            $errors[] = 'Barang yang diklaim tidak valid.';
        }

        // Prompt 35A: Bukan pelapor temuan itu sendiri & status barang boleh diklaim
        if ($foundReportId) {
            $foundReport = $this->foundReportModel->findById($foundReportId);
            if ($foundReport) {
                if ((int) $foundReport['reporter_user_id'] === $userId) {
                    $errors[] = 'Anda tidak dapat mengajukan klaim atas barang temuan yang Anda laporkan sendiri.';
                }
                $isHandedOver = !empty($foundReport['is_handed_over_to_center']) || in_array($foundReport['status'] ?? '', ['handed_over_to_center', 'verified', 'claimed'], true);
                if (!$isHandedOver || in_array($foundReport['status'] ?? '', ['draft', 'completed', 'cancelled'], true)) {
                    $errors[] = 'Barang temuan ini belum atau tidak dapat diklaim (harus diserahkan ke Posko L&F Center terlebih dahulu).';
                }
            }
        }

        if (empty($claimReason) || strlen($claimReason) < 10) {
            $errors[] = 'Alasan klaim wajib diisi minimal 10 karakter.';
        }

        if (empty($ownershipDesc) || strlen($ownershipDesc) < 10) {
            $errors[] = 'Informasi & deskripsi bukti kepemilikan wajib diisi minimal 10 karakter.';
        }

        if (empty($uniqueFeatures) || strlen($uniqueFeatures) < 10) {
            $errors[] = 'Detail unik barang wajib diisi minimal 10 karakter (misal goresan, stiker khusus, wallpaper layar, isi dalam tas).';
        }

        if ($this->claimModel->hasActiveClaimForFoundReport($userId, $foundReportId, $itemId)) {
            $errors[] = 'Anda sudah memiliki pengajuan klaim aktif untuk barang ini.';
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode('<br>', $errors));
            $redir = '/claims/create?' . ($itemId ? "item_id={$itemId}" : "found_id={$foundReportId}");
            $this->redirect($redir);
            return;
        }

        // Upload berkas bukti kepemilikan (opsional atau wajib jika ada)
        $evidenceFiles = [];
        if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadRes = FileUploadService::uploadClaimEvidence($_FILES['evidence']);
            if (!$uploadRes['success']) {
                $this->setFlash('error', $uploadRes['error'] ?? 'Gagal mengunggah file bukti klaim.');
                $redir = '/claims/create?' . ($itemId ? "item_id={$itemId}" : "found_id={$foundReportId}");
                $this->redirect($redir);
                return;
            }
            if ($uploadRes['path']) {
                $evidenceFiles[] = [
                    'file_path'     => $uploadRes['path'],
                    'original_name' => $uploadRes['original_name'] ?? 'Bukti Kepemilikan',
                    'type'          => $uploadRes['type'] ?? 'image',
                ];
            }
        }

        try {
            $claimId = $this->claimModel->createClaim([
                'claimant_user_id'            => $userId,
                'item_id'                     => $itemId,
                'found_report_id'             => $foundReportId,
                'lost_report_id'              => $lostReportId ?: null,
                'claim_reason'                => $claimReason,
                'ownership_proof_description' => $ownershipDesc,
                'unique_features'             => $uniqueFeatures,
                'additional_notes'            => $additionalNotes ?: null,
            ], $evidenceFiles);

            // Notifikasi: Pengajuan Klaim Baru ke Petugas
            try {
                $claimData = $this->claimModel->findDetail($claimId);
                \App\Services\NotificationService::notifyNewClaimSubmitted($claimData ?: [
                    'id'           => $claimId,
                    'claim_number' => '#' . $claimId,
                    'item_title'   => 'Barang Temuan',
                ]);
            } catch (\Throwable $notifEx) {
                error_log('[ClaimController] notifyNewClaimSubmitted error: ' . $notifEx->getMessage());
            }

            $this->setFlash('success', 'Klaim kepemilikan Anda berhasil diajukan! Petugas Lost & Found Center akan segera meninjau bukti privat yang Anda berikan.');
            $this->redirect("/claims/{$claimId}");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Terjadi kesalahan sistem saat menyimpan klaim: ' . $e->getMessage());
            $redir = '/claims/create?' . ($itemId ? "item_id={$itemId}" : "found_id={$foundReportId}");
            $this->redirect($redir);
        }
    }

    /**
     * GET /claims/{id}
     * Detail klaim milik civitas (hanya boleh dilihat pemilik klaim atau petugas)
     */
    public function show(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = AuthMiddleware::role();

        $claimId = (int) ($params['id'] ?? 0);
        $claim = $this->claimModel->findDetail($claimId);

        if (!$claim) {
            $this->setFlash('error', 'Data klaim tidak ditemukan.');
            $this->redirect('/civitas/my-claims');
            return;
        }

        // STRICT PRIVACY: Hanya pelapor klaim atau Petugas/Admin yang berhak melihat detail klaim
        if ((int) $claim['claimant_user_id'] !== $userId && !in_array($userRole, ['petugas', 'admin'], true)) {
            http_response_code(403);
            $this->render('errors/403', [
                'pageTitle' => 'Akses Ditolak — Campus Find',
                'message'   => 'Anda tidak memiliki hak akses untuk melihat data verifikasi klaim ini.',
            ], 'app_shell');
            return;
        }

        $this->render('claims/show', [
            'pageTitle'       => 'Detail Klaim #' . $claim['claim_number'] . ' — Campus Find',
            'metaDescription' => 'Status dan riwayat verifikasi klaim kepemilikan barang.',
            'claim'           => $claim,
            'user'            => $user,
            'userRole'        => $userRole,
        ], 'app_shell');
    }

    /**
     * GET /civitas/my-claims
     * Daftar klaim yang pernah diajukan oleh civitas
     */
    public function myClaims(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];

        $claims = $this->claimModel->getClaimsByClaimant($userId);

        $this->render('claims/my_claims', [
            'pageTitle'       => 'Klaim Kepemilikan Saya — Campus Find',
            'metaDescription' => 'Pantau status seluruh pengajuan klaim kepemilikan barang Anda.',
            'claims'          => $claims,
            'user'            => $user,
        ], 'app_shell');
    }
}
