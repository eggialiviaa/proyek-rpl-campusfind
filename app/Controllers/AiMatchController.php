<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Services\AiMatchService;
use App\Services\NvidiaNimService;
use App\Models\MatchCandidateModel;
use App\Models\LostReportModel;
use App\Models\FoundReportModel;

/**
 * AiMatchController — Controller untuk Rekomendasi Kecocokan AI (NVIDIA NIM)
 */
class AiMatchController extends BaseController
{
    private AiMatchService $aiMatchService;
    private MatchCandidateModel $candidateModel;
    private LostReportModel $lostReportModel;
    private FoundReportModel $foundReportModel;

    public function __construct()
    {
        $this->aiMatchService = new AiMatchService();
        $this->candidateModel = new MatchCandidateModel();
        $this->lostReportModel = new LostReportModel();
        $this->foundReportModel = new FoundReportModel();
    }

    /**
     * Tampilkan halaman daftar rekomendasi kandidat barang untuk 1 Lost Report
     * Route: GET /ai-match/lost/{id}
     */
    public function viewCandidatesForLost(array $vars): void
    {
        AuthMiddleware::requireAuth();

        $lostReportId = (int) ($vars['id'] ?? 0);
        $lostReport = $this->lostReportModel->findById($lostReportId);

        if (!$lostReport) {
            $this->flash('danger', 'Laporan kehilangan tidak ditemukan.');
            $this->redirect('/reports/lost');
            return;
        }

        // Cek hak akses: Pemilik laporan ATAU Petugas/Admin
        $user = AuthMiddleware::user();
        $isOwner = ((int) $lostReport['reporter_user_id'] === (int) $user['id']);
        $isOfficer = in_array(AuthMiddleware::role(), ['petugas', 'admin'], true);

        if (!$isOwner && !$isOfficer) {
            $this->flash('danger', 'Anda tidak memiliki hak akses melihat rekomendasi kecocokan laporan ini.');
            $this->redirect('/reports/lost/' . $lostReportId);
            return;
        }

        // Ambil data kandidat dari database
        $threshold = (float) \App\Services\SettingService::get('ai_matching_threshold', 60);
        $candidates = $this->candidateModel->getCandidatesForLostReport($lostReportId, 20.0);
        $diagnostic = $_SESSION['ai_match_diagnostic'] ?? null;

        // Jika belum ada kandidat dan belum ada diagnostik aktif, jalankan AI match otomatis 1x
        if (empty($candidates) && empty($diagnostic)) {
            $matchResult = $this->aiMatchService->runMatchingForLostReport($lostReportId);
            $candidates = $this->candidateModel->getCandidatesForLostReport($lostReportId, 20.0);
            $diagnostic = $matchResult['diagnostic'] ?? ($_SESSION['ai_match_diagnostic'] ?? null);
        }

        $nvidiaService = new NvidiaNimService();
        $isNimConfigured = $nvidiaService->isAvailable();
        $providerName = $this->aiMatchService->getActiveProviderName();

        $isEligibleStatus = in_array($lostReport['status'] ?? '', ['published', 'investigating', 'matched'], true);
        $canRetry = ($isOwner || $isOfficer) && $isEligibleStatus;

        $this->render('ai_match/candidates', [
            'lostReport'      => $lostReport,
            'candidates'      => $candidates,
            'threshold'       => $threshold,
            'diagnostic'      => $diagnostic,
            'isNimConfigured' => $isNimConfigured,
            'providerName'    => $providerName,
            'isOfficer'       => $isOfficer,
            'isOwner'         => $isOwner,
            'canRetry'        => $canRetry,
            'pageTitle'       => 'Rekomendasi Kecocokan AI — ' . $lostReport['title'],
        ], 'app_shell');
    }

    /**
     * Jalankan ulang pemindaian AI Matching untuk Lost Report
     * Route: POST /ai-match/lost/{id}/run
     */
    public function runForLost(array $vars): void
    {
        AuthMiddleware::requireAuth();
        \App\Middleware\CsrfMiddleware::validate();

        $lostReportId = (int) ($vars['id'] ?? 0);
        $lostReport = $this->lostReportModel->findById($lostReportId);

        if (!$lostReport) {
            $this->flash('danger', 'Laporan kehilangan tidak ditemukan.');
            $this->redirect('/reports/lost');
            return;
        }

        // Authorization Guard: Hanya pemilik laporan ATAU Petugas/Admin
        $user = AuthMiddleware::user();
        $isOwner = ((int) $lostReport['reporter_user_id'] === (int) $user['id']);
        $isOfficer = in_array(AuthMiddleware::role(), ['petugas', 'admin'], true);

        if (!$isOwner && !$isOfficer) {
            $this->flash('danger', '⛔ Anda tidak memiliki izin menjalankan analisis AI untuk laporan ini.');
            $this->redirect('/reports/lost/' . $lostReportId);
            return;
        }

        $isEligibleStatus = in_array($lostReport['status'] ?? '', ['published', 'investigating', 'matched'], true);
        if (!$isEligibleStatus) {
            $msg = 'Laporan kehilangan ini tidak dalam status yang memenuhi syarat untuk pencocokan AI.';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
                || (($_POST['format'] ?? '') === 'json');
            if ($isAjax) {
                $this->json(['success' => false, 'result_status' => 'ineligible_status', 'message' => $msg], 422);
                return;
            }
            $this->flash('warning', $msg);
            $this->redirect('/reports/lost/' . $lostReportId);
            return;
        }

        $matchResult = $this->aiMatchService->runMatchingForLostReport($lostReportId);
        $resultStatus = $matchResult['result_status'] ?? 'no_match';
        $matchesCount = (int) ($matchResult['matches_count'] ?? 0);
        $threshold = (float) ($matchResult['threshold_applied'] ?? 60);

        $fallbackMsg = "AI eksternal belum berhasil merespons. Campus Find tetap mencoba mencari kemungkinan kecocokan menggunakan kategori barang, deskripsi, lokasi, dan tanggal laporan. Hasil ini merupakan rekomendasi awal, bukan kecocokan yang sudah terverifikasi.";

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            || (($_POST['format'] ?? '') === 'json');

        if ($isAjax) {
            $msg = match ($resultStatus) {
                'match_found', 'completed' => "Analisis AI selesai! Ditemukan {$matchesCount} barang temuan yang berpotensi cocok.",
                'completed_fallback'       => $fallbackMsg,
                'no_match'                 => "Analisis selesai. Belum ditemukan barang temuan yang mencapai ambang kecocokan ({$threshold}%).",
                'no_candidates'            => "Belum ada barang temuan yang bisa dibandingkan. Seluruh laporan temuan saat ini memiliki kategori berbeda atau berada di luar rentang tanggal yang sesuai.",
                'provider_timeout'         => "AI eksternal belum berhasil merespons (waktu habis/timeout). Campus Find telah mengevaluasi kategori barang, deskripsi, lokasi, dan tanggal laporan, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.",
                'provider_error', 'ai_failed' => "Layanan AI eksternal sedang mengalami gangguan teknis. Sistem telah mengevaluasi atribut dasar, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.",
                'ineligible_status'        => "Laporan ini tidak dalam status yang memenuhi syarat untuk pencocokan AI.",
                default                    => "Analisis selesai ({$resultStatus}).",
            };

            $this->json([
                'success'           => true,
                'result_status'     => $resultStatus,
                'matches_count'     => $matchesCount,
                'threshold_applied' => $threshold,
                'message'           => $msg,
                'fallback_explanation' => $fallbackMsg,
                'candidates'        => $matchResult['candidates'] ?? [],
                'diagnostic'        => [
                    'call_status'  => $matchResult['diagnostic']['call_status'] ?? '',
                    'duration_ms'  => $matchResult['diagnostic']['duration_ms'] ?? 0,
                    'is_fallback'  => !empty($matchResult['diagnostic']['is_fallback']),
                ],
                'actions'           => [
                    'view_candidates' => [
                        'label' => 'Bandingkan Barang',
                        'url'   => '/ai-match/lost/' . $lostReportId,
                    ],
                    'retry' => [
                        'label'     => 'Coba Lagi',
                        'url'       => '/ai-match/lost/' . $lostReportId . '/run',
                        'available' => in_array($resultStatus, ['completed_fallback', 'provider_timeout', 'provider_error', 'no_match'], true),
                    ],
                    'close' => [
                        'label' => 'Tutup',
                    ],
                ],
            ]);
            return;
        }

        // Pemisahan Pesan UI dan Logika (Prompt 35D, BUG FIX 05, BUG FIX 09, TASK 11)
        if ($resultStatus === 'no_candidates') {
            $this->flash('info', [
                'title'       => 'Pemberitahuan Sistem',
                'message'     => 'Belum ada barang temuan yang bisa dibandingkan. Seluruh laporan temuan saat ini memiliki kategori berbeda atau berada di luar rentang tanggal yang sesuai.',
                'action_url'  => '/reports/lost/' . $lostReportId,
                'action_text' => 'Kembali ke Laporan',
                'cancel_text' => 'Tutup',
            ]);
        } elseif ($resultStatus === 'ineligible_status') {
            $this->flash('warning', 'Laporan ini tidak dalam status yang memenuhi syarat untuk pencocokan AI.');
        } elseif ($resultStatus === 'completed_fallback') {
            $this->flash('info', [
                'title'       => 'Kemungkinan Barang Cocok Ditemukan',
                'message'     => $fallbackMsg . " Ditemukan {$matchesCount} rekomendasi awal.",
                'action_url'  => '/ai-match/lost/' . $lostReportId,
                'action_text' => 'Bandingkan Barang',
                'cancel_text' => 'Tutup',
            ]);
        } elseif ($resultStatus === 'provider_timeout') {
            $this->flash('warning', [
                'title'       => 'Waktu Permintaan AI Habis',
                'message'     => 'AI eksternal belum berhasil merespons (waktu habis/timeout). Campus Find telah mengevaluasi kategori barang, deskripsi, lokasi, dan tanggal laporan, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.',
                'action_url'  => '/ai-match/lost/' . $lostReportId,
                'action_text' => 'Lihat Workspace',
                'cancel_text' => 'Tutup',
            ]);
        } elseif (in_array($resultStatus, ['ai_failed', 'provider_error'], true)) {
            $this->flash('warning', [
                'title'       => 'Kendala Layanan AI Eksternal',
                'message'     => 'Layanan AI eksternal sedang mengalami kendala. Campus Find telah mengevaluasi atribut dasar, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.',
                'action_url'  => '/ai-match/lost/' . $lostReportId,
                'action_text' => 'Lihat Workspace',
                'cancel_text' => 'Tutup',
            ]);
        } elseif ($resultStatus === 'no_match') {
            $this->flash('info', [
                'title'       => 'Hasil Pemindaian AI',
                'message'     => "Analisis selesai. Belum ditemukan barang temuan yang mencapai ambang batas kecocokan (ambang: {$threshold}%).",
                'action_url'  => '/ai-match/lost/' . $lostReportId,
                'action_text' => 'Lihat Hasil',
                'cancel_text' => 'Tutup',
            ]);
        } else {
            $this->flash('success', [
                'title'       => 'Kemungkinan Barang Cocok Ditemukan',
                'message'     => "Analisis AI selesai! Ditemukan {$matchesCount} kandidat Possible Match yang berpotensi cocok.",
                'action_url'  => '/ai-match/lost/' . $lostReportId,
                'action_text' => 'Bandingkan Barang',
                'cancel_text' => 'Tutup',
            ]);
        }

        $this->redirect('/ai-match/lost/' . $lostReportId);
    }

    /**
     * Jalankan ulang pemindaian AI Matching untuk Found Report (Petugas)
     * Route: POST /ai-match/found/{id}/run
     */
    public function runForFound(array $vars): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['petugas', 'admin']);
        \App\Middleware\CsrfMiddleware::validate();

        $foundReportId = (int) ($vars['id'] ?? 0);
        $foundReport = $this->foundReportModel->findById($foundReportId);

        if (!$foundReport) {
            $this->flash('danger', 'Laporan temuan tidak ditemukan.');
            $this->redirect('/reports/found');
            return;
        }

        $isEligibleStatus = in_array($foundReport['status'] ?? '', ['published', 'pending_handover', 'handed_over_to_center', 'verified'], true);
        if (!$isEligibleStatus) {
            $msg = 'Laporan temuan ini tidak dalam status yang memenuhi syarat untuk pencocokan AI.';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
                || (($_POST['format'] ?? '') === 'json');
            if ($isAjax) {
                $this->json(['success' => false, 'result_status' => 'ineligible_status', 'message' => $msg], 422);
                return;
            }
            $this->flash('warning', $msg);
            $this->redirect('/reports/found/' . $foundReportId);
            return;
        }

        $matchResult = $this->aiMatchService->runMatchingForFoundReport($foundReportId);
        $resultStatus = $matchResult['result_status'] ?? 'no_match';
        $matchesCount = (int) ($matchResult['matches_count'] ?? 0);
        $threshold = (float) ($matchResult['threshold_applied'] ?? 60);

        $fallbackMsg = "AI eksternal belum berhasil merespons. Campus Find tetap mencoba mencari kemungkinan kecocokan menggunakan kategori barang, deskripsi, lokasi, dan tanggal laporan. Hasil ini merupakan rekomendasi awal, bukan kecocokan yang sudah terverifikasi.";

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            || (($_POST['format'] ?? '') === 'json');

        if ($isAjax) {
            $msg = match ($resultStatus) {
                'match_found', 'completed' => "Analisis AI selesai! Ditemukan {$matchesCount} laporan kehilangan yang berpotensi cocok.",
                'completed_fallback'       => $fallbackMsg,
                'no_match'                 => "Analisis selesai. Belum ditemukan laporan kehilangan yang mencapai ambang kecocokan ({$threshold}%).",
                'no_candidates'            => "Belum ada laporan kehilangan yang bisa dibandingkan untuk barang temuan ini.",
                'provider_timeout'         => "AI eksternal belum berhasil merespons (waktu habis/timeout). Campus Find telah mengevaluasi kategori barang, deskripsi, lokasi, dan tanggal laporan, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.",
                'provider_error', 'ai_failed' => "Layanan AI eksternal sedang mengalami gangguan teknis. Sistem telah mengevaluasi atribut dasar, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.",
                'ineligible_status'        => "Laporan temuan ini tidak dalam status yang memenuhi syarat untuk pencocokan AI.",
                default                    => "Analisis selesai ({$resultStatus}).",
            };

            $this->json([
                'success'           => true,
                'result_status'     => $resultStatus,
                'matches_count'     => $matchesCount,
                'threshold_applied' => $threshold,
                'message'           => $msg,
                'fallback_explanation' => $fallbackMsg,
                'candidates'        => $matchResult['candidates'] ?? [],
                'diagnostic'        => [
                    'call_status'  => $matchResult['diagnostic']['call_status'] ?? '',
                    'duration_ms'  => $matchResult['diagnostic']['duration_ms'] ?? 0,
                    'is_fallback'  => !empty($matchResult['diagnostic']['is_fallback']),
                ],
                'actions'           => [
                    'view_candidates' => [
                        'label' => 'Bandingkan Barang',
                        'url'   => '/ai-match/found/' . $foundReportId,
                    ],
                    'retry' => [
                        'label'     => 'Coba Lagi',
                        'url'       => '/ai-match/found/' . $foundReportId . '/run',
                        'available' => in_array($resultStatus, ['completed_fallback', 'provider_timeout', 'provider_error', 'no_match'], true),
                    ],
                    'close' => [
                        'label' => 'Tutup',
                    ],
                ],
            ]);
            return;
        }

        // Pemisahan Pesan UI dan Logika (Prompt 35D, BUG FIX 05, BUG FIX 09, TASK 11)
        if ($resultStatus === 'no_candidates') {
            $this->flash('info', [
                'title'       => 'Pemberitahuan Sistem',
                'message'     => 'Belum ada laporan kehilangan yang bisa dibandingkan untuk barang temuan ini.',
                'action_url'  => '/reports/found/' . $foundReportId,
                'action_text' => 'Kembali ke Laporan',
                'cancel_text' => 'Tutup',
            ]);
            $this->redirect('/reports/found/' . $foundReportId);
            return;
        } elseif ($resultStatus === 'ineligible_status') {
            $this->flash('warning', 'Laporan temuan ini tidak dalam status yang memenuhi syarat untuk pencocokan AI.');
            $this->redirect('/reports/found/' . $foundReportId);
            return;
        } elseif ($resultStatus === 'completed_fallback') {
            $this->flash('info', [
                'title'       => 'Kemungkinan Barang Cocok Ditemukan',
                'message'     => $fallbackMsg . " Ditemukan {$matchesCount} laporan kehilangan potensial.",
                'action_url'  => '/ai-match/found/' . $foundReportId,
                'action_text' => 'Bandingkan Barang',
                'cancel_text' => 'Tutup',
            ]);
            if ($matchesCount > 0) {
                $this->redirect('/ai-match/found/' . $foundReportId);
                return;
            }
        } elseif ($resultStatus === 'provider_timeout') {
            $this->flash('warning', [
                'title'       => 'Waktu Permintaan AI Habis',
                'message'     => 'AI eksternal belum berhasil merespons (waktu habis/timeout). Campus Find telah mengevaluasi kategori barang, deskripsi, lokasi, dan tanggal laporan, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.',
                'action_url'  => '/reports/found/' . $foundReportId,
                'action_text' => 'Lihat Laporan',
                'cancel_text' => 'Tutup',
            ]);
        } elseif (in_array($resultStatus, ['ai_failed', 'provider_error'], true)) {
            $this->flash('warning', [
                'title'       => 'Kendala Layanan AI Eksternal',
                'message'     => 'Layanan AI eksternal sedang mengalami kendala. Campus Find telah mengevaluasi atribut dasar, namun belum menemukan rekomendasi awal yang memenuhi ambang batas.',
                'action_url'  => '/reports/found/' . $foundReportId,
                'action_text' => 'Lihat Laporan',
                'cancel_text' => 'Tutup',
            ]);
        } elseif ($resultStatus === 'no_match') {
            $this->flash('info', [
                'title'       => 'Hasil Pemindaian AI',
                'message'     => "Analisis selesai. Belum ditemukan laporan kehilangan yang mencapai ambang kecocokan (ambang: {$threshold}%).",
                'action_url'  => '/reports/found/' . $foundReportId,
                'action_text' => 'Lihat Laporan',
                'cancel_text' => 'Tutup',
            ]);
        } else {
            $this->flash('success', [
                'title'       => 'Kemungkinan Barang Cocok Ditemukan',
                'message'     => "Analisis AI selesai! Ditemukan {$matchesCount} laporan kehilangan yang berpotensi cocok.",
                'action_url'  => '/ai-match/found/' . $foundReportId,
                'action_text' => 'Bandingkan Barang',
                'cancel_text' => 'Tutup',
            ]);
            $this->redirect('/ai-match/found/' . $foundReportId);
            return;
        }

        $this->redirect('/reports/found/' . $foundReportId);
    }

    /**
     * Tampilkan halaman workspace komparasi visual untuk 1 Found Report (Petugas & Admin)
     * Route: GET /ai-match/found/{id}
     */
    public function viewCandidatesForFound(array $vars): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['petugas', 'admin']);

        $foundReportId = (int) ($vars['id'] ?? 0);
        $foundReport = $this->foundReportModel->findById($foundReportId);

        if (!$foundReport) {
            $this->flash('danger', 'Laporan temuan tidak ditemukan.');
            $this->redirect('/reports/found');
            return;
        }

        $threshold = (float) \App\Services\SettingService::get('ai_matching_threshold', 60);
        $candidates = $this->candidateModel->getCandidatesForFoundReport($foundReportId, 20.0);
        $diagnostic = $_SESSION['ai_match_diagnostic'] ?? null;

        if (empty($candidates) && empty($diagnostic)) {
            $matchResult = $this->aiMatchService->runMatchingForFoundReport($foundReportId);
            $candidates = $this->candidateModel->getCandidatesForFoundReport($foundReportId, 20.0);
            $diagnostic = $matchResult['diagnostic'] ?? ($_SESSION['ai_match_diagnostic'] ?? null);
        }

        $nvidiaService = new NvidiaNimService();
        $isNimConfigured = $nvidiaService->isAvailable();
        $providerName = $this->aiMatchService->getActiveProviderName();

        $isEligibleStatus = in_array($foundReport['status'] ?? '', ['published', 'pending_handover', 'handed_over_to_center', 'verified'], true);
        $canRetry = $isEligibleStatus;

        $this->render('ai_match/candidates_found', [
            'foundReport'     => $foundReport,
            'candidates'      => $candidates,
            'threshold'       => $threshold,
            'diagnostic'      => $diagnostic,
            'isNimConfigured' => $isNimConfigured,
            'providerName'    => $providerName,
            'isOfficer'       => true,
            'isOwner'         => false,
            'canRetry'        => $canRetry,
            'pageTitle'       => 'Rekomendasi Kecocokan AI & Komparasi Visual — ' . $foundReport['title'],
        ], 'app_shell');
    }

    /**
     * Update status kandidat pencocokan oleh Petugas
     * Route: POST /ai-match/{id}/status
     */
    public function updateStatus(array $vars): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['petugas', 'admin']);
        \App\Middleware\CsrfMiddleware::validate();

        $candidateId = (int) ($vars['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $redirectUrl = trim($_POST['redirect_url'] ?? '/reports/lost');

        $allowedStatuses = ['pending', 'reviewed', 'confirmed_match', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->flash('danger', 'Status kandidat tidak valid.');
            $this->redirect($redirectUrl);
            return;
        }

        $user = AuthMiddleware::user();
        $updated = $this->candidateModel->updateStatus($candidateId, $status, (int)$user['id']);

        $statusLabels = [
            'pending'         => 'Rekomendasi Baru',
            'reviewed'        => 'Sudah Ditinjau',
            'confirmed_match' => 'Dikonfirmasi Cocok',
            'rejected'        => 'Ditolak (Tidak Cocok)',
        ];
        $label = $statusLabels[$status] ?? $status;

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
            || (($_POST['format'] ?? '') === 'json');

        if ($isAjax) {
            $this->json([
                'success' => (bool)$updated,
                'status'  => $status,
                'label'   => $label,
                'message' => $updated ? "Status kandidat pencocokan diperbarui menjadi: {$label}." : "Gagal memperbarui status kandidat."
            ]);
            return;
        }

        if ($updated) {
            $this->flash('success', "Status kandidat pencocokan diperbarui menjadi: {$label}.");
        } else {
            $this->flash('danger', 'Gagal memperbarui status kandidat.');
        }

        $this->redirect($redirectUrl);
    }
}
