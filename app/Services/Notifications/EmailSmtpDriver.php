<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Services\SettingService;
use Core\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PDO;
use Throwable;

/**
 * EmailSmtpDriver — Driver Pengiriman Notifikasi via Email SMTP (Prompt 20)
 *
 * Menggunakan PHPMailer untuk mengirim email transaksional dengan:
 * - Konfigurasi dinamis dari System Settings (fallback ke .env)
 * - Dukungan STARTTLS (Port 587) dan SSL (Port 465)
 * - Safeguard NOTIFY_ALLOWLIST untuk lingkungan non-produksi
 * - Penanganan template aman (tanpa data pribadi/sensitif)
 * - Isolasi kredensial (tidak pernah bocor ke log atau exception)
 */
class EmailSmtpDriver implements NotificationChannelInterface
{
    private ?array $customConfig = null;

    public function __construct(?array $customConfig = null)
    {
        $this->customConfig = $customConfig;
    }

    public function getName(): string
    {
        return 'email';
    }

    /**
     * Dapatkan konfigurasi aktif (custom override -> database system_settings -> .env -> default)
     */
    public function getConfig(): array
    {
        if ($this->customConfig !== null) {
            return $this->customConfig;
        }

        // Isolasi Pengujian: Di lingkungan test, kredensial SMTP asli TIDAK PERNAH dimuat
        if (MailGateway::isTestEnvironment()) {
            return [
                'host'         => '127.0.0.1',
                'port'         => 1025,
                'encryption'   => 'none',
                'username'     => 'test_isolation@localhost',
                'password'     => 'test_isolation_dummy_secret',
                'from_address' => 'no-reply@campusfind.test',
                'from_name'    => 'Campus Find (Test Isolation)',
                'allowlist'    => '',
            ];
        }

        return [
            'host'         => (string) SettingService::get('notif_smtp_host', 'smtp.gmail.com'),
            'port'         => (int) SettingService::get('notif_smtp_port', 587),
            'encryption'   => (string) SettingService::get('notif_smtp_encryption', 'tls'),
            'username'     => (string) SettingService::get('notif_smtp_username', ''),
            'password'     => (string) SettingService::get('notif_smtp_password', ''),
            'from_address' => (string) SettingService::get('notif_smtp_from_address', 'no-reply@campusfind.ac.id'),
            'from_name'    => (string) SettingService::get('notif_smtp_from_name', 'Campus Find L&F'),
            'allowlist'    => (string) SettingService::get('notif_allowlist', 'admin@campusfind.test'),
        ];
    }

    /**
     * Periksa apakah alamat email diizinkan oleh NOTIFY_ALLOWLIST di lingkungan non-produksi
     * Menggunakan MailGateway terpusat: HANYA ALAMAT PERSIS TANPA WILDCARD
     */
    public function isAllowedRecipient(string $email): bool
    {
        if (MailGateway::isReservedOrExampleDomain($email)) {
            return false;
        }

        return MailGateway::isAllowedRecipient($email);
    }

    /**
     * Kirim email berdasarkan baris item dari notification_outbox
     */
    public function send(array $outboxItem): array
    {
        $recipient = trim((string) ($outboxItem['recipient'] ?? ''));

        // Evaluasi Terpusat MailGateway (Validasi format, TLD reservasi, saluran aktif, allowlist, kuota)
        $userId = !empty($outboxItem['user_id']) ? (int)$outboxItem['user_id'] : null;
        $templateKey = (string) ($outboxItem['template_key'] ?? 'general');
        $val = MailGateway::validateRecipient($recipient, $userId, $templateKey);
        if (!$val['allowed']) {
            $masked = $this->maskEmail($recipient);
            return [
                'success' => false,
                'error'   => $val['error'] ?? "[GATEWAY_BLOCKED] Alamat email {$masked} diblokir.",
                'blocked' => true,
            ];
        }

        // Safeguard Pengujian
        if (MailGateway::isTestEnvironment()) {
            return [
                'success' => false,
                'error'   => '[TEST_ISOLATION] Percobaan kirim email nyata diblokir dalam konteks pengujian.',
                'blocked' => true,
            ];
        }

        $templateKey = (string) ($outboxItem['template_key'] ?? 'general');
        $rawPayload  = $outboxItem['payload'] ?? [];
        $payload     = is_string($rawPayload) ? (json_decode($rawPayload, true) ?: []) : (array) $rawPayload;

        $template = $this->getTemplate($templateKey);
        $rendered = $this->renderMessage($template, $payload, $outboxItem);

        // Pengaman Pengiriman 32B: jika setelah render masih ada pola {...} yang tidak terganti, JANGAN kirim.
        if (!empty($rendered['has_unresolved'])) {
            $unresolvedList = implode(', ', $rendered['unresolved_placeholders']);
            $maskedRecipient = $this->maskEmail($recipient);
            error_log("[EmailSmtpDriver] Pengiriman DIBATALKAN: placeholder belum terganti ({$unresolvedList}) pada template '{$templateKey}' untuk penerima {$maskedRecipient}");
            return [
                'success' => false,
                'error'   => "placeholder belum terganti: {$unresolvedList}",
                'blocked' => false,
            ];
        }

        $config = $this->getConfig();

        if (empty($config['host'])) {
            return [
                'success' => false,
                'error'   => 'Konfigurasi SMTP Host belum diatur.',
                'blocked' => false,
            ];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->Port       = $config['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 15;

            // Enkripsi
            $enc = strtolower($config['encryption']);
            if ($enc === 'ssl' || $config['port'] === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls' || $config['port'] === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            // Autentikasi
            if (!empty($config['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $config['username'];
                $mail->Password = $config['password'];
            } else {
                $mail->SMTPAuth = false;
            }

            // Pengirim & Penerima
            $fromAddress = !empty($config['from_address']) ? $config['from_address'] : ($config['username'] ?: 'no-reply@campusfind.ac.id');
            $fromName    = !empty($config['from_name']) ? $config['from_name'] : 'Campus Find L&F';
            $mail->setFrom($fromAddress, $fromName);

            $userName = $payload['user_name'] ?? '';
            $mail->addAddress($recipient, $userName);

            // Konten
            $mail->isHTML(true);
            $mail->Subject = $rendered['subject'];
            $mail->Body    = $rendered['body_html'];
            $mail->AltBody = $rendered['body_text'];

            $mail->send();

            return [
                'success'    => true,
                'message_id' => $mail->getLastMessageID(),
            ];
        } catch (PHPMailerException $e) {
            $cleanErr = $this->sanitizeErrorMessage($e->getMessage(), $config);
            return [
                'success' => false,
                'error'   => $cleanErr,
                'blocked' => false,
            ];
        } catch (Throwable $e) {
            $cleanErr = $this->sanitizeErrorMessage($e->getMessage(), $config);
            return [
                'success' => false,
                'error'   => 'Kesalahan pengiriman: ' . $cleanErr,
                'blocked' => false,
            ];
        }
    }

    /**
     * Uji koneksi SMTP tanpa mengirim email penuh
     */
    public function testConnection(?array $configOverride = null): array
    {
        $config = $configOverride ?: $this->getConfig();

        if (empty($config['host'])) {
            return [
                'success' => false,
                'message' => 'Host SMTP belum ditentukan.',
            ];
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host    = $config['host'];
            $mail->Port    = (int) ($config['port'] ?? 587);
            $mail->Timeout = 10;

            $enc = strtolower($config['encryption'] ?? 'tls');
            if ($enc === 'ssl' || $mail->Port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($enc === 'tls' || $mail->Port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            if (!empty($config['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $config['username'];
                $mail->Password = $config['password'] ?? '';
            } else {
                $mail->SMTPAuth = false;
            }

            // Lakukan handshake koneksi
            $smtp = $mail->getSMTPInstance();
            $smtp->setTimeout(10);

            $connected = $smtp->connect($mail->Host, $mail->Port, 10, [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]);

            if (!$connected) {
                return [
                    'success' => false,
                    'message' => 'Gagal menghubungkan soket ke server SMTP ' . $mail->Host . ':' . $mail->Port,
                ];
            }

            $smtp->hello(gethostname() ?: 'localhost');

            if ($mail->SMTPSecure === PHPMailer::ENCRYPTION_STARTTLS) {
                if (!$smtp->startTLS()) {
                    $smtp->quit();
                    return [
                        'success' => false,
                        'message' => 'Gagal negosiasi STARTTLS dengan server SMTP.',
                    ];
                }
                $smtp->hello(gethostname() ?: 'localhost');
            }

            if ($mail->SMTPAuth && !empty($config['username']) && !empty($config['password'])) {
                if (!$smtp->authenticate($config['username'], $config['password'])) {
                    $smtp->quit();
                    return [
                        'success' => false,
                        'message' => 'Autentikasi SMTP gagal: Username atau App Password tidak valid.',
                    ];
                }
            }

            $smtp->quit();

            return [
                'success' => true,
                'message' => 'Koneksi dan autentikasi SMTP berhasil ke ' . $config['host'] . ':' . $config['port'],
                'details' => [
                    'host'       => $config['host'],
                    'port'       => $config['port'],
                    'encryption' => $config['encryption'],
                    'auth'       => !empty($config['username']),
                ],
            ];
        } catch (Throwable $e) {
            $msg = $this->sanitizeErrorMessage($e->getMessage(), $config);
            return [
                'success' => false,
                'message' => 'Uji koneksi gagal: ' . $msg,
            ];
        }
    }

    /**
     * Render body HTML untuk template tertentu dengan payload yang diberikan (untuk preview/test)
     */
    public function renderEmailBody(string $templateKey, array $payload): string
    {
        $template = $this->getTemplate($templateKey);
        $rendered = $this->renderMessage($template, $payload, ['recipient' => 'user@campusfind.test']);
        return $rendered['body_html'];
    }

    /**
     * Dapatkan data template dari database
     */
    private function getTemplate(string $templateKey): array
    {
        try {
            $pdo = Database::getInstance()->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM notification_templates WHERE template_key = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$templateKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return $row;
            }
        } catch (Throwable $e) {
            error_log('[EmailSmtpDriver::getTemplate] Error: ' . $e->getMessage());
        }

        // Fallback default template jika belum ada di database
        return [
            'subject'   => '[Campus Find] Pemberitahuan Notifikasi',
            'body_html' => '<p>Halo <strong>{{user_name}}</strong>,</p><p>{{title}}</p><p>{{message}}</p><p><a href="{{action_url}}">Buka Campus Find</a></p>',
            'body_text' => "Halo {{user_name}},\n\n{{title}}\n{{message}}\n\nBuka Campus Find:\n{{action_url}}",
        ];
    }

    /**
     * Render subject, body HTML, dan body text melalui NotificationTemplateService
     */
    public function renderMessage(array $template, array $payload, array $outboxItem): array
    {
        $appUrl  = rtrim($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://campus-find.test', '/');
        $appName = $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Campus Find';
        $templateKey = (string) ($outboxItem['template_key'] ?? 'general');

        $rendered = NotificationTemplateService::render($templateKey, $template, $payload, $outboxItem);

        $fullHtml = $this->wrapHtmlLayout($rendered['body_html'], $appName, $appUrl, $rendered['subject']);

        return [
            'subject'                 => $rendered['subject'],
            'body_html'               => $fullHtml,
            'body_text'               => $rendered['body_text'],
            'has_unresolved'          => $rendered['has_unresolved'],
            'unresolved_placeholders' => $rendered['unresolved_placeholders'],
        ];
    }

    /**
     * Bungkus konten dalam layout HTML email modern & responsif (tanpa elemen rentan)
     */
    private function wrapHtmlLayout(string $contentHtml, string $appName, string $appUrl, string $subject): string
    {
        return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
<style>
  body { margin:0; padding:0; background-color:#f1f5f9; font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color:#1e293b; }
  .email-wrapper { width:100%; max-width:600px; margin:24px auto; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.06); }
  .email-header { background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%); padding:24px; text-align:center; color:#ffffff; }
  .email-header h1 { margin:0; font-size:20px; font-weight:700; letter-spacing:-0.02em; }
  .email-header p { margin:4px 0 0; font-size:12px; opacity:0.8; }
  .email-body { padding:28px 24px; line-height:1.6; font-size:15px; }
  .email-footer { background-color:#f8fafc; padding:20px 24px; font-size:12px; color:#64748b; text-align:center; border-top:1px solid #e2e8f0; line-height:1.5; }
  .email-footer a { color:#0284c7; text-decoration:none; }
</style>
</head>
<body>
  <div class="email-wrapper">
    <div class="email-header">
      <h1>' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '</h1>
      <p>Layanan Terpadu Lost &amp; Found Kampus</p>
    </div>
    <div class="email-body">
      ' . $contentHtml . '
    </div>
    <div class="email-footer">
      <p>Email otomatis ini dikirimkan oleh sistem ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . ' karena Anda telah menyetujui penerimaan notifikasi via email.</p>
      <p>Anda dapat mengatur preferensi atau menonaktifkan notifikasi ini kapan saja melalui menu <a href="' . htmlspecialchars($appUrl . '/profile', ENT_QUOTES, 'UTF-8') . '">Profil Akun</a>.</p>
      <p style="margin-top:8px;font-size:11px;color:#94a3b8;">&copy; ' . date('Y') . ' ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . '. Seluruh hak cipta dilindungi.</p>
    </div>
  </div>
</body>
</html>';
    }

    /**
     * Bersihkan pesan error agar tidak membocorkan password atau kredensial rahasia
     */
    private function sanitizeErrorMessage(string $error, array $config): string
    {
        $sanitized = $error;

        if (!empty($config['password'])) {
            $sanitized = str_replace($config['password'], '••••••••', $sanitized);
        }
        if (!empty($config['username'])) {
            $sanitized = str_replace($config['username'], '[SMTP_USER]', $sanitized);
        }

        // Hapus path sensitif jika ada
        $sanitized = preg_replace('/[a-zA-Z]:\\\\[^:\n]+/i', '[FILE_PATH]', $sanitized);

        return mb_substr($sanitized, 0, 250, 'UTF-8');
    }

    /**
     * Samarkan alamat email (misal: "b***i@m***.test")
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $user = $parts[0];
        $domain = $parts[1];

        $maskedUser = strlen($user) <= 2
            ? substr($user, 0, 1) . '***'
            : substr($user, 0, 1) . '***' . substr($user, -1);

        $maskedDomain = strlen($domain) <= 4
            ? $domain
            : substr($domain, 0, 1) . '***.' . pathinfo($domain, PATHINFO_EXTENSION);

        return $maskedUser . '@' . $maskedDomain;
    }
}
