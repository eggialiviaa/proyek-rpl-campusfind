# Campus Find — Design Tokens & UI Guidelines
**Dokumen Acuan Tunggal Token Desain & Antarmuka Pengguna**  
*Standar Desain Global: Natural Minimalism (Soft White, Sage Green, Deep Olive)*  
*Tanggal: 9 November 2026 (Task 01 Design System Revamp)*

---

## 1. Filosofi & Prinsip Desain: Natural Minimalism

1. **Natural Minimalism**: Menghadirkan antarmuka modern, bersih, tenang, dan premium yang terinspirasi oleh palet alam kampus: *Soft White* sebagai kanvas, *Sage Green* sebagai aksen lembut, dan *Deep Olive* sebagai penekanan kontras tinggi.
2. **Keterbacaan & Kontras Maksimal**: Teks utama dan tombol aksi primer menggunakan **Deep Olive (`#2E3D28`)** di atas kanvas **Soft White (`#E8E9DF`)** maupun permukaan **Sage Green (`#A9B887`)**, menghasilkan rasio kontras WCAG AAA yang nyaman dibaca.
3. **Tampilan Konsisten (Bebas Konflik Dark Mode)**: Tampilan terang natural menjadi standar konsisten seluruh pengguna di semua peramban dan sistem operasi, tanpa kerusakan layout akibat pemaksaan tema gelap parsial oleh OS.
4. **Layout Lebar Penuh (Full-Width Responsive)**: Seluruh batas kaku konten lama (`max-width: 1100px`) telah dihilangkan. Kanvas, bilah navigasi, dan footer membentang penuh (100% viewport width) dengan wadah konten responsif hingga 1360px yang lapang untuk tabel data, statistik, dan operasional.
5. **Permukaan Bersih & Bayangan Halus**: Menghindari glassmorphism tebal dan bayangan hitam pekat; beralih ke permukaan putih bersih (`#FFFFFF`) dengan garis batas natural sangat halus (`rgba(46, 61, 40, 0.10)`) dan bayangan lembut.
6. **Aksesibilitas & Inklusivitas**: Navigasi keyboard penuh (*Enter, Space, ArrowDown, Esc, Tab*), target sentuh mobile minimal 44px, dan dukungan `prefers-reduced-motion`.

---

## 2. Palet Warna (Color Palette Tokens)

### 2.1 Palet Utama (Primary Palette)
| Token CSS | Nilai Hex / RGBA | Peran & Penggunaan |
| :--- | :--- | :--- |
| `--cf-bg` | `#E8E9DF` | **Soft White**: Latar belakang kanvas utama seluruh website |
| `--cf-primary` | `#2E3D28` | **Deep Olive**: Tombol utama, judul, teks penting, navigasi aktif |
| `--cf-accent` | `#A9B887` | **Sage Green**: Aksen visual, lencana netral, sorotan baris, indikator aktif |

### 2.2 Token Pendukung & Permukaan (Surfaces & Typography)
| Token CSS | Nilai Hex / RGBA | Peran & Penggunaan |
| :--- | :--- | :--- |
| `--cf-canvas` | `#E8E9DF` | Kanvas latar belakang halaman |
| `--cf-surface` | `#FFFFFF` | Permukaan kartu, modal, dan tabel di atas kanvas |
| `--cf-surface-subtle` | `#F5F6F0` | Permukaan hangat untuk header tabel dan section sekunder |
| `--cf-surface-muted` | `#DFE1D6` | Permukaan teredam untuk panel sub-menu flyout navigasi |
| `--cf-text-main` | `#2E3D28` | Teks utama, judul halaman (`h1`–`h6`), dan label kolom penting |
| `--cf-text-muted` | `#52604D` | Teks sekunder, deskripsi, paragraf, dan keterangan waktu |
| `--cf-text-subtle` | `#768571` | Teks placeholder input dan label metadata kecil |
| `--cf-border` | `rgba(46, 61, 40, 0.14)` | Garis pembatas halus berwarna zaitun alami |
| `--cf-border-light` | `rgba(46, 61, 40, 0.08)` | Garis pembatas sangat tipis antar baris tabel |
| `--cf-focus-ring` | `0 0 0 3.5px rgba(169, 184, 135, 0.45)` | Ring fokus aksesibilitas (Sage Green glow) |

### 2.3 Token Navigasi Terpadu
| Token CSS | Nilai Hex / RGBA | Peran & Penggunaan |
| :--- | :--- | :--- |
| `--bar` | `rgba(232, 233, 223, 0.92)` | Bilah navigasi atas (Soft White translucent + blur) |
| `--bg` | `#E8E9DF` | Latar belakang kanvas navigasi |
| `--bg2` | `#DFE1D6` | Latar belakang panel flyout desktop |
| `--tx` | `#2E3D28` | Warna teks menu utama |
| `--mu` | `#52604D` | Warna teks menu pendukung |
| `--ac` | `#2E3D28` | Warna aksen interaksi utama |
| `--ln` | `rgba(46, 61, 40, 0.14)` | Garis batas tipis navigasi |
| `--scrim` | `rgba(46, 61, 40, 0.28)` | Overlay redup lembut saat flyout terbuka |

### 2.4 Warna Status Semantik (Khusus Badge & Status Fungsional)
*Warna status dipertahankan makna fungsionalnya untuk identifikasi data yang cepat:*
- **Tersimpan / Selesai / Ditemukan**: Forest Green (`#2D7D46` / bg `rgba(45, 125, 70, 0.12)`)
- **Proses / Menunggu Verifikasi / Hilang**: Warm Amber (`#B45309` / bg `rgba(180, 83, 9, 0.12)`)
- **Ditolak / Bahaya / Error**: Crimson Rose (`#BE123C` / bg `rgba(190, 18, 60, 0.10)`)
- **Informasi / Rak Posko**: Slate Blue (`#0369A1` / bg `rgba(3, 105, 161, 0.10)`)
- **AI Matching / Rekomendasi**: Heather Purple (`#5E4B8B` / bg `rgba(94, 75, 139, 0.10)`)
- **Netral / Informasi Umum**: Sage Green Light (`rgba(169, 184, 135, 0.22)` / border `rgba(169, 184, 135, 0.45)` / text `#2E3D28`)

---

## 3. Tipografi (Typography Stack)

### 3.1 Keluarga Font
Menggunakan font Inter / System UI Font Stack:
```css
font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
```

### 3.2 Bobot Font
1. **Regular (400)**: Teks tubuh (*body*), paragraf, deskripsi form.
2. **Medium (500)**: Tautan navigasi, label tombol sekunder.
3. **Semibold (600)**: Label form, judul tabel, sub-judul, tombol aksi.
4. **Bold / Extrabold (700 / 800)**: Judul halaman (`h1`–`h3`), metrik angka stat card.

---

## 4. Spasi, Dimensi, & Tata Letak (Spacing & Layout)

| Token / Aturan | Nilai | Keterangan |
| :--- | :--- | :--- |
| **Tinggi Bar Navigasi** | `48px` | Baris navigasi atas dengan safe area support |
| **Lebar Maksimal Wadah Konten** | `1360px` | Memastikan ruang lapang di desktop untuk tabel dan dashboard |
| **Lebar Maksimal Bilah Navigasi** | `1360px` | Rata dengan batas konten utama |
| **Target Sentuh Minimal** | `42px` / `44px` | Kenyamanan akses tombol pada perangkat layar sentuh |
| **Radius Sudut Standar** | `8px` (`--cf-radius`) | Radius sudut tombol dan input form |
| **Radius Sudut Kartu** | `16px` (`--cf-radius-lg`) | Radius sudut kartu data, panel, dan tabel |
| **Garis Pembatas Halus** | `1px solid var(--cf-border)` | Garis batas alami tanpa bayangan berlebih |

---

## 5. Hierarki Interaktif & Aksesibilitas

1. **Tombol Primer (`.btn-primary`)**:
   - Berwarna **Deep Olive (`#2E3D28`)** dengan teks putih (`#FFFFFF`).
   - Efek hover berubah ke Deep Olive yang lebih pekat (`#1F2B1B`) dengan elevasi halus.
2. **Tombol Aksen (`.btn-accent` / `.btn-sage`)**:
   - Berwarna **Sage Green (`#A9B887`)** dengan teks Deep Olive (`#2E3D28`).
3. **Form Controls (`.form-control`, `.form-select`)**:
   - Latar belakang putih bersih (`#FFFFFF`), teks Deep Olive (`#2E3D28`), border zaitun halus.
   - Fokus: Border Deep Olive dengan ring Sage Green lembut (`var(--cf-focus-ring)`).
4. **Tabel Data (`.cf-table`)**:
   - Header tabel bernuansa hangat dengan teks Deep Olive kapital kecil berbobot 700.
   - Efek hover baris memberikan sorotan Sage Green tipis (`rgba(169, 184, 135, 0.12)`).
5. **Chatbot C-Find (`.cfind-launcher`)**:
   - Tombol mengambang gradien Deep Olive elegan dengan cincin denyut (*pulse*) Sage Green.
