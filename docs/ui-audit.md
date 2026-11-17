# Dokumen Audit Antarmuka Pengguna (UI) & Arsitektur Informasi
**Sistem Lost & Found Kampus — Campus Find**  
*Status Dokumen: Audit & Laporan Rekomendasi (Hanya Laporan — Tanpa Perubahan Kode)*  
*Tanggal Audit: 19 Oktober 2026*

---

## 1. Pendahuluan & Metodologi Audit

Audit ini dilakukan untuk memetakan seluruh lanskap antarmuka pengguna (UI) dan arsitektur informasi pada aplikasi **Campus Find**. Tujuannya adalah mengidentifikasi:
1. Seluruh elemen interaktif (tombol, tautan, menu) pada tiap halaman per peran pengguna (*Tamu, Civitas, Petugas, Admin*).
2. Tumpang tindih fungsionalitas (*duplikasi akses*).
3. Elemen yang tidak fungsional, berlebih, dekoratif tanpa nilai guna, atau sisa artefak pengembangan awal (*dead/zombie code*).
4. Tingkat kepadatan kognitif dan visual (*density score*) pada masing-masing layar.
5. Tabel keputusan tindakan (*Pertahankan / Gabung / Hapus*) dengan batasan ketat bahwa seluruh modul yang disyaratkan PRD/alur sistem utama tetap terakomodasi.
6. Rekomendasi struktur navigasi terpadu (maksimal 6 menu utama per peran, maksimal 5 sub-menu per grup) yang 100% dipetakan dari rute aktif yang sudah ada.

---

## 2. Inventaris Halaman & Elemen UI per Role / Kondisi

### 2.1 Kondisi: Tamu / Pengguna Publik (Guest)
Pengguna yang belum login atau mengakses portal secara anonim.

| Halaman & Rute | Elemen Interaktif (Lokasi) | Tipe Elemen | Fungsi & Perilaku | Rute / Target Aksi |
| :--- | :--- | :--- | :--- | :--- |
| **Beranda Publik**<br>`/` | Brand Logo (Navbar) | Link | Kembali ke landing page utama | `/` |
| | Link Nav "Beranda" (Navbar) | Link | Menu navigasi ke beranda | `/` |
| | Link Nav "Cari & Filter" (Navbar) | Link | Masuk ke katalog pencarian publik | `/search` |
| | Link Nav "Barang Hilang" (Navbar) | Link | Masuk ke daftar publik laporan kehilangan | `/reports/lost` |
| | Link Nav "Barang Temuan" (Navbar) | Link | Masuk ke daftar publik laporan temuan | `/reports/found` |
| | Tombol "Masuk" (Navbar Kanan) | Button Link | Menuju portal login akun kampus | `/login` |
| | Tombol "Mulai Sekarang" (Hero) | Button Link | Mengarahkan tamu untuk login | `/login` |
| | Tombol "Pelajari Alur Sistem" (Hero) | Button Link | Scroll halus ke section alur | `#fitur` (anchor lokal) |
| | Tombol "Ajukan Klaim →" (Card Demo) | Button Link | Mengarahkan klaim (redirect ke login) | `/login` |
| | Launcher Chatbot C-Find (Floating Kanan) | Button | Membuka drawer percakapan AI C-Find | `cfind-launcher` |
| | Chip Pertanyaan C-Find (Widget AI) | Button | Mengirim template tanya cepat ke AI | AJAX `/api/cfind/chat` |
| | Tombol Reset Chat (Header C-Find) | Button | Mengosongkan riwayat sesi obrolan | AJAX `/api/cfind/reset` |
| | Tombol Tutup Chat (Header C-Find) | Button | Menutup drawer percakapan AI | `cfind-panel` (toggle) |
| | Input & Kirim Chat (Footer C-Find) | Input + Btn | Mengetik dan submit pesan ke AI | AJAX `/api/cfind/chat` |
| | Link "Beranda" (Footer) | Link | Navigasi footer ke beranda | `/` |
| | Link "Masuk" (Footer) | Link | Navigasi footer ke login | `/login` |
| **Halaman Masuk**<br>`/login` | Brand Logo (Card Header) | Link | Kembali ke landing page | `/` |
| | Input Email & Password (Form) | Input Form | Masukan kredensial akun | Form POST `/login` |
| | Sakelar Lampu Interaktif (Visual Hero) | Button / Cord | Trik estetika switch background lit/dim | Interaksi DOM lokal |
| | Link "Lupa Password?" (Form) | Link/Button | Menampilkan alert pop-up kontak admin | Pop-up modal info |
| | Tombol "Masuk ke Sistem" (Form) | Submit Btn | Autentikasi sesi & redirect per role | POST `/login` |
| **Pencarian Publik**<br>`/search` | Input Kata Kunci (Filter Bar) | Input Form | Query pencarian barang | GET `/search?keyword=...` |
| | Dropdown Kategori & Lokasi | Select Form | Filter metadata kategori dan gedung | GET `/search?...` |
| | Pill Status Barang | Link Pill | Filter tab (Semua, Hilang, Ditemukan) | GET `/search?type=...` |
| | Tombol "Terapkan Filter" | Submit Btn | Reload katalog sesuai filter aktif | GET `/search` |
| | Tombol "Reset Filter" | Link Btn | Mengembalikan filter ke kondisi awal | `/search` |
| | Kartu Item Hasil Cari | Card Link | Membuka rincian laporan terkait | `/reports/lost/{id}` atau `/reports/found/{id}` |
| | Tombol "Laporkan Kehilangan" | Button Link | Shortcut bikin laporan (redirect jika tamu)| `/reports/lost/create` |
| | Tombol "Laporkan Temuan" | Button Link | Shortcut bikin laporan (redirect jika tamu)| `/reports/found/create` |
| **Daftar Barang Hilang**<br>`/reports/lost` | Form Pencarian & Kategori | Input Form | Filter daftar kehilangan | GET `/reports/lost` |
| | Tombol "Lapor Kehilangan" | Button Link | Menuju formulir laporan hilang | `/reports/lost/create` |
| | Kartu Laporan Hilang | Card Link | Melihat rincian laporan kehilangan | `/reports/lost/{id}` |
| | Tombol "Cari Barang Temuan Serupa" | Button Link | Mengarahkan ke pencarian temuan | `/search?type=found` |
| **Daftar Barang Temuan**<br>`/reports/found` | Form Pencarian & Kategori | Input Form | Filter daftar temuan | GET `/reports/found` |
| | Tombol "Lapor Penemuan" | Button Link | Menuju formulir laporan temuan | `/reports/found/create` |
| | Kartu Laporan Temuan | Card Link | Melihat rincian laporan temuan | `/reports/found/{id}` |

---

### 2.2 Kondisi: Civitas Akademika (Mahasiswa, Dosen, Tendik)
Pengguna yang telah login dengan hak akses dasar (melapor, mencari, klaim, diskusi).

| Halaman & Rute | Elemen Interaktif (Lokasi) | Tipe Elemen | Fungsi & Perilaku | Rute / Target Aksi |
| :--- | :--- | :--- | :--- | :--- |
| **Dashboard Civitas**<br>`/dashboard` | Kartu Stat "Laporan Kehilangan" | Stat Card | Ringkasan jumlah; klik ke daftar saya | `/civitas/my-reports#lost` |
| | Kartu Stat "Laporan Temuan" | Stat Card | Ringkasan jumlah; klik ke daftar saya | `/civitas/my-reports#found` |
| | Kartu Stat "Pengajuan Klaim" | Stat Card | Ringkasan jumlah; klik ke klaim saya | `/civitas/my-claims` |
| | Tombol "Buat Laporan Kehilangan" | Button Link | Tombol aksi cepat di header dashboard | `/reports/lost/create` |
| | Tombol "Buat Laporan Penemuan" | Button Link | Tombol aksi cepat di header dashboard | `/reports/found/create` |
| | Tautan "Lihat Semua Laporan" | Link | Navigasi ke tabel rekap pribadi | `/civitas/my-reports` |
| **Buat Laporan Hilang**<br>`/reports/lost/create` | Form Isian (Judul, Kategori, Lokasi, Tgl, Deskripsi) | Input Form | Masukan data kehilangan | POST `/reports/lost/create` |
| | Unggah Foto Bukti Barang | File Input | Unggah attachment foto | POST `/reports/lost/create` |
| | Tombol "Batal / Kembali" | Button Link | Membatalkan pembuatan laporan | `/reports/lost` |
| | Tombol "Terbitkan Laporan" | Submit Btn | Menyimpan laporan kehilangan baru | POST `/reports/lost/create` |
| **Detail Laporan Hilang**<br>`/reports/lost/{id}` | Tombol "Kembali ke Daftar" | Button Link | Navigasi breadcrumb kembali | `/reports/lost` |
| | Tombol "Rekomendasi AI Match" | Button Link | Membuka kalkulasi kecocokan AI NIM | `/ai-match/lost/{id}` |
| | Tombol "Edit Laporan" (Pemilik) | Button Link | Menuju halaman ubah data laporan | `/reports/lost/{id}/edit` |
| | Tombol "Hapus Laporan" (Pemilik) | Form Button | Menghapus laporan (konfirmasi dialog) | POST `/reports/lost/{id}/delete` |
| | Tombol "Diskusi / Bantuan Petugas" | Button Link | Membuka room chat terkait laporan | `/chats/report/lost/{id}` |
| **Edit Laporan Hilang**<br>`/reports/lost/{id}/edit` | Form Pengubahan Data | Input Form | Update isi data laporan | POST `/reports/lost/{id}/edit` |
| | Tombol "Simpan Perubahan" | Submit Btn | Simpan revisi | POST `/reports/lost/{id}/edit` |
| | Tombol "Batal" | Button Link | Batalkan edit, kembali ke detail | `/reports/lost/{id}` |
| **Buat Laporan Temuan**<br>`/reports/found/create` | Form Isian (Data temuan, ciri, lokasi) | Input Form | Masukan data penemuan | POST `/reports/found/create` |
| | Checkbox "Serahkan ke L&F Center" | Checkbox | Opsi serah terima langsung ke posko | Form Data |
| | Unggah Foto Barang | File Input | Bukti visual barang temuan | POST `/reports/found/create` |
| | Tombol "Terbitkan Laporan Temuan" | Submit Btn | Simpan laporan temuan | POST `/reports/found/create` |
| **Detail Laporan Temuan**<br>`/reports/found/{id}` | Tombol "Ini Barang Saya! (Ajukan Klaim)" | Button Link | Form klaim kepemilikan | `/claims/create?found_id={id}` |
| | Tombol "Serahkan ke Petugas Center" | Form Button | Update status siap serah terima | POST `/reports/found/{id}/handover` |
| | Tombol "Edit Laporan" (Pemilik) | Button Link | Revisi laporan sebelum terverifikasi | `/reports/found/{id}/edit` |
| | Tombol "Hapus Laporan" (Pemilik) | Form Button | Hapus laporan (jika belum masuk rak) | POST `/reports/found/{id}/delete` |
| | Tombol "Tanya Penemu / Diskusi" | Button Link | Membuka pesan terenkripsi ke penemu | `/chats/report/found/{id}` |
| **Laporan Saya**<br>`/civitas/my-reports` | Tab Navigasi "Kehilangan" / "Temuan" | Tab Button | Beralih tab tabel laporan pribadi | DOM Tab switching |
| | Tombol "Aksi Detail" per baris tabel | Button Link | Masuk ke halaman detail spesifik | `/reports/lost/{id}` atau `/found/{id}` |
| | Tombol "Edit" per baris tabel | Button Link | Edit laporan milik sendiri | `/reports/.../{id}/edit` |
| | Tombol "Hapus" per baris tabel | Form Button | Hapus laporan aktif | POST `/reports/.../{id}/delete` |
| **Formulir Klaim**<br>`/claims/create` | Dropdown Pemilihan Barang Temuan | Select Form | Menentukan barang yang diklaim | Form parameter |
| | Form "Ciri Rahasia / Bukti Kepemilikan" | Textarea | Keterangan privat anti-penipuan | POST `/claims/create` |
| | Unggah Foto Bukti Privat (Nota/Foto lama) | File Input | Bukti kepemilikan privat | POST `/claims/create` |
| | Tombol "Kirim Pengajuan Klaim" | Submit Btn | Submit klaim ke verifikator petugas | POST `/claims/create` |
| **Klaim Saya**<br>`/civitas/my-claims` | Kartu Item Pengajuan Klaim | Card Box | Menampilkan status tiket verifikasi | Info visual status |
| | Tombol "Lihat Rincian & Riwayat Klaim" | Button Link | Detail timeline dan keputusan petugas | `/claims/{id}` |
| | Tombol "Cari Barang Lain" (Empty State) | Button Link | Kembali mencari di katalog temuan | `/search?type=found` |
| **Detail Status Klaim**<br>`/claims/{id}` | Timeline Pelacakan Status Klaim | Visual Info | Progress verifikasi oleh petugas | Visual timeline |
| | Tombol "Buka Diskusi Tiket Klaim" | Button Link | Obrolan langsung dengan petugas piket | `/chats/claim/{id}` |
| | Tombol "Lihat Laporan Terkait" | Button Link | Tautan ke laporan penemuan asal | `/reports/found/{id}` |
| **Percakapan Internal**<br>`/chats` | Daftar Percakapan Aktif | List Group | Memilih ruang obrolan | `/chats/{room_id}` |
| | Tombol Filter Kategori Chat | Filter Btn | Filter diskusi klaim vs laporan | Interaksi lokal / parameter |
| **Ruang Obrolan**<br>`/chats/{id}` | Input Teks Pesan | Form Input | Mengetik pesan konfirmasi | AJAX POST `/chats/{id}/messages` |
| | Tombol Kirim Pesan | Button | Submit pesan obrolan | AJAX POST |
| | Tombol Kembali ke Daftar Chat | Button Link | Kembali ke inbox percakapan | `/chats` |
| | Tautan Pintasan Laporan/Klaim | Header Link | Melihat konteks barang yang dibahas | `/claims/{id}` atau `/reports/...` |
| **Riwayat Notifikasi**<br>`/notifications` | Tab Filter "Semua" / "Belum Dibaca" | Button Link | Filter notifikasi sistem | `/notifications?filter=...` |
| | Tombol "Tandai Semua Dibaca" | Form Button | Reset counter unread menjadi nol | POST `/notifications/read-all` |
| | Item Baris Notifikasi | Link Item | Buka halaman spesifik & mark read | POST `/notifications/{id}/read` |
| **Rekomendasi AI Match**<br>`/ai-match/lost/{id}` | Kartu Kandidat Kecocokan AI | Card Box | Hasil evaluasi kesamaan AI | Visual skor & reasoning |
| | Tombol "Jalankan Analisis AI Ulang" | Form Button | Memicu call API NIM baru | POST `/ai-match/lost/{id}/run` |
| | Tombol "Ajukan Klaim Berdasarkan Rekomendasi" | Button Link | Membuka form klaim dengan prefill item | `/claims/create?found_id={id}` |

---

### 2.3 Kondisi: Petugas Lost & Found Center
Pengguna dengan hak operasional posko fisik, inventaris rak, pemeriksaan klaim, dan serah terima.

| Halaman & Rute | Elemen Interaktif (Lokasi) | Tipe Elemen | Fungsi & Perilaku | Rute / Target Aksi |
| :--- | :--- | :--- | :--- | :--- |
| **Dashboard Petugas**<br>`/dashboard` | Kartu Stat "Barang Tersimpan di Rak" | Stat Card | Link ke inventaris rak aktif | `/petugas/inventory` |
| | Kartu Stat "Klaim Perlu Verifikasi" | Stat Card | Link ke antrean verifikasi bukti | `/petugas/claims?status=pending` |
| | Kartu Stat "Temuan Belum Diverifikasi" | Stat Card | Link ke antrean laporan masuk | `/petugas/found-reports` |
| | Tombol Pintasan "Periksa Klaim" | Button Link | Aksi cepat header dashboard | `/petugas/claims` |
| | Tombol Pintasan "Buka Inventaris Gudang"| Button Link | Aksi cepat header dashboard | `/petugas/inventory` |
| **Inventaris Rak Fisik**<br>`/petugas/inventory` | Filter Penyimpanan & Status Barang | Form Select | Filter barang per rak / status fisik | GET `/petugas/inventory?...` |
| | Tombol "Penerimaan Barang Baru (Intake)" | Button Link | Masuk antrean barang menunggu serah terima| `/petugas/handover` |
| | Tombol "Kelola Rak & Lemari" | Button Link | Masuk master storage loker | `/petugas/storage-locations` |
| | Tombol "Detail Fisik Barang" (Tabel) | Button Link | Buka lembar kontrol fisik barang | `/petugas/items/{id}` |
| **Detail Fisik Barang**<br>`/petugas/items/{id}` | Tombol "Pindah Rak Penyimpanan" | Modal Trigger | Memindahkan lokasi fisik barang ke rak lain | POST `/petugas/items/{id}/relocate` |
| | Tombol "Ubah Status Fisik Barang" | Modal Trigger | Ubah status (stored, claimed, disposed) | POST `/petugas/items/{id}/status` |
| | Tautan Laporan Penemuan Terkait | Link | Tinjau laporan asli dari civitas | `/reports/found/{id}` |
| **Antrean Temuan Masuk**<br>`/petugas/found-reports` | Tab Filter Status Antrean | Link Pill | Filter (Semua, Menunggu Serah, Publik) | `/petugas/found-reports?status=...` |
| | Tombol "Verifikasi & Intake Fisik" (Tabel)| Button Link | Membuka form penerimaan fisik barang | `/petugas/intake/{id}` |
| | Tombol "Lihat Laporan" (Tabel) | Button Link | Memeriksa rincian laporan penemuan | `/reports/found/{id}` |
| **Barang Menunggu Serah**<br>`/petugas/handover` | Tabel Barang Menunggu Posko | Table List | Daftar penemu yang menuju posko L&F | Info jadwal & penemu |
| | Tombol "Proses Intake Gudang" | Button Link | Mengalokasikan kode rak fisik | `/petugas/intake/{id}` |
| **Formulir Intake Fisik**<br>`/petugas/intake/{id}` | Form Nomor Kode Item Unik | Input Form | Kode barcode/label fisik barang | POST `/petugas/intake/{id}` |
| | Pilihan Rak / Lemari Penyimpanan | Select Form | Menentukan lokasi penyimpanan barang | POST `/petugas/intake/{id}` |
| | Catatan Kondisi Fisik Barang | Textarea | Catatan cacat/kondisi barang saat diterima | POST `/petugas/intake/{id}` |
| | Tombol "Konfirmasi Penerimaan Barang" | Submit Btn | Finalisasi penyimpanan ke database rak | POST `/petugas/intake/{id}` |
| **Lokasi Rak Petugas**<br>`/petugas/storage-locations` | Tombol "Tambah Lokasi Rak" | Modal Trigger | Membuat rak baru di posko | POST `/petugas/storage-locations/create` |
| | Kartu Grid Lokasi Rak | Card Box | Visualisasi kapasitas isi per rak | Status bar kapasitas |
| | Tombol "Kembali ke Inventaris" | Button Link | Navigasi ke daftar barang | `/petugas/inventory` |
| **Antrean Klaim Masuk**<br>`/petugas/claims` | Filter Status Klaim (6 Pill Tab) | Link Pill | Pending, Review, Info, Approved, Selesai | `/petugas/claims?status=...` |
| | Tombol "Periksa Bukti Klaim" (Tabel) | Button Link | Membuka berkas verifikasi klaim rahasia| `/petugas/claims/{id}` |
| **Tinjauan Bukti Klaim**<br>`/petugas/claims/{id}` | Tombol "Mulai Peninjauan (Under Review)" | Form Button | Mengunci tiket sedang diperiksa petugas | POST `/petugas/claims/{id}/review` |
| | Tombol "Minta Tambahan Bukti" | Modal Trigger | Meminta foto/nota tambahan ke pelapor | POST `/petugas/claims/{id}/request-info` |
| | Tombol "Setujui Klaim (Approve)" | Modal Trigger | Bukti cocok, instruksikan ambil barang | POST `/petugas/claims/{id}/approve` |
| | Tombol "Tolak Klaim (Reject)" | Modal Trigger | Bukti tidak sesuai / klaim palsu | POST `/petugas/claims/{id}/reject` |
| | Tombol "Proses Serah Terima Barang" | Button Link | Eksekusi serah terima jika sudah approve | `/petugas/claims/{id}/handover` |
| | Tombol "Hubungi Pengaju Klaim" | Button Link | Chat privat tiket verifikasi klaim | `/chats/claim/{id}` |
| **Serah Terima Klaim**<br>`/petugas/claims/{id}/handover`| Form Identitas Pengambil (Nama & No ID)| Input Form | Verifikasi KTP/KTM saat pengambilan | POST `/petugas/claims/{id}/handover` |
| | Unggah Bukti Penyerahan (Dokumentasi) | File Input | Foto serah terima di posko | POST `/petugas/claims/{id}/handover` |
| | Tombol "Konfirmasi Selesai Serah Terima" | Submit Btn | Menutup klaim & ubah status barang claimed| POST `/petugas/claims/{id}/handover` |

---

### 2.4 Kondisi: Administrator Sistem (Super User)
Pengguna dengan hak kontrol mutlak atas manajemen user, master data kampus, audit trail, dan konfigurasi AI/sistem.

| Halaman & Rute | Elemen Interaktif (Lokasi) | Tipe Elemen | Fungsi & Perilaku | Rute / Target Aksi |
| :--- | :--- | :--- | :--- | :--- |
| **Dashboard Admin**<br>`/admin` atau `/admin/dashboard` | 6 Kartu Metrik Utama | Stat Cards | Statistik pengguna, laporan, barang, audit | Data analitik |
| | Tombol "Pengaturan Sistem" | Button Link | Mengakses halaman konfigurasi AI/notif | `/admin/settings` |
| | Tombol "Manajemen Pengguna" | Button Link | Mengakses kelola user dan hak akses | `/admin/users` |
| | Tombol "Audit Trail" | Button Link | Memeriksa jejak audit keamanan | `/admin/audit-logs` |
| | Tombol Pintasan Cepat Master Data | Button Links | Kategori, Lokasi Kampus, Gudang | `/admin/categories`, `/locations`, dsb. |
| **Manajemen Pengguna**<br>`/admin/users` | Input Pencarian & Filter Peran/Status | Form Filter | Mencari user berdasarkan nama/email | GET `/admin/users?...` |
| | Tombol "Tambah Pengguna Baru" | Modal Trigger | Registrasi user manual oleh admin | POST `/admin/users/create` |
| | Tombol "Ubah Role" per baris tabel | Modal Trigger | Promosi/demosi (Civitas/Petugas/Admin) | POST `/admin/users/{id}/role` |
| | Tombol "Aktifkan/Nonaktifkan Akun" | Form Button | Suspend / un-suspend akses login user | POST `/admin/users/{id}/status` |
| | Kontrol Paginasi Halaman | Nav Links | Navigasi halaman 1, 2, dst. | GET `/admin/users?page=...` |
| **Master Data Kategori**<br>`/admin/categories` | Tombol "Tambah Kategori Baru" | Modal Trigger | Input kategori barang baru | POST `/admin/categories/create` |
| | Tombol "Edit Kategori" per baris | Modal Trigger | Revisi nama/icon kategori | POST `/admin/categories/{id}/update` |
| | Tombol "Toggle Aktif/Nonaktif" | Form Button | Menonaktifkan kategori dari opsi form | POST `/admin/categories/{id}/toggle` |
| **Master Data Lokasi Kampus**<br>`/admin/locations` | Tombol "Tambah Lokasi Baru" | Modal Trigger | Input lokasi gedung/ruang kampus | POST `/admin/locations/create` |
| | Tombol "Edit Lokasi" per baris | Modal Trigger | Revisi nama/zona lokasi | POST `/admin/locations/{id}/update` |
| | Tombol "Toggle Aktif/Nonaktif" | Form Button | Menonaktifkan lokasi dari opsi form | POST `/admin/locations/{id}/toggle` |
| **Master Gudang & Rak**<br>`/admin/storage-locations`| Tombol "Tambah Rak Gudang Baru" | Modal Trigger | Input rak/lemari penyimpanan | POST `/admin/storage-locations/create` |
| | Tombol "Edit Rak" per baris | Modal Trigger | Revisi nama/kapasitas rak | POST `/admin/storage-locations/{id}/update` |
| | Tombol "Toggle Aktif/Nonaktif" | Form Button | Mengunci rak agar tidak dipilih saat intake| POST `/admin/storage-locations/{id}/toggle` |
| **Audit Trail Keamanan**<br>`/admin/audit-logs` | Form Filter Pencarian, Aksi & Entitas | Form Filter | Menyaring jejak audit log keamanan | GET `/admin/audit-logs?...` |
| | Tombol "Reset Filter" | Button Link | Mengembalikan filter ke kondisi awal | `/admin/audit-logs` |
| | Tombol "Detail Payload Audit" | Modal Trigger | Melihat rekaman JSON perubahan data | Modal inspeksi JSON aman |
| **Pengaturan Sistem**<br>`/admin/settings` | Form Konfigurasi AI (Base URL, Model, Key)| Input Form | Konfigurasi runtime engine AI | POST `/admin/settings` |
| | Tombol "Tes Koneksi AI" (Live AJAX) | AJAX Button | Menguji responsivitas endpoint AI sungguhan | AJAX POST `/admin/settings/test-ai` |
| | Sakelar On/Off Chatbot per Role (4 Role) | Form Switches | Mematikan/menghidupkan C-Find per role | POST `/admin/settings` |
| | Input Batas Rate Limit & Kuota Harian | Form Number | Pembatasan kuota token AI per peran | POST `/admin/settings` |
| | Pengaturan Notifikasi Eksternal | Select & Input | Saluran webhook/email eksternal | POST `/admin/settings` |
| | Tombol "Simpan Semua Pengaturan" | Submit Btn | Menyimpan ke tabel `system_settings` | POST `/admin/settings` |

---

### 2.5 Komponen Navigasi Global (Shell)
Komponen pembungkus yang selalu ada di setiap halaman.

| Komponen | Elemen Interaktif | Tipe Elemen | Fungsi & Perilaku | Rute / Target Aksi |
| :--- | :--- | :--- | :--- | :--- |
| **Topbar Navbar**<br>`navbar.php` | Brand Logo CampusFind | Link | Menuju beranda utama | `/` |
| | Sidebar Drawer Toggler (Khusus Mobile) | Button | Membuka sidebar drawer (Petugas/Admin) | Offcanvas / drawer |
| | Link "Beranda" | Nav Link | Menuju beranda utama | `/` |
| | Link "Cari & Filter" | Nav Link | Menuju katalog pencarian publik | `/search` |
| | Link "Barang Hilang" | Nav Link | Menuju daftar laporan hilang | `/reports/lost` |
| | Link "Barang Temuan" | Nav Link | Menuju daftar laporan temuan | `/reports/found` |
| | Link "Dashboard" (Jika Login) | Nav Link | Menuju dashboard spesifik role | `/dashboard` |
| | Link Khusus Admin di Topbar Desktop | Nav Links | Pengguna, Audit Trail, Pengaturan | `/admin/users`, `/audit-logs`, `/settings` |
| | Link Khusus Petugas di Topbar Desktop | Nav Links | Klaim Masuk, Antrean Temuan, Inventaris Rak | `/petugas/claims`, `/found-reports`, `/inventory` |
| | Link Khusus Civitas di Topbar | Nav Links | Laporan Saya, Klaim Saya | `/civitas/my-reports`, `/my-claims` |
| | Link "Pesan" + Counter Badge Unread | Nav Link | Menuju daftar percakapan internal | `/chats` |
| | Bell Notifikasi Dropdown | Dropdown Btn | Menampilkan 5 notifikasi terbaru | Polling AJAX `/notifications/recent` |
| | Tombol "Tandai Semua Dibaca" (di Dropdown)| Button | Menghapus status unread notifikasi | AJAX POST `/notifications/read-all` |
| | Tautan "Lihat Semua Riwayat Notifikasi" | Link | Masuk ke arsip notifikasi lengkap | `/notifications` |
| | Dropdown Profil Avatar | Dropdown Btn | Menu akun pengguna | Dropdown menu |
| | Sub-item Profil: "Dashboard" | Link | Menuju dashboard | `/dashboard` |
| | Sub-item Profil: "Pesan & Diskusi" | Link | Menuju inbox obrolan | `/chats` |
| | Sub-item Profil: "Notifikasi" | Link | Menuju riwayat notifikasi | `/notifications` |
| | Sub-item Profil Spesifik Role | Link | Pintasan cepat per peran | Sesuai peran aktif |
| | Sub-item Profil: "Keluar Akun (Logout)" | Link Action | Konfirmasi logout aman via modal dialog | `/logout` |
| **Glass Sidebar**<br>`sidebar.php` (Khusus Petugas & Admin) | Header Avatar & Nama | Info Card | Identitas role aktif | Non-interaktif |
| | Nav: Dashboard | Link | Menuju dashboard | `/dashboard` |
| | Nav Admin: Dashboard Admin | Link | Menuju `/admin` | `/admin` |
| | Nav Admin: Manajemen Pengguna | Link | Menuju kelola pengguna | `/admin/users` |
| | Nav Admin: Master Kategori | Link | Menuju master kategori | `/admin/categories` |
| | Nav Admin: Master Lokasi Kampus | Link | Menuju master lokasi kampus | `/admin/locations` |
| | Nav Admin: Rak & Gudang Center | Link | Menuju master rak penyimpanan | `/admin/storage-locations` |
| | Nav Admin: Audit Trail Keamanan | Link | Menuju audit trail | `/admin/audit-logs` |
| | Nav Admin: Pengaturan Sistem | Link | Menuju pengaturan AI & sistem | `/admin/settings` |
| | Nav Petugas/Admin: Inventaris Rak Fisik | Link | Menuju daftar inventaris rak | `/petugas/inventory` |
| | Nav Petugas/Admin: Verifikasi Bukti Klaim | Link | Menuju antrean verifikasi klaim | `/petugas/claims` |
| | Nav Petugas: Pesan & Diskusi | Link | Menuju ruang obrolan internal | `/chats` |
| | Nav Bottom: "Portal Publik" | Link | Menuju halaman depan publik | `/` |
| | Nav Bottom: "Keluar Akun" | Link Action | Konfirmasi logout via modal | `/logout` |

---

## 3. Analisis Duplikasi Alur & Elemen UI (Redundansi)

Berdasarkan audit fungsionalitas, ditemukan sejumlah titik redundansi signifikan di mana satu tujuan fungsional dapat diakses dari beberapa tempat berbeda:

1. **Duplikasi Dashboard Administrator (`/admin` vs `/dashboard`)**:
   - `AdminController@dashboard` merender `app/Views/admin/dashboard.php`.
   - `DashboardController@index` (ketika role=admin) merender `app/Views/dashboard/admin.php`.
   - Di Sidebar Admin, terdapat link **"Dashboard"** (`/dashboard`) dan link **"Dashboard Admin"** (`/admin`). Keduanya menyajikan data metrik yang hampir serupa dengan tata letak berbeda. Hal ini menimbulkan kebingungan bagi administrator mengenai mana konsol kontrol yang sesungguhnya.

2. **Duplikasi Pengelolaan Rak Penyimpanan (`/admin/storage-locations` vs `/petugas/storage-locations`)**:
   - Administrator memiliki halaman `/admin/storage-locations` (`AdminController@storageLocations`).
   - Petugas memiliki halaman `/petugas/storage-locations` (`PetugasController@storageLocations`).
   - Kedua controller mengelola tabel basis data yang persis sama (`storage_locations`), menyediakan form pembuatan rak, dan indikator kapasitas yang identik. Ini merupakan duplikasi antarmuka murni.

3. **Duplikasi Alur Penerimaan Barang Temuan Petugas (`/petugas/found-reports` vs `/petugas/handover`)**:
   - Halaman `/petugas/found-reports` menyajikan tab filter `?status=pending_handover` yang berisi tombol "Verifikasi Penyerahan Fisik" yang memanggil form `intakeForm`.
   - Halaman `/petugas/handover` ("Barang Menunggu Penyerahan") menyajikan tabel laporan yang persis sama dan juga memiliki tombol "Proses Penerimaan (Intake)" ke rute `intakeForm`.
   - Adanya dua rute terpisah untuk satu pekerjaan operasional (menerima barang fisik dari penemu ke posko) memecah fokus petugas piket.

4. **Navigasi Ganda Topbar vs Sidebar pada Petugas & Admin**:
   - Di perangkat layar lebar (desktop), pengguna peran Petugas dan Admin melihat menu di Topbar Navbar (seperti *Manajemen Pengguna*, *Audit Trail*, *Pengaturan*, *Klaim Masuk*, *Inventaris Rak*) sekaligus melihat tautan yang sama persis di Sidebar sebelah kiri.
   - Hal ini menyebabkan beban kognitif ganda, pemborosan ruang horizontal topbar, dan inkonsistensi pola navigasi desktop.

5. **Akses Berulang Fitur Klaim Saya & Laporan Saya pada Civitas**:
   - Civitas dapat mengakses "Laporan Saya" dari: (1) Topbar Nav link, (2) User Profile dropdown, (3) Tombol di Dashboard Civitas, dan (4) Tombol aksi di bawah tabel pencarian.
   - Klaim Saya dapat diakses dari: (1) Topbar Nav link, (2) Kartu stat Dashboard, dan (3) Tombol riwayat klaim.
   - Penempatan pintasan di dashboard bermanfaat, namun kehadiran link statis di topbar dan dropdown profil sekaligus membuat menu topbar civitas terlalu padat.

---

## 4. Elemen Kurang Berguna, Dekoratif & Sisa Pengembangan (Dead Code)

Hasil pemindaian berkas tampilan (`app/Views`) mendeteksi sejumlah elemen dan berkas yang tidak lagi memiliki nilai fungsional aktif:

1. **Berkas Tampilan Sisa / Dead View: `app/Views/dashboard/index.php`**:
   - Berkas ini adalah stub placeholder awal (Prompt 1) yang berisi banner bertuliskan *"🚧 Fondasi siap! Dashboard ini adalah halaman placeholder."*
   - `DashboardController@index` sudah menggunakan conditional switch ke `dashboard/admin`, `dashboard/petugas`, atau `dashboard/civitas`. Berkas `index.php` ini **100% tidak pernah dieksekusi lagi** (dead code).

2. **Berkas Tampilan Sisa: `app/Views/petugas/verify.php`**:
   - Berkas awal untuk verifikasi penyerahan barang temuan.
   - Pada `PetugasController.php`, method `verifyForm()` dialihkan menjadi alias yang merender `petugas/intake`. Berkas `petugas/verify.php` tidak lagi dirender oleh rute mana pun.

3. **Berkas Tampilan Sisa: `app/Views/petugas/claims.php`**:
   - Berkas single-view lama untuk antrean klaim sebelum Prompt 9 dirombak menjadi folder modular `app/Views/petugas/claims/index.php`, `show.php`, dan `handover.php`.
   - `PetugasClaimController@index` merender `petugas/claims/index`. Berkas `petugas/claims.php` adalah berkas mati.

4. **Rute Khusus Pengembangan: `/ui-components` (`pages/ui_components.php`)**:
   - Halaman showcase komponen UI statis untuk kebutuhan uji coba lokal. Tidak memiliki relevansi dengan operasional nyata pengguna.

5. **Tautan Jangkar `#fitur` di Beranda Landing Page**:
   - Tombol *"Pelajari Alur Sistem"* di hero banner hanya melakukan smooth scroll ke anchor `#fitur` yang berisi 3 kartu statis sederhana tanpa adanya navigasi atau aksi interaktif lanjutan.

6. **Tautan Pintasan "Portal Publik" di Bagian Bawah Sidebar**:
   - Di bagian bawah sidebar Petugas & Admin terdapat tautan "Portal Publik" menuju `/`.
   - Di saat yang sama, logo brand *CampusFind* di pojok kiri atas navbar selalu terlihat dan berfungsi mengarahkan ke `/`. Keberadaan link ini redundan dan memakan ruang vertikal sidebar.

7. **Tombol "Tandai Semua Dibaca" Ganda**:
   - Terdapat tombol *"Tandai semua dibaca"* di dalam dropdown notifikasi ringkas topbar, dan tombol yang persis sama di dalam halaman penuh `/notifications`. Tombol di dropdown ringkas kerap tertekan tidak sengaja saat membuka dropdown di mobile.

---

## 5. Analisis Kepadatan Halaman (Density Score & Metrik Visual)

Metrik kepadatan dihitung berdasarkan jumlah elemen interaktif aktif pada tiap layar dengan bobot:
- **Button / Aksi Interaktif**: Bobot 1.5
- **Link Navigasi / Tautan**: Bobot 1.0
- **Kartu Informasi (Cards)**: Bobot 2.0
- **Tabel Data Kompleks**: Bobot 3.0

$$\text{Density Score} = (\text{Total Buttons} \times 1.5) + (\text{Links} \times 1.0) + (\text{Cards} \times 2.0) + (\text{Tables} \times 3.0)$$

### Peringkat Halaman Berdasarkan Kepadatan UI

| Peringkat | Halaman / View | Role Terkait | Tombol | Tautan | Cards | Tabel | Density Score | Tingkat Kepadatan |
| :---: | :--- | :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **1** | **Antrean Klaim Petugas** (`petugas/claims/index.php`) | Petugas / Admin | 9 | 22 | 7 | 1 | **52.5** | 🔴 Sangat Padat |
| **2** | **Dashboard Admin** (`admin/dashboard.php`) | Admin | 7 | 7 | 11 | 1 | **42.5** | 🔴 Sangat Padat |
| **3** | **Tinjauan Bukti Klaim** (`petugas/claims/show.php`) | Petugas / Admin | 14 | 8 | 5 | 0 | **39.0** | 🔴 Sangat Padat |
| **4** | **Navbar Global** (`partials/navbar.php`) | Semua Role | 6 | 26 | 2 | 0 | **39.0** | 🔴 Sangat Padat |
| **5** | **Pencarian & Filter** (`search/index.php`) | Tamu / Civitas | 6 | 18 | 3 | 0 | **33.0** | 🟡 Padat |
| **6** | **Manajemen Pengguna** (`admin/users.php`) | Admin | 11 | 5 | 4 | 1 | **32.5** | 🟡 Padat |
| **7** | **Pengaturan Sistem** (`admin/settings.php`) | Admin | 5 | 3 | 10 | 0 | **30.5** | 🟡 Padat |
| **8** | **Laporan Saya** (`civitas/my_reports.php`) | Civitas | 6 | 9 | 2 | 2 | **28.0** | 🟡 Padat |
| **9** | **Daftar Barang Hilang** (`reports/lost/index.php`) | Tamu / Civitas | 6 | 12 | 3 | 0 | **27.0** | 🟢 Seimbang |
| **10** | **Daftar Barang Temuan** (`reports/found/index.php`) | Tamu / Civitas | 6 | 12 | 3 | 0 | **27.0** | 🟢 Seimbang |
| **11** | **Kandidat AI Match** (`ai_match/candidates.php`) | Civitas / Petugas | 6 | 5 | 5 | 0 | **24.0** | 🟢 Seimbang |
| **12** | **Dashboard Petugas** (`dashboard/petugas.php`) | Petugas | 4 | 4 | 6 | 0 | **22.0** | 🟢 Seimbang |
| **13** | **Inventaris Rak Fisik** (`petugas/inventory.php`) | Petugas | 3 | 8 | 3 | 1 | **21.5** | 🟢 Seimbang |
| **14** | **Riwayat Notifikasi** (`notifications/index.php`) | Civitas / Petugas | 5 | 8 | 3 | 0 | **21.5** | 🟢 Seimbang |
| **15** | **Antrean Temuan Masuk** (`petugas/found_reports.php`)| Petugas | 4 | 10 | 1 | 1 | **21.0** | 🟢 Seimbang |
| **16** | **Audit Trail** (`admin/audit_logs.php`) | Admin | 4 | 5 | 3 | 1 | **20.0** | 🟢 Seimbang |
| **17** | **Detail Temuan** (`reports/found/show.php`) | Civitas / Petugas | 4 | 6 | 4 | 0 | **20.0** | 🟢 Seimbang |
| **18** | **Dashboard Civitas** (`dashboard/civitas.php`) | Civitas | 3 | 3 | 6 | 0 | **19.5** | 🟢 Seimbang |
| **19** | **Pending Handover** (`petugas/handover.php`) | Petugas | 2 | 8 | 2 | 1 | **18.0** | 🟢 Seimbang |
| **20** | **Master Kategori / Lokasi** (`admin/categories.php`)| Admin | 9 | 1 | 1 | 0 | **16.5** | 🟢 Ringan |
| **21** | **Detail Barang Hilang** (`reports/lost/show.php`) | Civitas | 2 | 5 | 4 | 0 | **16.0** | 🟢 Ringan |
| **22** | **Detail Klaim Civitas** (`claims/show.php`) | Civitas | 1 | 6 | 4 | 0 | **15.5** | 🟢 Ringan |
| **23** | **Beranda Landing Page** (`home/index.php`) | Tamu | 3 | 3 | 4 | 0 | **15.5** | 🟢 Ringan |
| **24** | **Sidebar Global** (`partials/sidebar.php`) | Petugas / Admin | 0 | 15 | 0 | 0 | **15.0** | 🟢 Ringan |
| **25** | **Detail Barang di Rak** (`petugas/item_detail.php`)| Petugas | 3 | 4 | 3 | 0 | **14.5** | 🟢 Ringan |
| **26** | **Widget Chat C-Find** (`partials/cfind_widget.php`)| Tamu / Semua | 8 | 0 | 0 | 0 | **12.0** | 🟢 Ringan |
| **27** | **Klaim Saya** (`claims/my_claims.php`) | Civitas | 2 | 4 | 2 | 0 | **11.0** | 🟢 Ringan |
| **28** | **Buat Laporan / Form Klaim** (`create.php`) | Civitas | 2 | 3 | 1-2 | 0 | **8.0 - 10.0** | 🟢 Ringan |

### Temuan Kunci Kepadatan:
1. **Layar Paling Kritis (Density > 40)**:
   - **`petugas/claims/index.php` (Score 52.5)** memiliki 6 tombol pill filter status, paginasi, pencarian ID, indikator warna status berlapis, dan aksi modal di setiap baris. Perlu penyederhanaan tab status dan pengelompokan aksi baris.
   - **`admin/dashboard.php` (Score 42.5)** memuat 11 kartu sekaligus (6 metrik utama, 3 kartu pintasan master data, 2 kartu statistik visual) plus tabel audit log ringkas.
   - **`navbar.php` (Score 39.0)** memiliki 26 link aktif karena memuat menu navigasi peran admin/petugas di desktop topbar bersamaan dengan menu publik.

---

## 6. Tabel Keputusan Elemen UI (Pertahankan / Gabung / Hapus)

> **Catatan Kepatuhan PRD & Use Case**: Seluruh fitur inti (*pelaporan, verifikasi klaim rahasia, intake gudang, audit trail, AI matching, C-Find, pengaturan sistem*) **wajib dipertahankan**. Keputusan "Hapus" hanya diterapkan pada kode mati/artefak sisa pengembangan atau tautan navigasi duplikat yang mengganggu hierarki visual.

| Elemen UI / Komponen | Lokasi Saat Ini | Status Keputusan | Rekomendasi & Alasan Rasional |
| :--- | :--- | :---: | :--- |
| **Tautan Navigasi Peran di Topbar Desktop** (Pengguna, Audit, Settings, Inventaris, Klaim Masuk) | `app/Views/partials/navbar.php` (Baris 78–121) | **Gabung** | **Pindahkan sepenuhnya ke Sidebar**. Petugas & Admin sudah memiliki Sidebar khusus (`app_shell`). Menampilkan menu ini di Topbar sekaligus Sidebar menciptakan duplikasi navigasi desktop yang memakan ruang. Topbar sebaiknya hanya memuat: Brand, Search Global, Notifikasi, Pesan, dan Avatar Akun. |
| **Rute & View Dashboard Admin Ganda** (`dashboard/admin.php` vs `admin/dashboard.php`) | `app/Views/dashboard/admin.php` & `app/Views/admin/dashboard.php` | **Gabung** | Standarisasi akses Dashboard Admin hanya ke `/admin` (`admin/dashboard.php`). Jika Admin mengakses `/dashboard`, redirect langsung ke `/admin`. Hapus view `dashboard/admin.php` yang redundan. |
| **Modul Master Rak Penyimpanan Ganda** (`/admin/storage-locations` vs `/petugas/storage-locations`) | `AdminController` & `PetugasController` | **Gabung** | Satukan tampilan antarmuka ke satu tempat: Petugas menggunakan `/petugas/storage-locations`, dan Admin dapat mengaksesnya melalui tautan menu yang sama atau link terpadu. Hindari memelihara dua template view (`admin/storage_locations.php` & `petugas/storage_locations.php`) yang menyajikan data tabel identik. |
| **Alur Serah Terima Temuan Ganda** (`/petugas/found-reports` vs `/petugas/handover`) | `app/Views/petugas/found_reports.php` & `handover.php` | **Gabung** | Satukan dalam satu halaman kerja terpadu: **"Antrean Barang Temuan & Serah Terima"**. Daftar temuan civitas yang butuh verifikasi dan yang siap diserahkan ke meja posko digabung dengan filter tab status (`Semua`, `Menunggu Serah Terima Fisik`, `Telah Masuk Gudang`). Tombol aksi langsung mengarah ke `/petugas/intake/{id}`. |
| **Berkas Sisa: `app/Views/dashboard/index.php`** | `app/Views/dashboard/index.php` | **Hapus** | Berkas placeholder stub Prompt 1 yang sudah tidak pernah dipanggil oleh controller mana pun. Aman dibersihkan pada tahap refactoring. |
| **Berkas Sisa: `app/Views/petugas/verify.php`** | `app/Views/petugas/verify.php` | **Hapus** | Berkas dead code; `verifyForm()` di `PetugasController` sudah dialihkan secara internal ke `petugas/intake`. |
| **Berkas Sisa: `app/Views/petugas/claims.php`** | `app/Views/petugas/claims.php` | **Hapus** | Berkas dead code lama; antrean klaim resmi menggunakan folder modular `app/Views/petugas/claims/index.php`. |
| **Tautan Sisa: `/ui-components`** | `config/routes.php` & `app/Views/pages/ui_components.php` | **Hapus** | Modul dev lokal statis; tidak boleh muncul di arsitektur menu production. |
| **Tautan Navigasi "Portal Publik" di Bottom Sidebar** | `app/Views/partials/sidebar.php` (Baris 137–146) | **Hapus** | Menghilangkan duplikasi karena klik pada Brand Logo di navbar atas sudah mengarah ke `/`. Menghemat ruang vertikal sidebar. |
| **Tautan Anchor `#fitur` di Hero Beranda** | `app/Views/home/index.php` (Baris 34–36) | **Gabung** | Ubah tombol "Pelajari Alur Sistem" menjadi pemicu pembuka drawer Chatbot C-Find dengan prompt prefilled *"Bagaimana cara kerja sistem Campus Find?"*, atau arahkan langsung ke halaman bantuan resmi. |
| **Popup "Lupa Password" Statis di Login** | `app/Views/auth/login.php` | **Pertahankan** | Pertahankan informasi narasi bantuan kontak admin Lost & Found Center sampai fitur OTP/Self-Service Password Reset dikembangkan. |
| **Filter Status Antrean Klaim (6 Tab Pill)** | `app/Views/petugas/claims/index.php` | **Gabung** | Gabungkan 6 tab pill terpisah menjadi **1 dropdown filter status** yang ringkas disertai 2 tab utama: *Perlu Tindakan Petugas* (`pending`, `under_review`, `need_more_info`) dan *Arsip / Selesai* (`approved`, `completed`, `rejected`). Ini akan menurunkan Density Score dari 52.5 menjadi ~32. |
| **Tombol "Tandai Semua Dibaca" di Dropdown Notifikasi Ringkas** | `app/Views/partials/navbar.php` (Baris 154–159) | **Hapus dari Dropdown** | Tombol di dropdown ringkas rawan *accidental click* di mobile. Pertahankan tombol ini di halaman riwayat penuh `/notifications`. Dropdown ringkas fokus menyajikan item belum dibaca. |
| **Widget Chatbot C-Find (Floating)** | `app/Views/partials/cfind_widget.php` | **Pertahankan** | Sangat fungsional, responsif, dan aman berbasis session role (Tier 1 & Tier 2). |
| **Modul Rekomendasi AI Matching NIM** | `app/Views/ai_match/candidates.php` | **Pertahankan** | Fitur utama evaluasi kecocokan barang berbasis AI. Akses tetap melalui tombol di Detail Laporan Kehilangan (`/reports/lost/{id}`). |
| **Halaman Pengaturan Sistem Admin** | `app/Views/admin/settings.php` | **Pertahankan** | Fitur vital konfigurasi database untuk AI NIM, sakelar role chatbot, dan saluran notifikasi eksternal. |

---

## 7. Usulan Struktur Menu per Role (Clean Navigation Architecture)

Sesuai aturan arsitektur informasi:
- **Maksimal 6 menu utama** per role di tingkat atas.
- **Sub-menu maksimal 5 item** per grup.
- **100% dipetakan dari rute yang saat ini aktif ada di dalam sistem**.

### 7.1 Role: Tamu (Guest / Publik)
Fokus: Kemudahan mencari barang, memahami cara kerja alur kampus, dan autentikasi login.

* **1. Beranda** &rarr; `/`
* **2. Cari Barang** &rarr; `/search`
* **3. Laporan Publik** *(Dropdown / Flyout)*
  * Barang Hilang &rarr; `/reports/lost`
  * Barang Temuan &rarr; `/reports/found`
* **4. Cara Kerja** &rarr; `/#fitur`
* **5. Bantuan C-Find** &rarr; Pemicu Launcher Chatbot AI `cfind-launcher`
* **6. Masuk** *(Tombol Aksen Kanan)* &rarr; `/login`

---

### 7.2 Role: Civitas Akademika (Mahasiswa / Tendik / Dosen)
Fokus: Pelaporan mandiri, pelacakan laporan pribadi, pengajuan klaim kepemilikan, dan komunikasi.

* **1. Beranda** &rarr; `/dashboard` *(Dashboard Personal)*
* **2. Laporan** *(Dropdown Grup)*
  * Lapor Barang Hilang &rarr; `/reports/lost/create`
  * Lapor Barang Ditemukan &rarr; `/reports/found/create`
  * Riwayat Laporan Saya &rarr; `/civitas/my-reports`
* **3. Cari Barang** &rarr; `/search` *(Katalog Publik Kampus)*
* **4. Klaim Saya** &rarr; `/civitas/my-claims` *(Status & Riwayat Klaim)*
* **5. Pesan** &rarr; `/chats` *(Diskusi Laporan & Verifikasi Klaim)*
* **6. Akun** *(Avatar Dropdown Kanan)*
  * Notifikasi Sistem &rarr; `/notifications`
  * Portal Publik &rarr; `/`
  * Keluar Sesi &rarr; `/logout`

---

### 7.3 Role: Petugas Lost & Found Center
Fokus: Operasional posko penerimaan, manajemen rak fisik gudang, verifikasi bukti klaim privat, dan serah terima.

* **1. Ringkasan** &rarr; `/dashboard` *(Dashboard Operasional Posko)*
* **2. Barang & Gudang** *(Dropdown / Sidebar Grup)*
  * Inventaris Rak Center &rarr; `/petugas/inventory`
  * Antrean Serah Terima Temuan &rarr; `/petugas/found-reports` *(Terpadu dengan Handover)*
  * Rak & Lokasi Gudang &rarr; `/petugas/storage-locations`
* **3. Verifikasi Klaim** *(Dropdown / Sidebar Grup)*
  * Antrean Bukti Klaim Masuk &rarr; `/petugas/claims`
  * Riwayat Klaim Selesai &rarr; `/petugas/claims?status=completed`
* **4. Pesan & Bantuan** &rarr; `/chats` *(Komunikasi dengan Pelapor/Klaiman)*
* **5. Portal Publik** &rarr; `/` *(Penelusuran Katalog Terbuka)*
* **6. Akun Petugas** *(Avatar Dropdown Kanan)*
  * Notifikasi Operasional &rarr; `/notifications`
  * Keluar Sesi &rarr; `/logout`

---

### 7.4 Role: Administrator Sistem (Super User)
Fokus: Pengawasan metrik menyeluruh, tata kelola akun user, master data kampus, audit trail keamanan, dan setelan sistem.

* **1. Ringkasan** &rarr; `/admin` *(Dashboard Pengawasan Eksekutif)*
* **2. Pengguna** &rarr; `/admin/users` *(Manajemen Akun, Role, & Status)*
* **3. Master Data** *(Dropdown / Sidebar Grup)*
  * Kategori Barang &rarr; `/admin/categories`
  * Lokasi Gedung Kampus &rarr; `/admin/locations`
  * Gudang & Rak Penyimpanan &rarr; `/admin/storage-locations`
* **4. Monitoring & Keamanan** *(Dropdown / Sidebar Grup)*
  * Audit Trail Keamanan &rarr; `/admin/audit-logs`
  * Riwayat Log Chat AI NIM &rarr; `/admin/audit-logs?action=ai_cfind_chat`
* **5. Pengaturan Sistem** &rarr; `/admin/settings` *(Konfigurasi AI Provider, Kuota Role, & Webhook)*
* **6. Akun Admin** *(Avatar Dropdown Kanan)*
  * Notifikasi Sistem &rarr; `/notifications`
  * Portal Publik &rarr; `/`
  * Keluar Sesi &rarr; `/logout`

---

## 8. Ringkasan Rencana Aksi Pembersihan Pasca-Persetujuan

Bila laporan audit dan rekomendasi arsitektur ini telah disetujui, langkah implementasi teknis berikutnya adalah:
1. **Pembersihan Berkas Mati**: Menghapus `app/Views/dashboard/index.php`, `app/Views/petugas/verify.php`, dan `app/Views/petugas/claims.php`.
2. **Penyatuan Topbar & Sidebar**: Menghapus tautan menu role di Topbar Desktop `navbar.php`, memusatkan navigasi Petugas dan Admin 100% pada Glass Sidebar `sidebar.php`.
3. **Penyatuan Antrean Temuan**: Mengharmonisasikan rute `/petugas/handover` dan `/petugas/found-reports` agar tidak saling tumpang tindih.
4. **Penerapan Menu 6 Pilar**: Mengonfigurasi ulang navbar dan sidebar sesuai usulan bab 7 untuk menghadirkan pengalaman pengguna yang bersih, cepat, dan intuitif.

---
*Laporan audit selesai dibuat dan siap untuk ditinjau oleh pemangku kepentingan sistem.*
