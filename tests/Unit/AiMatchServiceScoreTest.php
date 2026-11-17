<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AiMatchService;
use App\Services\MockAiMatchService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AiMatchServiceScoreTest extends TestCase
{
    private MockAiMatchService $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockProvider = new MockAiMatchService();
    }

    public function testMockProviderIsAlwaysAvailable(): void
    {
        $this->assertTrue($this->mockProvider->isAvailable());
    }

    public function testMockProviderMatchingWithHighSimilarity(): void
    {
        $lost = [
            'id'                     => 1,
            'title'                  => 'Laptop Asus ROG Zephyrus G14 Hitam',
            'description'            => 'Laptop gaming Asus ROG warna hitam ada sticker anime di cover',
            'category_id'            => 3,
            'category_name'          => 'Elektronik',
            'last_seen_location_id'  => 5,
            'location_name'          => 'Perpustakaan Pusat',
            'lost_date'              => '2026-11-10 10:00:00',
        ];

        $found = [
            'id'                => 101,
            'title'             => 'Laptop Asus ROG Hitam Ditemukan',
            'description'       => 'Ditemukan laptop Asus ROG hitam ada sticker di bagian punggung',
            'category_id'       => 3,
            'category_name'     => 'Elektronik',
            'found_location_id' => 5,
            'location_name'     => 'Perpustakaan Pusat',
            'found_date'        => '2026-11-10 14:00:00',
        ];

        $res = $this->mockProvider->matchReports($lost, $found);

        $this->assertArrayHasKey('score', $res);
        $this->assertArrayHasKey('explanation', $res);
        $this->assertArrayHasKey('provider', $res);

        // Score should be high (identical category +50, location +30, date +20 => meta=100 => 40 pts, plus token overlap)
        $this->assertGreaterThanOrEqual(60.0, $res['score']);
        $this->assertLessThanOrEqual(100.0, $res['score']);
        $this->assertStringContainsString('Kategori item identik', $res['explanation']);
        $this->assertStringContainsString('Lokasi kejadian cocok', $res['explanation']);
    }

    public function testMockProviderMatchingWithDisparateReports(): void
    {
        $lost = [
            'id'                     => 2,
            'title'                  => 'Jaket Parka Uniqlo Abu-abu',
            'description'            => 'Jaket tebal abu-abu ukuran L',
            'category_id'            => 1,
            'category_name'          => 'Pakaian',
            'last_seen_location_id'  => 2,
            'lost_date'              => '2026-11-01',
        ];

        $found = [
            'id'                => 102,
            'title'             => 'Kunci Motor Honda Vario',
            'description'       => 'Gantungan kunci merah nomor seri 1234',
            'category_id'       => 4,
            'category_name'     => 'Kunci',
            'found_location_id' => 9,
            'found_date'        => '2026-11-16',
        ];

        $res = $this->mockProvider->matchReports($lost, $found);

        $this->assertLessThan(20.0, $res['score']);
    }

    public function testMockProviderEvaluateCandidatesBatch(): void
    {
        $target = [
            'id'            => 10,
            'title'         => 'Dompet Kulit Coklat Baellerry',
            'description'   => 'Dompet berisi KTM dan sedikit uang tunai',
            'category_id'   => 2,
            'category_name' => 'Dompet & Aksesoris',
        ];

        $candidates = [
            [
                'id'            => 201,
                'title'         => 'Dompet Baellerry Coklat',
                'description'   => 'Ditemukan dompet lipat kulit warna coklat',
                'category_id'   => 2,
                'category_name' => 'Dompet & Aksesoris',
            ],
            [
                'id'            => 202,
                'title'         => 'Payung Lipat Biru',
                'description'   => 'Payung basah tertinggal di lobby',
                'category_id'   => 5,
                'category_name' => 'Lain-lain',
            ],
        ];

        $eval = $this->mockProvider->evaluateCandidates($target, $candidates, 'lost');

        $this->assertSame('fallback_local', $eval['status']);
        $this->assertArrayHasKey('scores', $eval);
        $this->assertCount(2, $eval['scores']);
        $this->assertArrayHasKey(201, $eval['scores']);
        $this->assertArrayHasKey(202, $eval['scores']);

        // Candidate 201 (same category & keywords) must score significantly higher than 202
        $this->assertGreaterThan($eval['scores'][202]['score'], $eval['scores'][201]['score']);
    }

    public function testMockProviderSimulatedStatusSuccess(): void
    {
        $this->mockProvider->setSimulatedStatus('success');

        $target = ['id' => 1, 'title' => 'Tas'];
        $candidates = [['id' => 10, 'title' => 'Tas']];

        $res = $this->mockProvider->evaluateCandidates($target, $candidates);

        $this->assertSame('success', $res['status']);
        $this->assertSame(85.0, $res['scores'][10]['score']);
    }

    public function testMockProviderSimulatedStatusTimeout(): void
    {
        $this->mockProvider->setSimulatedStatus('timeout', 'Gateway timed out after 10s');

        $target = ['id' => 1, 'title' => 'Tas'];
        $candidates = [['id' => 10, 'title' => 'Tas']];

        $res = $this->mockProvider->evaluateCandidates($target, $candidates);

        $this->assertSame('timeout', $res['status']);
        $this->assertSame('Gateway timed out after 10s', $res['error']);
        $this->assertEmpty($res['scores']);
    }

    public function testAiMatchServiceCalculateBaselineScoreWithoutDatabase(): void
    {
        // Instantiation without constructor to ensure zero DB calls
        $reflector = new ReflectionClass(AiMatchService::class);
        /** @var AiMatchService $service */
        $service = $reflector->newInstanceWithoutConstructor();

        $lost = [
            'title'                 => 'Tumbler Corkcicle Putih 16oz',
            'description'           => 'Botol minum Corkcicle warna putih polos',
            'category_id'           => 6,
            'category_name'         => 'Perlengkapan Pribadi',
            'last_seen_location_id' => 3,
            'location_name'         => 'Gedung Kuliah Bersama',
            'lost_date'             => '2026-11-12 09:00:00',
        ];

        $found = [
            'title'             => 'Botol Minum Corkcicle Putih',
            'description'       => 'Ditemukan tumbler Corkcicle putih 16oz',
            'category_id'       => 6,
            'category_name'     => 'Perlengkapan Pribadi',
            'found_location_id' => 3,
            'location_name'     => 'Gedung Kuliah Bersama',
            'found_date'        => '2026-11-12 11:30:00',
        ];

        $baseline = $service->calculateBaselineScore($lost, $found);

        $this->assertArrayHasKey('score', $baseline);
        $this->assertArrayHasKey('text_sim', $baseline);
        $this->assertArrayHasKey('meta_score', $baseline);
        $this->assertArrayHasKey('explanation', $baseline);

        // Category (50) + Location (30) + Date range (20) = 100 metadata score
        $this->assertEqualsWithDelta(100.0, $baseline['meta_score'], 0.1);
        $this->assertGreaterThan(0.0, $baseline['text_sim']);
        $this->assertGreaterThan(50.0, $baseline['score']);
    }
}
