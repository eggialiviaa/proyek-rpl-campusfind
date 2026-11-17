<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;
use Throwable;

/**
 * SettingService — Layanan Manajemen Pengaturan Sistem Dinamis (Prompt 21)
 *
 * Mengelola konfigurasi AI dan Notifikasi dari database dengan enkripsi untuk secret,
 * fallback ke .env jika belum diatur, validasi allowlist, dan logging audit.
 */
class SettingService
{
    /** Allowlist kunci pengaturan yang diizinkan untuk diubah oleh Admin */
    public const ALLOWLIST = [
        // AI Settings
        'cfind_max_output_tokens'      => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_timeout_seconds'        => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'ai_model_cfind'               => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_model_matching'            => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_matching_threshold'        => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'ai_matching_min_save'         => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],

        // Unified AI Provider & Feature Assignment (Task 14 / Task 2B)
        'ai_default_provider'          => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_default_model'             => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_provider_nvidia_nim_key'   => ['type' => 'secret',  'group' => 'ai',           'encrypted' => true],
        'ai_provider_nvidia_nim_url'   => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_provider_google_gemini_key'=> ['type' => 'secret',  'group' => 'ai',           'encrypted' => true],
        'ai_provider_google_gemini_url'=> ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_feature_cfind_provider'    => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_feature_matching_provider' => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],
        'ai_custom_providers'          => ['type' => 'string',  'group' => 'ai',           'encrypted' => false],

        // Rate limits per role (pesan per menit)
        'cfind_rate_limit_guest'    => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_rate_limit_civitas'  => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_rate_limit_petugas'  => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_rate_limit_admin'    => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],

        // Daily token budget per role
        'cfind_daily_budget_guest'  => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_daily_budget_civitas'=> ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_daily_budget_petugas'=> ['type' => 'int',     'group' => 'ai',           'encrypted' => false],
        'cfind_daily_budget_admin'  => ['type' => 'int',     'group' => 'ai',           'encrypted' => false],

        // Enable / Disable chatbot per role
        'cfind_enabled_guest'       => ['type' => 'bool',    'group' => 'ai',           'encrypted' => false],
        'cfind_enabled_civitas'     => ['type' => 'bool',    'group' => 'ai',           'encrypted' => false],
        'cfind_enabled_petugas'     => ['type' => 'bool',    'group' => 'ai',           'encrypted' => false],
        'cfind_enabled_admin'       => ['type' => 'bool',    'group' => 'ai',           'encrypted' => false],

        // Notification Settings (Prompt 20/21)
        'notif_external_channel'        => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_email_status'            => ['type' => 'bool',    'group' => 'notification', 'encrypted' => false],
        'notif_webhook_status'          => ['type' => 'bool',    'group' => 'notification', 'encrypted' => false],
        'notif_webhook_url'             => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_smtp_host'               => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_smtp_port'               => ['type' => 'int',     'group' => 'notification', 'encrypted' => false],
        'notif_smtp_encryption'         => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_smtp_username'           => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_smtp_password'           => ['type' => 'secret',  'group' => 'notification', 'encrypted' => true],
        'notif_smtp_from_address'       => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_smtp_from_name'          => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_daily_limit'             => ['type' => 'int',     'group' => 'notification', 'encrypted' => false],
        'notif_allowlist'               => ['type' => 'string',  'group' => 'notification', 'encrypted' => false],
        'notif_circuit_breaker_tripped' => ['type' => 'bool',    'group' => 'notification', 'encrypted' => false],
        'notif_consecutive_failures'    => ['type' => 'int',     'group' => 'notification', 'encrypted' => false],
        'notif_email_new_lost_report'   => ['type' => 'bool',    'group' => 'notification', 'encrypted' => false],
    ];

    private static ?array $memoryCache = null;

    /**
     * Dapatkan nilai pengaturan berdasarkan key (database -> fallback .env -> fallback default)
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadCache();

        if (isset(self::$memoryCache[$key]) && self::$memoryCache[$key] !== null && self::$memoryCache[$key] !== '') {
            $val = self::$memoryCache[$key];
            return self::castValue($key, $val);
        }

        // Fallback ke .env
        $envVal = self::getEnvFallback($key);
        if ($envVal !== null) {
            return self::castValue($key, $envVal);
        }

        return $default;
    }

    /**
     * Dapatkan semua pengaturan lengkap dengan metadata untuk form admin
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        self::loadCache();

        $result = [];
        foreach (self::ALLOWLIST as $key => $meta) {
            $rawVal = self::get($key);

            if ($meta['type'] === 'secret') {
                $masked = self::maskApiKey((string)$rawVal);
                $result[$key] = [
                    'value'     => $masked,
                    'is_set'    => !empty($rawVal),
                    'type'      => $meta['type'],
                    'group'     => $meta['group'],
                ];
            } else {
                $result[$key] = [
                    'value'     => $rawVal,
                    'type'      => $meta['type'],
                    'group'     => $meta['group'],
                ];
            }
        }

        return $result;
    }

    /**
     * Simpan pengaturan baru (hanya allowlist keys, enkripsi secret, catat audit log)
     *
     * @param array<string, mixed> $inputs
     * @param int|null $userId
     * @return array{success: bool, updated_keys: array<string>, errors: array<string>}
     */
    public static function updateSettings(array $inputs, ?int $userId = null): array
    {
        $pdo = Database::getInstance()->getPdo();
        $updatedKeys = [];
        $errors = [];

        // Validasi dan siapkan query UPSERT
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                `is_encrypted` = VALUES(`is_encrypted`),
                `updated_by` = VALUES(`updated_by`),
                `updated_at` = NOW()
        ");

        $apiKeyChanged = false;

        // Deteksi grup yang disentuh dan apakah ini submit form web (berisi token csrf)
        $touchedGroups = [];
        foreach ($inputs as $inputKey => $v) {
            if (isset(self::ALLOWLIST[$inputKey])) {
                $touchedGroups[self::ALLOWLIST[$inputKey]['group']] = true;
            }
        }
        $isWebFormSubmission = isset($inputs['_csrf_token']) || isset($inputs['csrf_token']);

        foreach (self::ALLOWLIST as $key => $meta) {
            $group = $meta['group'];

            // Khusus boolean:
            // Jika ada di input, gunakan nilainya.
            // Jika tidak ada di input TAPI merupakan submit form web dari grup yang sama, anggap unchecked ('0').
            // Jika pemanggilan spesifik/beda grup, JANGAN timpa nilai yang sudah ada!
            if ($meta['type'] === 'bool') {
                if (array_key_exists($key, $inputs)) {
                    $val = ($inputs[$key] === '1' || $inputs[$key] === 'on' || $inputs[$key] === true || $inputs[$key] === 1) ? '1' : '0';
                } elseif ($isWebFormSubmission && isset($touchedGroups[$group])) {
                    $val = '0';
                } else {
                    continue;
                }

                $stmt->execute([$key, $val, 0, $meta['group'], $userId]);
                $updatedKeys[] = $key;
                self::$memoryCache[$key] = $val;
                continue;
            }

            // Jika field tidak ada di input request, lewati
            if (!array_key_exists($key, $inputs)) {
                continue;
            }

            $rawVal = trim((string)$inputs[$key]);

            // Aturan Khusus Secret (API Key):
            // "Mengosongkan kolom berarti tidak diubah"
            if ($meta['type'] === 'secret') {
                if ($rawVal === '') {
                    // Jangan ubah nilai yang sudah ada di database / .env
                    continue;
                }

                $encryptedVal = self::encrypt($rawVal);
                $stmt->execute([$key, $encryptedVal, 1, $meta['group'], $userId]);
                $updatedKeys[] = $key;
                $apiKeyChanged = true;
                self::$memoryCache[$key] = $rawVal; // cache menyimpan decrypted
                continue;
            }

            // Validasi tipe numerik
            if ($meta['type'] === 'int') {
                $intVal = filter_var($rawVal, FILTER_VALIDATE_INT);
                if ($intVal === false || $intVal < 0) {
                    $errors[] = "Nilai untuk '{$key}' harus berupa bilangan bulat positif.";
                    continue;
                }
                $rawVal = (string)$intVal;
            }

            $stmt->execute([$key, $rawVal, 0, $meta['group'], $userId]);
            $updatedKeys[] = $key;
            self::$memoryCache[$key] = $rawVal;
        }

        // Dukungan Kunci Dinamis: ai_provider_{id}_key & ai_provider_{id}_url untuk provider custom (Task 14 Step B.1)
        foreach ($inputs as $dKey => $dVal) {
            if (isset(self::ALLOWLIST[$dKey])) {
                continue;
            }
            if (preg_match('/^ai_provider_[a-z0-9_]{3,32}_key$/', (string)$dKey)) {
                $rawVal = trim((string)$dVal);
                if ($rawVal === '') {
                    continue;
                }
                $encryptedVal = self::encrypt($rawVal);
                $stmt->execute([$dKey, $encryptedVal, 1, 'ai', $userId]);
                $updatedKeys[] = $dKey;
                $apiKeyChanged = true;
                self::$memoryCache[$dKey] = $rawVal;
            } elseif (preg_match('/^ai_provider_[a-z0-9_]{3,32}_url$/', (string)$dKey)) {
                $rawVal = trim((string)$dVal);
                if ($rawVal === '') {
                    continue;
                }
                $stmt->execute([$dKey, $rawVal, 0, 'ai', $userId]);
                $updatedKeys[] = $dKey;
                self::$memoryCache[$dKey] = $rawVal;
            }
        }

        // Catat jejak audit (siapa, kunci apa, kapan) TANPA nilai rahasia
        if (!empty($updatedKeys)) {
            AuditLogService::log(
                'update_system_settings',
                'system_settings',
                null,
                [
                    'keys_count'     => count($updatedKeys),
                    'keys_updated'   => $updatedKeys,
                    'api_key_updated'=> $apiKeyChanged,
                ],
                $userId
            );
        }

        return [
            'success'      => empty($errors),
            'updated_keys' => $updatedKeys,
            'errors'       => $errors,
        ];
    }

    /**
     * Set a single setting value directly
     */
    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        self::updateSettings([$key => $value], $userId);
    }

    /**
     * Samarkan API Key: tampilkan hanya 4 karakter terakhir (misal "••••••••••••••••3y4Q")
     */
    public static function maskApiKey(?string $key): string
    {
        if (empty($key)) {
            return '';
        }

        $len = strlen($key);
        if ($len <= 4) {
            return str_repeat('•', 16);
        }

        $last4 = substr($key, -4);
        return str_repeat('•', 16) . $last4;
    }

    /**
     * Enkripsi data sensitif menggunakan AES-256-CBC dengan APP_KEY
     */
    public static function encrypt(string $value): string
    {
        $appKey = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($value, 'aes-256-cbc', $appKey, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $cipher);
    }

    /**
     * Dekripsi data sensitif menggunakan AES-256-CBC dengan APP_KEY
     */
    public static function decrypt(string $payload): ?string
    {
        try {
            $raw = base64_decode($payload, true);
            if ($raw === false || strlen($raw) < 17) {
                return null;
            }

            $appKey = self::getEncryptionKey();
            $iv = substr($raw, 0, 16);
            $cipher = substr($raw, 16);

            $decrypted = openssl_decrypt($cipher, 'aes-256-cbc', $appKey, OPENSSL_RAW_DATA, $iv);
            return $decrypted !== false ? $decrypted : null;
        } catch (Throwable $e) {
            error_log('[SettingService::decrypt] Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Kunci simetris 256-bit diturunkan dari APP_KEY
     */
    private static function getEncryptionKey(): string
    {
        $secret = $_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: 'campus_find_default_fallback_key_2026';
        return hash('sha256', $secret, true);
    }

    /**
     * Kosongkan cache memori (berguna saat test atau setelah update)
     */
    public static function clearCache(): void
    {
        self::$memoryCache = null;
    }

    /**
     * Muat semua pengaturan dari database ke memory cache (singleton per request)
     */
    private static function loadCache(): void
    {
        if (self::$memoryCache !== null) {
            return;
        }

        self::$memoryCache = [];

        try {
            $pdo = Database::getInstance()->getPdo();
            $stmt = $pdo->query("SELECT `key`, `value`, `is_encrypted` FROM system_settings");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $k = $r['key'];
                $val = $r['value'];

                if ((int)$r['is_encrypted'] === 1 && !empty($val)) {
                    $val = self::decrypt((string)$val) ?: '';
                }

                self::$memoryCache[$k] = $val;
            }
        } catch (Throwable $e) {
            error_log('[SettingService::loadCache] Error: ' . $e->getMessage());
        }
    }

    /**
     * Konversi tipe data sesuai metadata allowlist
     */
    private static function castValue(string $key, mixed $val): mixed
    {
        $meta = self::ALLOWLIST[$key] ?? ['type' => 'string'];

        return match ($meta['type']) {
            'int'  => (int)$val,
            'bool' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
            default=> (string)$val,
        };
    }

    /**
     * Nilai cadangan (fallback) dari .env bila database kosong
     */
    private static function getEnvFallback(string $key): mixed
    {
        return match ($key) {
            'cfind_max_output_tokens'   => (int)($_ENV['CFIND_MAX_OUTPUT_TOKENS'] ?? getenv('CFIND_MAX_OUTPUT_TOKENS') ?: 500),
            'cfind_timeout_seconds'     => (int)($_ENV['CFIND_TIMEOUT_SECONDS'] ?? getenv('CFIND_TIMEOUT_SECONDS') ?: 60),
            'cfind_rate_limit_guest'    => (int)($_ENV['CFIND_RATE_LIMIT_PER_MIN'] ?? getenv('CFIND_RATE_LIMIT_PER_MIN') ?: 10),
            'cfind_rate_limit_civitas'  => 15,
            'cfind_rate_limit_petugas'  => 30,
            'cfind_rate_limit_admin'    => 60,
            'cfind_daily_budget_guest'  => 20000,
            'cfind_daily_budget_civitas'=> (int)($_ENV['CFIND_DAILY_TOKEN_BUDGET'] ?? getenv('CFIND_DAILY_TOKEN_BUDGET') ?: 50000),
            'cfind_daily_budget_petugas'=> 100000,
            'cfind_daily_budget_admin'  => 200000,
            'cfind_enabled_guest'       => true,
            'cfind_enabled_civitas'     => true,
            'cfind_enabled_petugas'     => true,
            'cfind_enabled_admin'       => true,
            'ai_default_provider'          => 'nvidia_nim',
            'ai_default_model'             => 'nvidia/nemotron-3.5-bolt-e4b',
            'ai_model_cfind'               => '',
            'ai_model_matching'            => '',
            'ai_feature_cfind_provider'    => '',
            'ai_feature_matching_provider' => '',
            'ai_matching_threshold'     => 60,
            'ai_matching_min_save'      => 30,
            'notif_external_channel'        => $_ENV['NOTIF_EXTERNAL_CHANNEL'] ?? getenv('NOTIF_EXTERNAL_CHANNEL') ?: 'email',
            'notif_email_status'            => true,
            'notif_webhook_status'          => false,
            'notif_webhook_url'             => '',
            'notif_smtp_host'               => $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com',
            'notif_smtp_port'               => (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587),
            'notif_smtp_encryption'         => $_ENV['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls',
            'notif_smtp_username'           => $_ENV['SMTP_USERNAME'] ?? getenv('SMTP_USERNAME') ?: '',
            'notif_smtp_password'           => $_ENV['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD') ?: '',
            'notif_smtp_from_address'       => $_ENV['SMTP_FROM_ADDRESS'] ?? getenv('SMTP_FROM_ADDRESS') ?: 'no-reply@campusfind.ac.id',
            'notif_smtp_from_name'          => $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Campus Find L&F',
            'notif_daily_limit'             => (int)($_ENV['NOTIFY_DAILY_LIMIT'] ?? getenv('NOTIFY_DAILY_LIMIT') ?: 100),
            'notif_allowlist'               => $_ENV['NOTIFY_ALLOWLIST'] ?? getenv('NOTIFY_ALLOWLIST') ?: 'admin@campusfind.test',
            'notif_circuit_breaker_tripped' => false,
            'notif_consecutive_failures'    => 0,
            default                         => null,
        };
    }
}
