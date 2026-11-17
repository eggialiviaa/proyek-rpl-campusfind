# AUDIT REPORT — CAMPUSFIND ARCHITECTURE, SECURITY & CODEBASE VERIFICATION

> **Tanggal Audit:** 17 November 2026  
> **Target Analisis:** Repositori Bersih (`campusfind-clean`)  
> **Status Verifikasi:** Diverifikasi Berdasarkan Kode Sumber Riil (*Zero Assumption*)

---

## 1. DIRECTORY TREE (STRUKTUR DIREKTORI & BERKAS TERVERIFIKASI)

Seluruh berkas dan direktori di bawah ini telah diverifikasi keberadaannya secara fisik pada lingkungan repositori:

```
campusfind-clean/
├── .github/
│   └── workflows/
│       └── ci.yml                          # GitHub Actions CI workflow (PHP 8.3 + PHPUnit)
├── app/
│   ├── Config/
│   │   └── cfind_schema.php                # Definisi skema semantik & permission role C-Find
│   ├── Controllers/
│   │   ├── AdminController.php             # Manajemen pengguna, master data, dan pengaturan sistem
│   │   ├── AiMatchController.php           # Tampilan komparasi visual & eksekusi pencocokan AI
│   │   ├── AuthController.php              # Autentikasi, login NIM/email, registrasi, aktivasi, OTP
│   │   ├── CFindController.php             # Endpoint API streaming/JSON chatbot C-Find
│   │   ├── ChatController.php              # Ruang obrolan internal antara civitas dan petugas posko
│   │   ├── CivitasController.php           # Dashboard civitas dan ringkasan aktivitas mandiri
│   │   ├── ClaimController.php             # Pengajuan dan pemantauan klaim kepemilikan oleh civitas
│   │   ├── DashboardController.php         # Pengarah dashboard berbasis role (RBAC router)
│   │   ├── FileController.php              # Pengendali unduhan berkas bukti terlindungi
│   │   ├── FoundReportController.php       # Pelaporan dan katalog publik barang temuan
│   │   ├── HomeController.php              # Halaman beranda utama dan pencarian cepat
│   │   ├── LostReportController.php        # Pelaporan dan katalog publik barang hilang
│   │   ├── NotificationController.php      # Pusat notifikasi pengguna dan penandaan baca
│   │   ├── PetugasClaimController.php      # Verifikasi bukti klaim bertahap dan persetujuan petugas
│   │   ├── PetugasController.php           # Operasional meja piket, intake fisik, dan inventaris rak
│   │   ├── ProfileController.php           # Pembaruan profil, nomor telepon, dan ganti kata sandi
│   │   ├── ReportManagementController.php  # Tata kelola seluruh laporan bagi administrator
│   │   └── SearchController.php            # Pencarian terpadu laporan dan barang posko
│   ├── Middleware/
│   │   ├── AuthMiddleware.php              # Pengawal autentikasi sesi dan otorisasi peran (RBAC)
│   │   └── CsrfMiddleware.php              # Validasi token anti-CSRF pada request mutasi (POST)
│   ├── Models/
│   │   ├── CategoryModel.php               # Data model kategori barang
│   │   ├── ChatModel.php                   # Data model percakapan dan pesan internal
│   │   ├── ClaimModel.php                  # Data model klaim kepemilikan dan bukti verifikasi
│   │   ├── FoundReportModel.php            # Data model laporan penemuan barang
│   │   ├── ItemModel.php                   # Data model barang posko fisik dan status handover
│   │   ├── LocationModel.php               # Data model lokasi kampus
│   │   ├── LostReportModel.php             # Data model laporan kehilangan barang
│   │   ├── MatchCandidateModel.php         # Data model kandidat pencocokan AI dan riwayat skor
│   │   ├── NotificationModel.php           # Data model notifikasi dalam aplikasi (in-app)
│   │   ├── SearchModel.php                 # Abstraksi query pencarian teks multi-kriteria
│   │   ├── StorageLocationModel.php        # Data model kapasitas dan lokasi rak penyimpanan posko
│   │   └── UserModel.php                   # Data model pengguna, role, dan kredensial
│   ├── Services/
│   │   ├── CFind/
│   │   │   ├── AbstractRoleProfile.php     # Abstraksi batasan profil peran pada C-Find
│   │   │   ├── AdminProfile.php            # Hak akses dan batasan prompt C-Find Admin
│   │   │   ├── CFindHistoryService.php     # Pengelolaan riwayat sesi obrolan C-Find
│   │   │   ├── CivitasProfile.php          # Hak akses dan batasan prompt C-Find Civitas
│   │   │   ├── DataMasker.php              # Redaksi informasi sensitif (PII) sebelum pemrosesan AI
│   │   │   ├── DataQueryHandler.php        # Kompiler dan eksekutor kueri data semantik
│   │   │   ├── EntityFocusResolver.php     # Resolusi fokus entitas percakapan interaktif
│   │   │   ├── GuestProfile.php            # Hak akses C-Find mode Tamu (hanya FAQ dan info umum)
│   │   │   ├── PetugasProfile.php          # Hak akses dan batasan prompt C-Find Petugas Posko
│   │   │   ├── QueryCompiler.php           # Penterjemah rencana query JSON menjadi query aman
│   │   │   ├── QueryPlanner.php            # Perencana query semantik C-Find
│   │   │   ├── QueryValidator.php          # Validasi skema kueri JSON terhadap batasan role & field
│   │   │   ├── ResponsePresenter.php       # Pemformat respons obrolan ramah pengguna
│   │   │   └── SemanticSchema.php          # Abstraksi izin entitas, atribut, dan relasi C-Find
│   │   ├── Notifications/
│   │   │   ├── EmailSmtpDriver.php         # Pengirim email aktual via PHPMailer SMTP
│   │   │   ├── FakeNotificationDriver.php  # Pengemudi tiruan untuk pengujian terisolasi
│   │   │   ├── MailGateway.php             # Gerbang validasi alamat penerima, allowlist, & limit
│   │   │   ├── NotificationChannelInterface.php # Antarmuka saluran notifikasi
│   │   │   ├── NotificationDecisionService.php  # Pencatat log keputusan pengiriman notifikasi
│   │   │   ├── NotificationOutboxService.php    # Antrean asinkron pengiriman email (outbox pattern)
│   │   │   └── NotificationTemplateService.php  # Penata variabel dan kompilasi template email
│   │   ├── AccountManagementService.php    # Layanan pembuatan akun, registrasi, dan aktivasi massal
│   │   ├── AiMatchService.php              # Layanan orkestrasi pencocokan laporan hilang & temuan
│   │   ├── AiProviderInterface.php         # Kontrak antarmuka adapter penyedia kecerdasan buatan
│   │   ├── AiProviderManager.php           # Manajemen multi-provider AI & penugasan fitur sistem
│   │   ├── AuditLogService.php             # Pencatatan riwayat audit keamanan dan mutasi data
│   │   ├── CFindService.php                # Layanan inti chatbot kampus (Tier 1 & Tier 2)
│   │   ├── ChatService.php                 # Logika bisnis obrolan internal civitas dan petugas
│   │   ├── CivitasImportService.php        # Impor data mahasiswa/dosen dari lembar kerja Excel/CSV
│   │   ├── FileUploadService.php           # Validasi MIME type, ukuran, dan penyimpanan berkas unggahan
│   │   ├── GoogleGeminiService.php         # Adapter koneksi REST API Google Gemini
│   │   ├── InventoryReconciliationService.php # Rekonsiliasi kapasitas rak penyimpanan fisik posko
│   │   ├── ItemCatalogService.php          # Manajemen katalog barang publik dan filtering
│   │   ├── MessageCatalog.php              # Katalog pesan antarmuka dan teks sistem
│   │   ├── MetricDefinitionService.php     # Definisi metrik operasional cepat untuk asisten C-Find
│   │   ├── MockAiMatchService.php          # Fallback pencocokan berbasis algoritma teks lokal
│   │   ├── NotificationService.php         # Layanan pusat penerbitan notifikasi sistem
│   │   ├── NvidiaNimService.php            # Adapter koneksi API OpenAI-compatible NVIDIA NIM
│   │   ├── OpenAICompatibleService.php     # Adapter koneksi custom endpoint OpenAI-compatible
│   │   ├── OtpService.php                  # Pengelolaan kode OTP, HMAC SHA-256, dan validasi password
│   │   ├── RateLimiterService.php          # Pembatas laju request IP dan akun pengguna
│   │   ├── SettingService.php              # Layanan konfigurasi dinamis & enkripsi AES-256-CBC
│   │   └── StatusHistoryService.php        # Pencatatan riwayat transisi siklus hidup laporan
│   └── Views/                              # Template antarmuka pengguna (Blade-like PHP Native)
├── bin/
│   └── campus                              # CLI tool utilitas operasional
├── cli/
│   └── notifications_worker.php            # Worker pengirim antrean email latar belakang
├── config/
│   ├── app.php                             # Pengaturan umum aplikasi, zona waktu, dan environment
│   ├── cfind_schema.php                    # Skema izin entitas data C-Find
│   ├── database.php                        # Konfigurasi koneksi basis data PDO MySQL
│   └── routes.php                          # Definisi rute URL aplikasi dan penugasan controller
├── core/
│   ├── App.php                             # Bootstrapper aplikasi dan penanganan error
│   ├── BaseController.php                  # Kontroler dasar dengan helper render view dan JSON
│   ├── BaseModel.php                       # Model dasar dengan abstraksi query PDO prepared statements
│   ├── Database.php                        # Singleton pembungkus koneksi PDO MySQL
│   └── Router.php                          # Router HTTP dengan pencocokan parameter dinamis
├── database/
│   ├── create_admin.php                    # CLI interaktif pembuatan akun administrator
│   ├── migrate.php                         # Runner migrasi otomatis basis data
│   ├── schema.sql                          # Skema DDL lengkap 24 tabel relasional
│   ├── seeds.sql                           # Master data referensi dan konfigurasi awal
│   ├── seed_demo.php                       # Pengisi data contoh (demo) untuk lingkungan lokal
│   └── migrations/                         # Arsip berkas migrasi SQL dan PHP historis
├── docs/                                   # Dokumentasi desain token, FAQ, dan audit UI
├── public/                                 # Document root publik web server
│   ├── css/                                # Berkas stylesheet (app.css, landing.css, dll)
│   ├── js/                                 # Skrip JavaScript interaktif frontend
│   ├── images/                             # Aset grafis statis
│   ├── index.php                           # Titik masuk utama seluruh HTTP request
│   └── .htaccess                           # Konfigurasi rewrite engine Apache
├── storage/
│   ├── logs/                               # Direktori berkas log (.gitkeep)
│   └── uploads/                            # Direktori penyimpanan berkas unggahan (.gitkeep)
├── tests/
│   ├── bootstrap.php                       # Bootstrapper lingkungan pengujian PHPUnit
│   ├── Unit/                               # Unit test murni tanpa database & tanpa API eksternal
│   │   ├── AiMatchServiceScoreTest.php     # Pengujian kalkulasi skor MockAiMatchService & baseline
│   │   ├── DataMaskerTest.php              # Pengujian redaksi PII, penyamaran nama, email, & row
│   │   ├── OtpValidationRulesTest.php      # Pengujian validasi OTP, kekuatan sandi, & normalisasi
│   │   ├── QueryValidatorTest.php          # Pengujian validasi schema query, role limit, & forbidden
│   │   ├── SessionTimeoutTest.php          # Pengujian evaluasi timeout sesi defensif
│   │   └── SettingServiceEncryptionTest.php# Pengujian enkripsi/dekripsi AES-256-CBC simetris
│   └── manual/                             # Skrip uji manual & verifikasi regresi historis
├── .env.example                            # Templat variabel lingkungan tanpa rahasia (UTF-8 no BOM)
├── .gitignore                              # Aturan pengecualian repositori git (UTF-8 no BOM)
├── .htaccess                               # Pengaman akses direktori akar
├── AUDIT_REPORT.md                         # Laporan audit arsitektur dan status verifikasi ini
├── composer.json                           # Manifes dependensi PHP Composer
├── composer.lock                           # File pengunci versi dependensi Composer
├── phpunit.xml                             # Konfigurasi runner pengujian PHPUnit
├── README.md                               # Panduan teknis utama proyek (UTF-8 no BOM)
└── TECH_STACK.md                           # Rincian arsitektur teknologi pendukung
```

---

## 2. TECH STACK & VERSI SISTEM

| Komponen | Spesifikasi & Versi | Catatan Implementasi |
| :--- | :--- | :--- |
| **Bahasa & Runtime** | PHP 8.3 (kompatibel PHP >= 8.0) | Strict typing (`declare(strict_types=1)`), typed properties |
| **Arsitektur Aplikasi** | Native MVC | PSR-4 autoloading via Composer, zero heavy framework overhead |
| **Basis Data** | MySQL 8.x / MariaDB 10.4+ | InnoDB Engine, Charset `utf8mb4_unicode_ci` |
| **Unit Testing** | PHPUnit 11.5.58 (`require-dev`) | Suite `tests/Unit/` murni tanpa koneksi database & tanpa API |
| **Continuous Integration** | GitHub Actions (`.github/workflows/ci.yml`) | Runner `ubuntu-latest`, PHP 8.3, `composer validate`, `vendor/bin/phpunit` |
| **Frontend Framework** | Bootstrap 5.3 + Bootstrap Icons 1.11 | Lucide Icons, Custom Aura Liquid Glass Design System |
| **Dependensi Composer** | `vlucas/phpdotenv` (^5.6) | Pemuatan variabel lingkungan dari `.env` |
| | `phpmailer/phpmailer` (^7.1) | Pengiriman email notifikasi via SMTP |
| | `phpoffice/phpspreadsheet` (^5.10) | Pembaca berkas impor data sivitas akademika |

---

## 3. SPESIFIKASI BASIS DATA (24 TABEL RELASIONAL)

Seluruh 24 tabel berikut dihitung dan diverifikasi langsung dari skrip DDL `database/schema.sql`:

| No | Nama Tabel | Deskripsi & Tujuan Fungsional |
| :---: | :--- | :--- |
| 1 | `roles` | Master peran otorisasi pengguna (`civitas`, `petugas`, `admin`). |
| 2 | `users` | Akun pengguna, hash kata sandi Argon2id/BCrypt, peran, status aktivasi, dan soft delete. |
| 3 | `categories` | Master kategori jenis barang (Elektronik, Dokumen, Aksesoris, dll). |
| 4 | `locations` | Master lokasi kampus tempat barang hilang atau ditemukan. |
| 5 | `storage_locations` | Manajemen rak penyimpanan fisik posko Lost & Found (Zona A–E) beserta kapasitas maksimal. |
| 6 | `items` | Pencatatan inventaris barang temuan fisik yang dikelola oleh posko Lost & Found Center. |
| 7 | `lost_reports` | Laporan kehilangan barang yang diajukan oleh civitas akademika. |
| 8 | `found_reports` | Laporan penemuan barang oleh civitas maupun registrasi langsung oleh petugas. |
| 9 | `claims` | Pengajuan klaim kepemilikan barang temuan oleh pengguna. |
| 10 | `claim_evidence` | Bukti verifikasi klaim (ciri rahasia, nomor seri, dokumen pendukung kepemilikan). |
| 11 | `match_candidates` | Riwayat kandidat pasangan laporan hilang dan temuan hasil kalkulasi skor AI/algoritma. |
| 12 | `chat_rooms` | Ruang percakapan terisolasi antara pelapor/pemilik barang dan petugas posko. |
| 13 | `chat_participants` | Pemetaan peserta yang memiliki hak akses terhadap ruang percakapan obrolan. |
| 14 | `chat_messages` | Pesan teks obrolan internal (tersimpan dalam plaintext, dilindungi otorisasi peran). |
| 15 | `notifications` | Notifikasi dalam aplikasi (*in-app notifications*) untuk pengguna. |
| 16 | `notification_templates` | Templat pesan dan subjek email sistem dengan substitusi penampung variabel. |
| 17 | `notification_outbox` | Antrean pengiriman email asinkron (*outbox pattern*) untuk menjaga responsivitas web. |
| 18 | `notification_decisions` | Jejak audit keputusan pengiriman notifikasi (status dikirim, diantrekan, atau dilewati). |
| 19 | `otp_verifications` | Penyimpanan hash HMAC SHA-256 kode OTP email, salt, masa kedaluwarsa, dan pembatas percobaan. |
| 20 | `status_histories` | Rekam jejak kronologis perubahan status laporan, barang, dan klaim. |
| 21 | `audit_logs` | Audit trail aktivitas sensitif pengguna dan sistem dengan pembersihan data privat. |
| 22 | `system_settings` | Konfigurasi sistem dinamis dan penyimpanan kredensial API key/SMTP terenkripsi. |
| 23 | `login_attempts` | Pencatatan percobaan masuk akun untuk pencegahan serangan brute-force. |
| 24 | `cfind_chat_history` | Riwayat percakapan pengguna dengan asisten AI C-Find untuk pemantauan administrator. |

---

## 4. STATUS VERIFIKASI FITUR APLIKASI

### A. Autentikasi, Profil & Keamanan Akun
- **Akses Multi-Role (RBAC):** Pemisahan hak akses ketat antara Civitas Akademika, Petugas Meja Piket Posko, dan Administrator Sistem via `AuthMiddleware`.
- **Aktivasi Akun & Reset Kata Sandi:** Menggunakan verifikasi OTP 6 digit via email. Kode di-hash menggunakan HMAC SHA-256 dengan salt unik per transaksi (tidak ada plain OTP di basis data). Masa berlaku 5 menit (300 detik), batasan 5 kali percobaan salah sebelum terkunci, dan jeda pengiriman ulang 60 detik.
- **Standar Kekuatan Kata Sandi:** Minimal 8 karakter, mewajibkan kombinasi huruf besar, huruf kecil, dan angka atau simbol.

### B. Pelaporan Barang Hilang & Penemuan
- **Laporan Publik vs Privat:** Judul, kategori, lokasi kejadian, dan foto publik dapat diakses di katalog; atribut pembuktian khusus (*unique features* / *private details*) disembunyikan dari publik.
- **Siklus Hidup Laporan:** Status transisi terdokumentasi rapi (`draft`, `published`, `investigating`, `matched`, `resolved`, `closed`).

### C. Posko Meja Piket & Inventaris Fisik
- **Registrasi Intake Fisik:** Petugas dapat mendaftarkan barang temuan langsung di posko, memberikan kode inventaris unik (*item code*), dan mengalokasikan rak penyimpanan di Zona A–E.
- **Verifikasi Klaim Bertahap:** Petugas memverifikasi bukti kepemilikan yang diunggah pelapor sebelum menjadwalkan protokol serah terima (*handover*).

### D. Komunikasi Internal (Obrolan Petugas - Civitas)
- **Status Enkripsi Chat:** Pesan obrolan disimpan dalam bentuk teks biasa (*plaintext*) pada tabel `chat_messages` di MySQL. Proteksi keamanan obrolan didasarkan pada otorisasi sesi pengguna dan validasi hak akses ruang obrolan (`chat_participants`), **bukan** enkripsi end-to-end (E2EE).
- **Klaim Enkripsi yang Terbukti:** Komponen yang **terenkripsi secara kriptografis (AES-256-CBC)** di basis data hanyalah nilai rahasia pada `system_settings`, yaitu **API Key penyedia AI** dan **kata sandi SMTP**.

### E. Notifikasi & Outbox Pattern
- **Pengiriman Asinkron:** Web controller tidak melakukan pengiriman SMTP secara blocking. Notifikasi dimasukkan ke antrean `notification_outbox` dan dieksekusi oleh CLI worker (`cli/notifications_worker.php`).
- **MailGateway Guard:** Mengevaluasi allowlist penerima, limit harian, dan kegagalan beruntun (*circuit breaker*) sebelum mengirim email.

---

## 5. STATUS AI MATCHING DAN CHATBOT C-FIND

### A. Sistem Manajemen Provider AI Terpadu (`AiProviderManager`)
Aplikasi mendukung beberapa adapter AI melalui antarmuka `AiProviderInterface`:
1. **NVIDIA NIM (`NvidiaNimService`):** Endpoint OpenAI-compatible pada hosted API catalog NVIDIA.
2. **Google Gemini (`GoogleGeminiService`):** Endpoint REST OpenAI-compatible pada Google Generative Language API.
3. **Custom OpenAI-Compatible (`OpenAICompatibleService`):** Endpoint custom mandiri seperti Ollama lokal atau API proksi.

Kredensial disimpan terenkripsi simetris (AES-256-CBC) pada tabel `system_settings` (`ai_provider_*_key`). Kunci dekripsi diturunkan dari `APP_KEY` melalui SHA-256.

### B. Status Fitur AI Matching (`AiMatchService`)
Fitur pencocokan barang hilang dan barang temuan dirancang dengan arsitektur 3 tahap berprinsip *Human-in-the-Loop*:
1. **Tahap 1 (Pra-seleksi Aturan):** Memfilter pool laporan berdasarkan kelayakan status, kesamaan mutlak kategori barang, dan rentang tanggal yang masuk akal (-1 hari hingga +60 hari).
2. **Tahap 2 (Skor Baseline Teks & Metadata):** Menghitung skor kemiripan teks dasar untuk memilih 5 kandidat teratas.
3. **Tahap 3 (Evaluasi Skor Terstruktur):** Menghasilkan skor 0–100% dan narasi alasan kesamaan atribut.

#### Status Ketersediaan Saat API Key Belum Dikonfigurasi / Offline:
- Apabila API Key penyedia AI belum diisi atau server eksternal tidak dapat dihubungi, sistem **secara otomatis beralih (*fallback*) ke `MockAiMatchService`**.
- **`MockAiMatchService` (Local Algorithmic Fallback):**
  - Menggunakan algoritma pemrosesan teks lokal (Token Jaccard Similarity) untuk membandingkan judul dan deskripsi laporan.
  - Menggabungkan kesamaan teks (bobot 60%) dengan kesamaan metadata atribut (bobot 40%: kesamaan kategori bernilai 50 poin, kesamaan lokasi bernilai 30 poin, dan kesesuaian rentang tanggal bernilai 20 poin).
  - Menghasilkan skor deterministik yang transparan disertai penjelasan berlabel `[Fallback Lokal]`.
  - **Tidak ada pembuatan hasil palsu (*no fake production records*):** Jika tidak ada data yang cocok, skor akan tetap rendah atau sistem menyatakan tidak ada kandidat yang memenuhi syarat.

### C. Status Fitur Chatbot Asisten Kampus (`CFindService` / C-Find)
Chatbot interaktif di pojok layar mengadopsi arsitektur 2 tier dengan 5 lapis pengamanan (*5-layer guardrails*):
- **Tier 1 (Pra-filter Aturan & FAQ Cepat):**
  - Menangani pertanyaan panduan kampus (FAQ), instruksi operasional posko, salam pembuka, dan tombol pintasan metrik (*quick chips*) secara deterministik **tanpa memanggil LLM eksternal**.
  - Bagian ini tetap aktif dan berfungsi meskipun API Key AI belum dikonfigurasi.
- **Tier 2 (Asistensi Data Semantik):**
  - Menerjemahkan pertanyaan data pengguna menjadi representasi JSON Semantic Query Plan.
  - Memvalidasi rencana melalui `QueryValidator` untuk memastikan role pengguna tidak mengakses entitas atau kolom terlarang (password, token, bukti klaim rahasia, nomor identitas).
  - Menyamarkan data PII menggunakan `DataMasker` sebelum data dipresentasikan.

#### Status Ketersediaan Saat API Key Belum Dikonfigurasi / Offline:
- Metode `isChatAvailable()` pada adapter AI bernilai `false`.
- Pertanyaan dan panduan Tier 1 tetap dapat merespons kebutuhan dasar pengguna.
- Untuk pertanyaan dinamis yang membutuhkan pemodelan bahasa lanjutan (LLM), controller mengembalikan respons terkelola bahwa layanan kecerdasan buatan sedang tidak aktif atau memerlukan konfigurasi API Key oleh Administrator, tanpa menimbulkan kegagalan fatal (*unhandled crash*).

---

## 6. STATUS PENGUJIAN & INTEGRASI BERKELANJUTAN (CI/CD)

### A. Pengujian Unit Terisolasi (PHPUnit)
- Dependensi `phpunit/phpunit` (^11.5) dipasang di `require-dev`.
- Konfigurasi `phpunit.xml` mengisolasi pengujian pada direktori `tests/Unit/`.
- Seluruh 6 berkas pengujian unit berjalan **100% murni tanpa basis data dan tanpa API eksternal**:
  1. `tests/Unit/DataMaskerTest.php`: Memverifikasi penyamaran nama, sensor email, pemotongan string panjang, dan redaksi kolom rahasia.
  2. `tests/Unit/QueryValidatorTest.php`: Memverifikasi penolakan kueri di luar hak akses role, batasan maksimal 3 kueri, pembatasan 50 baris, dan pemblokiran field terlarang.
  3. `tests/Unit/SettingServiceEncryptionTest.php`: Memverifikasi enkripsi/dekripsi simetris AES-256-CBC, keunikan IV per enkripsi, penolakan payload rusak, dan isolasi APP_KEY.
  4. `tests/Unit/AiMatchServiceScoreTest.php`: Memverifikasi skor kemiripan teks & metadata pada `MockAiMatchService`, penanganan status simulasi, dan kalkulasi baseline skor `AiMatchService` tanpa koneksi PDO.
  5. `tests/Unit/OtpValidationRulesTest.php`: Memverifikasi konstanta siklus OTP (60s cooldown, 300s expiry, 5 max attempts), aturan kekuatan kata sandi, pembuatan kata sandi sementara, verifikasi hash HMAC SHA-256, dan normalisasi nomor telepon.
  6. `tests/Unit/SessionTimeoutTest.php`: Memverifikasi pembacaan defensif `SESSION_LIFETIME` (string kosong, "0", negatif, dan tidak ada harus tetap menghasilkan default 7200 detik yang aman).

### B. Pengorganisasian Berkas Uji Manual
- 12 berkas skrip pengujian manual dan verifikasi terdahulu telah dipindahkan ke direktori `tests/manual/`:
  - `tests/manual/test_audit_masking_regression.php`
  - `tests/manual/test_cfind_regression.php`
  - `tests/manual/test_tahap2a_verification_bounded.php`
  - `tests/manual/test_tahap2b_verification.php`
  - `tests/manual/test_tahap2b1_matching_side_by_side.php`
  - `tests/manual/test_tahap2b1_negative_mock.php`
  - `tests/manual/test_tahap2b1_step3_single.php`
  - `tests/manual/verify_part0_save.php`
  - `tests/manual/verify_partA_regression.php`
  - `tests/manual/verify_partC_custom_provider.php`
  - `tests/manual/verify_section_b_simplification.php`
  - `tests/manual/verify_sidebar_state_polish.php`
- Direktori `tests/manual/` secara eksplisit dikecualikan dari runner PHPUnit pada `phpunit.xml` untuk menjamin eksekusi CI yang cepat, deterministik, dan tanpa ketergantungan lingkungan database lokal.

### C. Otomasi GitHub Actions CI (`.github/workflows/ci.yml`)
- Dikonfigurasi untuk mengeksekusi pipeline pada setiap aksi `push` dan `pull_request`.
- Menggunakan image `ubuntu-latest` dengan runtime PHP 8.3.
- Tahapan otomatis:
  1. Pemeriksaan integritas manifes: `composer validate --strict`.
  2. Cache dependensi Composer untuk kecepatan build.
  3. Pemasangan dependensi: `composer install --prefer-dist --no-progress`.
  4. Eksekusi pengujian unit: `vendor/bin/phpunit`.

---

## 7. KESIMPULAN AUDIT & INTEGRITAS KODE

1. **Integritas Repositori:** Tidak ada dependensi usang atau file pengujian yatim pada direktori utama. Seluruh berkas yang tercantum pada pohon direktori diverifikasi ada dan valid.
2. **Kepatuhan Privasi & Kriptografi:** Standar enkripsi simetris AES-256-CBC diterapkan khusus untuk API Key dan password SMTP. Obrolan internal dilindungi oleh kontrol akses sesi dan peran pengguna.
3. **Ketahanan Fitur AI:** Ketiadaan API Key AI ditangani secara aman melalui fallback lokal berbasis algoritma deterministik (`MockAiMatchService`) pada pencocokan laporan, serta fallback aturan FAQ pada asisten obrolan kampus.
4. **Kesiapan Otomasi Pengujian:** Repositori kini memiliki suite pengujian unit murni berbasis PHPUnit dan konfigurasi alur kerja CI yang siap dijalankan secara mandiri maupun pada platform Git hosting.