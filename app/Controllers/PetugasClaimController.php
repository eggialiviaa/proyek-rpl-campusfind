<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\ClaimModel;
use App\Models\ItemModel;
use Exception;
use Throwable;

/**
 * PetugasClaimController — Pengelolaan & verifikasi klaim kepemilikan oleh Petugas & Admin
 */
class PetugasClaimController extends BaseController
{
    private ClaimModel $claimModel;
    private ItemModel  $itemModel;

    public function __construct()
    {
        $this->claimModel = new ClaimModel();
        $this->itemModel  = new ItemModel();
    }

    /**
     * GET /petugas/claims
     * Antrean verifikasi klaim barang masuk
     */
    public function index(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        $user = AuthMiddleware::user();

        $actionStatuses  = ['pending', 'under_review', 'need_more_info'];
        $archiveStatuses = ['approved', 'completed', 'rejected', 'closed_duplicate'];

        $statusReq = isset($_GET['status']) && $_GET['status'] !== 'all' ? trim((string) $_GET['status']) : null;
        $tabReq    = isset($_GET['tab']) ? trim((string) $_GET['tab']) : null;
        $itemId    = isset($_GET['item_id']) ? (int) $_GET['item_id'] : null;
        $page      = max(1, (int) ($_GET['page'] ?? 1));
        $limit     = 15;
        $offset    = ($page - 1) * $limit;

        // Tentukan tab aktif & kriteria filter query
        if (!empty($statusReq)) {
            $queryStatus = $statusReq;
            $activeTab   = in_array($statusReq, $archiveStatuses, true) ? 'archive' : 'action';
        } elseif ($tabReq === 'archive') {
            $queryStatus = $archiveStatuses;
            $activeTab   = 'archive';
        } elseif ($tabReq === 'all') {
            $queryStatus = null;
            $activeTab   = 'all';
        } else {
            // Default: tab aksi (perlu tindakan)
            $queryStatus = $actionStatuses;
            $activeTab   = 'action';
        }

        $claims     = $this->claimModel->getClaimsForPetugas($queryStatus, $itemId, $limit, $offset);
        $totalCount = $this->claimModel->countClaimsForPetugas($queryStatus, $itemId);
        $totalPages = (int) ceil($totalCount / $limit);

        // Hitung counter status
        $pendingCount    = $this->claimModel->countClaimsForPetugas('pending');
        $reviewCount     = $this->claimModel->countClaimsForPetugas('under_review');
        $needInfoCount   = $this->claimModel->countClaimsForPetugas('need_more_info');
        $approvedCount   = $this->claimModel->countClaimsForPetugas('approved');
        $completedCount  = $this->claimModel->countClaimsForPetugas('completed');
        $rejectedCount   = $this->claimModel->countClaimsForPetugas('rejected');
        $closedCount     = $this->claimModel->countClaimsForPetugas('closed_duplicate');

        $actionCount  = $pendingCount + $reviewCount + $needInfoCount;
        $archiveCount = $approvedCount + $completedCount + $rejectedCount + $closedCount;
        $allCount     = $actionCount + $archiveCount;

        $this->render('petugas/claims/index', [
            'pageTitle'       => 'Verifikasi Klaim — Lost & Found Center',
            'metaDescription' => 'Verifikasi bukti kepemilikan dan proses serah terima barang temuan.',
            'claims'          => $claims,
            'selectedStatus'  => $statusReq,
            'activeTab'       => $activeTab,
            'selectedItemId'  => $itemId,
            'currentPage'     => $page,
            'totalPages'      => $totalPages,
            'totalCount'      => $totalCount,
            'actionCount'     => $actionCount,
            'archiveCount'    => $archiveCount,
            'allCount'        => $allCount,
            'counts'          => [
                'pending'          => $pendingCount,
                'review'           => $reviewCount,
                'need_info'        => $needInfoCount,
                'approved'         => $approvedCount,
                'completed'        => $completedCount,
                'rejected'         => $rejectedCount,
                'closed_duplicate' => $closedCount,
            ],
            'user'            => $user,
            'userRole'        => AuthMiddleware::role(),
        ], 'app_shell');
    }

    /**
     * GET /petugas/claims/{id}
     * Workspace verifikasi detail klaim (pemeriksaan bukti rahasia & perbandingan barang)
     */
    public function show(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        $user = AuthMiddleware::user();

        $claimId = (int) ($params['id'] ?? 0);
        $claim = $this->claimModel->findDetail($claimId);

        if (!$claim) {
            $this->setFlash('error', 'Klaim tidak ditemukan.');
            $this->redirect('/petugas/claims');
            return;
        }

        // Cek apakah item ini memiliki klaim lain (klaim ganda dari orang lain)
        $competingClaims = [];
        if (!empty($claim['item_id'])) {
            $competingClaims = $this->claimModel->getClaimsForPetugas(null, (int) $claim['item_id'], 50, 0);
            // Saring klaim saat ini dari daftar kompetitor
            $competingClaims = array_filter($competingClaims, fn($c) => (int) $c['id'] !== $claimId);
        }

        $this->render('petugas/claims/show', [
            'pageTitle'       => 'Verifikasi Klaim #' . $claim['claim_number'] . ' — Campus Find',
            'metaDescription' => 'Pemeriksaan bukti rahasia verifikasi kepemilikan barang.',
            'claim'           => $claim,
            'competingClaims' => $competingClaims,
            'csrfToken'       => CsrfMiddleware::getToken(),
            'user'            => $user,
            'userRole'        => AuthMiddleware::role(),
        ], 'app_shell');
    }

    /**
     * POST /petugas/claims/{id}/review
     * Mengubah status menjadi sedang diperiksa
     */
    public function review(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        CsrfMiddleware::validate();

        $claimId = (int) ($params['id'] ?? 0);
        $user = AuthMiddleware::user();

        if ($this->claimModel->startReview($claimId, (int) $user['id'])) {
            $this->setFlash('success', 'Status klaim diperbarui menjadi Sedang Diperiksa.');
        } else {
            $this->setFlash('error', 'Gagal memperbarui status pemeriksaan klaim.');
        }

        $this->redirect("/petugas/claims/{$claimId}");
    }

    /**
     * POST /petugas/claims/{id}/request-info
     * Meminta informasi tambahan dari claimant
     */
    public function requestInfo(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        CsrfMiddleware::validate();

        $claimId = (int) ($params['id'] ?? 0);
        $user = AuthMiddleware::user();
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if (empty($notes) || strlen($notes) < 5) {
            $this->setFlash('error', 'Catatan informasi yang dibutuhkan wajib diisi minimal 5 karakter.');
            $this->redirect("/petugas/claims/{$claimId}");
            return;
        }

        if ($this->claimModel->requestMoreInfo($claimId, (int) $user['id'], $notes)) {
            $this->setFlash('success', 'Permintaan informasi tambahan berhasil dikirimkan kepada pelapor klaim.');
        } else {
            $this->setFlash('error', 'Gagal memperbarui status klaim.');
        }

        $this->redirect("/petugas/claims/{$claimId}");
    }

    /**
     * POST /petugas/claims/{id}/approve
     * Menyetujui klaim & mengeksekusi penutupan otomatis klaim ganda dalam 1 transaksi
     */
    public function approve(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        CsrfMiddleware::validate();

        $claimId = (int) ($params['id'] ?? 0);
        $user = AuthMiddleware::user();
        $notes = trim((string) ($_POST['notes'] ?? ''));

        try {
            $result = $this->claimModel->approveClaim($claimId, (int) $user['id'], $notes);
            $closedCount = $result['closed_duplicates'];

            $msg = 'Klaim kepemilikan berhasil DISETUJUI!';
            if ($closedCount > 0) {
                $msg .= " Sebanyak {$closedCount} klaim ganda lainnya untuk item ini telah otomatis ditutup sistem.";
            }

            $this->setFlash('success', $msg);
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal menyetujui klaim: ' . $e->getMessage());
        }

        $this->redirect("/petugas/claims/{$claimId}");
    }

    /**
     * POST /petugas/claims/{id}/reject
     * Menolak klaim dengan alasan
     */
    public function reject(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        CsrfMiddleware::validate();

        $claimId = (int) ($params['id'] ?? 0);
        $user = AuthMiddleware::user();
        $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));

        if (empty($rejectionReason) || strlen($rejectionReason) < 5) {
            $this->setFlash('error', 'Alasan penolakan klaim wajib diisi secara jelas (minimal 5 karakter).');
            $this->redirect("/petugas/claims/{$claimId}");
            return;
        }

        if ($this->claimModel->rejectClaim($claimId, (int) $user['id'], $rejectionReason)) {
            $this->setFlash('success', 'Klaim kepemilikan telah ditolak.');
        } else {
            $this->setFlash('error', 'Gagal menolak klaim.');
        }

        $this->redirect("/petugas/claims/{$claimId}");
    }

    /**
     * GET /petugas/claims/{id}/handover
     * Form pencatatan serah terima fisik barang kepada pemilik
     */
    public function handover(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        $user = AuthMiddleware::user();

        $claimId = (int) ($params['id'] ?? 0);
        $claim = $this->claimModel->findDetail($claimId);

        if (!$claim) {
            $this->setFlash('error', 'Klaim tidak ditemukan.');
            $this->redirect('/petugas/claims');
            return;
        }

        if ($claim['status'] !== 'approved') {
            $this->setFlash('error', 'Serah terima hanya dapat dilakukan pada klaim yang telah Disetujui (Approved).');
            $this->redirect("/petugas/claims/{$claimId}");
            return;
        }

        $this->render('petugas/claims/handover', [
            'pageTitle'       => 'Serah Terima Fisik Barang #' . $claim['claim_number'] . ' — Campus Find',
            'metaDescription' => 'Catat pengembalian barang fisik kepada pemilik sah.',
            'claim'           => $claim,
            'csrfToken'       => CsrfMiddleware::getToken(),
            'user'            => $user,
        ], 'app_shell');
    }

    /**
     * POST /petugas/claims/{id}/handover
     * Eksekusi serah terima fisik barang (selesaikan klaim, fisik = returned, report = completed)
     */
    public function processHandover(array $params = []): void
    {
        AuthMiddleware::requireRole(['petugas', 'admin']);
        CsrfMiddleware::validate();

        $claimId = (int) ($params['id'] ?? 0);
        $user = AuthMiddleware::user();

        $handoverDate  = trim((string) ($_POST['handover_date'] ?? date('Y-m-d H:i:s')));
        $handoverNotes = trim((string) ($_POST['handover_notes'] ?? ''));

        if (empty($handoverNotes) || strlen($handoverNotes) < 5) {
            $this->setFlash('error', 'Catatan serah terima (nama penerima, nomor identitas KTP/KTM, kondisi serah terima) wajib diisi minimal 5 karakter.');
            $this->redirect("/petugas/claims/{$claimId}/handover");
            return;
        }

        try {
            $this->claimModel->completeHandover($claimId, (int) $user['id'], $handoverDate, $handoverNotes);
            $this->setFlash('success', 'Barang fisik berhasil diserahkan kepada pemilik! Status barang dan laporan kini berstatus Selesai (Returned / Completed).');
            $this->redirect("/petugas/claims/{$claimId}");
        } catch (Throwable $e) {
            $this->setFlash('error', 'Gagal memproses serah terima: ' . $e->getMessage());
            $this->redirect("/petugas/claims/{$claimId}/handover");
        }
    }
}
