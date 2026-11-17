<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use Core\Database;
use PDO;

/**
 * FileController — Penyedia file unggahan terproteksi otorisasi
 *
 * Mengamankan seluruh file upload (foto barang & bukti klaim) agar tidak bisa
 * diakses langsung oleh publik/tamu via static file serving.
 */
class FileController extends BaseController
{
    /**
     * GET /uploads/{type}/{file}
     * Layani file unggahan dari storage non-publik dengan pemeriksaan hak akses ketat
     */
    public function serveUpload(array $params = []): void
    {
        $type = $params['type'] ?? '';
        $file = $params['file'] ?? '';

        // 1. Validasi tipe folder allowlist
        $allowedTypes = ['lost_reports', 'found_reports', 'claims'];
        if (!in_array($type, $allowedTypes, true)) {
            $this->abort(404);
        }

        // 2. Sanitasi nama file (anti path traversal & command injection)
        $cleanFile = basename($file);
        if ($cleanFile !== $file || !preg_match('/^[a-zA-Z0-9._-]+$/', $cleanFile)) {
            $this->abort(400);
        }

        // 3. Verifikasi Autentikasi Pengguna
        // Tamu / belum login TIDAK BOLEH membuka file unggahan apa pun
        if (!AuthMiddleware::isLoggedIn()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            echo 'Forbidden: Login diperlukan untuk mengakses gambar atau berkas ini.';
            exit;
        }

        $user   = AuthMiddleware::user();
        $role   = AuthMiddleware::role();
        $userId = (int) ($user['id'] ?? 0);
        $pdo    = Database::getInstance()->getPdo();

        // 4. Verifikasi Otorisasi Berdasarkan Tipe File
        // Admin dan Petugas berhak mengakses seluruh file
        $isStaff = in_array($role, ['admin', 'petugas'], true);

        if (!$isStaff) {
            $likePattern = "%/{$type}/{$cleanFile}";

            if ($type === 'claims') {
                // Bukti klaim privat: HANYA Pemilik Klaim
                $isOwner = false;
                try {
                    $stmt = $pdo->prepare("
                        SELECT 1 FROM claims c
                        LEFT JOIN claim_evidence ce ON ce.claim_id = c.id
                        WHERE c.claimant_user_id = ? 
                          AND (c.proof_file LIKE ? OR ce.file_path LIKE ?)
                        LIMIT 1
                    ");
                    $stmt->execute([$userId, $likePattern, $likePattern]);
                    if ($stmt->fetchColumn()) {
                        $isOwner = true;
                    }
                } catch (\Throwable $e) {
                    $isOwner = false;
                }

                if (!$isOwner) {
                    $this->abort(403);
                }
            } elseif ($type === 'lost_reports') {
                // Laporan kehilangan:
                // - Pemilik laporan selalu boleh
                // - Pengguna login lain hanya boleh jika laporan berstatus publik di katalog
                $authorized = false;
                try {
                    $stmt = $pdo->prepare("
                        SELECT reporter_user_id, status 
                        FROM lost_reports 
                        WHERE primary_image LIKE ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$likePattern]);
                    $report = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($report) {
                        $isOwner = ((int) $report['reporter_user_id'] === $userId);
                        $isPublicInCatalog = in_array($report['status'], ['published', 'in_process', 'resolved'], true);

                        if ($isOwner || $isPublicInCatalog) {
                            $authorized = true;
                        }
                    }
                } catch (\Throwable $e) {
                    $authorized = false;
                }

                if (!$authorized) {
                    $this->abort(403);
                }
            } elseif ($type === 'found_reports') {
                // Laporan barang temuan:
                // - Penemu / pelapor selalu boleh
                // - Pengguna login lain hanya boleh jika barang temuan sudah diverifikasi di katalog
                $authorized = false;
                try {
                    $stmt = $pdo->prepare("
                        SELECT fr.reporter_user_id, fr.status 
                        FROM found_reports fr
                        WHERE fr.primary_image LIKE ? 
                        LIMIT 1
                    ");
                    $stmt->execute([$likePattern]);
                    $report = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($report) {
                        $isOwner = ((int) $report['reporter_user_id'] === $userId);
                        $isCatalogReady = in_array($report['status'], ['verified', 'stored', 'claimed', 'completed', 'handed_over'], true);

                        if ($isOwner || $isCatalogReady) {
                            $authorized = true;
                        }
                    } else {
                        // Cek kemungkinan file terikat pada tabel items
                        $stmtItem = $pdo->prepare("
                            SELECT status FROM items 
                            WHERE primary_image LIKE ? 
                            LIMIT 1
                        ");
                        $stmtItem->execute([$likePattern]);
                        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
                        if ($item) {
                            // Barang di items adalah barang temuan yang diproses petugas
                            $authorized = true;
                        }
                    }
                } catch (\Throwable $e) {
                    $authorized = false;
                }

                if (!$authorized) {
                    $this->abort(403);
                }
            }
        }

        // 5. Cek Keberadaan Fisik File di Storage Non-Publik
        $filePath = BASE_PATH . '/storage/uploads/' . $type . '/' . $cleanFile;
        if (!file_exists($filePath) || !is_file($filePath)) {
            // Fallback backward compatibility jika file lama masih tersisa di public
            $fallbackPath = BASE_PATH . '/public/uploads/' . $type . '/' . $cleanFile;
            if (file_exists($fallbackPath) && is_file($fallbackPath)) {
                $filePath = $fallbackPath;
            } else {
                $this->abort(404);
            }
        }

        // 6. Sajikan File dengan Header MIME yang Benar dan Cache Private
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
        finfo_close($finfo);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');

        readfile($filePath);
        exit;
    }
}
