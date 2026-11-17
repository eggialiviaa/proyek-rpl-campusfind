<?php

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
\Dotenv\Dotenv::createImmutable(BASE_PATH)->load();

use Core\Database;
use App\Services\GoogleGeminiService;
use App\Services\NvidiaNimService;
use App\Services\AiProviderManager;
use App\Services\SettingService;

echo "======================================================================\n";
echo "VERIFICATION F.1: AI MATCHING SIDE-BY-SIDE (Gemini vs NVIDIA)\n";
echo "======================================================================\n\n";

$targetReport = [
    'id'            => 100,
    'title'         => 'Dompet Kulit Hitam Eiger',
    'category_name' => 'Aksesoris & Dompet',
    'location_name' => 'Perpustakaan Lantai 2',
    'lost_date'     => '2026-11-14',
    'description'   => 'Dompet kulit pria lipat hitam merk Eiger hilang tercecer di meja baca perpustakaan lantai 2.',
];

$candidates = [
    [
        'id'            => 101, // CLEARLY MATCHING
        'title'         => 'Dompet Pria Kulit Hitam Eiger',
        'category_name' => 'Aksesoris & Dompet',
        'location_name' => 'Perpustakaan Lantai 2',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan dompet kulit pria lipat warna hitam ada logo Eiger di meja baca perpustakaan lantai 2.',
    ],
    [
        'id'            => 102, // SAME-CATEGORY BUT DIFFERENT ITEM (Similar: same category & brand, but different color/location details)
        'title'         => 'Dompet Pria Kulit Cokelat Eiger',
        'category_name' => 'Aksesoris & Dompet',
        'location_name' => 'Perpustakaan Lantai 2',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan dompet kulit pria lipat warna cokelat tua merk Eiger di area rak buku perpustakaan lantai 2.',
    ],
    [
        'id'            => 103, // CLEARLY UNRELATED
        'title'         => 'Helm Bogo Hitam Doff',
        'category_name' => 'Kendaraan & Helm',
        'location_name' => 'Parkiran Sepeda Motor Barat',
        'found_date'    => '2026-11-14',
        'description'   => 'Ditemukan helm bogo warna hitam doff tergantung di spion sepeda motor parkiran barat.',
    ],
];

// 1. Run Google Gemini Matching
echo "--- 1. GOOGLE GEMINI MATCHING ---\n";
$gemini = new GoogleGeminiService();
$geminiRes = $gemini->evaluateCandidates($targetReport, $candidates, 'lost');

echo "Gemini Status: " . ($geminiRes['status'] ?? 'unknown') . "\n";
echo "Gemini Parse Status: " . ($geminiRes['parse_status'] ?? 'unknown') . "\n";
echo "Gemini Model: " . ($geminiRes['model'] ?? '-') . "\n";
echo "Gemini Duration: " . ($geminiRes['duration_ms'] ?? 0) . "ms\n";
echo "Gemini Raw Model Output (Redacted):\n" . ($geminiRes['raw_content'] ?? '') . "\n\n";
echo "Gemini Parsed Scores:\n";
print_r($geminiRes['scores'] ?? []);

$gemScoreMatch = (float)($geminiRes['scores'][101]['score'] ?? 0);
$gemScoreSimilar = (float)($geminiRes['scores'][102]['score'] ?? 0);
$gemScoreUnrelated = (float)($geminiRes['scores'][103]['score'] ?? 0);

echo "Gemini Score Hierarchy Check:\n";
echo " - Clearly Matching (ID 101): {$gemScoreMatch}\n";
echo " - Same-Category Different (ID 102): {$gemScoreSimilar}\n";
echo " - Clearly Unrelated (ID 103): {$gemScoreUnrelated}\n";

$geminiAssertPass = (
    ($geminiRes['parse_status'] ?? '') === 'parsed' &&
    ($geminiRes['status'] ?? '') === 'success' &&
    $gemScoreMatch > $gemScoreSimilar &&
    $gemScoreSimilar > $gemScoreUnrelated
);
echo "Gemini Assertion (parse_status === 'parsed' AND score(match) > score(similar) > score(unrelated)): "
    . ($geminiAssertPass ? "PASSED [OK]" : "FAILED [FAIL]") . "\n\n";


// 2. Run NVIDIA NIM Matching
echo "--- 2. NVIDIA NIM MATCHING ---\n";
$nvidia = new NvidiaNimService();
$nvidiaRes = $nvidia->evaluateCandidates($targetReport, $candidates, 'lost');

echo "NVIDIA Status: " . ($nvidiaRes['status'] ?? 'unknown') . "\n";
echo "NVIDIA Parse Status: " . ($nvidiaRes['parse_status'] ?? 'unknown') . "\n";
echo "NVIDIA Model: " . ($nvidiaRes['model'] ?? '-') . "\n";
echo "NVIDIA Duration: " . ($nvidiaRes['duration_ms'] ?? 0) . "ms\n";
echo "NVIDIA Raw Model Output (Redacted):\n" . ($nvidiaRes['raw_content'] ?? '') . "\n\n";
echo "NVIDIA Parsed Scores:\n";
print_r($nvidiaRes['scores'] ?? []);

$nvScoreMatch = (float)($nvidiaRes['scores'][101]['score'] ?? 0);
$nvScoreSimilar = (float)($nvidiaRes['scores'][102]['score'] ?? 0);
$nvScoreUnrelated = (float)($nvidiaRes['scores'][103]['score'] ?? 0);

echo "NVIDIA Score Hierarchy Check:\n";
echo " - Clearly Matching (ID 101): {$nvScoreMatch}\n";
echo " - Same-Category Different (ID 102): {$nvScoreSimilar}\n";
echo " - Clearly Unrelated (ID 103): {$nvScoreUnrelated}\n";

$nvidiaAssertPass = (
    ($nvidiaRes['parse_status'] ?? '') === 'parsed' &&
    ($nvidiaRes['status'] ?? '') === 'success' &&
    $nvScoreMatch > $nvScoreSimilar &&
    $nvScoreSimilar > $nvScoreUnrelated
);
echo "NVIDIA Assertion (parse_status === 'parsed' AND score(match) > score(similar) > score(unrelated)): "
    . ($nvidiaAssertPass ? "PASSED [OK]" : "FAILED [FAIL]") . "\n\n";


// 3. Side-by-Side Comparison Table
echo "======================================================================\n";
echo "SIDE-BY-SIDE SUMMARY TABLE\n";
echo "======================================================================\n";
printf("%-15s | %-30s | %-30s\n", "Candidate", "Google Gemini", "NVIDIA NIM");
echo str_repeat("-", 80) . "\n";
printf("%-15s | Score: %-5.1f (Status: %s)  | Score: %-5.1f (Status: %s)\n", "#101 (Match)", $gemScoreMatch, $geminiRes['parse_status'] ?? '-', $nvScoreMatch, $nvidiaRes['parse_status'] ?? '-');
printf("%-15s | Score: %-5.1f (Status: %s)  | Score: %-5.1f (Status: %s)\n", "#102 (Similar)", $gemScoreSimilar, $geminiRes['parse_status'] ?? '-', $nvScoreSimilar, $nvidiaRes['parse_status'] ?? '-');
printf("%-15s | Score: %-5.1f (Status: %s)  | Score: %-5.1f (Status: %s)\n", "#103 (Unrelated)", $gemScoreUnrelated, $geminiRes['parse_status'] ?? '-', $nvScoreUnrelated, $nvidiaRes['parse_status'] ?? '-');
echo str_repeat("-", 80) . "\n";

if ($geminiAssertPass && $nvidiaAssertPass) {
    echo "VERIFICATION F.1 RESULT: 100% PASSED\n";
    exit(0);
} else {
    echo "VERIFICATION F.1 RESULT: FAILED\n";
    exit(1);
}
