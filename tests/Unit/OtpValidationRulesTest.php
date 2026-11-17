<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OtpService;
use PHPUnit\Framework\TestCase;

class OtpValidationRulesTest extends TestCase
{
    public function testOtpConstants(): void
    {
        $this->assertSame(60, OtpService::COOLDOWN_SECONDS);
        $this->assertSame(300, OtpService::EXPIRATION_SECONDS);
        $this->assertSame(5, OtpService::MAX_ATTEMPTS);
    }

    public function testPasswordTooShortFailsValidation(): void
    {
        $res = OtpService::validatePasswordStrength('Ab1!');
        $this->assertFalse($res['valid']);
        $this->assertContains('Kata sandi minimal harus 8 karakter.', $res['errors']);
    }

    public function testPasswordMissingUppercaseFails(): void
    {
        $res = OtpService::validatePasswordStrength('lowercase123!');
        $this->assertFalse($res['valid']);
        $this->assertContains('Kata sandi harus memuat setidaknya 1 huruf besar (A-Z).', $res['errors']);
    }

    public function testPasswordMissingLowercaseFails(): void
    {
        $res = OtpService::validatePasswordStrength('UPPERCASE123!');
        $this->assertFalse($res['valid']);
        $this->assertContains('Kata sandi harus memuat setidaknya 1 huruf kecil (a-z).', $res['errors']);
    }

    public function testPasswordMissingDigitOrSymbolFails(): void
    {
        $res = OtpService::validatePasswordStrength('OnlyLettersNoDigit');
        $this->assertFalse($res['valid']);
        $this->assertContains('Kata sandi harus memuat setidaknya 1 angka (0-9) atau simbol.', $res['errors']);
    }

    public function testStrongPasswordPassesValidation(): void
    {
        $res = OtpService::validatePasswordStrength('CampusFind2026!#');
        $this->assertTrue($res['valid']);
        $this->assertEmpty($res['errors']);
        $this->assertSame('Kata sandi memenuhi standar kekuatan keamanan.', $res['message']);
    }

    public function testGenerateTemporaryPasswordMeetsComplexity(): void
    {
        $tempPassword = OtpService::generateTemporaryPassword(14);

        $this->assertGreaterThanOrEqual(14, strlen($tempPassword));

        $val = OtpService::validatePasswordStrength($tempPassword);
        $this->assertTrue($val['valid'], 'Generated password should satisfy strength criteria');

        $this->assertMatchesRegularExpression('/[A-Z]/', $tempPassword);
        $this->assertMatchesRegularExpression('/[a-z]/', $tempPassword);
        $this->assertMatchesRegularExpression('/[0-9]/', $tempPassword);
        $this->assertMatchesRegularExpression('/[!@#$%&*]/', $tempPassword);
    }

    public function testOtpHashVerificationRule(): void
    {
        $plainOtp = '654321';
        $salt = bin2hex(random_bytes(16));
        $correctHash = hash_hmac('sha256', $plainOtp, $salt);

        // Verification with matching code
        $computedCorrect = hash_hmac('sha256', $plainOtp, $salt);
        $this->assertTrue(hash_equals($correctHash, $computedCorrect));

        // Verification with incorrect code
        $wrongInput = '123456';
        $computedWrong = hash_hmac('sha256', $wrongInput, $salt);
        $this->assertFalse(hash_equals($correctHash, $computedWrong));

        // Verification with wrong salt
        $differentSalt = bin2hex(random_bytes(16));
        $computedDifferentSalt = hash_hmac('sha256', $plainOtp, $differentSalt);
        $this->assertFalse(hash_equals($correctHash, $computedDifferentSalt));
    }

    public function testOtpMaskingFunctions(): void
    {
        $this->assertSame('-', OtpService::maskEmail(''));
        $this->assertSame('-', OtpService::maskEmail(null));
        $this->assertSame('***', OtpService::maskEmail('invalid-format'));

        $maskedEmail = OtpService::maskEmail('budi.santoso@univ.ac.id');
        $this->assertStringStartsWith('bu', $maskedEmail);
        $this->assertStringEndsWith('@univ.ac.id', $maskedEmail);
        $this->assertStringContainsString('*', $maskedEmail);

        $this->assertSame('-', OtpService::maskIdentity(''));
        $this->assertSame('1***', OtpService::maskIdentity('1234'));
        $this->assertSame('21******90', OtpService::maskIdentity('2100018190'));

        $this->assertSame('-', OtpService::maskPhone(''));
        $this->assertSame('***', OtpService::maskPhone('12345'));
        $this->assertSame('081****789', OtpService::maskPhone('08123456789'));
    }

    public function testPhoneNormalizationToIndonesiaStandard(): void
    {
        $this->assertSame('', OtpService::normalizePhone(''));
        $this->assertSame('', OtpService::normalizePhone(null));
        $this->assertSame('628123456789', OtpService::normalizePhone('08123456789'));
        $this->assertSame('628567890123', OtpService::normalizePhone('8567890123'));
        $this->assertSame('628123456789', OtpService::normalizePhone('+62 812-3456-789'));
    }
}
