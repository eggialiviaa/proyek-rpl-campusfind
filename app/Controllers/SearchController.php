<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Models\SearchModel;
use App\Models\CategoryModel;
use App\Models\LocationModel;
use App\Services\ItemCatalogService;

/**
 * SearchController — Unified public search across lost & found reports
 *
 * GET /search
 *
 * Accepted query parameters (all optional):
 *   q         string   keyword
 *   type      string   all | lost | found
 *   category  int      category ID
 *   location  int      location ID
 *   date_from string   Y-m-d
 *   date_to   string   Y-m-d
 *   sort      string   newest | oldest | date_asc | date_desc
 *   page      int      page number (default 1)
 */
class SearchController extends BaseController
{
    private ItemCatalogService $catalogService;
    private CategoryModel $categoryModel;
    private LocationModel $locationModel;

    private const VALID_TYPES = ['all', 'lost', 'found'];
    private const VALID_SORTS = ['newest', 'oldest', 'date_asc', 'date_desc'];
    private const PER_PAGE    = 12;

    public function __construct()
    {
        $this->catalogService = new ItemCatalogService();
        $this->categoryModel  = new CategoryModel();
        $this->locationModel  = new LocationModel();
    }

    /**
     * GET /search
     * Public unified search page — no authentication required.
     */
    public function index(array $params = []): void
    {
        // ── 1. Sanitize & validate all query parameters ───────────────────────
        $keyword  = trim(strip_tags((string) ($_GET['q']         ?? '')));
        $type     = $_GET['type']     ?? 'all';
        $catId    = isset($_GET['category'])  ? (int) $_GET['category']  : null;
        $locId    = isset($_GET['location'])  ? (int) $_GET['location']  : null;
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo   = trim((string) ($_GET['date_to']   ?? ''));
        $sortBy   = $_GET['sort'] ?? 'newest';
        $page     = max(1, (int) ($_GET['page'] ?? 1));

        // Whitelist enum values
        if (!in_array($type, self::VALID_TYPES, true)) {
            $type = 'all';
        }
        if (!in_array($sortBy, self::VALID_SORTS, true)) {
            $sortBy = 'newest';
        }
        if ($catId !== null && $catId <= 0) {
            $catId = null;
        }
        if ($locId !== null && $locId <= 0) {
            $locId = null;
        }

        // Validate date formats — silently discard invalid dates
        if ($dateFrom !== '' && !$this->isValidDate($dateFrom)) {
            $dateFrom = '';
        }
        if ($dateTo !== '' && !$this->isValidDate($dateTo)) {
            $dateTo = '';
        }
        // Ensure date range is logical
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Clamp keyword length (LIKE queries have practical limits)
        if (strlen($keyword) > 200) {
            $keyword = substr($keyword, 0, 200);
        }

        // ── 2. Build filter array ─────────────────────────────────────────────
        $filters = array_filter([
            'q'         => $keyword    ?: null,
            'type'      => $type !== 'all' ? $type : null,
            'category'  => $catId,
            'location'  => $locId,
            'date_from' => $dateFrom   ?: null,
            'date_to'   => $dateTo     ?: null,
            'sort'      => $sortBy,
        ], fn($v) => $v !== null);

        // ── 3. Execute search ─────────────────────────────────────────────────
        $offset     = ($page - 1) * self::PER_PAGE;
        $results    = $this->catalogService->searchPublicCatalog($filters, self::PER_PAGE, $offset);
        $totalCount = $this->catalogService->getPublicCatalogCount($filters);
        $totalPages = (int) ceil($totalCount / self::PER_PAGE);

        // ── 4. Load filter options ────────────────────────────────────────────
        $categories = $this->categoryModel->getActiveCategories();
        $locations  = $this->locationModel->getActiveLocations();

        // ── 5. Build active filter summary for UI ─────────────────────────────
        $hasActiveFilters = !empty($keyword) || !empty($catId) || !empty($locId)
                         || !empty($dateFrom) || !empty($dateTo) || $type !== 'all';

        $layout = AuthMiddleware::isLoggedIn() ? 'app_shell' : 'main';

        $this->render('search/index', [
            'pageTitle'        => 'Cari Barang Hilang & Temuan — Campus Find',
            'metaDescription'  => 'Temukan laporan barang hilang dan ditemukan di lingkungan kampus. Filter berdasarkan kategori, lokasi, tanggal, dan status.',
            'results'          => $results,
            'totalCount'       => $totalCount,
            'totalPages'       => $totalPages,
            'currentPage'      => $page,
            'perPage'          => self::PER_PAGE,
            'filters'          => [
                'q'         => $keyword,
                'type'      => $type,
                'category'  => $catId,
                'location'  => $locId,
                'date_from' => $dateFrom,
                'date_to'   => $dateTo,
                'sort'      => $sortBy,
            ],
            'categories'       => $categories,
            'locations'        => $locations,
            'hasActiveFilters' => $hasActiveFilters,
            'user'             => AuthMiddleware::user(),
        ], $layout);
    }

    /**
     * Validate Y-m-d date format and logical validity.
     */
    private function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }
}
