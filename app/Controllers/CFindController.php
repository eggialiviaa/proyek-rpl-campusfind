<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\CFindService;

/**
 * CFindController — Endpoint API Chatbot C-Find
 *
 * Mengendalikan komunikasi antara widget frontend dan CFindService
 */
class CFindController extends BaseController
{
    private CFindService $cFindService;

    public function __construct()
    {
        $this->cFindService = new CFindService();
    }

    /**
     * POST /api/cfind/chat
     * Endpoint utama pesan chatbot
     */
    public function chat(array $params = []): void
    {
        // 1. Pastikan Request Method adalah POST
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonResponse([
                'status'    => 'error',
                'reply'     => 'Metode request tidak diizinkan. Gunakan POST.',
                'available' => false,
            ], 405);
            return;
        }

        // Set batas waktu eksekusi PHP agar lebih besar dari CFIND_TIMEOUT_SECONDS (cURL timeout)
        $timeoutSeconds = (int)($_ENV['CFIND_TIMEOUT_SECONDS'] ?? getenv('CFIND_TIMEOUT_SECONDS') ?: 60);
        @set_time_limit(max($timeoutSeconds + 20, 80));

        // Registrasi shutdown function untuk mencatat kemungkinan fatal timeout PHP ke usage log
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                if (str_contains(strtolower($err['message']), 'maximum execution time')) {
                    $currentUser = AuthMiddleware::user();
                    $userId = isset($currentUser['id']) ? (int) $currentUser['id'] : null;
                    $role = $currentUser['role_name'] ?? 'guest';
                    $logDir = defined('BASE_PATH') ? BASE_PATH . '/storage/logs' : dirname(__DIR__, 2) . '/storage/logs';
                    $line = sprintf(
                        "[%s] [CFIND_USAGE] User: %s | Role: %s | IP: %s | Intent: ambigu | Tokens: 0 | Status: error [branch: PHP max_execution_time]\n",
                        date('Y-m-d H:i:s'),
                        $userId !== null ? (string)$userId : 'guest',
                        $role,
                        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    );
                    @file_put_contents($logDir . '/cfind_usage.log', $line, FILE_APPEND | LOCK_EX);
                }
            }
        });


        // 2. Validasi CSRF Token
        // Cek header X-CSRF-Token atau field POST _csrf_token/csrf_token
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $postToken = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? '';
        $sessionToken = CsrfMiddleware::getToken();

        $tokenToVerify = !empty($headerToken) ? $headerToken : $postToken;

        // Ambil JSON input jika content-type application/json
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $rawInput = [];
        if (str_contains($contentType, 'application/json')) {
            $body = file_get_contents('php://input');
            $rawInput = json_decode((string) $body, true) ?: [];
            if (empty($tokenToVerify) && !empty($rawInput['csrf_token'])) {
                $tokenToVerify = (string) $rawInput['csrf_token'];
            }
        }

        if (empty($tokenToVerify) || !hash_equals($sessionToken, (string) $tokenToVerify)) {
            $this->jsonResponse([
                'status'    => 'error',
                'reply'     => 'Sesi keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan refresh halaman.',
                'available' => true,
            ], 403);
            return;
        }

        // 3. Ambil Pesan Input User
        $userMessage = '';
        $displayText = null;
        if (!empty($_POST['message'])) {
            $userMessage = (string) $_POST['message'];
            if (!empty($_POST['display_text'])) {
                $displayText = (string) $_POST['display_text'];
            }
        } elseif (!empty($rawInput['message'])) {
            $userMessage = (string) $rawInput['message'];
            if (!empty($rawInput['display_text'])) {
                $displayText = (string) $rawInput['display_text'];
            }
        }

        // 4. Ambil Info Pengguna Aktif dari Sesi (Bila Login)
        $currentUser = AuthMiddleware::user();

        // 5. Proses Melalui CFindService (5-Layer Guardrail)
        try {
            $result = $this->cFindService->processMessage($userMessage, $currentUser, $displayText);
        } catch (\Throwable $e) {
            error_log('[CFindController] Fatal exception during chat: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonResponse([
                'status'    => 'error',
                'reply'     => 'C-Find sedang sibuk, coba lagi.',
                'available' => true,
            ], 200);
            return;
        }

        // 6. Kembalikan Respons Bersih (reply, status, available, dan metadata runtime transparan tanpa data sensitif)
        $this->jsonResponse([
            'status'    => $result['status'],
            'reply'     => $result['reply'],
            'available' => $result['available'],
            'metadata'  => $result['metadata'] ?? null,
        ]);
    }

    /**
     * POST /api/cfind/reset
     * Reset riwayat percakapan sesi user
     */
    public function reset(array $params = []): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->jsonResponse(['status' => 'error', 'message' => 'Method Not Allowed'], 405);
            return;
        }

        // Validasi CSRF
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $postToken = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? '';
        $sessionToken = CsrfMiddleware::getToken();
        $tokenToVerify = !empty($headerToken) ? $headerToken : $postToken;

        if (empty($tokenToVerify) || !hash_equals($sessionToken, (string) $tokenToVerify)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $this->cFindService->resetHistory();

        $this->jsonResponse([
            'status'  => 'success',
            'message' => 'Riwayat percakapan telah dibersihkan.',
        ]);
    }

    /**
     * GET /api/cfind/status
     * Status ketersediaan C-Find dan metadata awal untuk widget
     */
    public function status(array $params = []): void
    {
        $this->jsonResponse([
            'status'     => 'ok',
            'available'  => $this->cFindService->isAvailable(),
            'bot_name'   => $this->cFindService->getBotName(),
            'csrf_token' => CsrfMiddleware::getToken(),
        ]);
    }

    /**
     * GET /api/cfind/history
     * Mengambil riwayat tampilan percakapan user aktif (maks 50 pesan terstruktur)
     */
    public function history(array $params = []): void
    {
        $messages = $this->cFindService->getDisplayHistory();

        $this->jsonResponse([
            'status'   => 'ok',
            'messages' => $messages,
            'count'    => count($messages),
        ]);
    }

    /**
     * Helper kirim JSON response bersih (bebas output notice/warning liar)
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
