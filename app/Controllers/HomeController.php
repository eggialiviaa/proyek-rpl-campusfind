<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\SearchModel;
use App\Services\ItemCatalogService;
use App\Services\RateLimiterService;
use Core\BaseController;
use Core\Database;
use PDO;

/**
 * HomeController — Halaman utama / landing page
 */
class HomeController extends BaseController
{
    /**
     * GET /
     * Halaman landing page publik Campus Find
     */
    public function index(array $params = []): void
    {
        $isLoggedIn = AuthMiddleware::isLoggedIn();
        $isPublicBrowseEnabled = AuthMiddleware::isPublicBrowseEnabled();
        $canBrowseCatalog = $isLoggedIn || $isPublicBrowseEnabled;

        $recentItemsByType = [
            'all'   => [],
            'lost'  => [],
            'found' => [],
        ];

        // Gerbang keamanan katalog publik:
        // Saat PUBLIC_BROWSE_ENABLED=false, tamu (unauthenticated) TIDAK boleh menerima data katalog nyata.
        if ($canBrowseCatalog) {
            $catalogService = new ItemCatalogService();
            $imageAuthCache = [];

            $lostRows  = $catalogService->searchPublicCatalog(['type' => 'lost', 'sort' => 'newest'], 9, 0);
            $foundRows = $catalogService->searchPublicCatalog(['type' => 'found', 'sort' => 'newest'], 9, 0);
            $allRows   = $catalogService->searchPublicCatalog(['type' => 'all', 'sort' => 'newest'], 12, 0);

            $recentItemsByType = [
                'all'   => $this->prepareSafeCatalogItems($allRows, $isLoggedIn, $imageAuthCache),
                'lost'  => $this->prepareSafeCatalogItems($lostRows, $isLoggedIn, $imageAuthCache),
                'found' => $this->prepareSafeCatalogItems($foundRows, $isLoggedIn, $imageAuthCache),
            ];
        }

        $landingCssPath = BASE_PATH . '/public/css/landing.css';
        $landingJsPath  = BASE_PATH . '/public/js/landing.js';
        $landingCssVer  = file_exists($landingCssPath) ? (string) filemtime($landingCssPath) : (string) time();
        $landingJsVer   = file_exists($landingJsPath) ? (string) filemtime($landingJsPath) : (string) time();
        $csrfToken      = CsrfMiddleware::getToken();
        $lockInfo       = ['is_locked' => false, 'remaining_seconds' => 0];
        if (!$isLoggedIn) {
            $oldId = trim((string) ($_SESSION['_old_input']['identity'] ?? ''));
            if ($oldId !== '') {
                $lockInfo = RateLimiterService::checkLockout($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', $oldId);
            }
        }

        $this->render('home/index', [
            'pageTitle'             => 'Campus Find — Sistem Lost & Found Kampus',
            'metaDescription'       => 'Platform digital untuk melaporkan dan mencari barang hilang di lingkungan kampus.',
            'isLandingPage'         => true,
            'isLoggedInUser'        => $isLoggedIn,
            'isPublicBrowseEnabled' => $isPublicBrowseEnabled,
            'canBrowseCatalog'      => $canBrowseCatalog,
            'exploreFoundUrl'       => $canBrowseCatalog ? '#recently-reported' : '/login',
            'recentItemsByType'     => $recentItemsByType,
            'csrfToken'             => $csrfToken,
            'lockInfo'              => $lockInfo,
            'extraCss'              => '<link rel="stylesheet" href="/css/landing.css?v=' . $landingCssVer . '">',
            'extraJs'               => '<script src="/js/landing.js?v=' . $landingJsVer . '"></script>',
        ]);
    }

    /**
     * Filter dan sanitasi item katalog publik dengan otorisasi gambar berbasis aturan FileController.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, bool>              $imageAuthCache
     * @return array<int, array<string, mixed>>
     */
    private function prepareSafeCatalogItems(array $rows, bool $isLoggedIn, array &$imageAuthCache): array
    {
        $sanitized = [];

        foreach ($rows as $row) {
            $type   = (($row['type'] ?? '') === 'lost') ? 'lost' : 'found';
            $id     = (int) ($row['id'] ?? 0);
            $status = (string) ($row['status'] ?? '');

            $rawImage = trim((string) ($row['primary_image'] ?? ''));
            $hasProtectedImage = ($rawImage !== '');
            $canViewImage = false;

            if ($hasProtectedImage) {
                if (!array_key_exists($rawImage, $imageAuthCache)) {
                    $imageAuthCache[$rawImage] = $this->canCurrentUserViewItemImage($rawImage);
                }
                $canViewImage = $imageAuthCache[$rawImage];
            }

            $sanitized[] = [
                'type'                => $type,
                'id'                  => $id,
                'report_number'       => (string) ($row['report_number'] ?? ''),
                'title'               => (string) ($row['title'] ?? ''),
                'description'         => (string) ($row['description'] ?? ''),
                'date'                => (string) ($row['date'] ?? ''),
                'item_condition'      => $row['item_condition'] ?? null,
                'status'              => $status,
                'status_label'        => SearchModel::getPublicStatusLabel($type, $status),
                'created_at'          => (string) ($row['created_at'] ?? ''),
                'category_id'         => (int) ($row['category_id'] ?? 0),
                'category_name'       => (string) ($row['category_name'] ?? 'Umum'),
                'category_slug'       => (string) ($row['category_slug'] ?? ''),
                'category_icon'       => (string) ($row['category_icon'] ?? ''),
                'location_id'         => (int) ($row['location_id'] ?? 0),
                'location_name'       => (string) ($row['location_name'] ?? ''),
                'has_protected_image' => $hasProtectedImage,
                'can_view_image'      => $canViewImage,
                'image_url'           => ($isLoggedIn && $canViewImage) ? $rawImage : null,
                'detail_url'          => SearchModel::getDetailUrl($type, $id),
            ];
        }

        return $sanitized;
    }

    /**
     * Verifikasi otorisasi akses foto barang mengikuti aturan FileController::serveUpload().
     * Mencegah kebocoran URL /uploads/* maupun permintaan 403 ke FileController.
     */
    private function canCurrentUserViewItemImage(string $primaryImage): bool
    {
        if (!AuthMiddleware::isLoggedIn()) {
            return false;
        }

        $path = parse_url($primaryImage, PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#^/?uploads/(lost_reports|found_reports)/([a-zA-Z0-9._-]+)$#', $path, $matches)) {
            return false;
        }

        $uploadType = $matches[1];
        $cleanFile  = $matches[2];
        $role       = strtolower(trim((string) (AuthMiddleware::role() ?? '')));

        if (in_array($role, ['admin', 'petugas'], true)) {
            return true;
        }

        $user   = AuthMiddleware::user();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $likePattern = "%/{$uploadType}/{$cleanFile}";

        try {
            $pdo = Database::getInstance()->getPdo();

            if ($uploadType === 'lost_reports') {
                $stmt = $pdo->prepare('
                    SELECT reporter_user_id, status
                    FROM lost_reports
                    WHERE primary_image LIKE ?
                    LIMIT 1
                ');
                $stmt->execute([$likePattern]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$report) {
                    return false;
                }

                $isOwner = ((int) $report['reporter_user_id'] === $userId);
                $isPublicInCatalog = in_array((string) $report['status'], ['published', 'in_process', 'resolved'], true);

                return $isOwner || $isPublicInCatalog;
            }

            if ($uploadType === 'found_reports') {
                $stmt = $pdo->prepare('
                    SELECT reporter_user_id, status
                    FROM found_reports
                    WHERE primary_image LIKE ?
                    LIMIT 1
                ');
                $stmt->execute([$likePattern]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($report) {
                    $isOwner = ((int) $report['reporter_user_id'] === $userId);
                    $isCatalogReady = in_array(
                        (string) $report['status'],
                        ['verified', 'stored', 'claimed', 'completed', 'handed_over'],
                        true
                    );

                    return $isOwner || $isCatalogReady;
                }

                // Fallback: cek tabel items seperti pada FileController::serveUpload()
                $stmtItem = $pdo->prepare('
                    SELECT status
                    FROM items
                    WHERE primary_image LIKE ?
                    LIMIT 1
                ');
                $stmtItem->execute([$likePattern]);

                return (bool) $stmtItem->fetch(PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * GET /cara-kerja
     * Halaman panduan cara kerja dan alur sistem untuk tamu/publik
     */
    public function caraKerja(array $params = []): void
    {
        $this->redirect('/#fitur');
    }
}
