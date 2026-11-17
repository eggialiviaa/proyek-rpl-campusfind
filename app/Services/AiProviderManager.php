<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database;
use PDO;
use Throwable;

/**
 * AiProviderManager — Manajer Terpadu Koneksi Provider & Penugasan Fitur AI (Task 14)
 *
 * Mengonsolidasikan pengelolaan kredensial API key seluruh provider AI di satu tempat
 * dan menyediakan resolusi dinamis penugasan provider & model untuk setiap fitur (C-Find & Matching).
 */
class AiProviderManager
{
    /**
     * Registri resmi adapter provider yang didukung.
     * Hanya provider yang terdaftar di sini yang dapat ditambahkan dan dipilih.
     */
    public const REGISTERED_PROVIDERS = [
        'nvidia_nim' => [
            'id'            => 'nvidia_nim',
            'name'          => 'NVIDIA NIM',
            'badge'         => 'Hosted NIM Catalog',
            'icon'          => 'bi-cpu-fill',
            'description'   => 'Hosted API Catalog OpenAI-compatible inference dari NVIDIA',
            'default_url'   => 'https://integrate.api.nvidia.com/v1',
            'adapter_class' => NvidiaNimService::class,
        ],
        'google_gemini' => [
            'id'            => 'google_gemini',
            'name'          => 'Google Gemini',
            'badge'         => 'REST OpenAI-compatible',
            'icon'          => 'bi-stars',
            'description'   => 'Google Gemini Generative Language API via OpenAI-compatible REST endpoint',
            'default_url'   => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'adapter_class' => GoogleGeminiService::class,
        ],
    ];

    /**
     * Normalisasi ID model di satu tempat (buang prefix "models/" jika ada)
     */
    public static function normalizeModelId(string $modelId): string
    {
        $clean = trim($modelId);
        if (str_starts_with($clean, 'models/')) {
            return substr($clean, 7);
        }
        return $clean;
    }

    /**
     * Pola substring nama model yang bukan model chat interaktif standar (Task 2B.2)
     */
    public const NON_CHAT_MODEL_PATTERNS = [
        'guard', 'safeguard', 'whisper', 'tts', 'embed', 'rerank',
        'moderation', 'image', 'audio', 'speech', 'transcribe'
    ];

    /**
     * Cache in-memory / session untuk daftar model provider
     * @var array<string, array>
     */
    private static array $modelsMemoryCache = [];

    /**
     * Ambil model dari cache lokal (memori / session) tanpa blocking remote HTTP
     *
     * @param string $providerId
     * @return array|null
     */
    public static function getCachedModels(string $providerId): ?array
    {
        if (isset(self::$modelsMemoryCache[$providerId])) {
            return self::$modelsMemoryCache[$providerId];
        }
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['ai_models_cache'][$providerId])) {
            $cached = $_SESSION['ai_models_cache'][$providerId];
            if (is_array($cached) && !empty($cached)) {
                self::$modelsMemoryCache[$providerId] = $cached;
                return $cached;
            }
        }
        return null;
    }

    /**
     * Simpan model ke cache lokal (memori dan session)
     *
     * @param string $providerId
     * @param array $models
     */
    public static function setCachedModels(string $providerId, array $models): void
    {
        self::$modelsMemoryCache[$providerId] = $models;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ai_models_cache'][$providerId] = $models;
        }
    }

    /**
     * Periksa apakah suatu model tergolong model non-chat berdasarkan pola nama model
     */
    public static function isNonChatModel(string $modelId): bool
    {
        $lower = strtolower($modelId);
        foreach (self::NON_CHAT_MODEL_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Filter daftar model untuk penugasan chat/matching (Section B).
     * Menyembunyikan model non-chat kecuali jika model tersebut sedang tersimpan di database,
     * yang akan dipertahankan dengan label peringatan jelas.
     */
    public static function filterModelsForChatAssignment(array $models, ?string $currentlySavedModel = null): array
    {
        $filtered = [];
        $normSaved = !empty($currentlySavedModel) ? self::normalizeModelId($currentlySavedModel) : null;
        $savedIncluded = false;

        foreach ($models as $m) {
            $id = is_array($m) ? ($m['id'] ?? '') : (string)$m;
            $normId = self::normalizeModelId($id);
            if (empty($normId)) continue;

            $isNonChat = self::isNonChatModel($normId);
            $isCurrentlySaved = ($normSaved !== null && $normSaved === $normId);

            if ($isNonChat) {
                if ($isCurrentlySaved) {
                    $label = is_array($m) ? ($m['label'] ?? $normId) : $normId;
                    if (!str_contains($label, 'Bukan Model Chat Standar')) {
                        $label .= ' (Peringatan: Bukan Model Chat Standar)';
                    }
                    $filtered[] = [
                        'id'                  => $normId,
                        'label'               => $label,
                        'is_non_chat'         => true,
                        'compatible_chat'     => false,
                        'compatible_matching' => false,
                    ];
                    $savedIncluded = true;
                }
                continue;
            }

            if ($isCurrentlySaved) {
                $savedIncluded = true;
            }

            if (is_array($m)) {
                $filtered[] = $m;
            } else {
                $filtered[] = ['id' => $normId, 'label' => $normId];
            }
        }

        if ($normSaved !== null && !$savedIncluded && !empty($normSaved)) {
            $isNonChat = self::isNonChatModel($normSaved);
            $label = $normSaved . ($isNonChat ? ' (Peringatan: Bukan Model Chat Standar)' : ' (Model tersimpan, tidak ada di katalog)');
            array_unshift($filtered, [
                'id'                  => $normSaved,
                'label'               => $label,
                'is_non_chat'         => $isNonChat,
                'compatible_chat'     => !$isNonChat,
                'compatible_matching' => !$isNonChat,
            ]);
        }

        return $filtered;
    }

    /**
     * Dapatkan snapshot status penugasan AI terpadu untuk Section B
     */
    public static function getAiAssignmentState(): array
    {
        $default = self::getDefaultAssignment();
        $cfind = self::getFeatureAssignment('cfind');
        $matching = self::getFeatureAssignment('matching');

        return [
            'default' => [
                'provider_id'   => $default['provider_id'],
                'provider_name' => $default['provider_name'],
                'model'         => $default['model'],
                'key_masked'    => $default['key_masked'],
                'key_last4'     => $default['key_last4'],
                'key_source'    => $default['key_source'],
                'is_ready'      => $default['is_ready'],
            ],
            'features' => [
                'cfind' => [
                    'feature'       => 'cfind',
                    'feature_label' => $cfind['feature_label'],
                    'provider_id'   => $cfind['provider_id'],
                    'provider_name' => $cfind['provider_name'],
                    'model'         => $cfind['model'],
                    'is_override'   => $cfind['is_override'],
                    'key_masked'    => $cfind['key_masked'],
                    'key_last4'     => $cfind['key_last4'],
                    'is_ready'      => $cfind['is_ready'],
                ],
                'matching' => [
                    'feature'       => 'matching',
                    'feature_label' => $matching['feature_label'],
                    'provider_id'   => $matching['provider_id'],
                    'provider_name' => $matching['provider_name'],
                    'model'         => $matching['model'],
                    'is_override'   => $matching['is_override'],
                    'key_masked'    => $matching['key_masked'],
                    'key_last4'     => $matching['key_last4'],
                    'is_ready'      => $matching['is_ready'],
                ],
            ],
        ];
    }

    /**
     * Parser error yang menangani format objek {"error":{...}} dan array [{"error":{...}}]
     * Menghasilkan pesan ramah ke UI ("HTTP {status}: {message provider}") tanpa membocorkan key.
     */
    public static function parseProviderError(string $resBody, int $httpCode): string
    {
        $decoded = json_decode($resBody, true);
        $msg = '';

        if (is_array($decoded)) {
            // Format A: Array [{"error": {...}}]
            if (isset($decoded[0]['error']['message'])) {
                $msg = (string)$decoded[0]['error']['message'];
            }
            // Format B: Objek {"error": {...}}
            elseif (isset($decoded['error']['message'])) {
                $msg = (string)$decoded['error']['message'];
            }
            // Format C: Objek dengan message langsung
            elseif (isset($decoded['message'])) {
                $msg = (string)$decoded['message'];
            }
        }

        if ($msg === '') {
            $msg = 'Respons error tidak terstruktur dari provider.';
        }

        // Sanitasi key dan token dari pesan error UI
        $cleanMsg = preg_replace('/(AIzaSy[a-zA-Z0-9_\-]{20,}|nvapi-[a-zA-Z0-9_\-]{20,}|Bearer\s+[a-zA-Z0-9_\.\-]+|key=[a-zA-Z0-9_\-]+)/i', '[KEY_REDACTED]', $msg);

        // Catat detail lengkap ke error log server tanpa membocorkan key
        $logBody = preg_replace('/(AIzaSy[a-zA-Z0-9_\-]{20,}|nvapi-[a-zA-Z0-9_\-]{20,}|Bearer\s+[a-zA-Z0-9_\.\-]+)/i', '[KEY_REDACTED]', $resBody);
        error_log("[AiProviderManager] Provider error (HTTP {$httpCode}): {$logBody}");

        return "HTTP {$httpCode}: {$cleanMsg}";
    }

    /**
     * Dapatkan daftar registri provider resmi dan provider custom yang terdaftar (Task 14 Step B)
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getRegisteredProviders(): array
    {
        $providers = self::REGISTERED_PROVIDERS;

        $customJson = (string)SettingService::get('ai_custom_providers', '[]');
        $customList = json_decode($customJson, true);
        if (is_array($customList)) {
            foreach ($customList as $cp) {
                if (!is_array($cp) || empty($cp['id'])) {
                    continue;
                }
                $cId = (string)$cp['id'];
                if (isset(self::REGISTERED_PROVIDERS[$cId])) {
                    continue; // Provider bawaan selalu diutamakan
                }

                $cleanName = (string)($cp['name'] ?? $cId);
                $cleanUrl = (string)($cp['base_url'] ?? '');

                $providers[$cId] = [
                    'id'            => $cId,
                    'name'          => $cleanName,
                    'badge'         => 'OpenAI-Compatible',
                    'icon'          => 'bi-hdd-network',
                    'description'   => "Custom OpenAI-compatible API endpoint: {$cleanUrl}",
                    'base_url'      => $cleanUrl,
                    'default_url'   => $cleanUrl,
                    'adapter_class' => OpenAICompatibleService::class,
                    'is_custom'     => true,
                    'created_at'    => $cp['created_at'] ?? date('Y-m-d H:i:s'),
                ];
            }
        }

        return $providers;
    }

    /**
     * Dapatkan daftar custom providers dari system_settings
     *
     * @return array<int, array{id: string, name: string, base_url: string, created_at?: string}>
     */
    public static function getCustomProviders(): array
    {
        $json = (string)SettingService::get('ai_custom_providers', '[]');
        $list = json_decode($json, true);
        return is_array($list) ? $list : [];
    }

    /**
     * Cek apakah provider ID merupakan custom provider
     *
     * @param string $providerId
     * @return bool
     */
    public static function isCustomProvider(string $providerId): bool
    {
        $customs = self::getCustomProviders();
        foreach ($customs as $cp) {
            if (($cp['id'] ?? '') === $providerId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normalisasi URL endpoint untuk perbandingan yang konsisten dan pencegahan data-loss
     * - Trim whitespace
     * - Lowercase scheme & host
     * - Hapus port default (:443 untuk https, :80 untuk http)
     * - Hapus trailing slash pada path
     *
     * @param string $url
     * @return string
     */
    public static function normalizeEndpointUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return rtrim($url, '/');
        }
        $scheme = strtolower((string)$parsed['scheme']);
        $host = strtolower((string)$parsed['host']);
        $port = isset($parsed['port']) ? (int)$parsed['port'] : null;
        $portPart = '';
        if ($port !== null) {
            if (!($scheme === 'https' && $port === 443) && !($scheme === 'http' && $port === 80)) {
                $portPart = ':' . $port;
            }
        }
        $path = $parsed['path'] ?? '';
        $path = rtrim($path, '/');
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        return "{$scheme}://{$host}{$portPart}{$path}{$query}";
    }

    /**
     * SSRF Protection Helper Tunggal (Task 14 Step B.5)
     * Memvalidasi bahwa URL endpoint custom:
     * - Hanya protokol HTTPS dan port 443
     * - Tanpa userinfo (username/password)
     * - Bukan IP literal dan bukan localhost
     * - Menyelesaikan DNS (A dan AAAA) dan MENOLAK jika ADA IP loopback, privat, link-local, CGNAT, ULA, dll.
     * - Mengembalikan IP tervalidasi untuk disematkan pada CURLOPT_RESOLVE.
     *
     * @param string $url
     * @param (callable(string): array<string>)|null $dnsResolver Resolver kustom untuk pengujian
     * @return array{valid: bool, error?: string, host?: string, port?: int, pinned_ip?: string, all_ips?: array<string>, resolve_opt?: array<string>}
     */
    public static function validateCustomProviderUrl(string $url, ?callable $dnsResolver = null): array
    {
        $cleanUrl = trim($url);
        if ($cleanUrl === '') {
            return ['valid' => false, 'error' => 'URL endpoint tidak boleh kosong.'];
        }

        // 1. Parsing URL
        $parts = parse_url($cleanUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return ['valid' => false, 'error' => 'Format URL endpoint tidak valid.'];
        }

        // 2. HTTPS only
        if (strtolower($parts['scheme']) !== 'https') {
            return ['valid' => false, 'error' => 'Hanya protokol HTTPS yang diizinkan (port 443).'];
        }

        // 3. Port 443 only
        if (isset($parts['port']) && (int)$parts['port'] !== 443) {
            return ['valid' => false, 'error' => 'Hanya port 443 yang diizinkan untuk endpoint AI provider.'];
        }

        // 4. No userinfo
        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['valid' => false, 'error' => 'Userinfo (username/password dalam URL) dilarang.'];
        }

        // 5. Reject IP-literal hosts & localhost
        $rawHost = $parts['host'];
        $unbracketed = trim($rawHost, '[]');
        if (filter_var($unbracketed, FILTER_VALIDATE_IP)) {
            return ['valid' => false, 'error' => 'Host berupa IP literal dilarang (wajib menggunakan domain FQDN).'];
        }

        $hostLower = strtolower($rawHost);
        if ($hostLower === 'localhost' || str_ends_with($hostLower, '.localhost') || str_ends_with($hostLower, '.local')) {
            return ['valid' => false, 'error' => 'Akses ke localhost dilarang.'];
        }

        // 6. Resolusi DNS (A dan AAAA)
        $resolvedIps = [];
        if ($dnsResolver !== null) {
            $resolvedIps = $dnsResolver($rawHost);
        } else {
            $records = @dns_get_record($rawHost, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $r) {
                    if (!empty($r['ip']))   $resolvedIps[] = (string)$r['ip'];
                    if (!empty($r['ipv6'])) $resolvedIps[] = (string)$r['ipv6'];
                }
            }
            if (empty($resolvedIps)) {
                $v4List = @gethostbynamel($rawHost);
                if (is_array($v4List)) {
                    foreach ($v4List as $v4) {
                        $resolvedIps[] = (string)$v4;
                    }
                }
            }
        }

        if (empty($resolvedIps) || !is_array($resolvedIps)) {
            return ['valid' => false, 'error' => "Gagal meresolusi alamat IP untuk domain '{$rawHost}'."];
        }

        // 7. Periksa SETIAP alamat IP (Jika ADA yang terlarang/privat, tolak)
        foreach ($resolvedIps as $ip) {
            $check = self::isRestrictedIp((string)$ip);
            if ($check['restricted']) {
                return [
                    'valid' => false,
                    'error' => "SSRF Protection: Alamat IP '{$ip}' ditolak ({$check['reason']}).",
                ];
            }
        }

        $pinnedIp = (string)$resolvedIps[0];
        $port = 443;

        return [
            'valid'       => true,
            'host'        => $rawHost,
            'port'        => $port,
            'pinned_ip'   => $pinnedIp,
            'all_ips'     => $resolvedIps,
            'resolve_opt' => ["{$rawHost}:{$port}:{$pinnedIp}"],
        ];
    }

    /**
     * Cek apakah sebuah alamat IP berada di rentang yang dibatasi/privat (SSRF filter)
     *
     * @param string $ip
     * @return array{restricted: bool, reason: string}
     */
    public static function isRestrictedIp(string $ip): array
    {
        $ip = trim($ip);

        // Deteksi IPv4-mapped IPv6 mis. ::ffff:127.0.0.1
        if (str_starts_with(strtolower($ip), '::ffff:') || preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $ip, $m)) {
            $mappedIpv4 = $m[1] ?? substr($ip, 7);
            if (filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return self::isRestrictedIpv4($mappedIpv4, "IPv4-mapped IPv6 ({$ip})");
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isRestrictedIpv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isRestrictedIpv6($ip);
        }

        return ['restricted' => true, 'reason' => 'Bukan alamat IP yang valid'];
    }

    private static function isRestrictedIpv4(string $ip, string $prefix = 'IPv4'): array
    {
        $blockedCidrs = [
            '0.0.0.0/8'          => 'Unspecified address',
            '10.0.0.0/8'         => 'Private network (RFC 1918)',
            '100.64.0.0/10'      => 'Shared Address Space / CGNAT (RFC 6598)',
            '127.0.0.0/8'        => 'Loopback address',
            '169.254.0.0/16'     => 'Link-local / Cloud Metadata (RFC 3927)',
            '172.16.0.0/12'      => 'Private network (RFC 1918)',
            '192.0.0.0/24'       => 'IETF Protocol Assignments',
            '192.0.2.0/24'       => 'TEST-NET-1 (RFC 5737)',
            '192.88.99.0/24'     => '6to4 Relay Anycast (RFC 7526)',
            '192.168.0.0/16'     => 'Private network (RFC 1918)',
            '198.18.0.0/15'      => 'Network Benchmark Tests (RFC 2544)',
            '198.51.100.0/24'    => 'TEST-NET-2 (RFC 5737)',
            '203.0.113.0/24'     => 'TEST-NET-3 (RFC 5737)',
            '224.0.0.0/4'        => 'Multicast (RFC 5771)',
            '240.0.0.0/4'        => 'Reserved for future use (RFC 1112)',
            '255.255.255.255/32' => 'Limited broadcast',
        ];

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return ['restricted' => true, 'reason' => "Invalid {$prefix} address"];
        }

        foreach ($blockedCidrs as $cidr => $reason) {
            list($net, $mask) = explode('/', $cidr);
            $netLong = ip2long($net);
            $maskLong = ~((1 << (32 - (int)$mask)) - 1);
            if (($ipLong & $maskLong) === ($netLong & $maskLong)) {
                return ['restricted' => true, 'reason' => "{$prefix} {$reason}"];
            }
        }

        return ['restricted' => false, 'reason' => ''];
    }

    private static function isRestrictedIpv6(string $ip): array
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return ['restricted' => true, 'reason' => 'Invalid IPv6 address'];
        }

        if ($packed === inet_pton('::')) {
            return ['restricted' => true, 'reason' => 'IPv6 Unspecified address (::)'];
        }
        if ($packed === inet_pton('::1')) {
            return ['restricted' => true, 'reason' => 'IPv6 Loopback address (::1)'];
        }

        $b0 = ord($packed[0]);
        $b1 = ord($packed[1]);

        // fe80::/10 (link-local)
        if ($b0 === 0xfe && ($b1 & 0xc0) === 0x80) {
            return ['restricted' => true, 'reason' => 'IPv6 Link-local address (fe80::/10)'];
        }

        // fc00::/7 (unique local address - ULA)
        if (($b0 & 0xfe) === 0xfc) {
            return ['restricted' => true, 'reason' => 'IPv6 Unique-local address (fc00::/7)'];
        }

        // ff00::/8 (multicast)
        if ($b0 === 0xff) {
            return ['restricted' => true, 'reason' => 'IPv6 Multicast address (ff00::/8)'];
        }

        // 2001:db8::/32 (documentation)
        if ($b0 === 0x20 && $b1 === 0x01 && ord($packed[2]) === 0x0d && ord($packed[3]) === 0xb8) {
            return ['restricted' => true, 'reason' => 'IPv6 Documentation address (2001:db8::/32)'];
        }

        // 64:ff9b::/96 (IPv4-IPv6 translation)
        if ($b0 === 0x00 && $b1 === 0x64 && ord($packed[2]) === 0xff && ord($packed[3]) === 0x9b) {
            return ['restricted' => true, 'reason' => 'IPv6 Translation prefix (64:ff9b::/96)'];
        }

        // 100::/64 (discard-only prefix)
        if ($b0 === 0x01 && $b1 === 0x00) {
            return ['restricted' => true, 'reason' => 'IPv6 Discard-only prefix (100::/64)'];
        }

        return ['restricted' => false, 'reason' => ''];
    }

    /**
     * Tambah custom provider baru (Task 14 Step B.1 & B.4)
     *
     * @param string $name
     * @param string $baseUrl
     * @param string|null $apiKey
     * @param int|null $userId
     * @return array{success: bool, message: string, provider?: array, test_result?: array}
     */
    public static function addCustomProvider(
        string $name,
        string $baseUrl,
        ?string $apiKey = null,
        ?int $userId = null
    ): array {
        // 1. Sanitasi dan Validasi Nama (XSS protection)
        $cleanName = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($name));
        $cleanName = strip_tags($cleanName);
        if (mb_strlen($cleanName) < 2 || mb_strlen($cleanName) > 40) {
            return ['success' => false, 'message' => 'Nama provider harus antara 2 hingga 40 karakter.'];
        }

        // 2. Validasi SSRF Base URL
        $ssrf = self::validateCustomProviderUrl($baseUrl);
        if (!$ssrf['valid']) {
            return ['success' => false, 'message' => 'SSRF Error: ' . ($ssrf['error'] ?? 'URL endpoint tidak diizinkan.')];
        }
        $cleanUrl = rtrim(trim($baseUrl), '/');

        // 3. Batas Maksimal 10 Custom Providers
        $customJson = (string)SettingService::get('ai_custom_providers', '[]');
        $customList = json_decode($customJson, true);
        if (!is_array($customList)) {
            $customList = [];
        }
        if (count($customList) >= 10) {
            return ['success' => false, 'message' => 'Batas maksimum 10 custom provider telah tercapai. Hapus provider yang tidak digunakan terlebih dahulu.'];
        }

        // 4. Generate Provider ID Server-Side (slug nama + short random suffix)
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $cleanName), '_'));
        if (empty($slug)) $slug = 'custom';
        $slug = substr($slug, 0, 22);
        $suffix = substr(bin2hex(random_bytes(3)), 0, 5);
        $providerId = $slug . '_' . $suffix;

        if (isset(self::REGISTERED_PROVIDERS[$providerId])) {
            $providerId .= '_sub';
        }

        // 5. Simpan metadata ke ai_custom_providers
        $customList[] = [
            'id'         => $providerId,
            'name'       => $cleanName,
            'base_url'   => $cleanUrl,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
            VALUES ('ai_custom_providers', ?, 0, 'ai', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
        ");
        $stmt->execute([json_encode($customList, JSON_UNESCAPED_SLASHES), $userId]);

        // Simpan Base URL individual untuk provider
        $stmtUrl = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
            VALUES (?, ?, 0, 'ai', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
        ");
        $stmtUrl->execute(["ai_provider_{$providerId}_url", $cleanUrl, $userId]);

        // 6. Simpan API Key jika diisi
        $keyUpdated = false;
        $cleanKey = $apiKey !== null ? trim($apiKey) : '';
        if ($cleanKey !== '') {
            $encKey = SettingService::encrypt($cleanKey);
            $keySetting = "ai_provider_{$providerId}_key";
            $stmtKey = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
                VALUES (?, ?, 1, 'ai', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `is_encrypted` = 1, `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
            ");
            $stmtKey->execute([$keySetting, $encKey, $userId]);
            $keyUpdated = true;
        }

        SettingService::clearCache();

        // 7. Audit Log
        $last4 = ($keyUpdated && strlen($cleanKey) >= 4) ? substr($cleanKey, -4) : '';
        AuditLogService::log(
            'add_custom_ai_provider',
            'system_settings',
            null,
            [
                'provider'    => $providerId,
                'name'        => $cleanName,
                'host'        => $ssrf['host'] ?? parse_url($cleanUrl, PHP_URL_HOST),
                'key_updated' => $keyUpdated,
                'last4'       => $last4,
            ],
            $userId
        );

        // 8. Otomatis Uji Koneksi (GET /models)
        $testResult = self::testProviderConnection($providerId, null, $cleanKey ?: null);

        $conn = self::getProviderConnection($providerId);

        return [
            'success'     => true,
            'message'     => "Provider '{$cleanName}' berhasil ditambahkan.",
            'provider'    => $conn,
            'provider_id' => $providerId,
            'test_result' => $testResult,
        ];
    }

    /**
     * Hapus custom provider (Task 14 Step B.4)
     *
     * @param string $providerId
     * @param int|null $userId
     * @return array{success: bool, message: string, provider?: string}
     */
    public static function deleteCustomProvider(string $providerId, ?int $userId = null): array
    {
        if (isset(self::REGISTERED_PROVIDERS[$providerId])) {
            return [
                'success' => false,
                'message' => 'Provider bawaan sistem tidak dapat dihapus (hanya API Key yang dapat dihapus).',
            ];
        }

        // Delete Protection: Blokir jika sedang aktif digunakan oleh Default atau Fitur
        $defaultProv = (string)SettingService::get('ai_default_provider', '');
        $cfindProv   = (string)SettingService::get('ai_provider_cfind', '');
        $matchProv   = (string)SettingService::get('ai_provider_matching', '');
        $usedWhere   = [];
        if ($defaultProv === $providerId) $usedWhere[] = 'Default';
        if ($cfindProv === $providerId)   $usedWhere[] = 'Chatbot C-Find';
        if ($matchProv === $providerId)   $usedWhere[] = 'Pencocokan AI';

        if (!empty($usedWhere)) {
            return [
                'success'  => false,
                'message'  => "Tidak dapat menghapus provider: Sedang digunakan oleh " . implode(' / ', $usedWhere) . ". Ubah provider terlebih dahulu.",
                'provider' => $providerId,
            ];
        }

        $customJson = (string)SettingService::get('ai_custom_providers', '[]');
        $customList = json_decode($customJson, true);
        if (!is_array($customList)) {
            $customList = [];
        }

        $found = false;
        $deletedName = $providerId;
        $newList = [];
        foreach ($customList as $cp) {
            if (($cp['id'] ?? '') === $providerId) {
                $found = true;
                $deletedName = $cp['name'] ?? $providerId;
                continue;
            }
            $newList[] = $cp;
        }

        if (!$found) {
            return ['success' => false, 'message' => "Custom provider '{$providerId}' tidak ditemukan."];
        }

        $pdo = Database::getInstance()->getPdo();

        // Update ai_custom_providers
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
            VALUES ('ai_custom_providers', ?, 0, 'ai', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
        ");
        $stmt->execute([json_encode($newList, JSON_UNESCAPED_SLASHES), $userId]);

        // Hapus API Key dan URL setting
        $stmtDel = $pdo->prepare("DELETE FROM system_settings WHERE `key` IN (?, ?)");
        $stmtDel->execute(["ai_provider_{$providerId}_key", "ai_provider_{$providerId}_url"]);

        SettingService::clearCache();

        AuditLogService::log(
            'delete_custom_ai_provider',
            'system_settings',
            null,
            ['provider' => $providerId, 'name' => $deletedName],
            $userId
        );

        return [
            'success'          => true,
            'message'          => "Provider '{$deletedName}' dan kredensialnya berhasil dihapus.",
            'provider'         => $providerId,
            'assignment_state' => self::getAiAssignmentState(),
        ];
    }

    /**
     * Update metadata nama atau base_url untuk custom provider
     */
    public static function updateCustomProviderMetadata(string $providerId, ?string $name = null, ?string $baseUrl = null, ?int $userId = null): void
    {
        $customJson = (string)SettingService::get('ai_custom_providers', '[]');
        $customList = json_decode($customJson, true);
        if (!is_array($customList)) return;

        $updated = false;
        foreach ($customList as &$cp) {
            if (($cp['id'] ?? '') === $providerId) {
                if ($name !== null) {
                    $cleanName = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($name));
                    $cleanName = strip_tags($cleanName);
                    if (mb_strlen($cleanName) >= 2 && mb_strlen($cleanName) <= 40) {
                        $cp['name'] = $cleanName;
                        $updated = true;
                    }
                }
                if ($baseUrl !== null) {
                    $cp['base_url'] = rtrim(trim($baseUrl), '/');
                    $updated = true;
                }
                break;
            }
        }
        if ($updated) {
            $pdo = Database::getInstance()->getPdo();
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
                VALUES ('ai_custom_providers', ?, 0, 'ai', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
            ");
            $stmt->execute([json_encode($customList, JSON_UNESCAPED_SLASHES), $userId]);
            SettingService::clearCache();
        }
    }

    /**
     * Dapatkan informasi koneksi untuk sebuah provider (DATABASE adalah satu-satunya sumber)
     *
     * @param string $providerId
     * @return array<string, mixed>
     */
    public static function getProviderConnection(string $providerId): array
    {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            throw new \InvalidArgumentException("Provider '{$providerId}' tidak terdaftar.");
        }

        $meta = $allProviders[$providerId];

        // 1. Kredensial HANYA bersumber dari basis data (system_settings)
        $dbKeySetting = "ai_provider_{$providerId}_key";
        $dbRawKey = (string)SettingService::get($dbKeySetting, '');

        $isFromDb = !empty($dbRawKey);
        $effectiveKey = $dbRawKey;
        $source = $isFromDb ? 'database' : 'belum disetel';

        // 2. Base URL HANYA bersumber dari basis data atau default provider resmi
        $dbUrlSetting = "ai_provider_{$providerId}_url";
        $dbUrl = (string)SettingService::get($dbUrlSetting, '');
        $effectiveUrl = !empty($dbUrl) ? $dbUrl : $meta['default_url'];

        // 3. Masked key (hanya 4 karakter terakhir)
        $masked = 'Belum disetel';
        $last4 = '';
        if (!empty($effectiveKey)) {
            $len = mb_strlen($effectiveKey);
            $last4 = $len >= 4 ? mb_substr($effectiveKey, -4) : $effectiveKey;
            $masked = '••••••••' . $last4;
        }

        return [
            'id'            => $providerId,
            'name'          => $meta['name'],
            'badge'         => $meta['badge'],
            'icon'          => $meta['icon'],
            'description'   => $meta['description'],
            'base_url'      => $effectiveUrl,
            'default_url'   => $meta['default_url'],
            'is_configured' => !empty($effectiveKey),
            'is_active'     => !empty($effectiveKey),
            'is_custom'     => !empty($meta['is_custom']),
            'api_key_masked'=> $masked,
            'last4'         => $last4,
            'key_last4'     => $last4,
            'source'        => $source,
            'key_source'    => $source,
            'is_from_db'    => $isFromDb,
            'is_db'         => $isFromDb,
            'status'        => !empty($effectiveKey) ? 'configured' : 'unconfigured',
        ];
    }

    /**
     * Dapatkan seluruh koneksi provider yang terdaftar
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAllProviderConnections(): array
    {
        $connections = [];
        foreach (array_keys(self::getRegisteredProviders()) as $id) {
            $connections[$id] = self::getProviderConnection($id);
        }
        return $connections;
    }

    /**
     * Simpan kredensial & URL provider ke basis data dengan enkripsi aman
     *
     * @param string $providerId
     * @param string|null $apiKey Jika kosong / whitespace / null, pertahankan kunci aktif
     * @param string|null $baseUrl
     * @param int|null $userId
     * @return array{success: bool, message: string, provider: string, key_masked?: string, key_last4?: string, source?: string}
     */
    public static function saveProviderConnection(
        string $providerId,
        ?string $apiKey,
        ?string $baseUrl = null,
        ?int $userId = null
    ): array {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return [
                'success' => false,
                'message' => "Provider '{$providerId}' tidak dikenali dalam sistem.",
                'provider'=> $providerId,
            ];
        }

        $pdo = Database::getInstance()->getPdo();
        $meta = $allProviders[$providerId];
        $keyUpdated = false;
        $urlChanged = false;

        $existingConn = self::getProviderConnection($providerId);

        // 1. Simpan URL jika disediakan
        if ($baseUrl !== null && trim($baseUrl) !== '') {
            $cleanUrl = self::normalizeEndpointUrl($baseUrl);

            // Cek SSRF jika provider custom
            if (!empty($meta['is_custom'])) {
                $ssrf = self::validateCustomProviderUrl($cleanUrl);
                if (!$ssrf['valid']) {
                    return [
                        'success' => false,
                        'message' => 'SSRF Error: ' . ($ssrf['error'] ?? 'URL endpoint tidak diizinkan.'),
                        'provider'=> $providerId,
                    ];
                }
            }

            $normalizedExisting = self::normalizeEndpointUrl((string)($existingConn['base_url'] ?? ''));
            if ($normalizedExisting !== '' && $normalizedExisting !== $cleanUrl) {
                $urlChanged = true;
            }

            $urlKey = "ai_provider_{$providerId}_url";
            $stmtUrl = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
                VALUES (?, ?, 0, 'ai', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
            ");
            $stmtUrl->execute([$urlKey, $cleanUrl, $userId]);

            if (!empty($meta['is_custom'])) {
                self::updateCustomProviderMetadata($providerId, null, $cleanUrl, $userId);
            }
        }

        // 2. Jika base_url berubah pada provider yang memiliki stored key dan tidak ada key baru yang dimasukkan:
        // Hapus key lama dan wajibkan admin memasukkan key kembali!
        $cleanKey = $apiKey !== null ? trim($apiKey) : '';
        if ($urlChanged && $existingConn['is_configured'] && $cleanKey === '') {
            $stmtDel = $pdo->prepare("DELETE FROM system_settings WHERE `key` = ?");
            $stmtDel->execute(["ai_provider_{$providerId}_key"]);
            SettingService::clearCache();

            AuditLogService::log(
                'clear_ai_provider_key_url_changed',
                'system_settings',
                null,
                ['provider' => $providerId, 'reason' => 'base_url_changed'],
                $userId
            );

            return [
                'success'       => true,
                'message'       => "Base URL untuk {$meta['name']} diubah. Demi keamanan, API Key lama telah dihapus. Silakan masukkan API Key baru untuk endpoint ini.",
                'provider'      => $providerId,
                'key_masked'    => 'Belum disetel',
                'key_last4'     => '',
                'source'        => 'Belum Disetel',
                'is_configured' => false,
                'is_db'         => false,
                'status'        => 'unconfigured',
                'key_cleared'   => true,
            ];
        }

        // 3. Simpan API Key jika diisi
        if ($cleanKey !== '') {
            $encKey = SettingService::encrypt($cleanKey);
            $keySetting = "ai_provider_{$providerId}_key";

            $stmtKey = $pdo->prepare("
                INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
                VALUES (?, ?, 1, 'ai', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `is_encrypted` = 1, `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
            ");
            $stmtKey->execute([$keySetting, $encKey, $userId]);

            $keyUpdated = true;
        }

        // 4. Invalidate Memory Cache
        SettingService::clearCache();

        // 5. Verifikasi pembacaan ulang internal (Readback check)
        $conn = self::getProviderConnection($providerId);
        if ($keyUpdated) {
            $effectiveStored = self::resolveEffectiveApiKey($providerId);
            if ($effectiveStored !== $cleanKey) {
                return [
                    'success' => false,
                    'message' => 'Gagal memverifikasi pembacaan kembali kunci tersimpan di database.',
                    'provider'=> $providerId,
                ];
            }
        }

        // 6. Catat jejak audit TANPA membocorkan secret
        AuditLogService::log(
            'update_ai_provider_connection',
            'system_settings',
            null,
            [
                'provider'    => $providerId,
                'key_updated' => $keyUpdated,
                'url_updated' => $baseUrl !== null,
                'last4'       => $conn['last4'],
            ],
            $userId
        );

        $latestMeta = self::getRegisteredProviders()[$providerId] ?? $meta;

        $msg = $keyUpdated 
            ? "API Key untuk provider {$latestMeta['name']} berhasil disimpan dan dienkripsi secara aman."
            : "Konfigurasi provider {$latestMeta['name']} berhasil diperbarui (API key aktif dipertahankan).";

        return [
            'success'          => true,
            'message'          => $msg,
            'provider'         => $providerId,
            'name'             => $latestMeta['name'],
            'key_masked'       => $conn['api_key_masked'],
            'key_last4'        => $conn['last4'],
            'source'           => $conn['source'],
            'is_configured'    => $conn['is_configured'],
            'is_db'            => $conn['is_db'],
            'status'           => $conn['status'],
            'assignment_state' => self::getAiAssignmentState(),
        ];
    }

    /**
     * Hapus API Key tersimpan di database untuk provider tertentu
     * Blokir jika provider masih aktif digunakan oleh penugasan fitur
     *
     * @param string $providerId
     * @param int|null $userId
     * @return array{success: bool, message: string, provider: string, key_masked?: string, key_last4?: string, source?: string}
     */
    public static function deleteProviderKey(string $providerId, ?int $userId = null): array
    {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return ['success' => false, 'message' => 'Provider tidak valid.', 'provider' => $providerId];
        }

        $meta = $allProviders[$providerId];

        // Validasi: Blokir jika provider sedang aktif digunakan oleh Default atau Fitur (C-Find / Matching)
        $defaultProv = (string)SettingService::get('ai_default_provider', '');
        $cfindProv   = (string)SettingService::get('ai_provider_cfind', '');
        $matchProv   = (string)SettingService::get('ai_provider_matching', '');
        $usedWhere   = [];
        if ($defaultProv === $providerId) $usedWhere[] = 'Default';
        if ($cfindProv === $providerId)   $usedWhere[] = 'Chatbot C-Find';
        if ($matchProv === $providerId)   $usedWhere[] = 'Pencocokan AI';

        if (!empty($usedWhere)) {
            return [
                'success'  => false,
                'message'  => "Tidak dapat menghapus key: {$meta['name']} sedang digunakan oleh " . implode(' / ', $usedWhere) . ". Ubah provider terlebih dahulu.",
                'provider' => $providerId,
            ];
        }

        $pdo = Database::getInstance()->getPdo();
        $keySetting = "ai_provider_{$providerId}_key";

        $stmt = $pdo->prepare("DELETE FROM system_settings WHERE `key` = ?");
        $stmt->execute([$keySetting]);

        SettingService::clearCache();

        AuditLogService::log(
            'delete_ai_provider_key',
            'system_settings',
            null,
            ['provider' => $providerId],
            $userId
        );

        $conn = self::getProviderConnection($providerId);

        return [
            'success'          => true,
            'message'          => "API Key di database untuk {$conn['name']} berhasil dihapus.",
            'provider'         => $providerId,
            'key_masked'       => $conn['api_key_masked'],
            'key_last4'        => $conn['last4'],
            'source'           => $conn['source'],
            'is_configured'    => $conn['is_configured'],
            'is_db'            => $conn['is_db'],
            'status'           => $conn['status'],
            'assignment_state' => self::getAiAssignmentState(),
        ];
    }

    /**
     * Dapatkan API key efektif dalam bentuk string terdekripsi (DATABASE adalah satu-satunya sumber)
     */
    public static function resolveEffectiveApiKey(string $providerId): string
    {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return '';
        }

        $dbKeySetting = "ai_provider_{$providerId}_key";
        return (string)SettingService::get($dbKeySetting, '');
    }

    /**
     * Uji koneksi nyata ke provider:
     * (a) Kredensial dari form jika diisi, atau dari database tersimpan provider itu
     * (b) Langkah 1: GET {base_url}/models untuk validasi API key (200 = valid)
     * (c) Jika custom provider, jalankan SSRF validation dan binding CURLOPT_RESOLVE
     */
    public static function testProviderConnection(
        string $providerId,
        ?string $modelId = null,
        ?string $apiKey = null,
        int $timeout = 15
    ): array {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return [
                'success'      => false,
                'message'      => "Provider '{$providerId}' tidak valid.",
                'http_status'  => 400,
                'duration_sec' => 0.0,
            ];
        }

        $conn = self::getProviderConnection($providerId);
        $keyToUse = !empty($apiKey) ? trim($apiKey) : self::resolveEffectiveApiKey($providerId);

        if (empty($keyToUse)) {
            return [
                'success'      => false,
                'message'      => "API Key untuk provider {$conn['name']} belum dikonfigurasi.",
                'http_status'  => 401,
                'duration_sec' => 0.0,
                'provider'     => $providerId,
            ];
        }

        $meta = $allProviders[$providerId];
        $baseUrl = rtrim($conn['base_url'], '/');

        // Jika custom provider, lakukan pemeriksaan SSRF dan binding IP
        $resolveOpt = [];
        if (!empty($meta['is_custom'])) {
            $ssrf = self::validateCustomProviderUrl($baseUrl);
            if (!$ssrf['valid']) {
                return [
                    'success'      => false,
                    'message'      => 'SSRF Protection: ' . ($ssrf['error'] ?? 'URL endpoint ditolak.'),
                    'http_status'  => 400,
                    'duration_sec' => 0.0,
                    'provider'     => $providerId,
                ];
            }
            if (!empty($ssrf['resolve_opt'])) {
                $resolveOpt = $ssrf['resolve_opt'];
            }
        }

        // ─────────────────────────────────────────────────────────────
        // LANGKAH 1: Validasi API Key via GET {base_url}/models
        // ─────────────────────────────────────────────────────────────
        $modelsEndpoint = $baseUrl . '/models';
        $chModels = curl_init($modelsEndpoint);
        if (!$chModels) {
            return [
                'success'      => false,
                'message'      => 'Gagal inisialisasi cURL untuk pemeriksaan model.',
                'http_status'  => 500,
                'duration_sec' => 0.0,
                'provider'     => $providerId,
            ];
        }

        $curlOpts = [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $keyToUse,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => min($timeout, 15),
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if (!empty($resolveOpt)) {
            $curlOpts[CURLOPT_RESOLVE] = $resolveOpt;
        }

        curl_setopt_array($chModels, $curlOpts);

        $start1 = microtime(true);
        $resModels = curl_exec($chModels);
        $duration1 = round(microtime(true) - $start1, 2);
        $codeModels = (int)curl_getinfo($chModels, CURLINFO_HTTP_CODE);
        $errModels = curl_error($chModels);
        curl_close($chModels);

        if ($errModels) {
            return [
                'success'      => false,
                'message'      => "Gagal terhubung ke {$conn['name']}: {$errModels}",
                'http_status'  => $codeModels ?: 0,
                'duration_sec' => $duration1,
                'provider'     => $providerId,
            ];
        }

        if ($codeModels !== 200) {
            $parsedErr = self::parseProviderError((string)$resModels, $codeModels);
            return [
                'success'      => false,
                'message'      => "Koneksi ke {$conn['name']} ditolak: {$parsedErr}",
                'http_status'  => $codeModels,
                'duration_sec' => $duration1,
                'provider'     => $providerId,
            ];
        }

        // Hitung jumlah model yang tersedia dari endpoint
        $decodedModels = json_decode((string)$resModels, true);
        $modelsList = is_array($decodedModels) ? ($decodedModels['data'] ?? ($decodedModels['models'] ?? [])) : [];
        $modelCount = count($modelsList);

        // Section A (Task 2B.1 Requirement B):
        // Uji koneksi Section A HANYA memanggil GET {base_url}/models untuk memvalidasi kredensial dan melaporkan jumlah model
        return [
            'success'      => true,
            'message'      => "Koneksi ke {$conn['name']} berhasil! Kredensial valid ({$modelCount} model tersedia, HTTP 200).",
            'http_status'  => 200,
            'duration_sec' => $duration1,
            'provider'     => $providerId,
            'models_count' => $modelCount,
        ];
    }

    /**
     * Dapatkan daftar model kompatibel untuk provider dan fitur tertentu
     */
    public static function getCompatibleModels(string $providerId, string $feature = 'cfind', bool $forceRefresh = false): array
    {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return [];
        }

        // 1. Jika TIDAK forceRefresh, coba ambil dari cache lokal terlebih dahulu
        if (!$forceRefresh) {
            $cached = self::getCachedModels($providerId);
            if (!empty($cached)) {
                return $cached;
            }
        }

        if ($providerId === 'google_gemini') {
            if (!$forceRefresh) {
                // Pada render awal / tanpa forceRefresh, gunakan model tersimpan lokal tanpa blocking cURL
                $savedModel = self::getSavedModelForFeatureOrProvider('google_gemini', $feature);
                if (!empty($savedModel)) {
                    return [
                        [
                            'id'                  => $savedModel,
                            'label'               => $savedModel . ' (Tersimpan)',
                            'compatible_chat'     => true,
                            'compatible_matching' => true,
                        ]
                    ];
                }
                return [];
            }

            $gemini = new GoogleGeminiService();
            $rawModels = $gemini->fetchModels($forceRefresh);
            if (empty($rawModels)) {
                // Sesuai Task 2B.1 Requirement C:
                // Jika /models gagal, pertahankan model tersimpan yang dapat dipilih dan tandai "saved, unverified"
                $savedModel = self::getSavedModelForFeatureOrProvider('google_gemini', $feature);
                if (!empty($savedModel)) {
                    return [
                        [
                            'id'                  => $savedModel,
                            'label'               => $savedModel . ' (Tersimpan, belum terverifikasi)',
                            'compatible_chat'     => true,
                            'compatible_matching' => true,
                            'is_unverified'       => true,
                        ]
                    ];
                }
                return [];
            }
            self::setCachedModels('google_gemini', $rawModels);
            return $rawModels;
        }

        if (!empty($allProviders[$providerId]['is_custom'])) {
            if (!$forceRefresh) {
                // Pada render awal / tanpa forceRefresh, gunakan model tersimpan lokal tanpa blocking cURL
                $savedModel = self::getSavedModelForFeatureOrProvider($providerId, $feature);
                if (!empty($savedModel)) {
                    return [
                        [
                            'id'                  => $savedModel,
                            'label'               => $savedModel . ' (Tersimpan)',
                            'compatible_chat'     => true,
                            'compatible_matching' => true,
                        ]
                    ];
                }
                return [];
            }

            $service = new OpenAICompatibleService($providerId);
            $rawModels = $service->fetchModels($forceRefresh);
            if (empty($rawModels)) {
                $savedModel = self::getSavedModelForFeatureOrProvider($providerId, $feature);
                if (!empty($savedModel)) {
                    return [
                        [
                            'id'                  => $savedModel,
                            'label'               => $savedModel . ' (unverified)',
                            'compatible_chat'     => true,
                            'compatible_matching' => true,
                            'is_unverified'       => true,
                        ]
                    ];
                }
                return [];
            }
            self::setCachedModels($providerId, $rawModels);
            return $rawModels;
        }

        // Provider NVIDIA NIM
        return self::getNvidiaNimModels($forceRefresh);
    }

    /**
     * Ambil model NVIDIA NIM (live katalog provider atau kurasi default)
     */
    private static function getNvidiaNimModels(bool $forceRefresh = false): array
    {
        $curated = [
            ['id' => 'nvidia/nemotron-3.5-bolt-e4b', 'label' => 'nvidia/nemotron-3.5-bolt-e4b (Disarankan / Default)', 'compatible_chat' => true, 'compatible_matching' => true],
            ['id' => 'meta/llama-3.2-11b-vision-instruct', 'label' => 'meta/llama-3.2-11b-vision-instruct (Multimodal / Fallback)', 'compatible_chat' => true, 'compatible_matching' => true],
            ['id' => 'meta/llama-3.3-70b-instruct', 'label' => 'meta/llama-3.3-70b-instruct (Penalaran Kuat)', 'compatible_chat' => true, 'compatible_matching' => true],
            ['id' => 'meta/llama-3.1-8b-instruct', 'label' => 'meta/llama-3.1-8b-instruct (Ringan & Cepat)', 'compatible_chat' => true, 'compatible_matching' => true],
            ['id' => 'mistralai/mixtral-8x7b-instruct-v0.1', 'label' => 'mistralai/mixtral-8x7b-instruct-v0.1', 'compatible_chat' => true, 'compatible_matching' => true],
            ['id' => 'deepseek-ai/deepseek-r1', 'label' => 'deepseek-ai/deepseek-r1', 'compatible_chat' => true, 'compatible_matching' => true],
        ];

        // Sertakan model tersimpan saat ini jika belum ada dalam kurasi
        $savedDefault = self::getDefaultAssignment();
        if ($savedDefault['provider'] === 'nvidia_nim' && !empty($savedDefault['model'])) {
            $found = false;
            foreach ($curated as $c) {
                if ($c['id'] === $savedDefault['model']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $curated[] = [
                    'id'                  => $savedDefault['model'],
                    'label'               => $savedDefault['model'] . ' (Tersimpan)',
                    'compatible_chat'     => true,
                    'compatible_matching' => true,
                ];
            }
        }

        // Jika bukan permintaan refresh eksplisit, gunakan cache atau kurasi instan
        if (!$forceRefresh) {
            $cached = self::getCachedModels('nvidia_nim');
            if (!empty($cached)) {
                return $cached;
            }
            return $curated;
        }

        $key = self::resolveEffectiveApiKey('nvidia_nim');
        if (empty($key)) {
            return $curated;
        }

        $conn = self::getProviderConnection('nvidia_nim');
        $endpoint = rtrim($conn['base_url'], '/') . '/models';

        $ch = curl_init($endpoint);
        if (!$ch) return $curated;

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $key,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$res) {
            return $curated;
        }

        $decoded = json_decode($res, true);
        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            return $curated;
        }

        $models = [];
        $existingIds = [];
        foreach ($decoded['data'] as $item) {
            $id = $item['id'] ?? '';
            if (empty($id)) continue;

            $label = $id;
            if ($id === 'nvidia/nemotron-3.5-bolt-e4b') {
                $label .= ' (Disarankan / Default)';
            }
            $models[] = [
                'id'                  => $id,
                'label'               => $label,
                'compatible_chat'     => true,
                'compatible_matching' => true,
            ];
            $existingIds[$id] = true;
        }

        // Sertakan model kurasi sistem agar selalu valid dan kompatibel
        foreach ($curated as $c) {
            $normCuratedId = self::normalizeModelId($c['id']);
            if (!isset($existingIds[$normCuratedId])) {
                $c['id'] = $normCuratedId;
                $models[] = $c;
                $existingIds[$normCuratedId] = true;
            }
        }

        $finalModels = !empty($models) ? $models : $curated;
        self::setCachedModels('nvidia_nim', $finalModels);
        return $finalModels;
    }

    /**
     * Dapatkan model tersimpan untuk fitur tertentu atau dari default (Task 2B.1 Requirement C)
     */
    public static function getSavedModelForFeatureOrProvider(string $providerId, string $feature = 'cfind'): ?string
    {
        $overrideModel = SettingService::get("ai_model_{$feature}");
        $overrideProv = SettingService::get("ai_provider_{$feature}");
        if (!empty($overrideModel) && $overrideProv === $providerId) {
            return self::normalizeModelId((string)$overrideModel);
        }

        $defaultProv = SettingService::get('ai_default_provider', 'nvidia_nim');
        if ($defaultProv === $providerId) {
            $defaultModel = SettingService::get('ai_default_model');
            if (!empty($defaultModel)) {
                return self::normalizeModelId((string)$defaultModel);
            }
        }

        return null;
    }

    /**
     * Uji inferensi chat untuk Section B menggunakan model yang dipilih secara eksplisit oleh admin (Task 2B.1 Requirement B)
     */
    public static function testChatInference(string $providerId, string $modelId, ?string $apiKey = null, int $timeout = 25): array
    {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return [
                'success'      => false,
                'message'      => "Provider AI '{$providerId}' tidak dikenali.",
                'http_status'  => 400,
                'duration_sec' => 0.0,
                'provider'     => $providerId,
            ];
        }

        $meta = $allProviders[$providerId];
        $conn = self::getProviderConnection($providerId);
        $keyToUse = $apiKey !== null ? trim($apiKey) : self::resolveEffectiveApiKey($providerId);
        $baseUrl = $conn['base_url'];
        $chosenModel = self::normalizeModelId($modelId);

        if (empty($keyToUse)) {
            return [
                'success'      => false,
                'message'      => "API Key untuk {$conn['name']} belum dikonfigurasi.",
                'http_status'  => 400,
                'duration_sec' => 0.0,
                'provider'     => $providerId,
                'model'        => $chosenModel,
            ];
        }

        $resolveOpt = [];
        if (!empty($meta['is_custom'])) {
            $ssrf = self::validateCustomProviderUrl($baseUrl);
            if (!$ssrf['valid']) {
                return [
                    'success'      => false,
                    'message'      => 'SSRF Protection: ' . ($ssrf['error'] ?? 'URL ditolak.'),
                    'http_status'  => 400,
                    'duration_sec' => 0.0,
                    'provider'     => $providerId,
                    'model'        => $chosenModel,
                ];
            }
            if (!empty($ssrf['resolve_opt'])) {
                $resolveOpt = $ssrf['resolve_opt'];
            }
        }

        $chatEndpoint = $baseUrl . '/chat/completions';
        $payload = [
            'model'       => $chosenModel,
            'messages'    => [
                ['role' => 'user', 'content' => 'Test connection Campus Find. Jawab 1 kata: OK.']
            ],
            'max_tokens'  => 300,
            'temperature' => 0.1,
            'stream'      => false,
        ];

        // Untuk Google Gemini, kirim reasoning_effort: minimal untuk membatasi overhead thinking tokens
        if ($providerId === 'google_gemini') {
            $payload['reasoning_effort'] = 'minimal';
        }

        $chChat = curl_init($chatEndpoint);
        if (!$chChat) {
            return [
                'success'      => false,
                'message'      => 'Gagal inisialisasi cURL untuk uji chat.',
                'http_status'  => 500,
                'duration_sec' => 0.0,
                'provider'     => $providerId,
                'model'        => $chosenModel,
            ];
        }

        $curlOptsChat = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $keyToUse,
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(20, (int)$timeout),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if (!empty($resolveOpt)) {
            $curlOptsChat[CURLOPT_RESOLVE] = $resolveOpt;
        }

        curl_setopt_array($chChat, $curlOptsChat);

        $start = microtime(true);
        $resChat = curl_exec($chChat);
        $duration = round(microtime(true) - $start, 2);
        $codeChat = (int)curl_getinfo($chChat, CURLINFO_HTTP_CODE);
        $errChat = curl_error($chChat);
        curl_close($chChat);

        // Jika Gemini menolak reasoning_effort dengan HTTP 400, coba ulang 1x tanpa parameter tersebut
        if ($codeChat === 400 && isset($payload['reasoning_effort'])) {
            unset($payload['reasoning_effort']);
            $chRetry = curl_init($chatEndpoint);
            if ($chRetry) {
                curl_setopt_array($chRetry, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $keyToUse,
                        'Accept: application/json',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => max(20, (int)$timeout),
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $start = microtime(true);
                $resChat = curl_exec($chRetry);
                $duration = round(microtime(true) - $start, 2);
                $codeChat = (int)curl_getinfo($chRetry, CURLINFO_HTTP_CODE);
                $errChat = curl_error($chRetry);
                curl_close($chRetry);
            }
        }

        if ($errChat) {
            return [
                'success'      => false,
                'message'      => "Inferensi chat model '{$chosenModel}' gagal: {$errChat}",
                'http_status'  => $codeChat ?: 0,
                'duration_sec' => $duration,
                'provider'     => $providerId,
                'model'        => $chosenModel,
            ];
        }

        if ($codeChat !== 200) {
            $parsedErr = self::parseProviderError((string)$resChat, $codeChat);
            return [
                'success'      => false,
                'message'      => "Model '{$chosenModel}' gagal: {$parsedErr}",
                'http_status'  => $codeChat,
                'duration_sec' => $duration,
                'provider'     => $providerId,
                'model'        => $chosenModel,
            ];
        }

        $decodedChat = json_decode((string)$resChat, true);
        $choice = is_array($decodedChat) && isset($decodedChat['choices'][0]) ? $decodedChat['choices'][0] : [];
        $reply = is_array($choice) && isset($choice['message']['content']) ? trim((string)$choice['message']['content']) : '';
        $finishReason = (string)($choice['finish_reason'] ?? 'unknown');
        $usage = is_array($decodedChat) && isset($decodedChat['usage']) ? $decodedChat['usage'] : [];
        $usageStr = !empty($usage) ? ' (tokens: prompt=' . ($usage['prompt_tokens'] ?? '?') . ', completion=' . ($usage['completion_tokens'] ?? '?') . ', total=' . ($usage['total_tokens'] ?? '?') . ')' : '';

        if ($reply === '') {
            $msg = "Model '{$chosenModel}' mengembalikan respons teks kosong (finish_reason: {$finishReason}{$usageStr}). ";
            if ($finishReason === 'length') {
                $msg .= "Batas token habis sebelum model menghasilkan teks (kemungkinan kuota reasoning token atau max_tokens terlampaui).";
            } else {
                $msg .= "Model mungkin bukan model chat/instruksi interaktif (mis. guard/safety/embedding). Silakan pilih model chat lain.";
            }
            return [
                'success'       => false,
                'message'       => $msg,
                'http_status'   => 200,
                'duration_sec'  => $duration,
                'provider'      => $providerId,
                'model'         => $chosenModel,
                'finish_reason' => $finishReason,
                'usage'         => $usage,
                'snippet'       => '',
            ];
        }

        return [
            'success'       => true,
            'message'       => "Inferensi model '{$chosenModel}' berhasil dalam {$duration}s (HTTP 200).",
            'http_status'   => 200,
            'duration_sec'  => $duration,
            'provider'      => $providerId,
            'model'         => $chosenModel,
            'snippet'       => $reply,
            'finish_reason' => $finishReason,
            'usage'         => $usage,
        ];
    }

    /**
     * Dapatkan konfigurasi Model Default sistem (Task 2B)
     *
     * @return array<string, mixed>
     */
    public static function getDefaultAssignment(): array
    {
        $allProviders = self::getRegisteredProviders();
        $defaultProv = (string)SettingService::get('ai_default_provider', 'nvidia_nim');
        if (!isset($allProviders[$defaultProv])) {
            $defaultProv = 'nvidia_nim';
        }
        $meta = $allProviders[$defaultProv];
        $defaultModel = (string)SettingService::get('ai_default_model', 'nvidia/nemotron-3.5-bolt-e4b');
        $conn = self::getProviderConnection($defaultProv);

        return [
            'provider'      => $defaultProv,
            'provider_id'   => $defaultProv,
            'provider_name' => $meta['name'],
            'model'         => self::normalizeModelId($defaultModel),
            'key_masked'    => $conn['api_key_masked'],
            'key_last4'     => $conn['last4'],
            'key_source'    => $conn['source'],
            'is_ready'      => $conn['is_configured'],
        ];
    }

    /**
     * Simpan penugasan Model Default sistem (Task 2B)
     *
     * @param string $providerId
     * @param string $modelId
     * @param int|null $userId
     * @return array{success: bool, message: string, warning?: string, assignment?: array}
     */
    public static function saveDefaultAssignment(
        string $providerId,
        string $modelId,
        ?int $userId = null
    ): array {
        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return ['success' => false, 'message' => "Provider '{$providerId}' tidak terdaftar."];
        }

        $meta = $allProviders[$providerId];
        $key = self::resolveEffectiveApiKey($providerId);
        if (empty($key)) {
            return [
                'success' => false,
                'message' => "Provider '{$meta['name']}' belum memiliki API Key tersimpan di database. Masukkan API Key di Section A terlebih dahulu.",
            ];
        }

        $modelId = self::normalizeModelId($modelId);
        if ($modelId === '') {
            return ['success' => false, 'message' => 'Model default wajib dipilih.'];
        }

        // Validasi kompatibilitas model
        $compatModels = self::getCompatibleModels($providerId, 'cfind');
        $compatIds = array_map([self::class, 'normalizeModelId'], array_column($compatModels, 'id'));

        $warning = null;
        if (!empty($compatIds) && !in_array($modelId, $compatIds, true)) {
            if (!empty($meta['is_custom'])) {
                $warning = "Model '{$modelId}' disimpan sebagai model kustom (unverified).";
            } else {
                return [
                    'success' => false,
                    'message' => "Model '{$modelId}' tidak valid atau tidak kompatibel untuk provider '{$providerId}'.",
                ];
            }
        }

        // Cek apakah daftar model yang dipakai adalah daftar cadangan (fallback)
        $isFallbackUsed = false;
        foreach ($compatModels as $cm) {
            if (($cm['id'] ?? '') === $modelId && !empty($cm['is_fallback'])) {
                $isFallbackUsed = true;
                break;
            }
        }
        if ($isFallbackUsed) {
            $warning = "Daftar cadangan sedang digunakan karena koneksi katalog provider tidak terjangkau. Model mungkin tidak mutakhir.";
        }

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
            VALUES (?, ?, 0, 'ai', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
        ");
        $stmt->execute(['ai_default_provider', $providerId, $userId]);
        $stmt->execute(['ai_default_model', $modelId, $userId]);

        SettingService::clearCache();

        AuditLogService::log(
            'update_ai_default_assignment',
            'system_settings',
            null,
            ['provider' => $providerId, 'model' => $modelId],
            $userId
        );

        $assignment = self::getDefaultAssignment();

        $res = [
            'success'          => true,
            'message'          => "Model Default berhasil diperbarui ke {$assignment['provider_name']} ({$modelId}).",
            'assignment'       => $assignment,
            'assignment_state' => self::getAiAssignmentState(),
        ];
        if ($warning) {
            $res['warning'] = $warning;
        }

        return $res;
    }

    /**
     * Dapatkan konfigurasi penugasan fitur AI (C-Find atau Matching)
     * Mengikuti hirarki: Override Fitur -> Model Default -> Error jika keduanya kosong.
     *
     * @param string $feature 'cfind'|'matching'
     * @return array<string, mixed>
     */
    public static function getFeatureAssignment(string $feature): array
    {
        if (!in_array($feature, ['cfind', 'matching'], true)) {
            throw new \InvalidArgumentException("Fitur '{$feature}' tidak valid.");
        }

        $allProviders = self::getRegisteredProviders();
        $featureLabel = $feature === 'cfind' ? 'Chatbot C-Find' : 'Pencocokan AI (Matching)';
        $defaultAssignment = self::getDefaultAssignment();

        $rawProv = trim((string)SettingService::get("ai_feature_{$feature}_provider", ''));
        $rawModel = trim((string)SettingService::get("ai_model_{$feature}", ''));

        $hasOverride = (!empty($rawProv) && !empty($rawModel));

        if ($hasOverride && isset($allProviders[$rawProv])) {
            $assignedProvider = $rawProv;
            $assignedModel = self::normalizeModelId($rawModel);
            $isOverride = true;
        } else {
            // Mengikuti model default
            $assignedProvider = $defaultAssignment['provider'];
            $assignedModel = $defaultAssignment['model'];
            $isOverride = false;
        }

        if (empty($assignedProvider) || empty($assignedModel)) {
            throw new \RuntimeException("AI belum dikonfigurasi: Provider dan model default belum disetel.");
        }

        $meta = $allProviders[$assignedProvider];
        $conn = self::getProviderConnection($assignedProvider);

        return [
            'feature'          => $feature,
            'feature_label'    => $featureLabel,
            'provider'         => $assignedProvider,
            'provider_id'      => $assignedProvider,
            'provider_name'    => $meta['name'],
            'model'            => $assignedModel,
            'is_override'      => $isOverride,
            'default_provider' => $defaultAssignment['provider'],
            'default_model'    => $defaultAssignment['model'],
            'key_masked'       => $conn['api_key_masked'],
            'key_last4'        => $conn['last4'],
            'key_source'       => $conn['source'],
            'is_ready'         => $conn['is_configured'],
        ];
    }

    /**
     * Kembalikan konfigurasi fitur mengikuti default (mengosongkan override di DB)
     */
    public static function revertFeatureToDefault(string $feature, ?int $userId = null): array
    {
        if (!in_array($feature, ['cfind', 'matching'], true)) {
            return ['success' => false, 'message' => "Fitur '{$feature}' tidak valid."];
        }

        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare("DELETE FROM system_settings WHERE `key` IN (?, ?)");
        $stmt->execute(["ai_feature_{$feature}_provider", "ai_model_{$feature}"]);

        SettingService::clearCache();

        $featureLabel = $feature === 'cfind' ? 'Chatbot C-Find' : 'Pencocokan AI';
        AuditLogService::log(
            'revert_ai_feature_to_default',
            'system_settings',
            null,
            ['feature' => $feature, 'reverted_to' => 'default'],
            $userId
        );

        $assignment = self::getFeatureAssignment($feature);

        return [
            'success'          => true,
            'message'          => "Konfigurasi {$featureLabel} berhasil dikembalikan mengikuti Model Default ({$assignment['provider_name']} - {$assignment['model']}).",
            'assignment'       => $assignment,
            'assignment_state' => self::getAiAssignmentState(),
        ];
    }

    /**
     * Simpan penugasan provider & model untuk fitur AI tertentu (override)
     */
    public static function saveFeatureAssignment(
        string $feature,
        string $providerId,
        string $modelId,
        ?int $userId = null
    ): array {
        if (!in_array($feature, ['cfind', 'matching'], true)) {
            return ['success' => false, 'message' => "Fitur '{$feature}' tidak valid."];
        }

        // Jika provider kosong atau diminta kembali ke default
        if ($providerId === '' || $providerId === 'default') {
            return self::revertFeatureToDefault($feature, $userId);
        }

        $allProviders = self::getRegisteredProviders();
        if (!isset($allProviders[$providerId])) {
            return ['success' => false, 'message' => "Provider '{$providerId}' tidak terdaftar."];
        }

        $meta = $allProviders[$providerId];
        $key = self::resolveEffectiveApiKey($providerId);
        if (empty($key)) {
            return [
                'success' => false,
                'message' => "Provider '{$meta['name']}' belum memiliki API Key tersimpan di database. Masukkan API Key di Section A terlebih dahulu.",
            ];
        }

        $modelId = self::normalizeModelId($modelId);
        if ($modelId === '') {
            return ['success' => false, 'message' => 'Model wajib dipilih.'];
        }

        // Validasi kompatibilitas model dengan provider dan fitur
        $compatModels = self::getCompatibleModels($providerId, $feature);
        $compatIds = array_map([self::class, 'normalizeModelId'], array_column($compatModels, 'id'));
        $warning = null;
        if (!empty($compatIds) && !in_array($modelId, $compatIds, true)) {
            if (!empty($meta['is_custom'])) {
                $warning = "Model '{$modelId}' disimpan sebagai model kustom (unverified).";
            } else {
                return [
                    'success' => false,
                    'message' => "Model '{$modelId}' tidak valid atau tidak kompatibel untuk provider '{$providerId}'.",
                ];
            }
        }

        $isFallbackUsed = false;
        foreach ($compatModels as $cm) {
            if (($cm['id'] ?? '') === $modelId && !empty($cm['is_fallback'])) {
                $isFallbackUsed = true;
                break;
            }
        }
        if ($isFallbackUsed) {
            $warning = "Daftar cadangan sedang digunakan karena koneksi katalog provider tidak terjangkau. Model mungkin tidak mutakhir.";
        }

        $pdo = Database::getInstance()->getPdo();

        // Simpan override provider terpilih untuk fitur
        $providerKey = "ai_feature_{$feature}_provider";
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, `is_encrypted`, `group`, `updated_by`, `created_at`, `updated_at`)
            VALUES (?, ?, 0, 'ai', ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_by` = VALUES(`updated_by`), `updated_at` = NOW()
        ");
        $stmt->execute([$providerKey, $providerId, $userId]);

        // Simpan override model terpilih untuk fitur
        $modelKey = "ai_model_{$feature}";
        $stmt->execute([$modelKey, $modelId, $userId]);

        SettingService::clearCache();

        AuditLogService::log(
            'update_ai_feature_assignment',
            'system_settings',
            null,
            ['feature' => $feature, 'provider' => $providerId, 'model' => $modelId],
            $userId
        );

        $assignment = self::getFeatureAssignment($feature);

        $res = [
            'success'          => true,
            'message'          => "Penugasan khusus {$assignment['feature_label']} berhasil diperbarui ke {$assignment['provider_name']} ({$modelId}).",
            'assignment'       => $assignment,
            'assignment_state' => self::getAiAssignmentState(),
            'feature'          => $feature,
            'provider'         => $providerId,
            'provider_name'    => $assignment['provider_name'],
            'model'            => $modelId,
            'key_masked'       => $assignment['key_masked'],
            'key_last4'        => $assignment['key_last4'],
            'is_ready'         => $assignment['is_ready'],
            'is_override'      => true,
        ];
        if ($warning) {
            $res['warning'] = $warning;
        }

        return $res;
    }

    /**
     * Uji konfigurasi fitur aktif (C-Find atau Matching)
     */
    public static function testFeatureConfiguration(string $feature): array
    {
        $assignment = self::getFeatureAssignment($feature);
        $providerId = $assignment['provider'];
        $modelId = self::normalizeModelId($assignment['model']);

        if ($feature === 'matching') {
            return self::testMatchingEvaluation($providerId, $modelId, $assignment['provider_name']);
        }

        $res = self::testChatInference($providerId, $modelId, null, 25);
        $res['provider_name'] = $assignment['provider_name'];
        return $res;
    }

    /**
     * Uji evaluasi pencocokan AI dengan mini dataset 3 kandidat:
     * - Kandidat #201: Sangat cocok (clear match)
     * - Kandidat #202: Serupa kategori / merek (similar)
     * - Kandidat #203: Tidak relevan sama sekali (unrelated)
     * Memastikan parse_status === 'parsed' dan skor: match > similar > unrelated.
     */
    public static function testMatchingEvaluation(string $providerId, string $modelId, string $providerName): array
    {
        $start = microtime(true);
        $targetReport = [
            'id'            => 100,
            'title'         => 'Dompet Kulit Hitam Eiger',
            'category_name' => 'Dompet & Tas',
            'location_name' => 'Perpustakaan Pusat Lantai 2',
            'lost_date'     => date('Y-m-d'),
            'description'   => 'Dompet lipat kulit warna hitam merek Eiger, berisi identitas KTM atas nama Budi dan uang tunai.',
        ];

        $candidates = [
            [
                'id'            => 201,
                'title'         => 'Dompet Hitam Lipat Eiger',
                'category_name' => 'Dompet & Tas',
                'location_name' => 'Perpustakaan Pusat',
                'found_date'    => date('Y-m-d'),
                'description'   => 'Ditemukan dompet lipat kulit hitam merk Eiger di meja baca perpustakaan lantai 2, terdapat KTM.',
            ],
            [
                'id'            => 202,
                'title'         => 'Tas Ransel Hitam Eiger',
                'category_name' => 'Dompet & Tas',
                'location_name' => 'Kantin Gedung F',
                'found_date'    => date('Y-m-d'),
                'description'   => 'Tas ransel punggung warna hitam merek Eiger tertinggal di kursi kantin.',
            ],
            [
                'id'            => 203,
                'title'         => 'Kunci Motor Honda Vario',
                'category_name' => 'Kunci & Kendaraan',
                'location_name' => 'Parkiran Lapangan Basket',
                'found_date'    => date('Y-m-d'),
                'description'   => 'Kunci motor Honda dengan gantungan tali merah ditemukan di aspal dekat lapangan.',
            ],
        ];

        try {
            if ($providerId === 'google_gemini') {
                $service = new GoogleGeminiService(null, $modelId);
            } elseif ($providerId === 'nvidia_nim') {
                $service = new NvidiaNimService(null, $modelId);
            } else {
                $service = new OpenAICompatibleService($providerId, $modelId);
            }

            $evalRes = $service->evaluateCandidates($targetReport, $candidates, 'lost');
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $start, 2);
            return [
                'success'       => false,
                'message'       => "Eksekusi evaluasi pencocokan gagal: " . $e->getMessage(),
                'http_status'   => 500,
                'duration_sec'  => $duration,
                'provider'      => $providerId,
                'provider_name' => $providerName,
                'model'         => $modelId,
                'parse_status'  => 'error',
            ];
        }

        $duration = round(microtime(true) - $start, 2);
        $parseStatus = $evalRes['parse_status'] ?? 'failed';

        if ($parseStatus !== 'parsed') {
            $errDetail = $evalRes['error'] ?? 'Parsing JSON respons model tidak valid.';
            return [
                'success'       => false,
                'message'       => "Uji pencocokan AI gagal: Model tidak menghasilkan format JSON terstruktur yang valid ({$errDetail}, parse_status: {$parseStatus}).",
                'http_status'   => 200,
                'duration_sec'  => $duration,
                'provider'      => $providerId,
                'provider_name' => $providerName,
                'model'         => $modelId,
                'parse_status'  => $parseStatus,
                'snippet'       => $evalRes['raw_content'] ?? '',
            ];
        }

        $scoreMatch = (float)($evalRes['scores'][201]['score'] ?? 0);
        $scoreSimilar = (float)($evalRes['scores'][202]['score'] ?? 0);
        $scoreUnrelated = (float)($evalRes['scores'][203]['score'] ?? 0);

        $isRankValid = ($scoreMatch > $scoreSimilar && $scoreSimilar > $scoreUnrelated);

        if (!$isRankValid) {
            return [
                'success'       => false,
                'message'       => "Peringkat relevansi tidak terpenuhi: Match #201 ({$scoreMatch}%), Serupa #202 ({$scoreSimilar}%), Berbeda #203 ({$scoreUnrelated}%). Model gagal membedakan relevansi (syarat: Match > Serupa > Berbeda).",
                'http_status'   => 200,
                'duration_sec'  => $duration,
                'provider'      => $providerId,
                'provider_name' => $providerName,
                'model'         => $modelId,
                'parse_status'  => 'parsed',
                'scores'        => [
                    'match'     => $scoreMatch,
                    'similar'   => $scoreSimilar,
                    'unrelated' => $scoreUnrelated,
                ],
            ];
        }

        return [
            'success'       => true,
            'message'       => "Uji pencocokan AI model '{$modelId}' berhasil dalam {$duration}s! Evaluasi 3 kandidat valid: Match #201 ({$scoreMatch}%), Serupa #202 ({$scoreSimilar}%), Berbeda #203 ({$scoreUnrelated}%).",
            'http_status'   => 200,
            'duration_sec'  => $duration,
            'provider'      => $providerId,
            'provider_name' => $providerName,
            'model'         => $modelId,
            'parse_status'  => 'parsed',
            'scores'        => [
                'match'     => $scoreMatch,
                'similar'   => $scoreSimilar,
                'unrelated' => $scoreUnrelated,
            ],
        ];
    }

    /**
     * Resolusi adapter provider yang aktif untuk sebuah fitur
     * Digunakan oleh CFindService dan AiMatchService saat runtime!
     */
    public static function resolveFeatureProvider(string $feature): AiProviderInterface
    {
        $assignment = self::getFeatureAssignment($feature);
        $providerId = $assignment['provider'];
        $modelId = self::normalizeModelId($assignment['model']);

        if (empty($providerId) || empty($modelId)) {
            throw new \RuntimeException("AI belum dikonfigurasi: Provider dan model default belum disetel.");
        }

        if ($providerId === 'google_gemini') {
            return new GoogleGeminiService(null, $modelId);
        }

        if ($providerId === 'nvidia_nim') {
            return new NvidiaNimService(null, $modelId);
        }

        $allProviders = self::getRegisteredProviders();
        if (isset($allProviders[$providerId]) && !empty($allProviders[$providerId]['is_custom'])) {
            return new OpenAICompatibleService($providerId);
        }

        throw new \RuntimeException("AI belum dikonfigurasi: Provider '{$providerId}' tidak didukung.");
    }

    /**
     * Dapatkan metadata runtime aktual untuk sebuah fitur (C-Find atau Matching)
     * Menghasilkan: provider, model, execution_status, is_fallback
     *
     * @param string $feature 'cfind'|'matching'
     * @return array{
     *     provider: string,
     *     provider_id: string,
     *     model: string,
     *     configured_model: string,
     *     execution_status: string,
     *     is_fallback: bool,
     *     fallback_reason: ?string
     * }
     */
    public static function getFeatureRuntimeMetadata(string $feature = 'cfind'): array
    {
        $assignment = self::getFeatureAssignment($feature);
        $providerId = $assignment['provider'];
        $configuredModel = $assignment['model'];
        $providerName = $assignment['provider_name'];

        $provider = self::resolveFeatureProvider($feature);
        $isAvailable = $provider->isChatAvailable();
        $actualModel = method_exists($provider, 'getCurrentModel')
            ? $provider->getCurrentModel($feature)
            : $configuredModel;

        return [
            'provider'         => $providerName,
            'provider_id'      => $providerId,
            'model'            => $actualModel,
            'configured_model' => $configuredModel,
            'execution_status' => $isAvailable ? 'success' : 'fallback',
            'is_fallback'      => !$isAvailable,
            'fallback_reason'  => $isAvailable ? null : "Kredensial API key untuk {$providerName} belum dikonfigurasi.",
        ];
    }

    /**
     * Hasilkan teks jawaban transparansi yang natural dan mudah dipahami
     * berdasarkan metadata runtime aktual (Task 14 Additional Requirement)
     *
     * @param array<string, mixed> $runtimeMeta
     * @return string
     */
    public static function generateTransparencyAnswer(array $runtimeMeta): string
    {
        $providerName = (string)($runtimeMeta['provider'] ?? 'AI Provider');
        $model = (string)($runtimeMeta['model'] ?? 'default');
        $isFallback = !empty($runtimeMeta['is_fallback']) || 
                      in_array($runtimeMeta['execution_status'] ?? '', ['fallback', 'failed'], true);

        if ($isFallback) {
            return "Untuk jawaban ini, layanan AI utama sedang tidak tersedia. Saya menggunakan mekanisme fallback internal Campus Find, sehingga tidak ada model AI eksternal yang menghasilkan jawaban ini.";
        }

        if (str_contains(strtolower($providerName), 'gemini')) {
            return "Saat ini saya menggunakan provider Google Gemini dengan model {$model} untuk membantu menjawab pertanyaan Anda.";
        }

        if (str_contains(strtolower($providerName), 'nvidia')) {
            return "Saat ini saya menggunakan NVIDIA NIM sebagai provider dengan model {$model}.";
        }

        return "Saat ini saya menggunakan provider {$providerName} dengan model {$model} untuk membantu menjawab pertanyaan Anda.";
    }
}
