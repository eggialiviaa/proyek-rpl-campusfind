# CampusFind — Sistem Informasi Lost & Found Kampus Berbasis Web dengan AI

Proyek Akhir Mandiri — Rekayasa Perangkat Lunak (TIKB2503021)
Program Studi S1 Teknik Informatika, STMIK IKMI Cirebon

## Identitas

| | |
|---|---|
| Nama | Eggi Alivia R |
| NIM | 41257187 |
| Kelas | 1D-25 |
| Kelompok | Kere Hore |
| Anggota kelompok | Fajar Ma'ruf (ketua), Eggi Alivia R, Leo Ardiansyah J, Daryell ZS, Fakhri FR |
| Tema Topik Kelompok | Sistem informasi berbasis web di lingkungan kampus |
| Dosen pengampu | Yudhistira Arie Wijaya, M.Kom., CDS. |

Setiap anggota kelompok membangun aplikasi web utuh miliknya sendiri dalam tema yang sama. Repositori ini adalah aplikasi milik Eggi Alivia R.

## Tautan Luaran

| Luaran | Tautan |
|---|---|
| Video Progres 1 — PRD & Konsep Aplikasi | [LINK VIDEO PROGRES 1] |
| Video Progres 2 — Pemodelan UML & UI/UX | [LINK VIDEO PROGRES 2] |
| Video Final — Live Demo Aplikasi | [LINK VIDEO FINAL] |
| Prototipe Figma | [LINK FIGMA] |

Dokumen PRD/SRS, SDD (UML, ERD, UI/UX), dan Laporan Pengujian/CI-CD digabung dalam satu berkas PDF yang diunggah ke portal LMS perkuliahan.

## Deskripsi Aplikasi

CampusFind menghubungkan civitas akademika dengan Lost & Found Center kampus untuk melaporkan, mencari, mengklaim, dan mengembalikan barang hilang atau temuan. Pelaporan yang sebelumnya tercecer di grup percakapan dipusatkan dalam satu sistem dengan verifikasi kepemilikan oleh Petugas.

### Peran Pengguna

| Peran | Kewenangan utama |
|---|---|
| Civitas (mahasiswa, dosen, staf) | Membuat laporan kehilangan dan temuan, mencari barang, mengajukan klaim dengan bukti privat, chat dengan Petugas, melihat notifikasi, mengelola profil |
| Petugas Lost & Found | Menerima dan memeriksa barang temuan, mengelola inventaris dan rak, memverifikasi klaim, mencatat pengembalian, menjalankan AI Matching |
| Admin / Super User | Mengelola akun dan peran, data master (kategori, lokasi, rak), impor akun dari Excel, dashboard, audit log, serta pengaturan sistem (AI, SMTP, worker) |

Tamu hanya dapat melihat beranda, halaman login, dan aktivasi akun. Tidak ada pendaftaran mandiri: akun dibuat Admin (impor Excel atau manual), lalu diaktivasi pengguna lewat OTP email atau memakai password sementara yang wajib diganti.

### Fitur Utama

- Login dengan NIM atau email, kontrol akses berbasis peran (RBAC), pembatasan percobaan login
- Laporan kehilangan dan laporan temuan, pencarian dan filter
- Lost & Found Center: penerimaan barang, kode simpan, lokasi rak
- Klaim dan verifikasi dengan bukti privat, pencatatan pengembalian, riwayat status
- Chat internal Civitas dan Petugas
- Notifikasi dalam aplikasi dan antrean email (SMTP) dengan worker
- OTP email untuk aktivasi, verifikasi email, dan lupa kata sandi
- Impor akun Civitas dari Excel
- Audit log dengan penyamaran data sensitif
- Pengaturan sistem: penyedia AI, API key, dan SMTP (rahasia disimpan terenkripsi)

### Fitur AI

AI bersifat pendukung. Keputusan kepemilikan barang selalu ada pada Petugas.

1. **AI Matching** — mencocokkan laporan kehilangan dengan laporan temuan lewat penyedia AI (NVIDIA NIM, API kompatibel OpenAI). Hasilnya berupa skor dan penjelasan "Possible Match" untuk ditinjau Petugas, bukan bukti kepemilikan. Jika AI belum dikonfigurasi, sistem akan stuck di loading pencarian persamaan(scanning)
2. **Chatbot C-Find** — asisten tanya-jawab per peran. AI mengubah pertanyaan menjadi rencana terstruktur, backend memvalidasi sesuai peran lalu mengambil data lewat query ter-parameter dan menyusun jawaban dari templat. AI tidak menulis SQL dan tidak memutuskan klaim. Foto dan data baris tidak dikirim ke penyedia AI.

## Teknologi

| Lapisan | Teknologi |
|---|---|
| Backend | PHP 8 (diuji pada 8.3), arsitektur MVC kustom |
| Basis data | MySQL / MariaDB |
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript |
| Pustaka | PHPMailer, PhpSpreadsheet, vlucas/phpdotenv |
| Pengujian | PHPUnit 11 |
| CI/CD | GitHub Actions (`.github/workflows/ci.yml`) |
| Lingkungan pengembangan | Laragon (Windows) |

## Struktur Folder

```
app/        Controllers, Models, Middleware, Services, Views, Config
core/       Kerangka inti (router, aplikasi, basis controller)
config/     Rute dan konfigurasi
database/   schema.sql, seeds.sql, migrations, create_admin.php, seed_demo.php
cli/        Worker notifikasi
public/     Front controller dan aset
tests/      Unit test PHPUnit
docs/       Dokumentasi
```

## Petunjuk Instalasi (Setup Instruction)

Panduan ini untuk Windows dengan Laragon. Prasyarat: PHP 8.0 atau lebih baru, MySQL/MariaDB, Composer, Git.

**1. Ambil kode dan pasang dependensi**

```
cd C:\laragon\www
git clone https://github.com/eggialiviaa/proyek-rpl-campusfind.git campusfind-clean
cd campusfind-clean
composer install
```

Nama folder harus `campusfind-clean` agar Laragon membuat alamat `http://campusfind-clean.test`.

**2. Aktifkan ekstensi PHP `zip`**

Laragon: Menu > PHP > Extensions > centang `zip`. Tanpa ini impor Excel gagal dengan galat `ZipArchive not found`.

**3. Buat file `.env`**

```
copy .env.example .env
php -r "echo bin2hex(random_bytes(16));"
```

Salin hasil perintah kedua ke `APP_KEY`. Pastikan nilai berikut terisi di `.env`:

```
APP_NAME=CampusFind
APP_ENV=local
APP_URL=http://campusfind-clean.test
DB_HOST=127.0.0.1
DB_NAME=campus_find
DB_USER=root
SESSION_LIFETIME=7200
```

`DB_PASS` boleh kosong pada Laragon bawaan. `APP_KEY` wajib diisi karena dipakai mengenkripsi API key dan password SMTP.

**4. Buat database dan impor skema**

Buat database kosong baru. `schema.sql` diawali penghapusan tabel (`DROP TABLE IF EXISTS`), jadi jangan mengimpornya ke database yang sudah berisi data.

```
mysql -u root -e "CREATE DATABASE campus_find CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root campus_find < database\schema.sql
mysql -u root campus_find < database\seeds.sql
```

**5. Buat akun Admin**

```
php database\create_admin.php
```

**6. (Opsional) Isi data demo fiktif**

Isi `DEMO_ADMIN_PASSWORD`, `DEMO_PETUGAS_PASSWORD`, dan `DEMO_CIVITAS_PASSWORD` di `.env` dengan password pilihan sendiri, lalu:

```
php database\seed_demo.php
```

Skrip ini hanya berjalan pada `APP_ENV=local` atau `development`.

**7. Buka aplikasi**

`http://campusfind-clean.test`

Aplikasi harus dibuka dari akar domain. Membukanya dari sub-folder seperti `http://localhost/campusfind-clean/public` menghasilkan halaman "tidak ada".

**8. Konfigurasi AI dan email**

Login sebagai Admin, buka halaman Pengaturan Sistem/Konfigurasi, lalu isi API key NVIDIA dan kredensial SMTP (Gmail memakai App Password). Kunci tidak disimpan di berkas proyek dan tidak boleh dimasukkan ke repositori. Untuk memproses antrean email, gunakan tombol "Jalankan Worker" di halaman yang sama atau jalankan `php cli/notifications_worker.php`.

## Menjalankan Pengujian

```
vendor\bin\phpunit
```

Unit test tidak memakai database utama, API AI, maupun SMTP asli. Pengujian yang sama dijalankan otomatis oleh GitHub Actions pada setiap push.

## Batasan yang Diketahui

- Aplikasi hanya berfungsi bila dilayani dari akar domain (virtual host), bukan dari sub-folder.
- Fitur AI membutuhkan API key penyedia. Tanpa key, AI Matching memakai penyedia tiruan.
- Pengiriman email membutuhkan SMTP aktif; penerima harus verifikasi email dan opt-in notifikasi. Domain `.test` diblokir untuk email.
- Notifikasi WhatsApp dan Telegram belum dibuat.
- Belum disiapkan untuk lingkungan produksi (hosting, backup, kebijakan privasi formal).

## Catatan Keamanan

Jangan menyimpan `.env`, API key, atau App Password di repositori. Rahasia pada pengaturan sistem disimpan terenkripsi AES-256-CBC dengan `APP_KEY`.
