# TECH_STACK.md — Campus Find: Lost & Found System
# STATUS: TERKUNCI — Jangan diubah tanpa instruksi eksplisit dari owner project

---

## Overview

**Campus Find** adalah sistem Lost & Found berbasis web untuk lingkungan kampus.
Dikembangkan secara bertahap melalui serangkaian prompt terpisah di Antigravity,
dengan Laragon sebagai local development environment.

---

## 🔒 Stack Resmi dan Terkunci

### Backend

| Komponen       | Keputusan                                        |
|----------------|--------------------------------------------------|
| Bahasa         | **PHP 8.x**                                      |
| Arsitektur     | **Native MVC** (tanpa framework backend besar)   |
| Paradigma      | Server-side rendering                            |
| Routing        | Custom router MVC yang dibangun dalam project    |
| Prinsip        | Separation of Concerns (Model, View, Controller, Service, Middleware, Config) |

> Dilarang: Laravel, Symfony, CodeIgniter, Slim, atau framework PHP lainnya.

---

### Database

| Komponen       | Keputusan                        |
|----------------|----------------------------------|
| Database       | **MySQL**                        |
| Pengelolaan    | Via **Laragon** (local)          |
| Tipe           | Relational Database              |
| Schema         | Migration/schema file berbasis SQL |

> Dilarang: SQLite, PostgreSQL, MongoDB, atau ORM besar tanpa persetujuan eksplisit.

---

### Frontend

| Komponen       | Keputusan                     |
|----------------|-------------------------------|
| Markup         | **HTML5**                     |
| Styling        | **CSS3** (custom + Bootstrap) |
| Scripting      | **JavaScript** (Vanilla)      |
| UI Framework   | **Bootstrap 5**               |
| Responsif      | Desktop, Tablet, Smartphone   |

> Dilarang: React, Vue, Angular, Tailwind, atau frontend framework/library besar lainnya
> kecuali instruksi eksplisit dari owner.

---

### Environment Configuration

| Komponen       | Keputusan                                          |
|----------------|----------------------------------------------------|
| Config file    | **`.env`** di root project                         |
| PHP reader     | **`vlucas/phpdotenv`** (via Composer) — ringan, stabil, populer |
| Aturan ketat   | Tidak ada hardcode credential, password, API key, atau secret di source code |
| Git safety     | `.env` wajib masuk `.gitignore`, sediakan `.env.example` |

---

### Dependency Management

| Komponen       | Keputusan                                          |
|----------------|----------------------------------------------------|
| PHP            | **Composer** (hanya jika benar-benar diperlukan)   |
| Node/JS        | **npm** (hanya jika diperlukan untuk asset/build)  |

> Prinsip: Minimal dependency. Jangan tambahkan library yang tidak dibutuhkan.

---

### Local Development Environment

| Komponen       | Keputusan                                      |
|----------------|------------------------------------------------|
| Server         | **Laragon** (Apache + PHP + MySQL bundled)     |
| Root project   | `C:\laragon\www\campus-find`                   |
| URL lokal      | `http://campus-find.test` atau `http://localhost/campus-find` |
| PHP Version    | 8.x (sesuai versi Laragon yang terinstall)     |

---

### AI Integration

| Komponen       | Keputusan                                                       |
|----------------|-----------------------------------------------------------------|
| Provider       | **NVIDIA NIM API**                                              |
| Tahap          | Diintegrasikan **hanya pada tahap yang ditentukan** oleh prompt AI |
| API Key        | Disimpan **hanya di backend** via `.env`                        |

> DILARANG KERAS: Mengekspos NVIDIA API key ke frontend, HTML, JavaScript, atau repository.

---

## 📐 Arsitektur MVC

```
campus-find/
├── app/
│   ├── Controllers/        # Logika request/response
│   ├── Models/             # Interaksi database
│   ├── Services/           # Business logic (terpisah dari Controller)
│   ├── Middleware/         # Auth, logging, dsb
│   └── Views/              # Template HTML (PHP native)
├── config/                 # Konfigurasi app (db, routes, dsb)
├── core/                   # Router, BaseController, BaseModel, App bootstrap
├── public/                 # Document root (index.php, assets)
│   ├── index.php           # Entry point tunggal
│   ├── css/
│   ├── js/
│   └── assets/
├── database/
│   └── migrations/         # File SQL schema/migration
├── storage/                # Upload, logs (tidak di-commit)
├── vendor/                 # Composer packages (tidak di-commit)
├── .env                    # Environment variables (tidak di-commit)
├── .env.example            # Template .env (di-commit)
├── .gitignore
├── composer.json
└── TECH_STACK.md           # File ini
```

> Entry point tunggal: `public/index.php` — semua request diarahkan ke sini via `.htaccess`.

---

## 🏷️ Naming Convention (Terkunci)

| Target                  | Konvensi       | Contoh                        |
|-------------------------|----------------|-------------------------------|
| Folder & file PHP       | `snake_case`   | `lost_item.php`, `auth_middleware.php` |
| Folder & file View      | `snake_case`   | `item_detail.php`, `dashboard.php` |
| Class PHP               | `PascalCase`   | `LostItemController`, `UserModel` |
| Method / Function PHP   | `camelCase`    | `getItemById()`, `uploadImage()` |
| Database table          | `snake_case`   | `lost_items`, `found_reports` |
| Database field/column   | `snake_case`   | `user_id`, `created_at`       |
| CSS class               | `kebab-case`   | `.card-item`, `.btn-primary`  |
| JavaScript variable     | `camelCase`    | `itemList`, `fetchItems()`    |
| Constants PHP           | `UPPER_SNAKE`  | `APP_ENV`, `DB_HOST`          |

---

## ✅ Aturan Stack

1. **Stack ini bersifat TERKUNCI** sejak Prompt 0 dan berlaku untuk seluruh prompt berikutnya.
2. Tidak boleh mengganti bahasa, framework, database, atau environment utama tanpa instruksi eksplisit dari owner project.
3. Setiap keputusan teknis baru yang tidak tercakup di sini harus mengutamakan: **sederhana → stabil → aman → gratis → sesuai project akademik**.
4. Jika ada pilihan library/tool alternatif, pilih yang paling minimal dan tidak menambah kompleksitas tidak perlu.
5. NVIDIA NIM API key **tidak boleh pernah** muncul di frontend, commit, atau log.

---

## 📋 Composer Dependencies (Diizinkan)

| Package                  | Tujuan                        | Status     |
|--------------------------|-------------------------------|------------|
| `vlucas/phpdotenv`       | Membaca file `.env`           | Diizinkan  |

> Tambahan dependency lain harus mendapat persetujuan eksplisit dari owner project.

---

*Dibuat: Prompt 0 — Stack Lock*
*Terakhir diperbarui: Prompt 0*
