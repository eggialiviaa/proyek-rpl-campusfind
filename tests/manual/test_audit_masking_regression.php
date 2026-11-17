<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\AuditLogService;

$pdo = Database::getInstance()->getPdo();

echo "Running AuditLogService Regression Test...\n";

// Test payload containing:
// - sensitive keys (top-level and nested)
// - allowlisted keys (last4, key_updated, api_key_updated, keys_count)
// - harmless field with a value-shape secret (defense-in-depth)
$testPayload = [
    'action_type'     => 'config_update',
    'last4'           => '8f06',
    'key_last4'       => '3d21',
    'key_updated'     => true,
    'api_key_updated' => false,
    'keys_count'      => 4,
    'ai_provider_nvidia_nim_key' => 'nvapi-FAKEKEYABCDEFGHIJKLMN1234567890',
    'secret_token'    => 'AIzaSyTESTSECRETKEYFORAUDIT_1234',
    'auth_header'     => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.dummy',
    'nested'          => [
        'normal_name'   => 'Antigravity Test',
        'sub_key'       => 'nvapi-NESTEDSECRETKEY1234567890',
        'safe_nested'   => 'Safe Nested Value',
        'cookie_data'   => 'session_id=1234567',
        'credentials'   => [
            'password'  => 'supersecret123',
            'random_id' => 'AQ.FAKESECRET1234567890',
        ],
    ],
    // Harmless key name, but value matches secret pattern!
    'custom_notes'    => 'Admin updated key using nvapi-INOCENTLOOKINGFIELD123456789',
];

AuditLogService::log(
    'REGRESSION_AUDIT_MASKING_TEST',
    'system_settings',
    99999,
    $testPayload,
    1
);

$row = $pdo->query("SELECT * FROM audit_logs WHERE action = 'REGRESSION_AUDIT_MASKING_TEST' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "FAIL: No audit row inserted.\n";
    exit(1);
}

$rawPayload = $row['payload'];
echo "Stored Payload JSON:\n" . $rawPayload . "\n\n";

$dec = json_decode($rawPayload, true);

$assertions = [];

// 1. Allowlisted keys preserved
$assertions['last4 preserved'] = ($dec['last4'] === '8f06');
$assertions['key_last4 preserved'] = ($dec['key_last4'] === '3d21');
$assertions['key_updated preserved'] = ($dec['key_updated'] === true);
$assertions['api_key_updated preserved'] = ($dec['api_key_updated'] === false);
$assertions['keys_count preserved'] = ($dec['keys_count'] === 4);

// 2. Sensitive keys redacted
$assertions['ai_provider_nvidia_nim_key redacted'] = ($dec['ai_provider_nvidia_nim_key'] === '[REDACTED]');
$assertions['secret_token redacted'] = ($dec['secret_token'] === '[REDACTED]');
$assertions['auth_header redacted'] = ($dec['auth_header'] === '[REDACTED]');

// 3. Nested sensitive keys redacted
$assertions['nested.sub_key redacted'] = ($dec['nested']['sub_key'] === '[REDACTED]');
$assertions['nested.cookie_data redacted'] = ($dec['nested']['cookie_data'] === '[REDACTED]');
$assertions['nested.credentials redacted'] = ($dec['nested']['credentials'] === '[REDACTED]');

// 4. Defense-in-depth: string values matching secret shapes redacted
$assertions['custom_notes value-redacted'] = ($dec['custom_notes'] === '[REDACTED_SECRET_VALUE]');

// 5. Normal fields preserved
$assertions['nested.normal_name preserved'] = ($dec['nested']['normal_name'] === 'Antigravity Test');
$assertions['nested.safe_nested preserved'] = ($dec['nested']['safe_nested'] === 'Safe Nested Value');

// 6. Zero secret tokens in raw JSON string
$pattern = '/(?:nvapi-[A-Za-z0-9_\.\-]{10,}|AIza[A-Za-z0-9_\.\-]{25,}|AQ\.[A-Za-z0-9_\.\-]{15,}|Bearer\s+[A-Za-z0-9_\.\-]{20,})/i';
$assertions['No raw secret in JSON string'] = (preg_match($pattern, $rawPayload) === 0);

$allPassed = true;
foreach ($assertions as $label => $pass) {
    echo ($pass ? " [PASS] " : " [FAIL] ") . $label . "\n";
    if (!$pass) $allPassed = false;
}

// Clean up test row
$pdo->exec("DELETE FROM audit_logs WHERE action = 'REGRESSION_AUDIT_MASKING_TEST'");

if ($allPassed) {
    echo "\nALL AUDIT MASKING REGRESSION ASSERTIONS PASSED!\n";
    exit(0);
} else {
    echo "\nSOME ASSERTIONS FAILED!\n";
    exit(1);
}
