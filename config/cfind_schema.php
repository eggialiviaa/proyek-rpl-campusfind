<?php

declare(strict_types=1);

/**
 * config/cfind_schema.php — Registry Skema Semantik C-Find Data Agent (Prompt 29)
 *
 * Mendefinisikan:
 * 1. Entitas yang boleh ditanyakan per role.
 * 2. Field yang boleh dipilih, disaring, diurutkan, dikelompokkan (nama, tipe, label, sinonim).
 * 3. Relasi whitelist join antar tabel.
 * 4. Aturan cakupan baris (row-level security) wajib per role.
 * 5. Daftar field terlarang (blacklist absolut untuk semua role).
 */

return [
    /* ─────────────────────────────────────────────────────────────
       A. FORBIDDEN FIELDS (Blacklist Absolut - Tak Pernah Muncul)
       ───────────────────────────────────────────────────────────── */
    'forbidden_fields' => [
        'password',
        'password_hash',
        'token',
        'session_id',
        'remember_token',
        'api_key',
        'evidence_value',
        'file_path',
        'private_details',
        'internal_notes',
        'phone',
        'identity_number',
        'chat_messages',
        'reviewer_notes',
    ],

    /* ─────────────────────────────────────────────────────────────
       B. ROLE ENTITY PERMISSIONS (Entitas yang Boleh Diakses per Role)
       ───────────────────────────────────────────────────────────── */
    'role_permissions' => [
        'guest' => [], // Tamu: TIDAK ADA akses data sama sekali
        'civitas' => [
            'my_lost_reports',
            'my_claims',
            'my_notifications',
            'public_catalog',
            'categories',
            'locations',
        ],
        'petugas' => [
            'claims_queue',
            'posko_items',
            'stored_items',
            'found_reports',
            'lost_reports',
            'categories',
            'locations',
            'storage_locations',
            'status_histories',
            'public_catalog',
            'my_notifications',
        ],
        'admin' => [
            'claims_queue',
            'posko_items',
            'stored_items',
            'found_reports',
            'lost_reports',
            'categories',
            'locations',
            'storage_locations',
            'status_histories',
            'public_catalog',
            'my_notifications',
            'users',
            'audit_logs',
            'login_activity',
        ],
    ],

    /* ─────────────────────────────────────────────────────────────
       C. ENTITIES DEFINITIONS & FIELDS REGISTRY
       ───────────────────────────────────────────────────────────── */
    'entities' => [
        // 1. Antrean Klaim (Petugas & Admin) / Klaim Milik Sendiri (Civitas)
        'claims_queue' => [
            'table'       => 'claims',
            'primary_key' => 'id',
            'label'       => 'Pengajuan Klaim',
            'description' => 'Data klaim barang hilang/temuan yang diajukan oleh civitas',
            'joins'       => [
                'claimant' => [
                    'table'      => 'users',
                    'foreign_key'=> 'claimant_user_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
                'item' => [
                    'table'      => 'items',
                    'foreign_key'=> 'item_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
                'found_report' => [
                    'table'      => 'found_reports',
                    'foreign_key'=> 'found_report_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'claims.id',
                    'label'    => 'ID Klaim',
                    'synonyms' => ['id'],
                ],
                'claim_number' => [
                    'type'     => 'string',
                    'column'   => 'claims.claim_number',
                    'label'    => 'Nomor Tiket Klaim',
                    'synonyms' => ['kode klaim', 'nomor klaim', 'nomor tiket', 'kode'],
                ],
                'status' => [
                    'type'     => 'string',
                    'column'   => 'claims.status',
                    'label'    => 'Status Klaim',
                    'synonyms' => ['status', 'tahap', 'kondisi verifikasi'],
                    'allowed_values' => ['pending_verification', 'approved', 'rejected', 'handover_scheduled', 'completed', 'cancelled'],
                ],
                'claimant_name' => [
                    'type'     => 'string',
                    'column'   => 'claimant.name',
                    'label'    => 'Nama Pengaju Klaim',
                    'synonyms' => ['nama pengklaim', 'pengaju', 'pemilik', 'dari siapa', 'siapa saja'],
                    'restricted_to' => ['petugas', 'admin'],
                ],
                'item_title' => [
                    'type'     => 'string',
                    'column'   => 'COALESCE(item.title, found_report.title)',
                    'label'    => 'Barang yang Diklaim',
                    'synonyms' => ['nama barang', 'judul barang', 'barang'],
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'claims.created_at',
                    'label'    => 'Waktu Pengajuan Klaim',
                    'synonyms' => ['waktu', 'tanggal', 'kapan', 'jam berapa', 'hari ini'],
                ],
                'reviewed_at' => [
                    'type'     => 'datetime',
                    'column'   => 'claims.reviewed_at',
                    'label'    => 'Waktu Peninjauan Klaim',
                    'synonyms' => ['waktu verifikasi', 'tanggal review'],
                ],
            ],
            'default_order' => 'claims.created_at DESC',
            'row_scope' => [
                'civitas' => 'claims.claimant_user_id = :session_user_id',
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 2. Klaim Milik Sendiri (Alias khusus Civitas)
        'my_claims' => [
            'extends'   => 'claims_queue',
            'label'     => 'Klaim Milik Saya',
            'row_scope' => [
                'civitas' => 'claims.claimant_user_id = :session_user_id',
            ],
        ],

        // 3. Barang Fisik di Posko Center / Rak Gudang (items status='stored')
        'posko_items' => [
            'table'       => 'items',
            'primary_key' => 'id',
            'label'       => 'Barang di Posko (Rak Center)',
            'description' => 'Barang temuan yang disimpan secara fisik di rak posko Lost & Found Center',
            'joins'       => [
                'category' => [
                    'table'      => 'categories',
                    'foreign_key'=> 'category_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
                'storage' => [
                    'table'      => 'storage_locations',
                    'foreign_key'=> 'storage_location_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'items.id',
                    'label'    => 'ID Barang',
                    'synonyms' => ['id'],
                ],
                'item_code' => [
                    'type'     => 'string',
                    'column'   => 'items.item_code',
                    'label'    => 'Kode Barang Posko',
                    'synonyms' => ['kode barang', 'kode barcode', 'kode posko', 'nomor barang'],
                ],
                'title' => [
                    'type'     => 'string',
                    'column'   => 'items.title',
                    'label'    => 'Nama Barang',
                    'synonyms' => ['nama barang', 'judul', 'barang apa saja', 'list barang'],
                ],
                'category_name' => [
                    'type'     => 'string',
                    'column'   => 'category.name',
                    'label'    => 'Kategori Barang',
                    'synonyms' => ['kategori', 'jenis'],
                ],
                'storage_name' => [
                    'type'     => 'string',
                    'column'   => 'storage.name',
                    'label'    => 'Lokasi Rak / Lemari',
                    'synonyms' => ['lokasi rak', 'rak', 'tempat simpan', 'lemari'],
                ],
                'physical_condition' => [
                    'type'     => 'string',
                    'column'   => 'items.physical_condition',
                    'label'    => 'Kondisi Fisik',
                    'synonyms' => ['kondisi fisik', 'keadaan barang'],
                ],
                'status' => [
                    'type'     => 'string',
                    'column'   => 'items.status',
                    'label'    => 'Status Fisik',
                    'synonyms' => ['status', 'status barang'],
                    'allowed_values' => ['stored', 'in_claim', 'ready_for_handover', 'claimed', 'disposed'],
                ],
                'registered_at' => [
                    'type'     => 'datetime',
                    'column'   => 'items.registered_at',
                    'label'    => 'Waktu Registrasi ke Posko',
                    'synonyms' => ['waktu simpan', 'tanggal masuk posko'],
                ],
            ],
            'default_filter' => "items.status IN ('stored', 'registered', 'in_inspection', 'in_claim', 'ready_for_handover')",
            'default_order'  => 'items.registered_at DESC',
            'row_scope'      => [
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 3b. Barang Tersimpan di Rak Gudang (items status='stored')
        'stored_items' => [
            'extends'        => 'posko_items',
            'label'          => 'Barang Tersimpan di Rak Gudang',
            'description'    => 'Barang temuan yang saat ini berstatus tersimpan aktif di rak posko (status=stored)',
            'default_filter' => "items.status = 'stored'",
        ],

        // 4. Laporan Kehilangan (lost_reports)
        'lost_reports' => [
            'table'       => 'lost_reports',
            'primary_key' => 'id',
            'label'       => 'Laporan Kehilangan Barang',
            'description' => 'Laporan barang hilang yang diajukan civitas',
            'joins'       => [
                'category' => [
                    'table'      => 'categories',
                    'foreign_key'=> 'category_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
                'reporter' => [
                    'table'      => 'users',
                    'foreign_key'=> 'reporter_user_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'lost_reports.id',
                    'label'    => 'ID Laporan Hilang',
                    'synonyms' => ['id'],
                ],
                'report_number' => [
                    'type'     => 'string',
                    'column'   => 'lost_reports.report_number',
                    'label'    => 'Nomor Laporan',
                    'synonyms' => ['kode laporan', 'nomor tiket', 'kode'],
                ],
                'title' => [
                    'type'     => 'string',
                    'column'   => 'lost_reports.title',
                    'label'    => 'Nama Barang Hilang',
                    'synonyms' => ['judul', 'nama barang', 'barang'],
                ],
                'category_name' => [
                    'type'     => 'string',
                    'column'   => 'category.name',
                    'label'    => 'Kategori',
                    'synonyms' => ['kategori', 'jenis'],
                ],
                'reporter_name' => [
                    'type'     => 'string',
                    'column'   => 'reporter.name',
                    'label'    => 'Nama Pelapor',
                    'synonyms' => ['pelapor', 'nama'],
                    'restricted_to' => ['petugas', 'admin'],
                ],
                'status' => [
                    'type'     => 'string',
                    'column'   => 'lost_reports.status',
                    'label'    => 'Status Laporan',
                    'synonyms' => ['status', 'kondisi'],
                    'allowed_values' => ['published', 'resolved', 'closed', 'draft'],
                ],
                'lost_date' => [
                    'type'     => 'date',
                    'column'   => 'lost_reports.lost_date',
                    'label'    => 'Tanggal Hilang',
                    'synonyms' => ['tanggal hilang', 'waktu hilang'],
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'lost_reports.created_at',
                    'label'    => 'Waktu Pelaporan',
                    'synonyms' => ['waktu', 'kapan', 'tanggal lapor', 'hari ini'],
                ],
            ],
            'default_order' => 'lost_reports.created_at DESC',
            'row_scope' => [
                'civitas' => 'lost_reports.reporter_user_id = :session_user_id',
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 5. Laporan Kehilangan Milik Sendiri (Civitas)
        'my_lost_reports' => [
            'extends'   => 'lost_reports',
            'label'     => 'Laporan Kehilangan Saya',
            'row_scope' => [
                'civitas' => 'lost_reports.reporter_user_id = :session_user_id',
            ],
        ],

        // 6. Laporan Temuan (found_reports)
        'found_reports' => [
            'table'       => 'found_reports',
            'primary_key' => 'id',
            'label'       => 'Laporan Penemuan Barang',
            'description' => 'Laporan barang temuan dari civitas maupun petugas',
            'joins'       => [
                'category' => [
                    'table'      => 'categories',
                    'foreign_key'=> 'category_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
                'reporter' => [
                    'table'      => 'users',
                    'foreign_key'=> 'reporter_user_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'found_reports.id',
                    'label'    => 'ID Laporan Temuan',
                    'synonyms' => ['id'],
                ],
                'report_number' => [
                    'type'     => 'string',
                    'column'   => 'found_reports.report_number',
                    'label'    => 'Nomor Laporan Temuan',
                    'synonyms' => ['kode laporan', 'nomor laporan'],
                ],
                'title' => [
                    'type'     => 'string',
                    'column'   => 'found_reports.title',
                    'label'    => 'Nama Barang Ditemukan',
                    'synonyms' => ['judul', 'nama barang', 'barang'],
                ],
                'category_name' => [
                    'type'     => 'string',
                    'column'   => 'category.name',
                    'label'    => 'Kategori',
                    'synonyms' => ['kategori'],
                ],
                'reporter_name' => [
                    'type'     => 'string',
                    'column'   => 'reporter.name',
                    'label'    => 'Nama Penemu',
                    'synonyms' => ['penemu', 'pelapor'],
                    'restricted_to' => ['petugas', 'admin'],
                ],
                'status' => [
                    'type'     => 'string',
                    'column'   => 'found_reports.status',
                    'label'    => 'Status Temuan',
                    'synonyms' => ['status'],
                    'allowed_values' => ['pending_verification', 'published', 'resolved', 'archived'],
                ],
                'is_handed_over_to_center' => [
                    'type'     => 'bool',
                    'column'   => 'found_reports.is_handed_over_to_center',
                    'label'    => 'Sudah Diserahkan ke Posko',
                    'synonyms' => ['sudah diserahkan', 'serah terima posko'],
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'found_reports.created_at',
                    'label'    => 'Waktu Pelaporan Temuan',
                    'synonyms' => ['waktu', 'tanggal', 'hari ini'],
                ],
            ],
            'default_order' => 'found_reports.created_at DESC',
            'row_scope' => [
                'civitas' => 'found_reports.reporter_user_id = :session_user_id',
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 7. Pengguna Sistem (Users — Khusus ADMIN)
        'users' => [
            'table'       => 'users',
            'primary_key' => 'id',
            'label'       => 'Pengguna Sistem',
            'description' => 'Akun civitas, petugas, dan admin terdaftar di sistem',
            'joins'       => [
                'role' => [
                    'table'      => 'roles',
                    'foreign_key'=> 'role_id',
                    'target_key' => 'id',
                    'type'       => 'INNER',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'users.id',
                    'label'    => 'ID Pengguna',
                    'synonyms' => ['id'],
                ],
                'name' => [
                    'type'     => 'string',
                    'column'   => 'users.name',
                    'label'    => 'Nama Pengguna',
                    'synonyms' => ['nama', 'siapa saja', 'daftar nama'],
                ],
                'email' => [
                    'type'     => 'string',
                    'column'   => 'users.email',
                    'label'    => 'Email Pengguna',
                    'synonyms' => ['email', 'alamat email'],
                ],
                'role_name' => [
                    'type'     => 'string',
                    'column'   => 'role.name',
                    'label'    => 'Peran Pengguna (Role)',
                    'synonyms' => ['role', 'peran', 'jabatan', 'civitas/petugas/admin'],
                    'allowed_values' => ['admin', 'petugas', 'civitas'],
                ],
                'is_active' => [
                    'type'     => 'bool',
                    'column'   => 'users.is_active',
                    'label'    => 'Status Akun Aktif',
                    'synonyms' => ['status', 'aktif'],
                ],
                'status' => [
                    'type'     => 'bool',
                    'column'   => 'users.is_active',
                    'label'    => 'Status Akun Aktif',
                    'synonyms' => ['status', 'aktif'],
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'users.created_at',
                    'label'    => 'Waktu Terdaftar',
                    'synonyms' => ['tanggal daftar', 'kapan dibuat'],
                ],
            ],
            'default_order' => 'users.created_at DESC',
            'row_scope'     => [
                'admin' => '1=1',
            ],
        ],

        // 8. Log Audit Keamanan (audit_logs — Khusus ADMIN)
        'audit_logs' => [
            'table'       => 'audit_logs',
            'primary_key' => 'id',
            'label'       => 'Jejak Audit Keamanan',
            'description' => 'Rekam aktivitas mutasi dan administrasi sistem',
            'joins'       => [
                'user' => [
                    'table'      => 'users',
                    'foreign_key'=> 'user_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'audit_logs.id',
                    'label'    => 'ID Log',
                    'synonyms' => ['id'],
                ],
                'action' => [
                    'type'     => 'string',
                    'column'   => 'audit_logs.action',
                    'label'    => 'Aksi / Aktivitas',
                    'synonyms' => ['aksi', 'aktivitas', 'kegiatan', 'event'],
                ],
                'actor_name' => [
                    'type'     => 'string',
                    'column'   => 'user.name',
                    'label'    => 'Nama Aktor',
                    'synonyms' => ['aktor', 'pelaku', 'oleh siapa'],
                ],
                'entity_type' => [
                    'type'     => 'string',
                    'column'   => 'audit_logs.entity_type',
                    'label'    => 'Jenis Entitas',
                    'synonyms' => ['entitas', 'objek'],
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'audit_logs.created_at',
                    'label'    => 'Waktu Kejadian',
                    'synonyms' => ['waktu', 'tanggal', 'kapan', 'hari ini'],
                ],
            ],
            'default_order' => 'audit_logs.created_at DESC',
            'row_scope'     => [
                'admin' => '1=1',
            ],
        ],

        // 9. Aktivitas Login (Khusus ADMIN)
        'login_activity' => [
            'table'       => 'audit_logs',
            'primary_key' => 'id',
            'label'       => 'Aktivitas Login Pengguna',
            'description' => 'Rekam jejak autentikasi pengguna masuk ke sistem',
            'joins'       => [
                'user' => [
                    'table'      => 'users',
                    'foreign_key'=> 'user_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'audit_logs.id',
                    'label'    => 'ID Log',
                ],
                'actor_name' => [
                    'type'     => 'string',
                    'column'   => 'user.name',
                    'label'    => 'Nama Pengguna yang Login',
                    'synonyms' => ['siapa yang login', 'nama', 'pengguna'],
                ],
                'action' => [
                    'type'     => 'string',
                    'column'   => 'audit_logs.action',
                    'label'    => 'Aksi',
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'audit_logs.created_at',
                    'label'    => 'Waktu Login',
                    'synonyms' => ['waktu', 'jam berapa', 'hari ini'],
                ],
            ],
            'default_filter' => "audit_logs.action IN ('LOGIN_SUCCESS', 'auth_login', 'login', 'user_login')",
            'default_order'  => 'audit_logs.created_at DESC',
            'row_scope'      => [
                'admin' => '1=1',
            ],
        ],

        // 10. Master Kategori (categories)
        'categories' => [
            'table'       => 'categories',
            'primary_key' => 'id',
            'label'       => 'Kategori Barang',
            'description' => 'Daftar klasifikasi kategori barang kampus',
            'fields'      => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'categories.id',
                    'label'    => 'ID Kategori',
                ],
                'name' => [
                    'type'     => 'string',
                    'column'   => 'categories.name',
                    'label'    => 'Nama Kategori',
                    'synonyms' => ['nama kategori', 'apa saja', 'daftar kategori'],
                ],
                'description' => [
                    'type'     => 'string',
                    'column'   => 'categories.description',
                    'label'    => 'Deskripsi',
                ],
                'is_active' => [
                    'type'     => 'bool',
                    'column'   => 'categories.is_active',
                    'label'    => 'Status Aktif',
                ],
            ],
            'default_filter' => 'categories.is_active = 1',
            'default_order'  => 'categories.name ASC',
            'row_scope'      => [
                'civitas' => '1=1',
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 11. Master Lokasi Kampus (locations)
        'locations' => [
            'table'       => 'locations',
            'primary_key' => 'id',
            'label'       => 'Lokasi / Gedung Kampus',
            'description' => 'Daftar gedung dan lokasi area kampus',
            'fields'      => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'locations.id',
                    'label'    => 'ID Lokasi',
                ],
                'name' => [
                    'type'     => 'string',
                    'column'   => 'locations.name',
                    'label'    => 'Nama Lokasi',
                    'synonyms' => ['nama lokasi', 'lokasi', 'gedung'],
                ],
                'building_name' => [
                    'type'     => 'string',
                    'column'   => 'locations.building_name',
                    'label'    => 'Nama Gedung',
                    'synonyms' => ['gedung'],
                ],
                'is_active' => [
                    'type'     => 'bool',
                    'column'   => 'locations.is_active',
                    'label'    => 'Status Aktif',
                ],
            ],
            'default_filter' => 'locations.is_active = 1',
            'default_order'  => 'locations.name ASC',
            'row_scope'      => [
                'civitas' => '1=1',
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 12. Master Lokasi Penyimpanan Rak Posko (storage_locations)
        'storage_locations' => [
            'table'       => 'storage_locations',
            'primary_key' => 'id',
            'label'       => 'Lokasi Rak / Penyimpanan Posko',
            'description' => 'Daftar rak dan lemari penyimpanan di ruang posko center',
            'fields'      => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'storage_locations.id',
                    'label'    => 'ID Rak',
                ],
                'code' => [
                    'type'     => 'string',
                    'column'   => 'storage_locations.code',
                    'label'    => 'Kode Rak',
                    'synonyms' => ['kode rak', 'kode lemari'],
                ],
                'name' => [
                    'type'     => 'string',
                    'column'   => 'storage_locations.name',
                    'label'    => 'Nama Rak',
                    'synonyms' => ['nama rak', 'tempat simpan'],
                ],
                'zone' => [
                    'type'     => 'string',
                    'column'   => 'storage_locations.zone',
                    'label'    => 'Zona',
                    'synonyms' => ['zona', 'area'],
                ],
                'capacity' => [
                    'type'     => 'int',
                    'column'   => 'storage_locations.capacity',
                    'label'    => 'Kapasitas',
                    'synonyms' => ['kapasitas'],
                ],
                'is_active' => [
                    'type'     => 'bool',
                    'column'   => 'storage_locations.is_active',
                    'label'    => 'Status',
                ],
            ],
            'default_filter' => 'storage_locations.is_active = 1',
            'default_order'  => 'storage_locations.code ASC',
            'row_scope'      => [
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 13. Riwayat Status Barang (status_histories)
        'status_histories' => [
            'table'       => 'status_histories',
            'primary_key' => 'id',
            'label'       => 'Riwayat Status Barang',
            'description' => 'Catatan mutasi status fisik barang',
            'joins'       => [
                'changer' => [
                    'table'      => 'users',
                    'foreign_key'=> 'changed_by_user_id',
                    'target_key' => 'id',
                    'type'       => 'LEFT',
                ],
            ],
            'fields' => [
                'id' => [
                    'type'     => 'int',
                    'column'   => 'status_histories.id',
                    'label'    => 'ID Riwayat',
                ],
                'entity_type' => [
                    'type'     => 'string',
                    'column'   => 'status_histories.entity_type',
                    'label'    => 'Tipe Entitas',
                ],
                'old_status' => [
                    'type'     => 'string',
                    'column'   => 'status_histories.old_status',
                    'label'    => 'Status Lama',
                ],
                'new_status' => [
                    'type'     => 'string',
                    'column'   => 'status_histories.new_status',
                    'label'    => 'Status Baru',
                ],
                'created_at' => [
                    'type'     => 'datetime',
                    'column'   => 'status_histories.created_at',
                    'label'    => 'Waktu Perubahan',
                ],
            ],
            'default_order' => 'status_histories.created_at DESC',
            'row_scope'     => [
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],

        // 14. Katalog Publik (SAMA dengan halaman Cari Barang via ItemCatalogService)
        'public_catalog' => [
            'virtual'     => true,
            'label'       => 'Katalog Barang Publik (Cari Barang)',
            'description' => 'Barang hilang dan temuan aktif yang dapat dicari di portal publik',
            'fields'      => [
                'title' => [
                    'type'     => 'string',
                    'label'    => 'Nama Barang',
                    'synonyms' => ['nama barang', 'judul', 'barang'],
                ],
                'type' => [
                    'type'     => 'string',
                    'label'    => 'Tipe Laporan',
                    'synonyms' => ['tipe', 'jenis laporan', 'hilang/temuan'],
                ],
                'category_name' => [
                    'type'     => 'string',
                    'label'    => 'Kategori',
                    'synonyms' => ['kategori'],
                ],
                'location_name' => [
                    'type'     => 'string',
                    'label'    => 'Lokasi',
                    'synonyms' => ['lokasi'],
                ],
                'status' => [
                    'type'     => 'string',
                    'label'    => 'Status Publik',
                    'synonyms' => ['status'],
                ],
            ],
            'row_scope'   => [
                'civitas' => '1=1',
                'petugas' => '1=1',
                'admin'   => '1=1',
            ],
        ],
    ],
];
