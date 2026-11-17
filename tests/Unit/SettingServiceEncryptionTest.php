<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SettingService;
use PHPUnit\Framework\TestCase;

class SettingServiceEncryptionTest extends TestCase
{
    private ?string $originalKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalKey = $_ENV['APP_KEY'] ?? null;
        $_ENV['APP_KEY'] = 'unit_test_secret_key_32_characters_12345';
    }

    protected function tearDown(): void
    {
        if ($this->originalKey !== null) {
            $_ENV['APP_KEY'] = $this->originalKey;
        }
        parent::tearDown();
    }

    public function testEncryptAndDecryptRoundtrip(): void
    {
        $plainText = 'nvapi-secret-api-key-test-value-12345';
        $encrypted = SettingService::encrypt($plainText);

        $this->assertNotEmpty($encrypted);
        $this->assertNotSame($plainText, $encrypted);

        $decrypted = SettingService::decrypt($encrypted);
        $this->assertSame($plainText, $decrypted);
    }

    public function testEncryptProducesDifferentCiphertextsDueToRandomIv(): void
    {
        $plainText = 'smtp-password-secure-campusfind';
        $encrypted1 = SettingService::encrypt($plainText);
        $encrypted2 = SettingService::encrypt($plainText);

        $this->assertNotSame($encrypted1, $encrypted2);
        $this->assertSame($plainText, SettingService::decrypt($encrypted1));
        $this->assertSame($plainText, SettingService::decrypt($encrypted2));
    }

    public function testDecryptReturnsNullForInvalidBase64(): void
    {
        $invalidBase64 = '!!!ThisIsNotBase64!!!';
        $decrypted = SettingService::decrypt($invalidBase64);
        $this->assertNull($decrypted);
    }

    public function testDecryptReturnsNullForTruncatedPayload(): void
    {
        // Payload shorter than 17 bytes decoded (16 bytes IV + at least 1 byte ciphertext)
        $short = base64_encode('short_iv');
        $decrypted = SettingService::decrypt($short);
        $this->assertNull($decrypted);
    }

    public function testDecryptReturnsNullForCorruptedCiphertext(): void
    {
        $plainText = 'critical_credential_value';
        $encrypted = SettingService::encrypt($plainText);
        $raw = base64_decode($encrypted);

        // Corrupt the ciphertext portion (bytes after first 16 bytes IV)
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 0xFF);
        $corrupted = base64_encode($raw);

        $decrypted = SettingService::decrypt($corrupted);
        $this->assertNull($decrypted);
    }

    public function testDecryptFailsWithDifferentAppKey(): void
    {
        $plainText = 'gemini-api-key-testing-98765';
        $encrypted = SettingService::encrypt($plainText);

        // Change APP_KEY to simulate key rotation or mismatch
        $_ENV['APP_KEY'] = 'different_app_key_32_characters_67890';

        $decrypted = SettingService::decrypt($encrypted);
        $this->assertNull($decrypted);
    }
}
