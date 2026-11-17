# CampusFind — Lost & Found Academic Management System

> **CampusFind** adalah platform terpadu penanganan barang hilang dan penemuan untuk lingkungan perguruan tinggi. Aplikasi ini mengintegrasikan tata kelola posko fisik Lost & Found, verifikasi klaim bertahap dengan proteksi privasi, komunikasi internal langsung, serta kecerdasan buatan (*AI Assistance*) untuk pencocokan cerdas dan asistensi mahasiswa.

---

## 📌 Fitur Utama

### 1. Tata Kelola Multi-Peran (RBAC)
- **Civitas Akademika (Mahasiswa/Dosen/Staf):** Pelaporan barang hilang & temuan, pemantauan status real-time, pengajuan klaim kepemilikan dengan bukti rahasia, obrolan langsung dengan petugas posko, dan personalisasi profil akun.
- **Petugas Lost & Found Center:** Panel operasional meja piket, registrasi intake fisik barang temuan, alokasi kapasitas rak penyimpanan (Zona A–E), verifikasi bertingkat atas bukti kepemilikan, protokol serah terima (*handover*), dan pemanfaatan asisten pencocokan AI.
- **Administrator Sistem:** Dashboard monitoring eksekutif, manajemen akun pengguna & aktivasi massal, master data kategori & lokasi kampus, audit trail aktivitas berkeamanan tinggi, pengaturan gerbang notifikasi SMTP, serta pusat kendali AI terpadu (*Unified AI Center*).

### 2. Fitur Kecerdasan Buatan (AI)
- **C-Find AI Campus Assistant:** Chatbot kontekstual di pojok layar untuk membantu pencarian barang hilang, menjawab panduan operasional kampus (FAQ), dan memberikan arahan pelaporan. Mendukung integrasi multi-provider (NVIDIA NIM / Hosted API Catalog, Google Gemini, Ollama, dan custom OpenAI-compatible endpoint).
- **AI Matching Assistance:** Komparasi visual berdampingan (*side-by-side*) antara laporan kehilangan dan barang temuan posko secara otomatis dengan penentuan skor kemiripan (0–100%). Tetap mengedepankan prinsip *Human-in-the-Loop* (keputusan akhir mutlak berada pada petugas).

### 3. Keamanan & Perlindungan Privasi
- **Atribut Rahasia / Private Proof:** Ciri khusus dan bukti kepemilikan disembunyikan dari katalog publik dan hanya dapat diakses oleh pelapor serta petugas posko yang berwenang.
- **Penyimpanan Kredensial Terenkripsi:** API Key AI dan password SMTP dienkripsi secara simetris (AES-256-CBC) di basis data dan tidak pernah tersimpan dalam bentuk teks terbuka di file repositori maupun browser.
- **Audit Log Tamper-Evident:** Pencatatan setiap aksi sensitif dengan pembersihan otomatis (*sanitization/masking*) terhadap data rahasia.

---

## 🛠️ Tech Stack & Persyaratan Sistem

- **Bahasa & Runtime:** PHP 8.3 (kompatibel PHP >= 8.0)
- **Arsitektur:** Native MVC (Model-View-Controller) dengan Autoloading PSR-4
- **Basis Data:** MySQL 8.x / MariaDB 10.4+ (Engine InnoDB, Charset `utf8mb4_unicode_ci`)
- **Frontend:** Bootstrap 5.3, Bootstrap Icons 1.11, Lucide Icons, Custom Aura Liquid Glass Design System
- **Dependensi Composer:**
  - `vlucas/phpdotenv` (^5.7): Pengelolaan variabel lingkungan
  - `phpmailer/phpmailer` (^7.1): Pengiriman email notifikasi via SMTP
  - `phpoffice/phpspreadsheet` (^5.10): Ekspor & impor data civitas via Excel/CSV

---

## 🚀 Panduan Setup & Instalasi

### 1. Ekstraksi / Salin Folder Proyek
Tempatkan folder `campusfind-clean` di direktori web server lokal Anda:
- **Laragon:** `C:\laragon\www\campusfind-clean`
- **XAMPP:** `C:\xampp\htdocs\campusfind-clean`
- **Linux/Unix:** `/var/www/html/campusfind-clean`

### 2. Konfigurasi Variabel Lingkungan (.env)
Salin berkas `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Buka `.env` dan sesuaikan kredensial koneksi basis data Anda:
```env
APP_NAME=CampusFind
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=isi_dengan_string_acak_32_karakter_misal_base64

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=campus_find
DB_USER=root
DB_PASS=
```

### 3. Inisialisasi Basis Data (Migrasi & Seeding)
Jalankan runner migrasi otomatis dari terminal:
```bash
php database/migrate.php
```
Perintah ini akan secara otomatis:
1. Membuat basis data (jika belum ada).
2. Mengeksekusi `database/schema.sql` (24 tabel relasional lengkap).
3. Mengisi data master awal melalui `database/seeds.sql` (3 Roles, 7 Kategori, 9 Lokasi Kampus, 5 Lokasi Rak Posko, Baseline Settings, dan 16 Templat Email Resmi).
4. Menjalankan seeder data demo lokal (`seed_demo.php`) jika `APP_ENV=local`.

### 4. Membuat Akun Administrator
Jika ingin membuat akun Administrator baru secara mandiri:
```bash
php database/create_admin.php
```
Ikuti instruksi interaktif di terminal untuk menentukan Nama, Email, dan Kata Sandi admin.

---

## 🔑 Cara Memasukkan API Key ke Database (AI Configuration)

Sesuai standar keamanan CampusFind, **API Key AI TIDAK disimpan di berkas `.env`**, melainkan dikelola langsung di basis data (`system_settings`) secara terenkripsi melalui panel antarmuka Admin:

1. Jalankan aplikasi dan buka halaman login (`/login`).
2. Masuk menggunakan akun berlevel **Administrator**.
3. Buka menu navigasi sidebar dan pilih **Pengaturan AI & Konfigurasi Sistem** (`/admin/settings`).
4. Pada bagian **Koneksi Provider AI (Unified AI Center)**:
   - Pilih provider yang ingin dikonfigurasi (misalnya: **NVIDIA Hosted API Catalog** atau **Google Gemini** atau buat **Custom Provider**).
   - Masukkan API Key Anda pada kolom input bertipe password (contoh format: `nvapi-...` untuk NVIDIA atau `AIzaSy...` untuk Gemini).
   - Masukkan Base URL (opsional jika menggunakan default resmi).
   - Klik tombol **Simpan & Uji Koneksi**.
5. Sistem backend akan memvalidasi keabsahan API Key langsung ke server penyedia.
6. Apabila terverifikasi, sistem mengenkripsi nilai API Key menggunakan algoritma **AES-256-CBC** dengan kunci `APP_KEY` dan menyimpannya di tabel `system_settings` (`is_encrypted = 1`).
7. Saat ditampilkan kembali di halaman web, API Key akan otomatis disamarkan (*masked*, contoh: `nvapi-••••••••1234`) demi mencegah kebocoran data visual.

---

## 💻 Menjalankan Aplikasi di Lingkungan Lokal

### Opsi A: Menggunakan PHP Built-in Server
Dari root direktori `campusfind-clean`:
```bash
php -S localhost:8000 -t public
```
Buka peramban dan akses: [http://localhost:8000](http://localhost:8000)

### Opsi B: Menggunakan Laragon / Apache Virtual Host
Pastikan DocumentRoot mengarah ke folder `public`:
- Contoh URL Virtual Host: `http://campusfind-clean.test`

### Menjalankan Worker Antrean Notifikasi Email (Opsional)
Aplikasi menggunakan antrean asinkron (*outbox pattern*) untuk pengiriman notifikasi email agar proses web tetap instan:
```bash
php cli/notifications_worker.php
```

---

## 📂 Struktur Direktori Proyek

```
campusfind-clean/
├── app/
│   ├── Controllers/         # Controller penangan HTTP request & respons
│   ├── Middleware/          # Filter autentikasi, otorisasi RBAC, dan CSRF
│   ├── Models/              # Objek entitas & abstraksi query basis data
│   ├── Services/            # Business logic utama (AI, Chat, Intake, Notifikasi)
│   └── Views/               # Antarmuka template PHP (Civitas, Petugas, Admin)
├── bin/
│   └── campus               # Tool executable CLI
├── cli/
│   └── notifications_worker.php # Worker pengirim antrean email latar belakang
├── config/                  # Konfigurasi aplikasi, basis data, route, dan skema AI
├── core/                    # Engine inti MVC (Router, Database PDO, BaseController)
├── database/
│   ├── create_admin.php     # CLI tool pembuatan akun administrator
│   ├── migrate.php          # CLI runner migrasi dan seeding basis data
│   ├── schema.sql           # DDL lengkap 24 tabel relasional
│   ├── seeds.sql            # Master data & baseline konfigurasi
│   ├── seed_demo.php        # Seeder data demo untuk lingkungan lokal
│   └── migrations/          # Berkas migrasi skema tambahan
├── docs/                    # Dokumentasi desain token, FAQ, dan audit UI
├── public/                  # Document root web server (index.php, css, js, images)
├── storage/
│   ├── logs/                # Berkas log operasional sistem (.gitkeep)
│   └── uploads/             # Berkas berkas unggahan publik & bukti terproteksi
├── tests/                   # Skrip uji regresi & verifikasi keamanan
├── .env.example             # Templat variabel lingkungan (nama variabel saja)
├── .gitignore               # Aturan pengabaian berkas repositori
├── composer.json            # Daftar dependensi PHP Composer
├── composer.lock            # Penguncian versi dependensi PHP
└── README.md                # Dokumentasi utama proyek
```

---

## 📜 Lisensi & Kontributor

Dikembangkan untuk kebutuhan operasional Lost & Found perguruan tinggi.
Lisensi: MIT License.
