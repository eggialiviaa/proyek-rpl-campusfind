<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\NotificationModel;
use App\Services\AuditLogService;

/**
 * NotificationController — Pengendali sistem notifikasi in-app proaktif
 *
 * Semua endpoint di-scope ketat ke user yang sedang login.
 * Tidak ada data privat / bukti klaim yang dikirim di payload.
 */
class NotificationController extends BaseController
{
    private NotificationModel $model;

    public function __construct()
    {
        AuthMiddleware::requireAuth();
        $this->model = new NotificationModel();
    }

    /**
     * GET /notifications
     * Halaman riwayat notifikasi lengkap (read & unread) dengan paginasi
     */
    public function index(array $params = []): void
    {
        $currentUser = AuthMiddleware::user();
        $userId      = (int) $currentUser['id'];

        $page       = max(1, (int) ($_GET['page'] ?? 1));
        $limit      = 20;
        $offset     = ($page - 1) * $limit;
        $filter     = (isset($_GET['filter']) && $_GET['filter'] === 'unread') ? 'unread' : 'all';
        $unreadOnly = ($filter === 'unread');

        $notifications = $this->model->getUserNotifications($userId, $unreadOnly, $limit, $offset);
        $totalCount    = $this->model->countUserNotifications($userId, $unreadOnly);
        $totalPages    = (int) ceil($totalCount / $limit);
        $unreadCount   = $this->model->getUnreadCount($userId);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['notif'] = (int) $unreadCount;

        $this->render('notifications/index', [
            'pageTitle'     => 'Riwayat Notifikasi — Campus Find',
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
            'totalCount'    => $totalCount,
            'currentPage'   => $page,
            'totalPages'    => $totalPages,
            'filter'        => $filter,
            'csrfToken'     => CsrfMiddleware::getToken(),
            'user'          => $currentUser,
        ], 'app_shell');
    }

    /**
     * GET /notifications/recent
     * JSON endpoint: ambil notifikasi terbaru untuk dropdown navbar (polling)
     */
    public function recent(array $params = []): void
    {
        $currentUser = AuthMiddleware::user();
        $userId      = (int) $currentUser['id'];

        $limit       = max(1, min(20, (int) ($_GET['limit'] ?? 5)));
        $recent      = $this->model->getRecentNotifications($userId, $limit);
        $unreadCount = (int) $this->model->getUnreadCount($userId);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['notif'] = $unreadCount;

        $this->jsonResponse([
            'success'       => true,
            'unread_count'  => $unreadCount,
            'notifications' => $recent,
        ]);
    }

    /**
     * GET /notifications/unread-count
     * JSON endpoint ringan untuk polling badge di navbar
     */
    public function unreadCount(array $params = []): void
    {
        $currentUser = AuthMiddleware::user();
        $userId      = (int) $currentUser['id'];
        $unreadCount = (int) $this->model->getUnreadCount($userId);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['notif'] = $unreadCount;

        $this->jsonResponse([
            'success'      => true,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /notifications/{id}/read
     * Tandai satu notifikasi sebagai terbaca
     */
    public function markAsRead(array $params = []): void
    {
        CsrfMiddleware::validate();

        $currentUser    = AuthMiddleware::user();
        $userId         = (int) $currentUser['id'];
        $notificationId = (int) ($params['id'] ?? 0);

        if ($notificationId <= 0) {
            $this->jsonResponse(['success' => false, 'message' => 'ID notifikasi tidak valid.'], 400);
            return;
        }

        // Verifikasi kepemilikan: user tidak boleh mark-read milik orang lain
        if (!$this->model->findUserNotification($notificationId, $userId)) {
            $statusCode = $this->model->exists($notificationId) ? 403 : 404;
            $message    = ($statusCode === 403) ? 'Akses ditolak.' : 'Notifikasi tidak ditemukan.';
            $this->jsonResponse(['success' => false, 'message' => $message], $statusCode);
            return;
        }

        $marked = $this->model->markAsRead($notificationId, $userId);

        AuditLogService::log('NOTIFICATION_READ', $userId, 'notification', $notificationId, []);

        $unreadCount = (int) $this->model->getUnreadCount($userId);
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['notif'] = $unreadCount;

        $this->jsonResponse([
            'success'      => true,
            'marked'       => $marked,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * POST /notifications/read-all
     * Tandai seluruh notifikasi milik user sebagai terbaca
     */
    public function markAllAsRead(array $params = []): void
    {
        CsrfMiddleware::validate();

        $currentUser = AuthMiddleware::user();
        $userId      = (int) $currentUser['id'];
        $markedCount = $this->model->markAllAsRead($userId);

        AuditLogService::log('NOTIFICATION_READ_ALL', $userId, 'notification', 0, [
            'marked_count' => $markedCount,
        ]);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['notif'] = 0;

        // Jika AJAX → JSON; jika redirect biasa → flash
        if ($this->isAjaxRequest()) {
            $this->jsonResponse([
                'success'      => true,
                'marked_count' => $markedCount,
                'unread_count' => 0,
            ]);
            return;
        }

        $this->setFlash('success', "Semua {$markedCount} notifikasi ditandai sebagai telah dibaca.");
        $this->redirectBack('/notifications');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function isAjaxRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        return str_contains($accept, 'application/json') || $xrw === 'xmlhttprequest';
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
