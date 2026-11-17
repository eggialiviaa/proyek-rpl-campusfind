<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CFind\DataMasker;
use PHPUnit\Framework\TestCase;

class DataMaskerTest extends TestCase
{
    public function testMaskNameWithUserId(): void
    {
        $result = DataMasker::maskName('Ahmad Dahlan', 42);
        $this->assertSame('User #42', $result);
    }

    public function testMaskNameEmpty(): void
    {
        $this->assertSame('Pengguna Kampus', DataMasker::maskName(null));
        $this->assertSame('Pengguna Kampus', DataMasker::maskName(''));
    }

    public function testMaskNameShortFirstWord(): void
    {
        $result = DataMasker::maskName('Al Pratama');
        $this->assertSame('Al***', $result);
    }

    public function testMaskNameStandard(): void
    {
        $result = DataMasker::maskName('Budi Santoso');
        $this->assertSame('Bu**', $result);
    }

    public function testMaskEmailEmptyOrInvalid(): void
    {
        $this->assertSame('***@***.***', DataMasker::maskEmail(null));
        $this->assertSame('***@***.***', DataMasker::maskEmail(''));
        $this->assertSame('***@***.***', DataMasker::maskEmail('invalid-email-string'));
    }

    public function testMaskEmailShortLocal(): void
    {
        $result = DataMasker::maskEmail('jo@kampus.ac.id');
        $this->assertSame('jo***@kampus.ac.id', $result);
    }

    public function testMaskEmailStandard(): void
    {
        $result = DataMasker::maskEmail('mahasiswa123@univ.ac.id');
        $this->assertStringStartsWith('ma', $result);
        $this->assertStringEndsWith('@univ.ac.id', $result);
        $this->assertStringContainsString('*', $result);
    }

    public function testSanitizeRowRemovesDisallowedFields(): void
    {
        $rawRow = [
            'id' => 10,
            'title' => 'Tas Hitam',
            'password' => 'secret123',
            'password_hash' => '$2y$10$...',
            'api_key' => 'nvapi-xyz',
            'secret' => 'supersecret',
            'token' => 'token-abc',
            'phone' => '08123456789',
            'identity_number' => '1234567890',
            'ownership_proof_description' => 'Ada struk pembelian di kantong',
            'claim_reason' => 'Punya saya yang tertinggal',
            'unique_features' => 'Ada stiker Batman',
            'status' => 'published',
        ];

        $cleaned = DataMasker::sanitizeRow($rawRow);

        $this->assertArrayHasKey('id', $cleaned);
        $this->assertArrayHasKey('title', $cleaned);
        $this->assertArrayHasKey('status', $cleaned);
        $this->assertArrayNotHasKey('password', $cleaned);
        $this->assertArrayNotHasKey('password_hash', $cleaned);
        $this->assertArrayNotHasKey('api_key', $cleaned);
        $this->assertArrayNotHasKey('secret', $cleaned);
        $this->assertArrayNotHasKey('token', $cleaned);
        $this->assertArrayNotHasKey('phone', $cleaned);
        $this->assertArrayNotHasKey('identity_number', $cleaned);
        $this->assertArrayNotHasKey('ownership_proof_description', $cleaned);
        $this->assertArrayNotHasKey('claim_reason', $cleaned);
        $this->assertArrayNotHasKey('unique_features', $cleaned);
    }

    public function testSanitizeRowCaseInsensitiveAndExtraDisallowed(): void
    {
        $rawRow = [
            'PASSWORD' => 'secret',
            'SECRET' => 'hidden',
            'custom_field' => 'should_be_removed',
            'valid_key' => 'kept',
        ];

        $cleaned = DataMasker::sanitizeRow($rawRow, ['custom_field']);

        $this->assertArrayNotHasKey('PASSWORD', $cleaned);
        $this->assertArrayNotHasKey('SECRET', $cleaned);
        $this->assertArrayNotHasKey('custom_field', $cleaned);
        $this->assertArrayHasKey('valid_key', $cleaned);
    }

    public function testSanitizeRowTruncatesLongStrings(): void
    {
        $longString = str_repeat('A', 300);
        $row = ['description' => $longString];

        $cleaned = DataMasker::sanitizeRow($row);

        $this->assertLessThanOrEqual(200, mb_strlen($cleaned['description']));
        $this->assertStringEndsWith('...', $cleaned['description']);
    }

    public function testWrapUntrustedSanitizesTagsAndControlChars(): void
    {
        $input = "<b>Barang</b> hilang\r\n\r\n\r\n\r\nPerlu bantuan!";
        $wrapped = DataMasker::wrapUntrusted('Deskripsi', $input);

        $this->assertStringNotContainsString('<b>', $wrapped);
        $this->assertStringNotContainsString('</b>', $wrapped);
        $this->assertStringContainsString('DATA_PENGGUNA: "Barang hilang', $wrapped);
        $this->assertStringStartsWith('Deskripsi: [DATA_PENGGUNA:', $wrapped);
    }
}
