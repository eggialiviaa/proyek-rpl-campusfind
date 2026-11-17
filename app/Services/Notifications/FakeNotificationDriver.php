<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use Exception;

/**
 * FakeNotificationDriver — Driver Simulasi Pengujian Notifikasi (Prompt 20)
 *
 * Digunakan dalam automated test suite untuk memvalidasi:
 * - Sukses kirim
 * - Gagal kirim & pemicuan retry backoff
 * - Simulasi circuit breaker
 * - Pengecekan isi pesan dan payload tanpa memanggil SMTP server nyata
 */
class FakeNotificationDriver implements NotificationChannelInterface
{
    private string $name = 'email';
    private bool $shouldFail = false;
    private string $failureMessage = 'Simulated SMTP connection failure';
    private array $sentItems = [];
    private ?array $allowlist = null;

    public function __construct(string $name = 'email')
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setShouldFail(bool $fail, string $msg = 'Simulated SMTP connection failure'): self
    {
        $this->shouldFail = $fail;
        $this->failureMessage = $msg;
        return $this;
    }

    public function setAllowlist(?array $list): self
    {
        $this->allowlist = $list;
        return $this;
    }

    public function getSentItems(): array
    {
        return $this->sentItems;
    }

    public function clearSentItems(): void
    {
        $this->sentItems = [];
    }

    public function reset(): void
    {
        $this->sentItems = [];
        $this->shouldFail = false;
        $this->allowlist = null;
    }

    public function clear(): void
    {
        $this->reset();
    }

    public function isAllowedRecipient(string $email, ?int $userId = null): bool
    {
        if (MailGateway::isReservedOrExampleDomain($email)) {
            return false;
        }

        $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';
        if (strtolower($appEnv) === 'production') {
            return true;
        }

        if ($this->allowlist !== null) {
            $allowedEmails = $this->allowlist;
            $target = strtolower(trim($email));
            foreach ($allowedEmails as $pattern) {
                $pattern = strtolower($pattern);
                if (str_contains($pattern, '*')) {
                    continue;
                }
                if ($pattern === $target) {
                    return true;
                }
            }
            return false;
        }

        // Prompt 34C: Evaluasi terpusat kelayakan penerima lewat MailGateway
        $eligibility = MailGateway::evaluateRecipientEligibility($email, $userId);
        return $eligibility['is_ready'];
    }

    public function send(array $outboxItem): array
    {
        $recipient = trim((string) ($outboxItem['recipient'] ?? ''));
        $userId = !empty($outboxItem['user_id']) ? (int)$outboxItem['user_id'] : null;

        // Cek validitas email
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error'   => 'Format email penerima tidak valid: ' . $recipient,
                'blocked' => false,
            ];
        }

        // Cek kelayakan penerima (Prompt 34C)
        if (!$this->isAllowedRecipient($recipient, $userId)) {
            return [
                'success' => false,
                'error'   => "[ALLOWLIST_BLOCKED] Alamat email {$recipient} belum memenuhi ketentuan penerimaan notifikasi.",
                'blocked' => true,
            ];
        }

        if ($this->shouldFail) {
            return [
                'success' => false,
                'error'   => $this->failureMessage,
                'blocked' => false,
            ];
        }

        // Pengaman Pengiriman: Validasi apakah ada unresolved placeholders
        $templateKey = (string) ($outboxItem['template_key'] ?? '');
        if (!empty($templateKey)) {
            $rawPayload = $outboxItem['payload'] ?? [];
            $payload = is_string($rawPayload) ? (json_decode($rawPayload, true) ?: []) : (array) $rawPayload;
            $template = $this->getTemplate($templateKey);
            $rendered = NotificationTemplateService::render($templateKey, $template, $payload, $outboxItem);
            if (!empty($rendered['has_unresolved'])) {
                $unresolvedList = implode(', ', $rendered['unresolved_placeholders']);
                return [
                    'success' => false,
                    'error'   => "placeholder belum terganti: {$unresolvedList}",
                    'blocked' => false,
                ];
            }
        }

        $messageId = '<fake-' . bin2hex(random_bytes(8)) . '@campusfind.test>';
        $this->sentItems[] = [
            'item'       => $outboxItem,
            'message_id' => $messageId,
            'sent_at'    => date('Y-m-d H:i:s'),
        ];

        return [
            'success'    => true,
            'message_id' => $messageId,
        ];
    }

    public function testConnection(?array $configOverride = null): array
    {
        if ($this->shouldFail) {
            return [
                'success' => false,
                'message' => 'Simulasi gagal koneksi: ' . $this->failureMessage,
            ];
        }

        return [
            'success' => true,
            'message' => 'Simulasi koneksi SMTP berhasil',
            'details' => ['driver' => 'fake'],
        ];
    }

    private function getTemplate(string $templateKey): array
    {
        try {
            $pdo = \Core\Database::getInstance()->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM notification_templates WHERE template_key = ? LIMIT 1");
            $stmt->execute([$templateKey]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (\Throwable) {
        }

        return [
            'subject'   => '[Campus Find] Notifikasi',
            'body_html' => '<p>{{title}}</p><p>{{message}}</p>',
            'body_text' => "{{title}}\n{{message}}",
        ];
    }
}
