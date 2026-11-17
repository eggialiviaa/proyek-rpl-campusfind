<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use Throwable;

/**
 * AuditLogService — Pencatatan jejak aktivitas user dan keamanan sistem
 * 
 * Memastikan tidak ada data sensitif (password, secret, API key, bukti rahasia) 
 * yang masuk ke dalam log audit. Mendukung dua format pemanggilan untuk kompatibilitas penuh.
 */
class AuditLogService
{
    /**
     * Kata kunci sensitif yang bila muncul pada nama key (substring match) WAJIB disamarkan
     */
    private const SENSITIVE_KEY_WORDS = [
        'key',
        'token',
        'secret',
        'password',
        'credential',
        'authorization',
        'auth',
        'cookie',
    ];

    /**
     * Pengecualian allowlist: key yang mengandung kata sensitif namun aman dan WAJIB dipertahankan
     */
    private const ALLOWLIST_KEYS = [
        'key_last4',
        'last4',
        'key_changed',
        'key_present',
        'key_deleted',
        'key_updated',
        'api_key_updated',
        'keys_count',
        'keys_updated',
    ];

    /**
     * Pola bentuk string rahasia untuk pertahanan berlapis (defense in depth)
     */
    private const SECRET_VALUE_PATTERN = '/(?:nvapi-[A-Za-z0-9_\.\-]{10,}|AIza[A-Za-z0-9_\.\-]{25,}|AQ\.[A-Za-z0-9_\.\-]{15,}|Bearer\s+[A-Za-z0-9_\.\-]{20,})/i';

    /**
     * Catat aktivitas ke tabel audit_logs
     * 
     * Mendukung format pemanggilan:
     * Format A: log(action, entityType, entityId, payload, userId)
     * Format B: log(action, userId, entityType, entityId, payload)
     */
    public static function log(
        string $action,
        mixed $param2 = null,
        mixed $param3 = null,
        mixed $param4 = null,
        mixed $param5 = null
    ): void {
        try {
            $userId = null;
            $entityType = null;
            $entityId = null;
            $payload = null;

            // Deteksi calling convention
            if (is_int($param2) || (is_string($param2) && ctype_digit($param2))) {
                // Format B: log(action, userId, entityType, entityId, payload)
                $userId = (int) $param2;
                $entityType = is_string($param3) ? $param3 : null;
                $entityId = is_int($param4) ? $param4 : null;
                $payload = is_array($param5) ? $param5 : null;
            } else {
                // Format A: log(action, entityType, entityId, payload, userId)
                $entityType = is_string($param2) ? $param2 : null;
                $entityId = is_int($param3) ? $param3 : null;
                $payload = is_array($param4) ? $param4 : null;
                $userId = is_int($param5) ? $param5 : null;
            }

            // Fallback userId dari session jika belum terisi
            if ($userId === null && isset($_SESSION['user']['id'])) {
                $userId = (int) $_SESSION['user']['id'];
            } elseif ($userId === null && isset($_SESSION['user_id'])) {
                $userId = (int) $_SESSION['user_id'];
            }

            $pdo = Database::getInstance()->getPdo();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 255);

            // Sanitasi payload dari key sensitif
            $sanitizedPayload = self::sanitizePayload($payload);
            $jsonPayload = !empty($sanitizedPayload) ? json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE) : null;

            $stmt = $pdo->prepare('
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, payload, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $userId,
                $action,
                $entityType,
                $entityId,
                $ipAddress,
                $userAgent,
                $jsonPayload,
            ]);
        } catch (Throwable $e) {
            // Jangan menghentikan alur utama jika audit logging error
            error_log('[AuditLogService] Error logging activity: ' . $e->getMessage());
        }
    }

    /**
     * Sanitasi array payload secara rekursif menghapus key sensitif dan value berbentuk secret
     */
    public static function sanitizePayload(?array $payload): ?array
    {
        if (empty($payload)) {
            return null;
        }

        $clean = [];
        foreach ($payload as $key => $value) {
            $lowerKey = mb_strtolower((string)$key, 'UTF-8');

            // 1. Cek allowlist pengecualian terlebih dahulu
            $isAllowlisted = in_array($lowerKey, self::ALLOWLIST_KEYS, true);

            // 2. Jika bukan allowlist, periksa apakah nama key mengandung kata sensitif
            $isSensitiveKey = false;
            if (!$isAllowlisted) {
                foreach (self::SENSITIVE_KEY_WORDS as $word) {
                    if (str_contains($lowerKey, $word)) {
                        $isSensitiveKey = true;
                        break;
                    }
                }
            }

            if ($isSensitiveKey) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitizePayload($value);
            } elseif (is_string($value) && preg_match(self::SECRET_VALUE_PATTERN, $value)) {
                // Defense-in-depth: samarkan string value yang cocok dengan format API key/token rahasia
                $clean[$key] = '[REDACTED_SECRET_VALUE]';
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
