<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Models\ChatModel;
use App\Models\ClaimModel;
use App\Services\ChatService;

/**
 * ChatController — Mengelola obrolan internal antara Civitas dan Petugas
 */
class ChatController extends BaseController
{
    private ChatModel  $chatModel;
    private ClaimModel $claimModel;

    public function __construct()
    {
        $this->chatModel  = new ChatModel();
        $this->claimModel = new ClaimModel();
    }

    /**
     * GET /chats
     * Tampilkan daftar percakapan aktif milik user
     */
    public function index(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();

        // Prompt 34D: Admin tidak ikut dalam perpesanan internal
        if (($user['role_name'] ?? '') === 'admin') {
            $this->setFlash('info', 'Admin tidak berpartisipasi dalam perpesanan internal civitas dan petugas.');
            $this->redirect('/admin');
            return;
        }

        $rooms = $this->chatModel->getUserRooms((int) $user['id'], $user['role_name']);
        $totalUnread = (int) $this->chatModel->getTotalUnreadCount((int) $user['id'], $user['role_name']);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['chats'] = $totalUnread;

        $this->render('chats/index', [
            'pageTitle'   => 'Pesan & Diskusi — Campus Find',
            'user'        => $user,
            'rooms'       => $rooms,
            'totalUnread' => $totalUnread,
        ], 'app_shell');
    }

    /**
     * GET /chats/claim/{id}
     * Inisialisasi atau buka ruang chat untuk klaim tertentu
     */
    public function initClaimChat(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = $user['role_name'];

        $claimId = (int) ($params['id'] ?? $_GET['claim_id'] ?? 0);
        if ($claimId <= 0) {
            $this->setFlash('error', 'Klaim tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        // Cek kepemilikan klaim jika yang mengakses adalah civitas
        $claim = $this->claimModel->findDetail($claimId);
        if (!$claim) {
            $this->setFlash('error', 'Data klaim tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        // Prompt 34D: Admin tidak ikut dalam chat internal, dan civitas hanya boleh membuka klaim miliknya
        if ($userRole === 'admin' || ($userRole === 'civitas' && (int) $claim['claimant_user_id'] !== $userId)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $room = $this->chatModel->getOrCreateRoomForClaim($claimId, $userId, $userRole);
        if (!$room) {
            $this->setFlash('error', 'Gagal memulai ruang percakapan.');
            $this->redirect("/claims/{$claimId}");
            return;
        }

        $this->redirect("/chats/{$room['id']}");
    }

    /**
     * GET /chats/report/{type}/{id}
     * Inisialisasi atau buka ruang chat untuk laporan (lost/found)
     */
    public function initReportChat(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = $user['role_name'];

        $type = ($params['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
        $reportId = (int) ($params['id'] ?? 0);

        if ($reportId <= 0) {
            $this->setFlash('error', 'Laporan tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        // Verifikasi kepemilikan laporan untuk mencegah IDOR
        $tbl = $type === 'found' ? 'found_reports' : 'lost_reports';
        $pdo = \Core\Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("SELECT reporter_user_id FROM {$tbl} WHERE id = ? LIMIT 1");
        $stmt->execute([$reportId]);
        $reportOwnerId = $stmt->fetchColumn();

        if ($reportOwnerId === false) {
            $this->setFlash('error', 'Laporan tidak ditemukan.');
            $this->redirect('/dashboard');
            return;
        }

        // Prompt 34D: Admin tidak ikut dalam chat internal, dan civitas hanya boleh membuka laporannya sendiri
        if ($userRole === 'admin' || ($userRole === 'civitas' && (int) $reportOwnerId !== $userId)) {
            http_response_code(403);
            require BASE_PATH . '/app/Views/errors/403.php';
            exit;
        }

        $room = $this->chatModel->getOrCreateRoomForReport($type, $reportId, $userId, $userRole);
        if (!$room) {
            $this->setFlash('error', 'Gagal membuka obrolan laporan.');
            $this->redirect('/dashboard');
            return;
        }

        $this->redirect("/chats/{$room['id']}");
    }


    /**
     * GET /chats/{id}
     * Tampilkan halaman ruang obrolan spesifik
     */
    public function room(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = $user['role_name'];

        $roomId = (int) ($params['id'] ?? 0);
        if ($roomId <= 0) {
            $this->setFlash('error', 'Ruang percakapan tidak valid.');
            $this->redirect('/chats');
            return;
        }

        // STRICT RBAC & PRIVACY ISOLATION:
        // Civitas hanya boleh membuka obrolan yang melibatkan klaim miliknya
        if (!$this->chatModel->isUserAuthorized($roomId, $userId, $userRole)) {
            http_response_code(403);
            $errorView = BASE_PATH . '/app/Views/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<h1>403 — Akses Ditolak (Unauthorized Chat Room)</h1>';
            }
            exit;
        }

        $room = $this->chatModel->getRoomDetail($roomId);
        if (!$room) {
            $this->setFlash('error', 'Ruang percakapan tidak ditemukan.');
            $this->redirect('/chats');
            return;
        }

        // Tandai pesan sebagai terbaca
        $this->chatModel->markAsRead($roomId, $userId);

        // Ambil riwayat pesan
        $messages = $this->chatModel->getMessages($roomId);

        $this->render('chats/room', [
            'pageTitle'       => "Chat: {$room['context_title']} — Campus Find",
            'metaDescription' => 'Percakapan resmi Lost & Found Center kampus',
            'user'            => $user,
            'room'            => $room,
            'messages'        => $messages,
            'csrfToken'       => CsrfMiddleware::getToken(),
        ], 'app_shell');
    }

    /**
     * POST /chats/{id}/messages
     * Kirim pesan baru ke ruang chat (mendukung Form submit & AJAX JSON)
     */
    public function sendMessage(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = $user['role_name'];

        $roomId = (int) ($params['id'] ?? 0);

        // Validasi Otorisasi
        if (!$this->chatModel->isUserAuthorized($roomId, $userId, $userRole)) {
            $this->respondJson(['error' => 'Akses ditolak.'], 403);
            return;
        }

        // Deteksi format input (JSON atau Form POST)
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
               || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

        // Rate Limiting (Maks 15 pesan / menit per user session)
        if (!isset($_SESSION['chat_rate_limit']) || !is_array($_SESSION['chat_rate_limit'])) {
            $_SESSION['chat_rate_limit'] = [];
        }
        $nowTs = time();
        $_SESSION['chat_rate_limit'] = array_filter(
            $_SESSION['chat_rate_limit'],
            fn($ts) => ($nowTs - $ts) < 60
        );
        if (count($_SESSION['chat_rate_limit']) >= 15) {
            $limitMsg = 'Terlalu banyak pesan terkirim. Harap tunggu sebentar sebelum mengirim pesan kembali.';
            if ($isAjax) {
                $this->respondJson(['error' => $limitMsg], 429);
            } else {
                $this->setFlash('error', $limitMsg);
                $this->redirect("/chats/{$roomId}");
            }
            return;
        }

        $clientMsgId = '';
        if ($isAjax) {
            $raw = file_get_contents('php://input');
            $jsonData = json_decode($raw, true) ?: [];
            $message = trim((string) ($jsonData['message'] ?? $_POST['message'] ?? ''));
            $csrf = (string) ($jsonData['_csrf_token'] ?? $jsonData['csrf_token'] ?? $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? '');
            $clientMsgId = trim((string) ($jsonData['client_msg_id'] ?? ''));
        } else {
            CsrfMiddleware::validate();
            $message = trim((string) ($_POST['message'] ?? ''));
        }

        if (empty($message)) {
            if ($isAjax) {
                $this->respondJson(['error' => 'Pesan tidak boleh kosong.'], 422);
            } else {
                $this->setFlash('error', 'Pesan tidak boleh kosong.');
                $this->redirect("/chats/{$roomId}");
            }
            return;
        }

        if (mb_strlen($message, 'UTF-8') > 2000) {
            if ($isAjax) {
                $this->respondJson(['error' => 'Pesan terlalu panjang (maksimal 2000 karakter).'], 422);
            } else {
                $this->setFlash('error', 'Pesan terlalu panjang.');
                $this->redirect("/chats/{$roomId}");
            }
            return;
        }

        // Catat timestamp untuk rate limiting setelah validasi berhasil
        $_SESSION['chat_rate_limit'][] = $nowTs;

        // Simpan pesan
        $storedMessage = $this->chatModel->storeMessage($roomId, $userId, $message);

        if (!$storedMessage) {
            if ($isAjax) {
                $this->respondJson(['error' => 'Gagal menyimpan pesan.'], 500);
            } else {
                $this->setFlash('error', 'Gagal mengirim pesan.');
                $this->redirect("/chats/{$roomId}");
            }
            return;
        }

        if ($isAjax) {
            $this->respondJson([
                'success' => true,
                'message' => $storedMessage,
                'client_msg_id' => $clientMsgId,
            ]);
        } else {
            $this->redirect("/chats/{$roomId}");
        }
    }

    /**
     * GET /chats/{id}/poll
     * Endpoint polling JSON untuk mengambil pesan-pesan baru secara berkala
     */
    public function poll(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();
        $userId = (int) $user['id'];
        $userRole = $user['role_name'];

        $roomId = (int) ($params['id'] ?? 0);

        if (!$this->chatModel->isUserAuthorized($roomId, $userId, $userRole)) {
            $this->respondJson(['error' => 'Unauthorized'], 403);
            return;
        }

        $afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : 0;
        $messages = $this->chatModel->getMessages($roomId, $afterId, 50);

        // Jika ada pesan baru dari lawan bicara, tandai terbaca
        if (!empty($messages)) {
            $this->chatModel->markAsRead($roomId, $userId);
        }

        $this->respondJson([
            'room_id'  => $roomId,
            'count'    => count($messages),
            'messages' => $messages,
        ]);
    }

    /**
     * GET /chats/unread-count
     * Endpoint polling unread badge untuk navbar
     */
    public function unreadCount(array $params = []): void
    {
        AuthMiddleware::require();
        $user = AuthMiddleware::user();

        $unreadCount = (int) $this->chatModel->getTotalUnreadCount((int) $user['id'], $user['role_name']);

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        $_SESSION['_unread_counts']['chats'] = $unreadCount;

        $this->respondJson([
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Helper response JSON
     */
    private function respondJson(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
