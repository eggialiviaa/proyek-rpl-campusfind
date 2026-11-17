<?php

declare(strict_types=1);

/**
 * database/seed_demo.php — Seeder Data Demo Ekstensif (Local/Development Environment ONLY)
 *
 * Persyaratan:
 * 1. Hanya berjalan saat APP_ENV=local atau development.
 * 2. Tanpa password terbuka di dalam file (mengambil dari DEMO_*_PASSWORD di .env atau acak aman).
 * 3. Idempotent: Aman dijalankan berulang kali tanpa error duplikasi.
 * 4. JANGAN menghapus atau menimpa akun admin yang sudah ada.
 * 5. Seluruh data fiktif menggunakan nama persis dari spesifikasi.
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;

// 1. Muat Environment
if (file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

$appEnv = strtolower(trim((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ''))));

// 2. Proteksi Ketat Lingkungan
if ($appEnv !== 'local' && $appEnv !== 'development') {
    echo "\n⛔ [ACCESS DENIED] Seeder data demo DITOLAK!\n";
    echo "Environment terdeteksi: '{$appEnv}'. Seeder ini HANYA diizinkan berjalan saat APP_ENV=local atau development.\n";
    exit(1);
}

// 3. Database Connection
$host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port   = (int) ($_ENV['DB_PORT'] ?? 3306);
$dbname = $_ENV['DB_NAME'] ?? 'campus_find';
$user   = $_ENV['DB_USER'] ?? 'root';
$pass   = $_ENV['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    echo "✗ Gagal koneksi database `{$dbname}`: " . $e->getMessage() . "\n";
    exit(1);
}

echo "========================================================================\n";
echo "   Campus Find — Seeder Data Demo Ekstensif ({$appEnv})   \n";
echo "========================================================================\n\n";

$pdo->beginTransaction();

try {
    // -------------------------------------------------------------
    // 4. Seeding Akun Pengguna (2 Petugas + 15 Civitas)
    // -------------------------------------------------------------
    $demoPetugasPass = trim((string)($_ENV['DEMO_PETUGAS_PASSWORD'] ?? getenv('DEMO_PETUGAS_PASSWORD') ?: ''));
    $demoCivitasPass = trim((string)($_ENV['DEMO_CIVITAS_PASSWORD'] ?? getenv('DEMO_CIVITAS_PASSWORD') ?: ''));

    $petugasHash = $demoPetugasPass !== '' 
        ? password_hash($demoPetugasPass, PASSWORD_DEFAULT) 
        : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $civitasHash = $demoCivitasPass !== '' 
        ? password_hash($demoCivitasPass, PASSWORD_DEFAULT) 
        : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $rosterPetugas = [
        ['name' => 'Raffi Maulana Hakim',     'email' => 'raffimaulanahakim@petugas.campusfind.test', 'id_num' => '19950801', 'role_id' => 2, 'phone' => '081298765431'],
        ['name' => 'Nadira Kusumawardhani',   'email' => 'nadirakusumawardhani@petugas.campusfind.test', 'id_num' => '19960415', 'role_id' => 2, 'phone' => '081298765432'],
    ];

    $rosterCivitas = [
        ['name' => 'Bagas Prakoso',          'email' => 'bagasprakoso@mahasiswa.campusfind.test',       'id_num' => '21081001', 'role_id' => 3, 'prog' => 'Teknik Informatika',     'phone' => '082110001001'],
        ['name' => 'Cut Zahira Alfarisi',    'email' => 'cutzahiraalfarisi@mahasiswa.campusfind.test',  'id_num' => '21081002', 'role_id' => 3, 'prog' => 'Sistem Informasi',       'phone' => '082110001002'],
        ['name' => 'Dimas Aryaguna',         'email' => 'dimasaryaguna@mahasiswa.campusfind.test',       'id_num' => '21081003', 'role_id' => 3, 'prog' => 'Teknik Elektro',         'phone' => '082110001003'],
        ['name' => 'Elvina Tampubolon',      'email' => 'elvinatampubolon@mahasiswa.campusfind.test',    'id_num' => '21081004', 'role_id' => 3, 'prog' => 'Manajemen Bisnis',       'phone' => '082110001004'],
        ['name' => 'Farrel Nugraha',         'email' => 'farrelnugraha@mahasiswa.campusfind.test',       'id_num' => '21081005', 'role_id' => 3, 'prog' => 'Ilmu Komunikasi',        'phone' => '082110001005'],
        ['name' => 'Gita Anindya Pratiwi',   'email' => 'gitaanindyapratiwi@mahasiswa.campusfind.test', 'id_num' => '21081006', 'role_id' => 3, 'prog' => 'Desain Komunikasi Visual','phone' => '082110001006'],
        ['name' => 'Hendra Simanjuntak',     'email' => 'hendrasimanjuntak@mahasiswa.campusfind.test',   'id_num' => '21081007', 'role_id' => 3, 'prog' => 'Teknik Sipil',           'phone' => '082110001007'],
        ['name' => 'Ilham Dzakwan',          'email' => 'ilhamdzakwan@mahasiswa.campusfind.test',        'id_num' => '21081008', 'role_id' => 3, 'prog' => 'Arsitektur',             'phone' => '082110001008'],
        ['name' => 'Jessica Lumintang',      'email' => 'jessicalumintang@mahasiswa.campusfind.test',    'id_num' => '21081009', 'role_id' => 3, 'prog' => 'Akuntansi',              'phone' => '082110001009'],
        ['name' => 'Lintang Ayuningtyas',    'email' => 'lintangayuningtyas@mahasiswa.campusfind.test',  'id_num' => '21081010', 'role_id' => 3, 'prog' => 'Psikologi',              'phone' => '082110001010'],
        ['name' => 'Muhammad Rizqullah',     'email' => 'muhammadrizqullah@mahasiswa.campusfind.test',   'id_num' => '21081011', 'role_id' => 3, 'prog' => 'Hubungan Internasional', 'phone' => '082110001011'],
        ['name' => 'Nabila Azzahra',         'email' => 'nabilaazzahra@mahasiswa.campusfind.test',       'id_num' => '21081012', 'role_id' => 3, 'prog' => 'Farmasi',                'phone' => '082110001012'],
        ['name' => 'Oktavianus Wenda',       'email' => 'oktavianuswenda@mahasiswa.campusfind.test',     'id_num' => '21081013', 'role_id' => 3, 'prog' => 'Hukum',                  'phone' => '082110001013'],
        ['name' => 'Putu Gede Ananta',       'email' => 'putugedeananta@mahasiswa.campusfind.test',     'id_num' => '21081014', 'role_id' => 3, 'prog' => 'Kedokteran Gigi',        'phone' => '082110001014'],
        ['name' => 'Qonita Ramadhani',       'email' => 'qonitaramadhani@mahasiswa.campusfind.test',     'id_num' => '21081015', 'role_id' => 3, 'prog' => 'Teknik Lingkungan',      'phone' => '082110001015'],
    ];

    $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $insertUserStmt = $pdo->prepare("
        INSERT INTO users (role_id, name, email, password_hash, identity_number, phone, study_program, batch_year, is_active, email_verified_at, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 2023, 1, NOW(), NOW(), NOW())
    ");

    $userMap = [];

    // Daftarkan Petugas
    foreach ($rosterPetugas as $p) {
        $checkUserStmt->execute([$p['email']]);
        $uid = $checkUserStmt->fetchColumn();
        if (!$uid) {
            $insertUserStmt->execute([$p['role_id'], $p['name'], $p['email'], $petugasHash, $p['id_num'], $p['phone'], 'L&F Center Staff']);
            $uid = $pdo->lastInsertId();
        }
        $userMap[$p['name']] = (int)$uid;
    }

    // Daftarkan Civitas
    foreach ($rosterCivitas as $c) {
        $checkUserStmt->execute([$c['email']]);
        $uid = $checkUserStmt->fetchColumn();
        if (!$uid) {
            $insertUserStmt->execute([$c['role_id'], $c['name'], $c['email'], $civitasHash, $c['id_num'], $c['phone'], $c['prog']]);
            $uid = $pdo->lastInsertId();
        }
        $userMap[$c['name']] = (int)$uid;
    }

    echo "✓ 2 Petugas & 15 Civitas terverifikasi aktif di basis data.\n";

    // -------------------------------------------------------------
    // 5. Seeding 10 Item Inventaris Fisik (Zona A–E)
    // -------------------------------------------------------------
    $demoItems = [
        [
            'code'       => 'DEMO-ITM-01',
            'cat_id'     => 1, // Elektronik & Gadget
            'storage_id' => 1, // Rak Elektronik A-01 (Zona A)
            'title'      => 'iPad Mini 6 64GB Space Gray',
            'desc'       => 'Tablet Apple iPad Mini generasi ke-6 warna Space Gray dengan casing magnetic hitam polos. Layar mulus tanpa retak.',
            'notes'      => 'Tersimpan di brankas kecil laci A-01. Baterai kondisi 45%.',
            'condition'  => 'sangat_baik',
            'status'     => 'stored',
        ],
        [
            'code'       => 'DEMO-ITM-02',
            'cat_id'     => 2, // Dokumen & Kartu
            'storage_id' => 2, // Lemari Dokumen B-01 (Zona B)
            'title'      => 'Kartu ATM BCA Platinum & SIM A atas nama Muhammad Rizqullah',
            'desc'       => 'Dua kartu identitas dan transaksi: ATM BCA berchip dan SIM A masa berlaku 2028 dalam plastik pelindung bening.',
            'notes'      => 'Ditemukan di dekat mesin ATM center rektorat.',
            'condition'  => 'baik',
            'status'     => 'in_claim',
        ],
        [
            'code'       => 'DEMO-ITM-03',
            'cat_id'     => 3, // Dompet & Tas
            'storage_id' => 3, // Lemari Tas & Dompet C-01 (Zona C)
            'title'      => 'Dompet Kulit Baellerry Pria Warna Hitam (Item Pair 1)',
            'desc'       => 'Dompet lipat kulit warna hitam merek Baellerry dengan logo emboss perak di sudut luar. Ada saku ritsleting uang koin.',
            'notes'      => 'Verifikasi fisik: isi uang tunai dan kartu identitas telah dicocokkan dengan laporan posko.',
            'condition'  => 'baik',
            'status'     => 'in_claim',
        ],
        [
            'code'       => 'DEMO-ITM-04',
            'cat_id'     => 4, // Kunci & Aksesoris
            'storage_id' => 4, // Kotak Kunci D-01 (Zona D)
            'title'      => 'Kunci Motor Honda Vario 160 & Remote Smartkey (Item Pair 2)',
            'desc'       => 'Anak kunci motor Honda beserta remote smartkey hitam dan gantungan pita kain merah bertuliskan One Heart.',
            'notes'      => 'Gantungan kunci memiliki nomor seri cetak remote 89412.',
            'condition'  => 'sangat_baik',
            'status'     => 'in_claim',
        ],
        [
            'code'       => 'DEMO-ITM-05',
            'cat_id'     => 7, // Lain-lain
            'storage_id' => 5, // Gudang Umum E-01 (Zona E)
            'title'      => 'Tumbler Minum Corkcicle Canteen 16oz Putih Gloss (Item Pair 3)',
            'desc'       => 'Botol minum insulasi stainless steel merk Corkcicle Canteen ukuran 16oz warna putih dengan tutup putar perak.',
            'notes'      => 'Ada sedikit goresan gesekan halus di sisi bawah bodi.',
            'condition'  => 'baik',
            'status'     => 'stored',
        ],
        [
            'code'       => 'DEMO-ITM-06',
            'cat_id'     => 1, // Elektronik & Gadget
            'storage_id' => 1, // Rak Elektronik A-01 (Zona A)
            'title'      => 'TWS Samsung Galaxy Buds 2 Pro Bora Purple (Item Pair 4)',
            'desc'       => 'Sepasang earphone nirkabel Samsung Galaxy Buds 2 Pro dalam charging case ungu muda berlapis case silikon transparan.',
            'notes'      => 'Serial number case R52N601XXXX. Berfungsi normal saat diuji.',
            'condition'  => 'sangat_baik',
            'status'     => 'stored',
        ],
        [
            'code'       => 'DEMO-ITM-07',
            'cat_id'     => 3, // Dompet & Tas
            'storage_id' => 3, // Lemari Tas & Dompet C-01 (Zona C)
            'title'      => 'Tas Ransel Laptop Eiger Diario 25L Abu-abu',
            'desc'       => 'Backpack harian Eiger warna abu-abu kombinasi hitam, kompartemen laptop 14 inci, resleting depan ganda.',
            'notes'      => 'Di saku samping terdapat payung lipat hitam kecil.',
            'condition'  => 'baik',
            'status'     => 'stored',
        ],
        [
            'code'       => 'DEMO-ITM-08',
            'cat_id'     => 4, // Kunci & Aksesoris
            'storage_id' => 4, // Kotak Kunci D-01 (Zona D)
            'title'      => 'Kunci Mobil Toyota Avanza dengan Remote Alarm',
            'desc'       => 'Kunci kontak mobil Toyota hitam dengan remote integrated 2 tombol (Lock/Unlock) dan gantungan plat nomor B 1420.',
            'notes'      => 'Baterai remote aktif, lampu indikator merah menyala saat ditekan.',
            'condition'  => 'baik',
            'status'     => 'stored',
        ],
        [
            'code'       => 'DEMO-ITM-09',
            'cat_id'     => 6, // Buku & Alat Tulis
            'storage_id' => 2, // Lemari Dokumen B-01 (Zona B)
            'title'      => 'Binder B5 Kokuyo Campus Cover Mika Transparan (Item Pair 5)',
            'desc'       => 'Binder spiral ring 26 lubang merk Kokuyo Campus B5 isi kertas bergaris catatan kalkulus dan stiker indeks warna-warni.',
            'notes'      => 'Telah diverifikasi dan diserahkan ke pemilik sah (Jessica Lumintang).',
            'condition'  => 'baik',
            'status'     => 'returned',
        ],
        [
            'code'       => 'DEMO-ITM-010',
            'cat_id'     => 5, // Pakaian & Sepatu
            'storage_id' => 5, // Gudang Umum E-01 (Zona E)
            'title'      => 'Jaket Hoodie Zipper Uniqlo Abu-abu Muda (Size L)',
            'desc'       => 'Jaket hoodie bertopi dengan resleting depan bahan katun fleece Uniqlo warna misty grey ukuran L.',
            'notes'      => 'Dicuci bersih oleh petugas sebelum disimpan di rak pakaian.',
            'condition'  => 'sangat_baik',
            'status'     => 'stored',
        ],
    ];

    $checkItemStmt = $pdo->prepare("SELECT id FROM items WHERE item_code = ? LIMIT 1");
    $insertItemStmt = $pdo->prepare("
        INSERT INTO items (item_code, category_id, storage_location_id, verified_by_user_id, title, description, internal_notes, physical_condition, status, registered_at, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
    ");

    $itemIdMap = [];
    foreach ($demoItems as $di) {
        $checkItemStmt->execute([$di['code']]);
        $itemId = $checkItemStmt->fetchColumn();
        if (!$itemId) {
            $insertItemStmt->execute([
                $di['code'],
                $di['cat_id'],
                $di['storage_id'],
                $userMap['Raffi Maulana Hakim'],
                $di['title'],
                $di['desc'],
                $di['notes'],
                $di['condition'],
                $di['status'],
            ]);
            $itemId = $pdo->lastInsertId();
        }
        $itemIdMap[$di['code']] = (int)$itemId;
    }

    echo "✓ 10 Item inventaris posko fisik (Zona A–E) terdaftar.\n";

    // -------------------------------------------------------------
    // 6. Seeding 30 Laporan Kehilangan (Lost Reports)
    // -------------------------------------------------------------
    // Termasuk 5 Pasang Mirip untuk AI Matching Demo:
    // Pair 1: LR-SIM-01 (Dompet Baellerry)
    // Pair 2: LR-SIM-02 (Kunci Honda)
    // Pair 3: LR-SIM-03 (Tumbler Corkcicle)
    // Pair 4: LR-SIM-04 (Earbuds Samsung)
    // Pair 5: LR-SIM-05 (Binder Kokuyo)
    $lostData = [
        // 5 Pasangan Demo AI Matching
        [
            'num'       => 'LR-SIM-01',
            'user'      => $userMap['Bagas Prakoso'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 5, // Kantin Utama
            'title'     => 'Dompet Lipat Kulit Baellerry Hitam Tertinggal di Kantin',
            'desc'      => 'Dompet lipat pria bahan kulit sintetis warna hitam pekat merek Baellerry. Terdapat logo kecil timbul warna perak di sudut kanan bawah. Di dalamnya ada e-KTP, KTM, dan uang tunai sekitar lima puluh ribu rupiah.',
            'detail'    => 'Meja panjang dekat stand jus buah kantin utama',
            'priv'      => 'Terdapat struk pembelian minimarket tanggal 16 November dan foto pas 3x4 di balik saku kartu transparan.',
            'days_ago'  => 0, // Hari ini
            'status'    => 'matched',
        ],
        [
            'num'       => 'LR-SIM-02',
            'user'      => $userMap['Dimas Aryaguna'],
            'cat'       => 4, // Kunci & Aksesoris
            'loc'       => 8, // Area Parkir Barat
            'title'     => 'Kunci Motor Honda Vario 160 Hitam dengan Remote Smartkey',
            'desc'      => 'Kehilangan satu set kunci kontak motor Honda Vario 160 warna hitam beserta remote smartkey dan gantungan tali webbing merah tebal bertuliskan One Heart.',
            'detail'    => 'Jalur pejalan kaki antara pos satpam dan barisan parkir motor barat',
            'priv'      => 'Ada gantungan miniatur helm putih kecil di ring gantungan yang sama.',
            'days_ago'  => 1,
            'status'    => 'investigating',
        ],
        [
            'num'       => 'LR-SIM-03',
            'user'      => $userMap['Farrel Nugraha'],
            'cat'       => 7, // Lain-lain
            'loc'       => 2, // Perpustakaan Pusat
            'title'     => 'Botol Minum Tumbler Stainless Corkcicle Canteen 16oz Putih Matte',
            'desc'      => 'Botol minum tumbler stainless steel merek Corkcicle Canteen ukuran 16oz (475ml) warna putih matte (Gloss White). Tutup putar perak dengan sedikit lecet gesekan halus di sisi samping.',
            'detail'    => 'Meja belajar kubikel mandiri lantai 2 perpustakaan',
            'priv'      => 'Bagian dasar botol ditempeli inisial FN menggunakan spidol permanen hitam tipis.',
            'days_ago'  => 2,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-SIM-04',
            'user'      => $userMap['Hendra Simanjuntak'],
            'cat'       => 1, // Elektronik & Gadget
            'loc'       => 3, // GKB A
            'title'     => 'TWS Earphone Samsung Galaxy Buds 2 Pro Warna Bora Purple',
            'desc'      => 'Earphone nirkabel TWS Samsung Galaxy Buds 2 Pro warna ungu muda (Bora Purple) lengkap di dalam wadah pengisi daya (charging case) dengan casing silikon transparan.',
            'detail'    => 'Laci meja bangku baris ketiga ruang kuliah 304 GKB A',
            'priv'      => 'Casing silikon bagian bawah ada robekan kecil sekitar 3mm dekat port Type-C.',
            'days_ago'  => 3,
            'status'    => 'matched',
        ],
        [
            'num'       => 'LR-SIM-05',
            'user'      => $userMap['Jessica Lumintang'],
            'cat'       => 6, // Buku & Alat Tulis
            'loc'       => 4, // GKB B
            'title'     => 'Buku Binder Spiral B5 Kokuyo Campus Cover Mika Bening',
            'desc'      => 'Buku catatan binder loose leaf ukuran B5 merek Kokuyo Campus sampul mika transparan bening berisi catatan kuliah kalkulus lanjut dan stiker tab warna-warni di tepi kertas.',
            'detail'    => 'Meja diskusi selasar lantai 1 GKB B dekat vending machine',
            'priv'      => 'Halaman pertama bertuliskan nama Jessica Lumintang NIM 21081009 dengan pena tinta gel ungu.',
            'days_ago'  => 4,
            'status'    => 'resolved',
        ],

        // 25 Laporan Kehilangan Tambahan (Variatif di Seluruh Kategori & Lokasi)
        [
            'num'       => 'LR-DEMO-006',
            'user'      => $userMap['Cut Zahira Alfarisi'],
            'cat'       => 1, // Elektronik
            'loc'       => 2, // Perpustakaan
            'title'     => 'Kalkulator Saintifik Casio FX-991CW Warna Hitam',
            'desc'      => 'Kalkulator ilmiah Casio seri ClassWiz FX-991CW casing hitam dengan pelindung geser.',
            'detail'    => 'Ruang baca tandon lantai 1',
            'priv'      => 'Di balik tutup geser ada stiker barcode toko buku Gramedia.',
            'days_ago'  => 0, // Hari ini
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-007',
            'user'      => $userMap['Elvina Tampubolon'],
            'cat'       => 5, // Pakaian
            'loc'       => 7, // Masjid Kampus
            'title'     => 'Mukena Katun Rayon Warna Sage Green Motif Bunga',
            'desc'      => 'Mukena travelling bahan rayon adem warna hijau sage bermotif bunga kecil dalam pouch resleting senada.',
            'detail'    => 'Rak mukena jamaah putri shaf belakang masjid',
            'priv'      => 'Label merek bertuliskan Tazbiya dan ada aroma parfum vanila lembut.',
            'days_ago'  => 0, // Hari ini
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-008',
            'user'      => $userMap['Gita Anindya Pratiwi'],
            'cat'       => 2, // Dokumen
            'loc'       => 1, // Rektorat
            'title'     => 'Map Plastik Biru Berisi Transkrip Nilai & Sertifikat TOEFL',
            'desc'      => 'Map zipper plastik bening warna biru berisi berkas legalisir transkrip akademik dan sertifikat uji bahasa Inggris.',
            'detail'    => 'Kursi tunggu loket layanan terpadu lantai 1 rektorat',
            'priv'      => 'Ada foto copy KTP atas nama Gita Anindya Pratiwi.',
            'days_ago'  => 1,
            'status'    => 'investigating',
        ],
        [
            'num'       => 'LR-DEMO-009',
            'user'      => $userMap['Ilham Dzakwan'],
            'cat'       => 4, // Kunci
            'loc'       => 9, // Parkir Timur
            'title'     => 'Kunci Motor Yamaha NMAX dengan Gantungan Kulit Cokelat',
            'desc'      => 'Kunci motor Yamaha NMAX hitam berserta remote immobilizer dan gantungan kulit cokelat tua berukir inisial ID.',
            'detail'    => 'Area parkir motor timur baris E dekat pohon beringin',
            'priv'      => 'Anak kunci cadangan kecil warna perak ikut terpasang di ring kunci.',
            'days_ago'  => 1,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-010',
            'user'      => $userMap['Lintang Ayuningtyas'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 6, // Pusat Olahraga
            'title'     => 'Tote Bag Kanvas Putih Gambar Sketsa Kucing',
            'desc'      => 'Tas jinjing kain kanvas tebal warna broken white dengan sablon gambar kucing tidur warna hitam garis sederhana.',
            'detail'    => 'Tribun barat lapangan basket indoor',
            'priv'      => 'Isi kantong dalam: dompet koin rajut merah muda dan kunci loker nomor 24.',
            'days_ago'  => 2,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-011',
            'user'      => $userMap['Muhammad Rizqullah'],
            'cat'       => 1, // Elektronik
            'loc'       => 3, // GKB A
            'title'     => 'Mouse Wireless Logitech M331 Silent Plus Hitam',
            'desc'      => 'Mouse tanpa kabel Logitech M331 Silent warna hitam doff dengan grip karet abu-abu di sisi samping.',
            'detail'    => 'Lab Komputer 2 lantai 3 GKB A',
            'priv'      => 'Slot baterai berisi baterai Alkaline AA dan stiker garansi distributor Surya Cipta.',
            'days_ago'  => 2,
            'status'    => 'investigating',
        ],
        [
            'num'       => 'LR-DEMO-012',
            'user'      => $userMap['Nabila Azzahra'],
            'cat'       => 5, // Pakaian
            'loc'       => 5, // Kantin Utama
            'title'     => 'Cardigan Rajut Crop Warna Krem Merek Mango',
            'desc'      => 'Baju rajut outer lengan panjang warna krem muda dengan kancing batok kelapa cokelat di bagian dada.',
            'detail'    => 'Sandaran kursi stand makanan sehat kantin utama',
            'priv'      => 'Tag kerah bertuliskan Mango size S dan sedikit benang terurai di saku kiri.',
            'days_ago'  => 3,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-013',
            'user'      => $userMap['Oktavianus Wenda'],
            'cat'       => 2, // Dokumen
            'loc'       => 4, // GKB B
            'title'     => 'KTM Mahasiswa & Kartu Asuransi BPJS atas nama Oktavianus Wenda',
            'desc'      => 'Card holder kulit sintetis cokelat berisi kartu tanda mahasiswa Fakultas Hukum angkatan 2023 dan kartu BPJS.',
            'detail'    => 'Toilet lantai dasar GKB B dekat tangga darurat',
            'priv'      => 'Tali lanyard card holder warna biru dongker bertuliskan BEM FH.',
            'days_ago'  => 3,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-014',
            'user'      => $userMap['Putu Gede Ananta'],
            'cat'       => 7, // Lain-lain
            'loc'       => 7, // Masjid Kampus
            'title'     => 'Kacamata Minus Frame Titanium Hitam Bulat Merek Owndays',
            'desc'      => 'Kacamata optik lensa minus dengan bingkai titanium ringan warna hitam matte bentuk bulat klasik.',
            'detail'    => 'Rak penitipan barang dekat tempat wudhu pria masjid kampus',
            'priv'      => 'Kaki kacamata bagian kanan tertulis kode OWNDAYS AIR Ultem 0.75 -1.25.',
            'days_ago'  => 4,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-015',
            'user'      => $userMap['Qonita Ramadhani'],
            'cat'       => 6, // Buku & Alat Tulis
            'loc'       => 2, // Perpustakaan
            'title'     => 'Pencil Case Kulit Cokelat Merek Lihit Lab',
            'desc'      => 'Kotak tempat pensil lipat bahan kanvas-kulit warna cokelat tan merek Lihit Lab berisi pulpen gel Pentel Energel dan stabilo.',
            'detail'    => 'Meja komputer katalog digital lantai 1 perpustakaan',
            'priv'      => 'Di saku dalam ada flashdisk SanDisk Ultra Dual Drive 32GB warna silver.',
            'days_ago'  => 4,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-016',
            'user'      => $userMap['Bagas Prakoso'],
            'cat'       => 1, // Elektronik
            'loc'       => 8, // Parkir Barat
            'title'     => 'Powerbank Anker PowerCore 20000mAh Hitam Doff',
            'desc'      => 'Pengisi daya portabel kapasitas 20000mAh warna hitam tebal dengan 2 port USB-A dan 1 port USB-C.',
            'detail'    => 'Tertinggal di dashboard jok motor Honda Beat di parkir barat',
            'priv'      => 'Terdapat stiker logo Github warna putih bundar di bagian punggung powerbank.',
            'days_ago'  => 5,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-017',
            'user'      => $userMap['Cut Zahira Alfarisi'],
            'cat'       => 7, // Lain-lain
            'loc'       => 5, // Kantin Utama
            'title'     => 'Payung Lipat Otomatis Warna Lilac Lavender',
            'desc'      => 'Payung lipat anti-UV warna ungu pastel lavender dengan tombol buka-tutup otomatis pada gagang karet hitam.',
            'detail'    => 'Gantungan payung dekat pintu masuk barat kantin',
            'priv'      => 'Tali pengikat payung ada gantungan mutiara akrilik kecil.',
            'days_ago'  => 5,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-018',
            'user'      => $userMap['Dimas Aryaguna'],
            'cat'       => 5, // Pakaian
            'loc'       => 6, // Pusat Olahraga
            'title'     => 'Sepatu Futsal Specs Lightspeed 3 Warna Putih Emas Ukuran 42',
            'desc'      => 'Sepatu olahraga futsal outsole karet indoor merek Specs seri Lightspeed 3 dominasi putih dengan aksen garis emas metalik.',
            'detail'    => 'Loker ruang ganti pemain pria lapangan futsal',
            'priv'      => 'Tali sepatu diganti warna hitam dan ujung sol depan kanan sedikit terkikis.',
            'days_ago'  => 5,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-019',
            'user'      => $userMap['Elvina Tampubolon'],
            'cat'       => 4, // Kunci
            'loc'       => 1, // Rektorat
            'title'     => 'Gantungan Kunci Rumah Akrilik Karakter Totoro',
            'desc'      => 'Tiga bilah anak kunci pintu gerbang dan kamar kos dengan gantungan akrilik dua sisi bergambar Studio Ghibli Totoro.',
            'detail'    => 'Meja resepsionis pendaftaran beasiswa lantai 2',
            'priv'      => 'Salah satu anak kunci bertuliskan merek Yale warna kuningan.',
            'days_ago'  => 6,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-020',
            'user'      => $userMap['Farrel Nugraha'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 3, // GKB A
            'title'     => 'Pouch Organizer Kabel Elektronik Ugreen Warna Abu-abu',
            'desc'      => 'Tas kecil organizer penyimpanan kabel charger, kepala adaptor 65W, dan dongle HDMI bahan poliester tahan air.',
            'detail'    => 'Meja dosen ruang seminar 201 GKB A',
            'priv'      => 'Kabel data diikat velcro strap hitam bertuliskan Ugreen.',
            'days_ago'  => 6,
            'status'    => 'resolved',
        ],
        [
            'num'       => 'LR-DEMO-021',
            'user'      => $userMap['Gita Anindya Pratiwi'],
            'cat'       => 6, // Buku
            'loc'       => 2, // Perpustakaan
            'title'     => 'Buku Teks Desain Grafis "Thinking with Type" Ellen Lupton',
            'desc'      => 'Buku rujukan desain tipografi edisi kedua berbahasa Inggris sampul merah bata penerbit Princeton Architectural Press.',
            'detail'    => 'Sofa santai ruang audio visual lantai 3 perpustakaan',
            'priv'      => 'Terdapat pembatas buku pita kain merah dan catatan pensil di bab Grid System.',
            'days_ago'  => 6,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-022',
            'user'      => $userMap['Hendra Simanjuntak'],
            'cat'       => 1, // Elektronik
            'loc'       => 4, // GKB B
            'title'     => 'Harddisk Eksternal WD My Passport 1TB Warna Merah Marun',
            'desc'      => 'Penyimpanan data eksternal 2.5 inci Western Digital My Passport USB 3.0 casing merah dengan kabel data terpasang.',
            'detail'    => 'Meja komputer ruang rendering studio arsitektur GKB B',
            'priv'      => 'Ada stiker inventaris lab praktikum komputasi struktur di sisi bawah.',
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-023',
            'user'      => $userMap['Ilham Dzakwan'],
            'cat'       => 5, // Pakaian
            'loc'       => 7, // Masjid Kampus
            'title'     => 'Jaket Parka Uniqlo Blocktech Warna Navy Blue (Ukuran XL)',
            'desc'      => 'Jaket pelindung angin dan air merek Uniqlo Blocktech warna biru gelap berresleting kedap air dan velcro di pergelangan.',
            'detail'    => 'Kapstok gantungan jaket teras samping masjid kampus',
            'priv'      => 'Saku dalam kanan tertinggal karcis parkir stasiun kereta tanggal 10 November.',
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-024',
            'user'      => $userMap['Jessica Lumintang'],
            'cat'       => 2, // Dokumen
            'loc'       => 5, // Kantin Utama
            'title'     => 'Buku Tabungan BNI Taplus Mahasiswa atas nama Jessica L.',
            'desc'      => 'Buku rekening bank BNI sampul hijau toska dengan logo Taplus Mahasiswa.',
            'detail'    => 'Meja kasir fotokopi pojok kantin utama',
            'priv'      => 'Nomor rekening berakhiran angka 4821 dan cabang kampus terdaftar.',
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-025',
            'user'      => $userMap['Lintang Ayuningtyas'],
            'cat'       => 7, // Lain-lain
            'loc'       => 6, // Pusat Olahraga
            'title'     => 'Topi Baseball New Era NY Yankees Warna Hitam Bordir Putih',
            'desc'      => 'Topi bisbol model 9FORTY New York Yankees warna hitam pekat dengan bordir logo NY warna putih tebal di depan.',
            'detail'    => 'Bangku cadangan pinggir lapangan tenis outdoor',
            'priv'      => 'Stiker hologram emas New Era masih menempel di visor bagian atas.',
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-026',
            'user'      => $userMap['Muhammad Rizqullah'],
            'cat'       => 4, // Kunci
            'loc'       => 9, // Parkir Timur
            'title'     => 'Kunci Gembok Loker Koper Kuningan Kecil Merek Master Lock',
            'desc'      => 'Dua anak kunci kecil gembok koper travel berbahan kuningan merek Master Lock pada ring perak kecil.',
            'detail'    => 'Jalan setapak aspal antara parkir timur menuju mushola rektorat',
            'priv'      => 'Ujung anak kunci memiliki cap kode angka 24B.',
            'days_ago'  => 1,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-027',
            'user'      => $userMap['Nabila Azzahra'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 3, // GKB A
            'title'     => 'Tas Ransel Kanken Fjallraven Classic Warna Ox Red',
            'desc'      => 'Ransel Fjallraven Kanken bahan Vinylon warna merah marun (Ox Red) dengan pegangan jinjing kancing atas.',
            'detail'    => 'Kursi lorong depan lift lantai 2 GKB A',
            'priv'      => 'Bordir emblem reflektif logo rubah kutub di bagian depan tengah.',
            'days_ago'  => 2,
            'status'    => 'investigating',
        ],
        [
            'num'       => 'LR-DEMO-028',
            'user'      => $userMap['Oktavianus Wenda'],
            'cat'       => 6, // Buku
            'loc'       => 1, // Rektorat
            'title'     => 'Buku Agenda Kuliah Hardcover Hitam Polos Berpita Pembatas',
            'desc'      => 'Buku agenda catatan harian ukuran A5 sampul kulit sintetis hitam bergaris dengan karet pengikat elastis samping.',
            'detail'    => 'Meja tunggu pelayanan administrasi akademik lantai 1',
            'priv'      => 'Selipan lembar jadwal ujian tengah semester di bagian kantong belakang.',
            'days_ago'  => 3,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-029',
            'user'      => $userMap['Putu Gede Ananta'],
            'cat'       => 1, // Elektronik
            'loc'       => 2, // Perpustakaan
            'title'     => 'Flashdisk Kingston DataTraveler Kyson 64GB Metalik',
            'desc'      => 'USB Flashdisk berbahan logam metalik warna perak ramping tanpa tutup dengan lubang gantungan gantungan kunci.',
            'detail'    => 'Tertinggal menancap di port USB PC nomor 08 lab internet lantai 2',
            'priv'      => 'Nama partisi drive ketika dicolok bertuliskan ANANTA_DENT.',
            'days_ago'  => 4,
            'status'    => 'published',
        ],
        [
            'num'       => 'LR-DEMO-030',
            'user'      => $userMap['Qonita Ramadhani'],
            'cat'       => 7, // Lain-lain
            'loc'       => 5, // Kantin Utama
            'title'     => 'Jas Hujan Poncho Elmondo Hijau Toska dalam Kantong Serut',
            'desc'      => 'Jas hujan mantel terusan bahan parasut kedap air warna toska dengan scotlight reflektif di bagian punggung.',
            'detail'    => 'Tempat gantungan helm dan jas hujan selasar kantin',
            'priv'      => 'Kantong serut pembungkus memiliki tali serut hitam dengan stopper plastik bulat.',
            'days_ago'  => 0, // Hari ini
            'status'    => 'published',
        ],
    ];

    $checkLostStmt = $pdo->prepare("SELECT id FROM lost_reports WHERE report_number = ? LIMIT 1");
    $insertLostStmt = $pdo->prepare("
        INSERT INTO lost_reports (report_number, reporter_user_id, category_id, last_seen_location_id, title, description, location_detail, private_details, lost_date, lost_time, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY), '10:30:00', ?, DATE_SUB(NOW(), INTERVAL ? DAY), DATE_SUB(NOW(), INTERVAL ? DAY))
    ");

    $lostIdMap = [];
    foreach ($lostData as $ld) {
        $checkLostStmt->execute([$ld['num']]);
        $lid = $checkLostStmt->fetchColumn();
        if (!$lid) {
            $insertLostStmt->execute([
                $ld['num'],
                $ld['user'],
                $ld['cat'],
                $ld['loc'],
                $ld['title'],
                $ld['desc'],
                $ld['detail'],
                $ld['priv'],
                $ld['days_ago'],
                $ld['status'],
                $ld['days_ago'],
                $ld['days_ago'],
            ]);
            $lid = $pdo->lastInsertId();
        }
        $lostIdMap[$ld['num']] = (int)$lid;
    }

    echo "✓ 30 Laporan kehilangan (Lost Reports) terdaftar.\n";

    // -------------------------------------------------------------
    // 7. Seeding 25 Laporan Temuan (Found Reports)
    // -------------------------------------------------------------
    // Termasuk 5 Pasang Mirip untuk AI Matching Demo:
    // Pair 1: FND-SIM-01 (Dompet Baellerry) -> item DEMO-ITM-03
    // Pair 2: FND-SIM-02 (Kunci Honda)     -> item DEMO-ITM-04
    // Pair 3: FND-SIM-03 (Tumbler Corkcicle) -> item DEMO-ITM-05
    // Pair 4: FND-SIM-04 (Earbuds Samsung)  -> item DEMO-ITM-06
    // Pair 5: FND-SIM-05 (Binder Kokuyo)   -> item DEMO-ITM-09
    $foundData = [
        // 5 Pasangan Demo AI Matching
        [
            'num'       => 'FND-SIM-01',
            'user'      => $userMap['Cut Zahira Alfarisi'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 5, // Kantin Utama
            'item_id'   => $itemIdMap['DEMO-ITM-03'] ?? null,
            'title'     => 'Ditemukan Dompet Pria Hitam Merk Baellerry di Meja Kantin',
            'desc'      => 'Ditemukan dompet lipat kulit warna hitam merek Baellerry di dekat meja kasir kantin utama, kondisi mulus dengan aksen timbul perak di pojok luar.',
            'detail'    => 'Meja makan panjang seberang konter kasir utama',
            'condition' => 'baik',
            'priv'      => 'Tersimpan di posko meja piket. Berisi kartu identitas mahasiswa dan uang kertas.',
            'handed'    => 1,
            'days_ago'  => 0, // Hari ini
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-SIM-02',
            'user'      => $userMap['Elvina Tampubolon'],
            'cat'       => 4, // Kunci & Aksesoris
            'loc'       => 8, // Area Parkir Barat
            'item_id'   => $itemIdMap['DEMO-ITM-04'] ?? null,
            'title'     => 'Anak Kunci Motor Honda Vario Smartkey & Tali Merah One Heart',
            'desc'      => 'Ditemukan anak kunci kontak motor matic Honda beserta remote keyless hitam dan gantungan pita kain warna merah One Heart terjatuh di aspal dekat pos satpam.',
            'detail'    => 'Aspal jalan masuk gerbang barat parkiran motor',
            'condition' => 'sangat_baik',
            'priv'      => 'Ada gantungan miniatur helm putih kecil di ring gantungan.',
            'handed'    => 1,
            'days_ago'  => 1,
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-SIM-03',
            'user'      => $userMap['Gita Anindya Pratiwi'],
            'cat'       => 7, // Lain-lain
            'loc'       => 2, // Perpustakaan Pusat
            'item_id'   => $itemIdMap['DEMO-ITM-05'] ?? null,
            'title'     => 'Ditemukan Botol Minum Corkcicle Canteen Putih di Perpustakaan',
            'desc'      => 'Ditemukan tumbler minum berbahan stainless steel merk Corkcicle Canteen ukuran 16oz warna putih susu berpenutup silver di atas bilik kubikel belajar.',
            'detail'    => 'Bilik belajar kubikel 14 lantai 2 perpustakaan',
            'condition' => 'baik',
            'priv'      => 'Terdapat inisial huruf FN tipis di bagian bawah luar botol.',
            'handed'    => 1,
            'days_ago'  => 2,
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-SIM-04',
            'user'      => $userMap['Ilham Dzakwan'],
            'cat'       => 1, // Elektronik & Gadget
            'loc'       => 3, // GKB A
            'item_id'   => $itemIdMap['DEMO-ITM-06'] ?? null,
            'title'     => 'Ditemukan TWS Earbuds Samsung Galaxy Buds Case Silikon Ungu',
            'desc'      => 'Ditemukan wadah charging case headset bluetooth Samsung Galaxy Buds 2 Pro warna ungu muda terbalut casing silikon bening di laci ruang kelas.',
            'detail'    => 'Laci meja belajar baris ketiga ruang 304 GKB A',
            'condition' => 'sangat_baik',
            'priv'      => 'Silikon pelindung sedikit robek di bagian sudut bawah port charger.',
            'handed'    => 1,
            'days_ago'  => 3,
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-SIM-05',
            'user'      => $userMap['Lintang Ayuningtyas'],
            'cat'       => 6, // Buku & Alat Tulis
            'loc'       => 4, // GKB B
            'item_id'   => $itemIdMap['DEMO-ITM-09'] ?? null,
            'title'     => 'Ditemukan Buku Binder B5 Kokuyo Campus Cover Bening',
            'desc'      => 'Buku binder ukuran B5 merek Kokuyo Campus cover mika transparan berisi ringkasan rumus matematika kalkulus tulisan tangan tinta biru dan tab pembatas warna-warni.',
            'detail'    => 'Selasar lantai 1 GKB B depan lift barat',
            'condition' => 'baik',
            'priv'      => 'Tertulis nama pemilik Jessica di halaman catatan awal.',
            'handed'    => 1,
            'days_ago'  => 4,
            'status'    => 'completed',
        ],

        // 20 Laporan Temuan Tambahan (Variatif di Seluruh Kategori & Lokasi)
        [
            'num'       => 'FND-DEMO-006',
            'user'      => $userMap['Muhammad Rizqullah'],
            'cat'       => 1, // Elektronik
            'loc'       => 1, // Rektorat
            'item_id'   => $itemIdMap['DEMO-ITM-01'] ?? null,
            'title'     => 'iPad Mini 6 Space Gray Ditemukan di Ruang Rapat Senat',
            'desc'      => 'Ditemukan tablet Apple iPad Mini warna Space Gray dengan smart cover magnetik hitam tertinggal di bawah kursi ruang rapat.',
            'detail'    => 'Gedung Rektorat lantai 3 ruang rapat senat',
            'condition' => 'sangat_baik',
            'priv'      => 'Wallpaper layar terkunci menampilkan ilustrasi pemandangan gunung malam hari.',
            'handed'    => 1,
            'days_ago'  => 0, // Hari ini
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-DEMO-007',
            'user'      => $userMap['Nabila Azzahra'],
            'cat'       => 2, // Dokumen
            'loc'       => 1, // Rektorat
            'item_id'   => $itemIdMap['DEMO-ITM-02'] ?? null,
            'title'     => 'Ditemukan Kartu ATM BCA & SIM A di Area ATM Rektorat',
            'desc'      => 'Dua kartu identitas dan transaksi berpelindung plastik transparan ditemukan tertinggal di atas boks mesin ATM.',
            'detail'    => 'Bilik ATM Center depan lobi utama gedung rektorat',
            'condition' => 'baik',
            'priv'      => 'Kartu atas nama Muhammad Rizqullah.',
            'handed'    => 1,
            'days_ago'  => 0, // Hari ini
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-DEMO-008',
            'user'      => $userMap['Oktavianus Wenda'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 3, // GKB A
            'item_id'   => $itemIdMap['DEMO-ITM-07'] ?? null,
            'title'     => 'Tas Ransel Eiger Abu-abu Ditemukan di Lobi GKB A',
            'desc'      => 'Tas punggung laptop merek Eiger warna abu-abu kombinasi hitam tertinggal di bangku tunggu lobi tengah.',
            'detail'    => 'Sofa ruang tunggu depan lift lantai 1 GKB A',
            'condition' => 'baik',
            'priv'      => 'Di saku samping ada payung lipat hitam kecil dan botol air mineral.',
            'handed'    => 1,
            'days_ago'  => 1,
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-DEMO-009',
            'user'      => $userMap['Putu Gede Ananta'],
            'cat'       => 4, // Kunci
            'loc'       => 9, // Parkir Timur
            'item_id'   => $itemIdMap['DEMO-ITM-08'] ?? null,
            'title'     => 'Kunci Mobil Toyota Avanza dengan Remote Alarm di Parkir Timur',
            'desc'      => 'Ditemukan anak kunci kontak mobil Toyota beserta remote 2 tombol dan gantungan plat nomor di paving blok parkir timur.',
            'detail'    => 'Sela-sela mobil baris depan parkir timur',
            'condition' => 'baik',
            'priv'      => 'Gantungan kunci bertuliskan plat nomor B 1420.',
            'handed'    => 1,
            'days_ago'  => 1,
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-DEMO-010',
            'user'      => $userMap['Qonita Ramadhani'],
            'cat'       => 5, // Pakaian
            'loc'       => 5, // Kantin Utama
            'item_id'   => $itemIdMap['DEMO-ITM-010'] ?? null,
            'title'     => 'Jaket Hoodie Zipper Uniqlo Abu-abu Muda Ukuran L',
            'desc'      => 'Jaket hoodie bertopi bahan katun tebal warna abu-abu muda merek Uniqlo tertinggal di kursi makan kantin.',
            'detail'    => 'Kursi pojok dekat konter minuman kantin utama',
            'condition' => 'sangat_baik',
            'priv'      => 'Ukuran L, kondisi bersih dan sudah diserahkan ke posko Lost & Found.',
            'handed'    => 1,
            'days_ago'  => 2,
            'status'    => 'verified',
        ],
        [
            'num'       => 'FND-DEMO-011',
            'user'      => $userMap['Bagas Prakoso'],
            'cat'       => 1, // Elektronik
            'loc'       => 4, // GKB B
            'item_id'   => null,
            'title'     => 'Charger Laptop USB-C Lenovo 65W Warna Hitam',
            'desc'      => 'Adaptor pengisi daya laptop USB Type-C original Lenovo 65W kabel hitam panjang tertinggal di stopkontak.',
            'detail'    => 'Colokan dinding ruang kuliah 205 GKB B',
            'condition' => 'baik',
            'priv'      => 'Kabel digulung dengan strap velcro bawaan pabrik.',
            'handed'    => 0,
            'days_ago'  => 2,
            'status'    => 'pending_handover',
        ],
        [
            'num'       => 'FND-DEMO-012',
            'user'      => $userMap['Cut Zahira Alfarisi'],
            'cat'       => 2, // Dokumen
            'loc'       => 7, // Masjid Kampus
            'item_id'   => null,
            'title'     => 'KTP & SIM C atas nama Fajar Nugroho Ditemukan di Teras Masjid',
            'desc'      => 'Kartu Tanda Penduduk dan Surat Izin Mengemudi C tercecer di lantai teras samping masjid kampus dekat rak sepatu.',
            'detail'    => 'Lantai keramik teras barat dekat rak sandal pria',
            'condition' => 'baik',
            'priv'      => 'Alamat KTP terdaftar di Sleman, Yogyakarta.',
            'handed'    => 0,
            'days_ago'  => 3,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-013',
            'user'      => $userMap['Dimas Aryaguna'],
            'cat'       => 7, // Lain-lain
            'loc'       => 6, // Pusat Olahraga
            'item_id'   => null,
            'title'     => 'Botol Minum Plastik Tupperware Eco Bottle 1L Hitam',
            'desc'      => 'Botol air minum plastik Tupperware kapasitas 1 liter warna hitam transparan tutup ulir biru.',
            'detail'    => 'Pinggir lintasan lari lapangan atletik outdoor',
            'condition' => 'baik',
            'priv'      => 'Ada stiker nomor pelari maraton di badan botol.',
            'handed'    => 0,
            'days_ago'  => 3,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-014',
            'user'      => $userMap['Elvina Tampubolon'],
            'cat'       => 5, // Pakaian
            'loc'       => 2, // Perpustakaan
            'item_id'   => null,
            'title'     => 'Syal Rajut Musim Dingin Motif Kotak-kotak Tartan Merah',
            'desc'      => 'Syal leher bahan wol lembut motif kotak tartan perpaduan merah marun, hijau tua, dan garis kuning.',
            'detail'    => 'Sandaran kursi ruang baca berkala lantai 2 perpustakaan',
            'priv'      => 'Ujung syal memiliki rumbai benang wol rapi sepanjang 8cm.',
            'handed'    => 0,
            'days_ago'  => 4,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-015',
            'user'      => $userMap['Farrel Nugraha'],
            'cat'       => 4, // Kunci
            'loc'       => 3, // GKB A
            'item_id'   => null,
            'title'     => 'Kunci Gembok Kuningan Kecil dengan Pita Biru',
            'desc'      => 'Anak kunci kuningan ukuran kecil dengan ikatan pita kain biru tua ditemukan di lantai lorong.',
            'detail'    => 'Lantai koridor depan laboratorium jaringan komputer lantai 3',
            'condition' => 'baik',
            'priv'      => 'Nomor seri anak kunci terukir 103.',
            'handed'    => 0,
            'days_ago'  => 4,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-016',
            'user'      => $userMap['Gita Anindya Pratiwi'],
            'cat'       => 6, // Buku
            'loc'       => 5, // Kantin Utama
            'item_id'   => null,
            'title'     => 'Buku Sketsa Gambar A4 Canson XL Aquarelle',
            'desc'      => 'Sketchbook kertas tebal watercolor ukuran A4 merek Canson XL sampul biru spiral samping sebagian sudah terisi sketsa pensil.',
            'detail'    => 'Meja bundar outdoor halaman kantin utama',
            'priv'      => 'Gambar halaman pertama sketsa arsitektur tugu kampus.',
            'handed'    => 0,
            'days_ago'  => 5,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-017',
            'user'      => $userMap['Hendra Simanjuntak'],
            'cat'       => 1, // Elektronik
            'loc'       => 8, // Parkir Barat
            'item_id'   => null,
            'title'     => 'Kabel Data Type-C to Lightning Merek Baseus 1 Meter Hitam',
            'desc'      => 'Kabel charger fast charging braided nylon warna hitam ujung colokan metalik abu-abu dengan tali pengikat velcro.',
            'detail'    => 'Tergantung di stang motor dekat tiang nomor 12 parkir barat',
            'condition' => 'baik',
            'priv'      => 'Ada cap tulisan PD 20W di kepala konektor Type-C.',
            'handed'    => 0,
            'days_ago'  => 5,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-018',
            'user'      => $userMap['Ilham Dzakwan'],
            'cat'       => 3, // Dompet & Tas
            'loc'       => 7, // Masjid Kampus
            'item_id'   => null,
            'title'     => 'Dompet Kartu Cardholder Kulit Cokelat Tan Merek Fossil',
            'desc'      => 'Dompet kartu ramping bahan kulit asli warna cokelat karamel dengan 6 slot kartu dan 1 kompartemen tengah uang kertas.',
            'detail'    => 'Bawah karpet shaf kedua masjid kampus lantai utama',
            'priv'      => 'Di slot utama ada kartu e-toll Mandiri saldo tercetak.',
            'handed'    => 0,
            'days_ago'  => 5,
            'status'    => 'pending_handover',
        ],
        [
            'num'       => 'FND-DEMO-019',
            'user'      => $userMap['Jessica Lumintang'],
            'cat'       => 7, // Lain-lain
            'loc'       => 4, // GKB B
            'item_id'   => null,
            'title'     => 'Payung Lipat Mini Motif Garis-garis Hitam Putih',
            'desc'      => 'Payung lipat ukuran saku bahan ringan motif strip zebra hitam putih gagang silver.',
            'detail'    => 'Bawah meja dosen ruang kelas 102 GKB B',
            'condition' => 'rusak_ringan',
            'priv'      => 'Salah satu jeruji ujung kanan agak longgar ikatannya.',
            'handed'    => 0,
            'days_ago'  => 6,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-020',
            'user'      => $userMap['Lintang Ayuningtyas'],
            'cat'       => 2, // Dokumen
            'loc'       => 2, // Perpustakaan
            'item_id'   => null,
            'title'     => 'KTM Universitas Mahasiswa Teknik Kimia angkatan 2022',
            'desc'      => 'Kartu Tanda Mahasiswa berbahan PVC resmi kampus program studi Teknik Kimia dengan foto almamater biru.',
            'detail'    => 'Tertinggal di konter peminjaman buku sirkulasi lantai 1',
            'priv'      => 'NIM berawalan 220501...',
            'handed'    => 0,
            'days_ago'  => 6,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-021',
            'user'      => $userMap['Muhammad Rizqullah'],
            'cat'       => 4, // Kunci
            'loc'       => 6, // Pusat Olahraga
            'item_id'   => null,
            'title'     => 'Kunci Kontak Motor Suzuki Satria F150 dengan Gantungan Karet',
            'desc'      => 'Anak kunci motor Suzuki dengan gantungan bahan karet silikon logo monster energy warna hijau hitam.',
            'detail'    => 'Pinggir lapangan voli luar ruangan',
            'condition' => 'baik',
            'priv'      => 'Kunci sedikit melengkung sekitar 2 derajat tapi masih berfungsi normal.',
            'handed'    => 0,
            'days_ago'  => 6,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-022',
            'user'      => $userMap['Nabila Azzahra'],
            'cat'       => 5, // Pakaian
            'loc'       => 1, // Rektorat
            'item_id'   => null,
            'title'     => 'Topi Bucket Hat Bolak-balik Warna Hitam dan Motif Bunga',
            'desc'      => 'Topi santai model bucket hat bahan katun kanvas bisa dipakai bolak-balik (sisi luar hitam polos, sisi dalam bunga daisy).',
            'detail'    => 'Taman air mancur depan gedung rektorat',
            'condition' => 'sangat_baik',
            'priv'      => 'Ada tali pengait dagu elastis warna hitam.',
            'handed'    => 0,
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-023',
            'user'      => $userMap['Oktavianus Wenda'],
            'cat'       => 6, // Buku
            'loc'       => 3, // GKB A
            'item_id'   => null,
            'title'     => 'Kamus Saku Oxford Learner Pocket Dictionary',
            'desc'      => 'Kamus bahasa Inggris saku sampul plastik lentur warna biru navy cetakan Oxford edisi keempat.',
            'detail'    => 'Loker bersama ruang himpunan mahasiswa lantai 1 GKB A',
            'priv'      => 'Tepi kertas kamus ada cap stempel perpustakaan sekolah SMA.',
            'handed'    => 0,
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-024',
            'user'      => $userMap['Putu Gede Ananta'],
            'cat'       => 1, // Elektronik
            'loc'       => 9, // Parkir Timur
            'item_id'   => null,
            'title'     => 'Smartband Xiaomi Mi Band 7 Tali Karet Hitam',
            'desc'      => 'Gelang kebugaran pintar Xiaomi Mi Smart Band 7 layar AMOLED lengkung dengan strap karet hitam original.',
            'detail'    => 'Jalur pedestrian dekat loket karcis keluar parkir timur',
            'condition' => 'baik',
            'priv'      => 'Layar masih menyala menampilkan indikator langkah dan baterai tersisa 68%.',
            'handed'    => 0,
            'days_ago'  => 7,
            'status'    => 'published',
        ],
        [
            'num'       => 'FND-DEMO-025',
            'user'      => $userMap['Qonita Ramadhani'],
            'cat'       => 7, // Lain-lain
            'loc'       => 2, // Perpustakaan
            'item_id'   => null,
            'title'     => 'Tempat Kacamata Hardcase Kulit Cokelat Gelap Merk Ray-Ban',
            'desc'      => 'Kotak wadah kacamata cangkang keras bahan kulit sintetis warna cokelat tua dengan kancing jepret logo Ray-Ban emas di penutup.',
            'detail'    => 'Meja sofa ruang majalah lantai 1 perpustakaan',
            'condition' => 'sangat_baik',
            'priv'      => 'Di dalam kotak terdapat kain microfiber pembersih lensa warna abu-abu bertuliskan Ray-Ban.',
            'handed'    => 0,
            'days_ago'  => 0, // Hari ini
            'status'    => 'published',
        ],
    ];

    $checkFoundStmt = $pdo->prepare("SELECT id FROM found_reports WHERE report_number = ? LIMIT 1");
    $insertFoundStmt = $pdo->prepare("
        INSERT INTO found_reports (report_number, reporter_user_id, category_id, location_id, item_id, title, description, location_detail, item_condition, private_details, found_date, found_time, is_handed_over_to_center, handed_over_at, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL ? DAY), '14:15:00', ?, IF(? = 1, NOW(), NULL), ?, DATE_SUB(NOW(), INTERVAL ? DAY), DATE_SUB(NOW(), INTERVAL ? DAY))
    ");

    $foundIdMap = [];
    foreach ($foundData as $fd) {
        $checkFoundStmt->execute([$fd['num']]);
        $fid = $checkFoundStmt->fetchColumn();
        if (!$fid) {
            $insertFoundStmt->execute([
                $fd['num'],
                $fd['user'],
                $fd['cat'],
                $fd['loc'],
                $fd['item_id'],
                $fd['title'],
                $fd['desc'],
                $fd['detail'],
                $fd['condition'],
                $fd['priv'],
                $fd['days_ago'],
                $fd['handed'],
                $fd['handed'],
                $fd['status'],
                $fd['days_ago'],
                $fd['days_ago'],
            ]);
            $fid = $pdo->lastInsertId();
        }
        $foundIdMap[$fd['num']] = (int)$fid;
    }

    echo "✓ 25 Laporan temuan (Found Reports) terdaftar.\n";

    // -------------------------------------------------------------
    // 8. Seeding 8 Pengajuan Klaim Kepemilikan (Berstatus Campuran)
    // -------------------------------------------------------------
    $claimsData = [
        [
            'num'       => 'CLM-DEMO-001',
            'user'      => $userMap['Bagas Prakoso'],
            'lost_id'   => $lostIdMap['LR-SIM-01'] ?? null,
            'found_id'  => $foundIdMap['FND-SIM-01'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-03'] ?? null,
            'reason'    => 'Dompet ini tertinggal saat saya makan siang bersama teman di kantin utama.',
            'proof'     => 'KTM atas nama Bagas Prakoso NIM 21081001 dan foto KTP fisik.',
            'features'  => 'Ada logo Baellerry emboss perak di sudut luar dan struk belanja terselip di saku tengah.',
            'status'    => 'pending',
            'reviewer'  => null,
            'notes'     => null,
            'evidence'  => [
                ['type' => 'secret_details', 'key' => 'Isi Dompet', 'val' => 'KTM 21081001, e-KTP, dan uang kertas nominal Rp 50.000'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-002',
            'user'      => $userMap['Dimas Aryaguna'],
            'lost_id'   => $lostIdMap['LR-SIM-02'] ?? null,
            'found_id'  => $foundIdMap['FND-SIM-02'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-04'] ?? null,
            'reason'    => 'Kunci motor Vario saya terjatuh dari saku jaket saat berjalan menuju parkir barat.',
            'proof'     => 'STNK motor Honda Vario 160 atas nama Dimas Aryaguna dan foto fisik motor.',
            'features'  => 'Gantungan kunci memiliki remote smartkey dan gantungan tali merah bertuliskan One Heart serta miniatur helm putih.',
            'status'    => 'under_review',
            'reviewer'  => $userMap['Nadira Kusumawardhani'],
            'notes'     => 'Petugas sedang mencocokkan nomor seri fisik remote keyless di posko.',
            'evidence'  => [
                ['type' => 'serial_number', 'key' => 'Nomor Seri Remote', 'val' => '89412 pada badan belakang remote keyless'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-003',
            'user'      => $userMap['Muhammad Rizqullah'],
            'lost_id'   => null,
            'found_id'  => $foundIdMap['FND-DEMO-007'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-02'] ?? null,
            'reason'    => 'Kartu ATM dan SIM tertinggal di bilik mesin ATM setelah melakukan penarikan uang.',
            'proof'     => 'Tangkapan layar profil m-banking BCA dan buku tabungan fisik.',
            'features'  => 'Kartu SIM A atas nama Muhammad Rizqullah dan kartu ATM BCA chip Platinum.',
            'status'    => 'need_more_info',
            'reviewer'  => $userMap['Raffi Maulana Hakim'],
            'notes'     => 'Mohon unggah foto identitas pendukung lain seperti KTM untuk verifikasi ganda.',
            'evidence'  => [
                ['type' => 'secret_details', 'key' => 'Nama Pemilik Rekening', 'val' => 'Muhammad Rizqullah'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-004',
            'user'      => $userMap['Farrel Nugraha'],
            'lost_id'   => $lostIdMap['LR-SIM-03'] ?? null,
            'found_id'  => $foundIdMap['FND-SIM-03'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-05'] ?? null,
            'reason'    => 'Tumbler Corkcicle putih tertinggal di kubikel perpustakaan saat saya pulang kuliah.',
            'proof'     => 'Bukti invoice pembelian online e-commerce Corkcicle Official Store.',
            'features'  => 'Ada tanda inisial huruf FN tipis di dasar botol menggunakan spidol permanen.',
            'status'    => 'approved',
            'reviewer'  => $userMap['Raffi Maulana Hakim'],
            'notes'     => 'Bukti inisial FN pada dasar botol terbukti identik. Jadwal serah terima diatur besok jam 10.00 WIB.',
            'evidence'  => [
                ['type' => 'invoice_receipt', 'key' => 'Invoice Pembelian', 'val' => 'INV-CRK-2026-9912 Tokopedia'],
                ['type' => 'secret_details', 'key' => 'Tanda Unik Dasar Botol', 'val' => 'Inisial FN spidol hitam'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-005',
            'user'      => $userMap['Oktavianus Wenda'],
            'lost_id'   => null,
            'found_id'  => $foundIdMap['FND-DEMO-006'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-01'] ?? null,
            'reason'    => 'Mengklaim iPad Mini Space Gray yang ditemukan di ruang senat rektorat.',
            'proof'     => 'Menyatakan memiliki iPad serupa yang hilang.',
            'features'  => 'iPad warna abu-abu dengan casing hitam.',
            'status'    => 'rejected',
            'reviewer'  => $userMap['Nadira Kusumawardhani'],
            'notes'     => 'Klaim ditolak karena kode sandi layar kunci dan nomor seri Apple ID yang diberikan pemohon tidak cocok.',
            'evidence'  => [
                ['type' => 'secret_details', 'key' => 'Passcode Layar', 'val' => '123456 (Tidak cocok)'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-006',
            'user'      => $userMap['Jessica Lumintang'],
            'lost_id'   => $lostIdMap['LR-SIM-05'] ?? null,
            'found_id'  => $foundIdMap['FND-SIM-05'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-09'] ?? null,
            'reason'    => 'Binder catatan kalkulus saya tertinggal di meja selasar lantai 1 GKB B.',
            'proof'     => 'KTM Jessica Lumintang dan bukti kesesuaian tulisan tangan pada lembar catatan.',
            'features'  => 'Cover bening, tulisan nama Jessica Lumintang 21081009 di halaman awal, pembatas warna pastel.',
            'status'    => 'completed',
            'reviewer'  => $userMap['Raffi Maulana Hakim'],
            'notes'     => 'Barang telah diverifikasi langsung dan diserahkan di posko meja piket tanggal 15 November.',
            'evidence'  => [
                ['type' => 'secret_details', 'key' => 'Nama di Halaman Depan', 'val' => 'Jessica Lumintang 21081009'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-007',
            'user'      => $userMap['Hendra Simanjuntak'],
            'lost_id'   => $lostIdMap['LR-SIM-04'] ?? null,
            'found_id'  => $foundIdMap['FND-SIM-04'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-06'] ?? null,
            'reason'    => 'TWS Samsung Buds saya tertinggal di laci meja kelas ruang 304 GKB A.',
            'proof'     => 'Kotak kemasan asli Samsung Galaxy Buds 2 Pro dengan nomor IMEI/SN yang sesuai.',
            'features'  => 'Casing silikon ada robekan kecil 3mm di dekat lubang kabel Type-C.',
            'status'    => 'pending',
            'reviewer'  => null,
            'notes'     => null,
            'evidence'  => [
                ['type' => 'serial_number', 'key' => 'Serial Number Case', 'val' => 'R52N601XXXX tertera di dus asli'],
            ],
        ],
        [
            'num'       => 'CLM-DEMO-008',
            'user'      => $userMap['Nabila Azzahra'],
            'lost_id'   => null,
            'found_id'  => $foundIdMap['FND-DEMO-008'] ?? null,
            'item_id'   => $itemIdMap['DEMO-ITM-07'] ?? null,
            'reason'    => 'Tas ransel Eiger tertinggal saat menunggu teman di lobi GKB A.',
            'proof'     => 'Kunci loker yang tersimpan di dalam saku depan tas.',
            'features'  => 'Di saku samping terdapat payung lipat hitam kecil berstiker bunga.',
            'status'    => 'under_review',
            'reviewer'  => $userMap['Nadira Kusumawardhani'],
            'notes'     => 'Petugas sedang memverifikasi isi bawaan tas sesuai deskripsi.',
            'evidence'  => [
                ['type' => 'secret_details', 'key' => 'Isi Saku Samping', 'val' => 'Payung lipat hitam kecil'],
            ],
        ],
    ];

    $checkClaimStmt = $pdo->prepare("SELECT id FROM claims WHERE claim_number = ? LIMIT 1");
    $insertClaimStmt = $pdo->prepare("
        INSERT INTO claims (claim_number, claimant_user_id, lost_report_id, found_report_id, item_id, claim_reason, ownership_proof_description, unique_features, status, reviewed_by_user_id, reviewer_notes, reviewed_at, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? IS NOT NULL, NOW(), NULL), NOW(), NOW())
    ");

    $insertEvidenceStmt = $pdo->prepare("
        INSERT INTO claim_evidence (claim_id, evidence_type, evidence_key, evidence_value, is_verified, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");

    $claimIdMap = [];
    foreach ($claimsData as $cd) {
        $checkClaimStmt->execute([$cd['num']]);
        $cid = $checkClaimStmt->fetchColumn();
        if (!$cid) {
            $insertClaimStmt->execute([
                $cd['num'],
                $cd['user'],
                $cd['lost_id'],
                $cd['found_id'],
                $cd['item_id'],
                $cd['reason'],
                $cd['proof'],
                $cd['features'],
                $cd['status'],
                $cd['reviewer'],
                $cd['notes'],
                $cd['reviewer'],
            ]);
            $cid = $pdo->lastInsertId();

            if (!empty($cd['evidence'])) {
                foreach ($cd['evidence'] as $ev) {
                    $insertEvidenceStmt->execute([$cid, $ev['type'], $ev['key'], $ev['val']]);
                }
            }
        }
        $claimIdMap[$cd['num']] = (int)$cid;
    }

    echo "✓ 8 Pengajuan klaim kepemilikan berstatus campuran terdaftar.\n";

    // -------------------------------------------------------------
    // 9. Seeding 3 Ruang Chat (Chat Rooms, Participants, Messages)
    // -------------------------------------------------------------
    $chatData = [
        [
            'code'        => 'ROOM-CLM-001',
            'claim_id'    => $claimIdMap['CLM-DEMO-001'] ?? null,
            'creator'     => $userMap['Bagas Prakoso'],
            'participant' => $userMap['Raffi Maulana Hakim'],
            'messages'    => [
                ['sender' => 'Raffi Maulana Hakim', 'msg' => 'Halo Bagas Prakoso, klaim dompet Baellerry hitam Anda sedang dalam pemeriksaan petugas. Apakah ada tanda kartu lain di dalam dompet?'],
                ['sender' => 'Bagas Prakoso',        'msg' => 'Halo Pak Raffi, di dalam dompet selain KTM ada kartu minimarket Alfagift dan foto pas 3x4 berlatar merah terselip di saku.'],
                ['sender' => 'Raffi Maulana Hakim', 'msg' => 'Baik, keterangan tersebut sangat spesifik dan cocok dengan fisik barang di posko. Kami akan segera memperbarui status verifikasi Anda.'],
            ],
        ],
        [
            'code'        => 'ROOM-CLM-002',
            'claim_id'    => $claimIdMap['CLM-DEMO-002'] ?? null,
            'creator'     => $userMap['Dimas Aryaguna'],
            'participant' => $userMap['Nadira Kusumawardhani'],
            'messages'    => [
                ['sender' => 'Dimas Aryaguna',        'msg' => 'Selamat siang Bu Nadira, izin menanyakan perkembangan verifikasi kunci Honda Vario saya.'],
                ['sender' => 'Nadira Kusumawardhani', 'msg' => 'Selamat siang Dimas. Kunci dan remote smartkey sudah aman di posko meja piket. Apakah ada gantungan lain selain tali One Heart?'],
                ['sender' => 'Dimas Aryaguna',        'msg' => 'Ada gantungan miniatur helm putih kecil terpasang di ring yang sama, Bu.'],
                ['sender' => 'Nadira Kusumawardhani', 'msg' => 'Sempurna, data sudah diverifikasi cocok. Silakan datang ke Posko Gedung Rektorat Lt. 1 untuk serah terima.'],
            ],
        ],
        [
            'code'        => 'ROOM-CLM-003',
            'claim_id'    => $claimIdMap['CLM-DEMO-003'] ?? null,
            'creator'     => $userMap['Muhammad Rizqullah'],
            'participant' => $userMap['Raffi Maulana Hakim'],
            'messages'    => [
                ['sender' => 'Raffi Maulana Hakim',   'msg' => 'Halo Muhammad Rizqullah, mohon unggah foto KTM pendukung untuk verifikasi kepemilikan kartu ATM BCA Anda.'],
                ['sender' => 'Muhammad Rizqullah',    'msg' => 'Siap Pak Raffi, saya sudah melampirkan screenshot profil m-banking dan akan membawa KTM fisik saat pengambilan.'],
                ['sender' => 'Raffi Maulana Hakim',   'msg' => 'Terima kasih, berkas Anda akan kami tinjau ulang hari ini.'],
            ],
        ],
    ];

    $checkRoomStmt = $pdo->prepare("SELECT id FROM chat_rooms WHERE room_code = ? LIMIT 1");
    $insertRoomStmt = $pdo->prepare("
        INSERT INTO chat_rooms (room_code, claim_id, created_by_user_id, status, last_message_at, created_at, updated_at)
        VALUES (?, ?, ?, 'active', NOW(), NOW(), NOW())
    ");
    $insertPartStmt = $pdo->prepare("
        INSERT IGNORE INTO chat_participants (room_id, user_id, role_in_room, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $insertMsgStmt = $pdo->prepare("
        INSERT INTO chat_messages (room_id, sender_user_id, message_type, message, is_read, created_at)
        VALUES (?, ?, 'text', ?, 1, NOW())
    ");

    foreach ($chatData as $cd) {
        $checkRoomStmt->execute([$cd['code']]);
        $roomId = $checkRoomStmt->fetchColumn();
        if (!$roomId) {
            $insertRoomStmt->execute([$cd['code'], $cd['claim_id'], $cd['creator']]);
            $roomId = $pdo->lastInsertId();

            // Daftarkan Partisipan (Civitas & Petugas)
            $insertPartStmt->execute([$roomId, $cd['creator'], 'claimant']);
            $insertPartStmt->execute([$roomId, $cd['participant'], 'petugas']);

            // Masukkan Percakapan
            foreach ($cd['messages'] as $m) {
                $senderId = $userMap[$m['sender']] ?? $cd['creator'];
                $insertMsgStmt->execute([$roomId, $senderId, $m['msg']]);
            }
        }
    }

    echo "✓ 3 Ruang obrolan internal (Chat Rooms & Messages) terdaftar.\n";

    // -------------------------------------------------------------
    // 10. Seeding Notifikasi In-App Pengguna
    // -------------------------------------------------------------
    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, payload, is_read, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $demoNotifs = [
        [
            'user'    => $userMap['Bagas Prakoso'],
            'type'    => 'claim_status_changed',
            'payload' => json_encode([
                'title'   => 'Pengajuan Klaim Diterima',
                'message' => 'Klaim Anda untuk Dompet Baellerry Hitam telah tercatat di posko.',
                'url'     => '/claims/' . ($claimIdMap['CLM-DEMO-001'] ?? 1),
            ], JSON_UNESCAPED_UNICODE),
            'read'    => 1,
        ],
        [
            'user'    => $userMap['Farrel Nugraha'],
            'type'    => 'claim_approved',
            'payload' => json_encode([
                'title'   => 'Klaim Anda Telah Disetujui!',
                'message' => 'Tumbler Corkcicle Canteen putih siap diambil di Posko Lost & Found Center.',
                'url'     => '/claims/' . ($claimIdMap['CLM-DEMO-004'] ?? 4),
            ], JSON_UNESCAPED_UNICODE),
            'read'    => 0,
        ],
        [
            'user'    => $userMap['Dimas Aryaguna'],
            'type'    => 'chat_message_received',
            'payload' => json_encode([
                'title'   => 'Pesan Baru dari Petugas Nadira',
                'message' => 'Data kunci motor Honda Vario Anda telah diverifikasi cocok.',
                'url'     => '/chats',
            ], JSON_UNESCAPED_UNICODE),
            'read'    => 0,
        ],
        [
            'user'    => $userMap['Jessica Lumintang'],
            'type'    => 'handover_completed',
            'payload' => json_encode([
                'title'   => 'Serah Terima Selesai',
                'message' => 'Buku Binder Kokuyo B5 telah diserahkan kembali kepada Anda. Terima kasih.',
                'url'     => '/claims/' . ($claimIdMap['CLM-DEMO-006'] ?? 6),
            ], JSON_UNESCAPED_UNICODE),
            'read'    => 1,
        ],
        [
            'user'    => $userMap['Raffi Maulana Hakim'],
            'type'    => 'new_claim_submitted',
            'payload' => json_encode([
                'title'   => 'Klaim Baru Menunggu Review',
                'message' => 'Bagas Prakoso mengajukan klaim untuk Dompet Baellerry Hitam.',
                'url'     => '/petugas/claims',
            ], JSON_UNESCAPED_UNICODE),
            'read'    => 0,
        ],
    ];

    foreach ($demoNotifs as $dn) {
        $notifStmt->execute([$dn['user'], $dn['type'], $dn['payload'], $dn['read']]);
    }

    echo "✓ Notifikasi in-app pengguna berhasil diinjeksikan.\n";

    // -------------------------------------------------------------
    // 11. Seeding Match Candidates untuk 5 Pasang Mirip (AI Matching)
    // -------------------------------------------------------------
    $insertMatchStmt = $pdo->prepare("
        INSERT INTO match_candidates (lost_report_id, found_report_id, relevance_score, matching_explanation, source, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'ai', 'pending', NOW(), NOW())
        ON DUPLICATE KEY UPDATE relevance_score = VALUES(relevance_score), matching_explanation = VALUES(matching_explanation)
    ");

    $similarPairsMeta = [
        ['lr' => 'LR-SIM-01', 'fr' => 'FND-SIM-01', 'score' => 92.50, 'reason' => 'Kategori Dompet & Tas identik, lokasi Kantin Utama cocok, token kemiripan Baellerry & dompet hitam sangat tinggi.'],
        ['lr' => 'LR-SIM-02', 'fr' => 'FND-SIM-02', 'score' => 88.00, 'reason' => 'Kategori Kunci identik, lokasi Parkir Barat cocok, kemiripan atribut Honda Vario, remote smartkey, & gantungan One Heart.'],
        ['lr' => 'LR-SIM-03', 'fr' => 'FND-SIM-03', 'score' => 90.00, 'reason' => 'Kategori Lain-lain cocok, lokasi Perpustakaan Pusat cocok, deskripsi spesifik Corkcicle Canteen 16oz putih matte.'],
        ['lr' => 'LR-SIM-04', 'fr' => 'FND-SIM-04', 'score' => 94.00, 'reason' => 'Kategori Elektronik cocok, lokasi GKB A cocok, kesamaan Samsung Galaxy Buds 2 Pro Bora Purple & case silikon.'],
        ['lr' => 'LR-SIM-05', 'fr' => 'FND-SIM-05', 'score' => 89.50, 'reason' => 'Kategori Buku cocok, lokasi GKB B cocok, kesamaan Binder Kokuyo Campus B5 mika bening catatan kalkulus.'],
    ];

    foreach ($similarPairsMeta as $sp) {
        $lId = $lostIdMap[$sp['lr']] ?? null;
        $fId = $foundIdMap[$sp['fr']] ?? null;
        if ($lId && $fId) {
            $insertMatchStmt->execute([$lId, $fId, $sp['score'], $sp['reason']]);
        }
    }

    echo "✓ 5 Pasangan kandidat pencocokan AI (Match Candidates) siap untuk demo.\n";

    $pdo->commit();
    echo "\n========================================================================\n";
    echo "   >>> SEEDER DATA DEMO BERHASIL SELESAI DENGAN SEMPURNA! <<<   \n";
    echo "========================================================================\n\n";

    // -------------------------------------------------------------
    // LAPORAN HASIL SEEDING
    // -------------------------------------------------------------

    // A. Jumlah Baris per Tabel
    echo "📊 JUMLAH BARIS PER TABEL DATABASE:\n";
    echo "------------------------------------------------------------------------\n";
    $tablesToCheck = [
        'users',
        'roles',
        'categories',
        'locations',
        'storage_locations',
        'items',
        'lost_reports',
        'found_reports',
        'claims',
        'claim_evidence',
        'chat_rooms',
        'chat_participants',
        'chat_messages',
        'notifications',
        'match_candidates',
    ];

    foreach ($tablesToCheck as $tbl) {
        $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        printf("  • %-22s : %4d baris\n", $tbl, $count);
    }
    echo "------------------------------------------------------------------------\n\n";

    // B. ID 5 Pasangan Laporan Hilang-Temuan Mirip (AI Matching Demo)
    echo "🎯 ID 5 PASANGAN LAPORAN HILANG-TEMUAN MIRIP (AI MATCHING DEMO):\n";
    echo "------------------------------------------------------------------------\n";
    $pairIndex = 1;
    foreach ($similarPairsMeta as $sp) {
        $lId = $lostIdMap[$sp['lr']] ?? 0;
        $fId = $foundIdMap[$sp['fr']] ?? 0;
        $lrTitle = $pdo->query("SELECT title FROM lost_reports WHERE id = {$lId}")->fetchColumn() ?: '-';
        $frTitle = $pdo->query("SELECT title FROM found_reports WHERE id = {$fId}")->fetchColumn() ?: '-';
        echo "  Pasang #{$pairIndex} (Skor: {$sp['score']}%):\n";
        echo "    - Laporan Hilang ID #{$lId} ({$sp['lr']}) : {$lrTitle}\n";
        echo "    - Laporan Temuan ID #{$fId} ({$sp['fr']}) : {$frTitle}\n";
        echo "    - Alasan Kecocokan   : {$sp['reason']}\n\n";
        $pairIndex++;
    }
    echo "------------------------------------------------------------------------\n\n";

    // C. 10 Pertanyaan Chatbot Contoh beserta Jawaban yang Diharapkan
    echo "💬 10 CONTOH PERTANYAAN CHATBOT (C-FIND) & JAWABAN YANG DIHARAPKAN:\n";
    echo "------------------------------------------------------------------------\n";

    $qaList = [
        // Civitas
        [
            'role'   => 'Civitas',
            'q'      => 'Apakah ada dompet hitam yang ditemukan di kantin hari ini?',
            'answer' => 'Ya, ada laporan temuan dompet pria kulit hitam merk Baellerry di Kantin Utama Kampus pada hari ini (FND-SIM-01). Silakan periksa detailnya atau ajukan klaim kepemilikan bila itu milik Anda.',
        ],
        [
            'role'   => 'Civitas',
            'q'      => 'Di mana alamat posko Lost & Found dan jam buka pelayanannya?',
            'answer' => 'Pusat Layanan Lost & Found Center berlokasi di Gedung Rektorat Lantai 1 (Ruang Piket Layanan Terpadu), melayani civitas setiap hari Senin sampai Jumat pukul 08.00 hingga 16.00 WIB.',
        ],
        [
            'role'   => 'Civitas',
            'q'      => 'Bagaimana status pengajuan klaim dompet saya?',
            'answer' => 'Pengajuan klaim Anda (CLM-DEMO-001) untuk dompet lipat Baellerry hitam saat ini berstatus "pending" (menunggu peninjauan awal oleh petugas posko).',
        ],
        [
            'role'   => 'Civitas',
            'q'      => 'Saya kehilangan botol minum Corkcicle putih di perpustakaan, apakah sudah ada yang menemukan?',
            'answer' => 'Ya, terdapat laporan barang temuan botol minum tumbler Corkcicle Canteen 16oz putih gloss di Perpustakaan Pusat lantai 2 (FND-SIM-03). Anda dapat mengajukan klaim dengan menyertakan bukti kepemilikan.',
        ],
        // Petugas
        [
            'role'   => 'Petugas',
            'q'      => 'Berapa banyak barang fisik yang saat ini tersimpan di posko?',
            'answer' => 'Saat ini terdapat 10 item inventaris resmi yang terdaftar di posko meja piket, mencakup barang berstatus tersimpan (stored), dalam proses klaim (in_claim), dan yang telah diserahkan (returned).',
        ],
        [
            'role'   => 'Petugas',
            'q'      => 'Berapa jumlah klaim masuk yang membutuhkan tindakan verifikasi?',
            'answer' => 'Terdapat 4 antrean klaim aktif yang membutuhkan tindak lanjut petugas: 2 klaim berstatus "pending" dan 2 klaim berstatus "under_review".',
        ],
        [
            'role'   => 'Petugas',
            'q'      => 'Barang apa saja yang tersimpan di Rak Elektronik & Gadget (Zona A)?',
            'answer' => 'Di Rak Elektronik A-01 (Zona A, STG-EL-01) saat ini tersimpan iPad Mini 6 Space Gray (DEMO-ITM-01) dan TWS Samsung Galaxy Buds 2 Pro Bora Purple (DEMO-ITM-06).',
        ],
        // Admin
        [
            'role'   => 'Admin',
            'q'      => 'Berapa total laporan kehilangan dan laporan penemuan yang terdaftar dalam sistem?',
            'answer' => 'Sistem mencatat total 30 laporan kehilangan (Lost Reports) dan 25 laporan barang temuan (Found Reports) yang tersebar merata di 7 kategori kampus.',
        ],
        [
            'role'   => 'Admin',
            'q'      => 'Kategori apa yang paling banyak dilaporkan oleh civitas akademika?',
            'answer' => 'Kategori dengan frekuensi laporan tertinggi adalah Elektronik & Gadget, Dompet & Tas, serta Kunci & Aksesoris, dengan konsentrasi lokasi utama di Kantin, Perpustakaan, dan Parkir.',
        ],
        [
            'role'   => 'Admin',
            'q'      => 'Bagaimana ringkasan serah terima barang (handover) di posko?',
            'answer' => 'Dari 8 pengajuan klaim, 1 klaim telah selesai diserahterimakan (completed atas nama Jessica Lumintang), 1 klaim disetujui (approved), dan 1 klaim ditolak (rejected) karena ketidakcocokan bukti.',
        ],
    ];

    $qIndex = 1;
    foreach ($qaList as $item) {
        echo "{$qIndex}. [{$item['role']}] Pertanyaan : \"{$item['q']}\"\n";
        echo "   👉 Jawaban Diharapkan : {$item['answer']}\n\n";
        $qIndex++;
    }
    echo "------------------------------------------------------------------------\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Gagal menjalankan seeder demo: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " baris " . $e->getLine() . "\n";
    exit(1);
}
