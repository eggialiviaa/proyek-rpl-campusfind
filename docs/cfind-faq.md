# Dokumentasi Panduan Tanya Jawab Resmi (FAQ) — Campus Find

Dokumen ini memuat panduan operasional dan alur kerja sistem **Campus Find** yang disusun langsung dari perilaku aktual kode sumber aplikasi (*as-built behavior*). Chatbot C-Find menggunakan tabel FAQ ini sebagai basis pengetahuan resmi untuk menjawab pertanyaan pengguna seputar fitur sistem.

---

## 0. Akun, Login, Aktivasi, dan Keamanan Kredensial

| Pertanyaan / Topik | Jawaban & Perilaku Nyata Sistem |
| :--- | :--- |
| **Bagaimana cara login / masuk ke Campus Find?** | Anda dapat masuk menggunakan NIM atau alamat email kampus terdaftar beserta kata sandi Anda di halaman Masuk (`/login`). Pastikan akun Anda telah didaftarkan oleh Administrator kampus dan sudah diaktivasi sebelum melakukan login. Jika terjadi 5 kali kegagalan login berturut-turut, akun atau perangkat Anda akan terkunci sementara selama 5 menit. |
| **Siapa yang mendaftarkan akun? / Bagaimana cara daftar jika belum punya akun?** | Akun Campus Find didaftarkan secara resmi oleh Administrator kampus melalui impor data civitas atau penambahan akun manual oleh Admin. Pengguna tidak dapat mendaftar mandiri secara bebas di dalam aplikasi. Jika Anda belum terdaftar atau belum memiliki akun, silakan hubungi Administrator kampus untuk pendaftaran data akun Anda. |
| **Bagaimana cara mengaktifkan akun baru (Aktivasi Akun)?** | Akun baru yang diimpor oleh Admin berstatus belum aktif dan dapat diaktivasi mandiri melalui menu **Aktivasi Akun** (`/activate`) dengan memasukkan NIM atau email untuk menerima kode OTP 6 digit di email kampus Anda dan membuat kata sandi pribadi. Selain itu, Admin juga dapat memberikan kata sandi sementara yang wajib langsung diganti saat Anda pertama kali masuk ke sistem. Setelah kata sandi ditetapkan, akun otomatis aktif dan siap digunakan. |
| **Bagaimana jika lupa kata sandi (Lupa Password)?** | Buka halaman login lalu klik tautan **Lupa password?** (`/forgot-password`), kemudian masukkan NIM atau alamat email terdaftar Anda. Sistem akan mengirimkan kode verifikasi OTP 6 digit ke alamat email kampus Anda untuk mengatur ulang kata sandi baru. Kode OTP berlaku selama 5 menit. |
| **Apa aturan kunci akun (Account Lockout)?** | Akun atau perangkat akan terkunci sementara selama 5 menit apabila terjadi 5 kali percobaan login yang gagal secara berturut-turut. Sistem sengaja menampilkan pesan kredensial generik ("NIM/email atau kata sandi salah") untuk mencegah penebakan akun terdaftar. Anda dapat mencoba masuk kembali setelah waktu penguncian berakhir. |

---

## 1. Diskusi Internal & Perpesanan Resmi (Chat)

| Pertanyaan | Jawaban & Perilaku Nyata Sistem |
| :--- | :--- |
| **Kapan ruang obrolan (chat room) dibuat?** | Ruang obrolan dibuat secara otomatis (*on-demand*) ketika salah satu pihak (pelapor atau pengaju klaim) menekan tombol **"Diskusi / Bantuan Petugas"** pada halaman detail laporan kehilangan/temuan, atau tombol **"Diskusi Klaim"** pada halaman detail klaim. Ruang chat tidak dibuat secara bebas tanpa konteks agar riwayat percakapan selalu terikat pada tiket laporan atau klaim terkait. |
| **Siapa yang bisa memulai obrolan?** | Obrolan dapat dimulai oleh **pemilik laporan**, **pengaju klaim**, atau **Petugas/Admin** yang sedang memverifikasi laporan/klaim tersebut. Pengguna lain tidak memiliki akses (dilindungi proteksi anti-IDOR dan RBAC). |
| **Bagaimana cara mengakses pesan yang sudah ada?** | Seluruh ruang percakapan aktif yang melibatkan akun Anda dapat diakses kapan saja melalui bilah navigasi atas pada menu **Pesan** (`/chats`). |
| **Apakah pengunjung tamu (belum login) bisa melakukan chat?** | Tidak. Fitur diskusi internal mewajibkan login akun kampus terdaftar demi keamanan, pertanggungjawaban verifikasi bukti kepemilikan, dan rekam jejak audit. |

---

## 2. Pelaporan Barang Hilang (Lost Reports)

| Pertanyaan | Jawaban & Perilaku Nyata Sistem |
| :--- | :--- |
| **Bagaimana cara melapor barang hilang?** | Masuk ke akun Anda, buka menu **Laporan** &rarr; **Lapor Barang Hilang** (`/reports/lost/create`), isi judul barang, kategori, lokasi perkiraan hilang, tanggal, deskripsi ciri-ciri barang, dan unggah foto referensi (jika ada), lalu klik tombol **"Terbitkan Laporan"**. |
| **Siapa saja yang bisa melihat laporan kehilangan?** | Laporan yang telah diterbitkan berstatus `published` dan dapat dilihat di katalog pencarian kampus. Data sensitif seperti identitas kontak pelapor tetap terlindungi dan hanya dapat dihubungi melalui sistem pesan resmi. |
| **Bisakah laporan diedit atau dihapus?** | Ya, pelapor dapat mengedit atau menghapus laporan kehilangan miliknya melalui tombol aksi di halaman detail laporan, selama laporan tersebut belum berstatus `claimed` (selesai diserahkan). |

---

## 3. Pelaporan Barang Temuan & Serah Terima Fisik (Found Reports)

| Pertanyaan | Jawaban & Perilaku Nyata Sistem |
| :--- | :--- |
| **Bagaimana alur melapor barang yang ditemukan?** | Buka menu **Laporan** &rarr; **Lapor Barang Ditemukan** (`/reports/found/create`). Anda dapat memilih opsi apakah barang langsung diserahkan ke posko Lost & Found Center (`pending_handover`) atau disimpan mandiri sementara waktu oleh penemu (`published`). |
| **Kapan barang temuan diberi kode inventaris fisik?** | Kode inventaris fisik (`ITM-YYYYMMDD-XXXX`) dan alokasi nomor rak penyimpanan diterbitkan secara resmi oleh Petugas posko saat barang fisik diserahkan dan diverifikasi melalui modul penerimaan (*Intake*) di posko. |

---

## 4. Pengajuan & Verifikasi Klaim (Claims)

| Pertanyaan | Jawaban & Perilaku Nyata Sistem |
| :--- | :--- |
| **Bagaimana cara mengajukan klaim kepemilikan?** | Temukan barang terkait di katalog temuan (`/reports/found/{id}`), lalu klik tombol **"Ini Barang Saya! (Ajukan Klaim)"**. Isi formulir bukti kepemilikan secara detail (ciri rahasia, nomor seri, isi khusus, atau foto struk pembelian) yang tidak dipublikasikan ke umum. Kotak klaim hanya tampil bagi Civitas yang bukan pelapor temuan dan barang sudah tersimpan di posko. |
| **Apa saja tahapan status klaim?** | 1. `pending`: Menunggu pemeriksaan awal oleh Petugas.<br>2. `under_review`: Sedang diperiksa dan dicocokkan buktinya oleh Petugas.<br>3. `need_more_info`: Petugas meminta klarifikasi bukti tambahan melalui fitur chat.<br>4. `approved`: Klaim disetujui, pemohon dipersilakan mengambil barang fisik di posko.<br>5. `rejected`: Klaim ditolak karena bukti tidak sesuai atau tidak memadai.<br>6. `completed`: Barang fisik telah diserahkan dan diterima oleh pemohon di posko. |
| **Siapa yang memutuskan persetujuan klaim?** | Keputusan verifikasi klaim 100% berada di tangan Petugas Lost & Found Center melalui pemeriksaan bukti kepemilikan. Chatbot C-Find tidak pernah mengambil keputusan hukum terkait persetujuan klaim. |

---

## 5. Inventaris Rak Center vs Katalog Publik

| Konsep | Definisi & Perbedaan Nyata |
| :--- | :--- |
| **Barang di Rak Posko (Inventaris Fisik)** | Seluruh barang fisik yang telah diterima, diperiksa kondisinya, diberi label kode inventaris, dan disimpan di rak/lemari posko Center oleh Petugas (tabel `items` dengan status `stored`). |
| **Katalog Publik (Cari Barang)** | Seluruh laporan kehilangan dan laporan temuan yang masih terbuka dan aktif dicari/ditemukan di lingkungan kampus (tabel `lost_reports` dan `found_reports` dengan status publik). Barang yang telah selesai diklaim atau dikembalikan otomatis keluar dari pencarian publik. |

---

## 6. Lokasi Posko & Jam Operasional

| Informasi | Keterangan Resmi |
| :--- | :--- |
| **Lokasi Posko Fisik** | Gedung Rektorat Lt. 1, Ruang Layanan Terpadu Lost & Found Center, Kampus Utama. |
| **Jam Operasional Posko** | Senin sampai Jumat, pukul 08:00 – 16:00 WIB (Hari libur nasional dan cuti bersama tutup). |
| **Syarat Pengambilan Barang** | Membawa Kartu Tanda Mahasiswa (KTM) / Kartu Pegawai / KTP asli, serta menunjukkan tiket klaim berstatus `approved` di aplikasi. |
