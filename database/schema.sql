-- =============================================================
-- Campus Find — Complete Database Schema (24 Tables)
-- Generated for campusfind-clean
-- Architecture: Relational MySQL (InnoDB, utf8mb4_unicode_ci)
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- TABLE: `audit_logs`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3008 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log audit aktivitas pengguna dan sistem';

-- -------------------------------------------------------------
-- TABLE: `categories`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Icon identifier / emoji',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  KEY `idx_categories_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kategori jenis barang';

-- -------------------------------------------------------------
-- TABLE: `cfind_chat_history`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `cfind_chat_history`;
CREATE TABLE `cfind_chat_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `session_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `intent` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `status` enum('dijawab','ditolak','tidak bisa dijawab','gagal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dijawab',
  `duration_ms` int unsigned NOT NULL DEFAULT '0',
  `tokens_used` int unsigned NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cfind_chat_created_at` (`created_at`),
  KEY `idx_cfind_chat_user_id` (`user_id`),
  KEY `idx_cfind_chat_role` (`role`),
  KEY `idx_cfind_chat_status` (`status`),
  CONSTRAINT `fk_cfind_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=301 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: `chat_messages`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `sender_user_id` int unsigned NOT NULL,
  `message_type` enum('text','image','system_event') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_room_time` (`room_id`,`created_at`),
  KEY `idx_chat_messages_sender` (`sender_user_id`),
  CONSTRAINT `fk_messages_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pesan percakapan dalam ruang chat';

-- -------------------------------------------------------------
-- TABLE: `chat_participants`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `chat_participants`;
CREATE TABLE `chat_participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role_in_room` enum('claimant','reporter','petugas','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_user` (`room_id`,`user_id`),
  KEY `fk_cp_user` (`user_id`),
  CONSTRAINT `fk_cp_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: `chat_rooms`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `chat_rooms`;
CREATE TABLE `chat_rooms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_id` int unsigned DEFAULT NULL,
  `lost_report_id` int unsigned DEFAULT NULL,
  `found_report_id` int unsigned DEFAULT NULL,
  `created_by_user_id` int unsigned NOT NULL,
  `status` enum('active','closed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_message_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_rooms_code` (`room_code`),
  KEY `idx_chat_rooms_status` (`status`),
  KEY `idx_chat_rooms_claim` (`claim_id`),
  KEY `fk_chat_lost` (`lost_report_id`),
  KEY `fk_chat_found` (`found_report_id`),
  KEY `fk_chat_creator` (`created_by_user_id`),
  CONSTRAINT `fk_chat_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_found` FOREIGN KEY (`found_report_id`) REFERENCES `found_reports` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_chat_lost` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_reports` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ruang percakapan terpusat antar pengguna dan petugas';

-- -------------------------------------------------------------
-- TABLE: `claim_evidence`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `claim_evidence`;
CREATE TABLE `claim_evidence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `claim_id` int unsigned NOT NULL,
  `evidence_type` enum('secret_details','serial_number','ownership_proof_image','invoice_receipt','custom_question_answer','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Label atribut (misal: IMEI, Isi Dompet, Password Layar)',
  `evidence_value` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Detail rahasia verifikasi - TIDAK BOLEH DIAKSES PUBLIK',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verified_by_user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_claim_evidence_claim` (`claim_id`),
  KEY `fk_evidence_verifier` (`verified_by_user_id`),
  CONSTRAINT `fk_evidence_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_evidence_verifier` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=237 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data bukti/jawaban verifikasi privat untuk klaim barang';

-- -------------------------------------------------------------
-- TABLE: `claims`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `claims`;
CREATE TABLE `claims` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `claim_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claimant_user_id` int unsigned NOT NULL,
  `lost_report_id` int unsigned DEFAULT NULL,
  `found_report_id` int unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `claim_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ownership_proof_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `unique_features` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','under_review','need_more_info','approved','rejected','completed','closed_duplicate','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` int unsigned DEFAULT NULL,
  `reviewer_notes` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` datetime DEFAULT NULL,
  `handover_scheduled_at` datetime DEFAULT NULL,
  `handed_over_at` datetime DEFAULT NULL,
  `handover_notes` text COLLATE utf8mb4_unicode_ci,
  `handed_over_by_user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_claims_number` (`claim_number`),
  KEY `idx_claims_status` (`status`),
  KEY `idx_claims_claimant` (`claimant_user_id`),
  KEY `idx_claims_item` (`item_id`),
  KEY `idx_claims_created_at` (`created_at`),
  KEY `fk_claims_lost_report` (`lost_report_id`),
  KEY `fk_claims_reviewer` (`reviewed_by_user_id`),
  KEY `fk_claims_handed_over_by` (`handed_over_by_user_id`),
  KEY `idx_claims_item_status` (`item_id`,`status`),
  KEY `idx_claims_found_status` (`found_report_id`,`status`),
  KEY `idx_claims_claimant_status` (`claimant_user_id`,`status`),
  CONSTRAINT `fk_claims_claimant` FOREIGN KEY (`claimant_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_claims_found_report` FOREIGN KEY (`found_report_id`) REFERENCES `found_reports` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_claims_handed_over_by` FOREIGN KEY (`handed_over_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_claims_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_claims_lost_report` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_reports` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_claims_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=145 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan klaim kepemilikan barang';

-- -------------------------------------------------------------
-- TABLE: `found_reports`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `found_reports`;
CREATE TABLE `found_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reporter_user_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `location_id` int unsigned NOT NULL,
  `item_id` int unsigned DEFAULT NULL COMMENT 'Terkait ke items ketika barang diserahkan ke Center',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_condition` enum('sangat_baik','baik','rusak_ringan','rusak_berat') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baik',
  `private_details` text COLLATE utf8mb4_unicode_ci COMMENT 'Informasi internal/rahasia penemu (ciri khusus, lokasi penyimpanan sementara jika belum diserahkan)',
  `found_date` date NOT NULL,
  `found_time` time DEFAULT NULL,
  `primary_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_handed_over_to_center` tinyint(1) NOT NULL DEFAULT '0',
  `handed_over_at` datetime DEFAULT NULL,
  `status` enum('draft','published','pending_handover','handed_over_to_center','verified','claimed','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_found_report_number` (`report_number`),
  KEY `idx_found_status` (`status`),
  KEY `idx_found_category` (`category_id`),
  KEY `idx_found_location` (`location_id`),
  KEY `idx_found_date` (`found_date`),
  KEY `idx_found_created_at` (`created_at`),
  KEY `fk_found_reporter` (`reporter_user_id`),
  KEY `fk_found_item` (`item_id`),
  KEY `idx_fr_status` (`status`),
  KEY `idx_fr_category` (`category_id`),
  KEY `idx_fr_location` (`location_id`),
  KEY `idx_fr_found_date` (`found_date`),
  KEY `idx_fr_created_at` (`created_at`),
  CONSTRAINT `fk_found_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_found_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_found_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_found_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=152 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laporan penemuan barang oleh civitas/penemu';

-- -------------------------------------------------------------
-- TABLE: `items`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int unsigned NOT NULL,
  `storage_location_id` int unsigned DEFAULT NULL,
  `verified_by_user_id` int unsigned DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Catatan pemeriksaan & histori internal petugas',
  `primary_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_images` json DEFAULT NULL,
  `physical_condition` enum('sangat_baik','baik','rusak_ringan','rusak_berat') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baik',
  `status` enum('registered','pending_inspection','in_inspection','stored','in_claim','ready_for_handover','returned','donated','disposed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stored',
  `registered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `returned_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_items_code` (`item_code`),
  KEY `idx_items_status` (`status`),
  KEY `idx_items_category` (`category_id`),
  KEY `idx_items_storage` (`storage_location_id`),
  KEY `idx_items_created_at` (`created_at`),
  KEY `fk_items_verifier` (`verified_by_user_id`),
  CONSTRAINT `fk_items_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_items_storage` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_items_verifier` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Record fisik kanonik barang yang masuk/disimpan di L&F Center';

-- -------------------------------------------------------------
-- TABLE: `locations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `building_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_locations_code` (`code`),
  KEY `idx_locations_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar lokasi area kampus';

-- -------------------------------------------------------------
-- TABLE: `login_attempts`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '1',
  `last_attempt_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_ip_email` (`ip_address`,`email`),
  KEY `idx_login_locked` (`locked_until`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limiting dan lockout percobaan login gagal';

-- -------------------------------------------------------------
-- TABLE: `lost_reports`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `lost_reports`;
CREATE TABLE `lost_reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reporter_user_id` int unsigned NOT NULL,
  `category_id` int unsigned NOT NULL,
  `last_seen_location_id` int unsigned NOT NULL,
  `resolved_item_id` int unsigned DEFAULT NULL COMMENT 'Terkait ke items jika barang berhasil ditemukan/diklaim',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_detail` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `private_details` text COLLATE utf8mb4_unicode_ci COMMENT 'Informasi sensitif/rahasia verifikasi kepemilikan - HANYA DILIHAT PEMILIK & PETUGAS',
  `lost_date` date NOT NULL,
  `lost_time` time DEFAULT NULL,
  `primary_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','published','investigating','matched','claimed','resolved','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lost_report_number` (`report_number`),
  KEY `idx_lost_status` (`status`),
  KEY `idx_lost_category` (`category_id`),
  KEY `idx_lost_location` (`last_seen_location_id`),
  KEY `idx_lost_date` (`lost_date`),
  KEY `idx_lost_created_at` (`created_at`),
  KEY `fk_lost_reporter` (`reporter_user_id`),
  KEY `fk_lost_resolved_item` (`resolved_item_id`),
  KEY `idx_lr_status` (`status`),
  KEY `idx_lr_category` (`category_id`),
  KEY `idx_lr_location` (`last_seen_location_id`),
  KEY `idx_lr_lost_date` (`lost_date`),
  KEY `idx_lr_created_at` (`created_at`),
  CONSTRAINT `fk_lost_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lost_location` FOREIGN KEY (`last_seen_location_id`) REFERENCES `locations` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lost_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_lost_resolved_item` FOREIGN KEY (`resolved_item_id`) REFERENCES `items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Laporan kehilangan barang yang diajukan oleh pemilik';

-- -------------------------------------------------------------
-- TABLE: `match_candidates`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `match_candidates`;
CREATE TABLE `match_candidates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `lost_report_id` int unsigned NOT NULL,
  `found_report_id` int unsigned NOT NULL,
  `relevance_score` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Nilai relevansi 0.00 - 100.00%',
  `matching_explanation` text COLLATE utf8mb4_unicode_ci COMMENT 'Penjelasan pencocokan dari AI / Petugas',
  `source` enum('ai','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ai',
  `status` enum('pending','reviewed','confirmed_match','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match_pair` (`lost_report_id`,`found_report_id`),
  KEY `idx_match_score` (`relevance_score`),
  KEY `idx_match_source` (`source`),
  KEY `idx_match_status` (`status`),
  KEY `idx_match_created_at` (`created_at`),
  KEY `fk_match_found` (`found_report_id`),
  KEY `fk_match_reviewer` (`reviewed_by_user_id`),
  CONSTRAINT `fk_match_found` FOREIGN KEY (`found_report_id`) REFERENCES `found_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_lost` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_match_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=165 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kandidat pencocokan barang hilang vs barang temuan';

-- -------------------------------------------------------------
-- TABLE: `notification_decisions`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `notification_decisions`;
CREATE TABLE `notification_decisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `recipient_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision` enum('queued','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email',
  `outbox_id` bigint unsigned DEFAULT NULL,
  `context_data` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_dec_event` (`event_name`),
  KEY `idx_notif_dec_decision` (`decision`),
  KEY `idx_notif_dec_user` (`user_id`),
  KEY `idx_notif_dec_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=773 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: `notification_outbox`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `notification_outbox`;
CREATE TABLE `notification_outbox` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `channel` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email',
  `recipient` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` enum('pending','sending','sent','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` int NOT NULL DEFAULT '0',
  `last_attempt_at` datetime DEFAULT NULL,
  `next_try_at` datetime NOT NULL,
  `error_reason` text COLLATE utf8mb4_unicode_ci,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_key` (`idempotency_key`),
  KEY `idx_outbox_status_next_try` (`status`,`next_try_at`),
  KEY `idx_outbox_user_id` (`user_id`),
  KEY `idx_outbox_channel_status` (`channel`,`status`),
  KEY `idx_outbox_created_at` (`created_at`),
  CONSTRAINT `fk_outbox_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=343 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: `notification_templates`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `notification_templates`;
CREATE TABLE `notification_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `template_key` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `variables_hint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_key` (`template_key`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: `notifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. match_found, claim_approved, chat_received',
  `payload` json NOT NULL COMMENT 'JSON payload: title, message, url, entity_id',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`,`is_read`),
  KEY `idx_notifications_created_at` (`created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=864 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifikasi in-app untuk pengguna';

-- -------------------------------------------------------------
-- TABLE: `otp_verifications`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE `otp_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `purpose` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `salt` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `max_attempts` int unsigned NOT NULL DEFAULT '5',
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_user_purpose` (`user_id`,`purpose`),
  KEY `idx_otp_expires_at` (`expires_at`),
  KEY `idx_otp_created_at` (`created_at`),
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verifikasi OTP email untuk aktivasi akun dan reset password';

-- -------------------------------------------------------------
-- TABLE: `roles`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar role sistem: Admin, Petugas, Civitas';

-- -------------------------------------------------------------
-- TABLE: `status_histories`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `status_histories`;
CREATE TABLE `status_histories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('lost_report','found_report','item','claim') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by_user_id` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_histories_entity` (`entity_type`,`entity_id`),
  KEY `idx_status_histories_created` (`created_at`),
  KEY `fk_status_changed_by` (`changed_by_user_id`),
  CONSTRAINT `fk_status_changed_by` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=543 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log transisi status laporan dan barang fisik';

-- -------------------------------------------------------------
-- TABLE: `storage_locations`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `storage_locations`;
CREATE TABLE `storage_locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `zone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int unsigned NOT NULL DEFAULT '50',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_storage_code` (`code`),
  KEY `idx_storage_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lokasi fisik penyimpanan barang di Lost & Found Center';

-- -------------------------------------------------------------
-- TABLE: `system_settings`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `group` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  KEY `idx_settings_key` (`key`),
  KEY `idx_settings_group` (`group`)
) ENGINE=InnoDB AUTO_INCREMENT=1416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- TABLE: `users`
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NIM / NIP / NIK',
  `study_program` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_year` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `session_version` int unsigned NOT NULL DEFAULT '1',
  `temp_password_expires_at` datetime DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_verified_by_otp` tinyint(1) NOT NULL DEFAULT '0',
  `email_notifications_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `email_consent_at` datetime DEFAULT NULL,
  `email_consent_text_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_identity_number` (`identity_number`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_is_active` (`is_active`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=664 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data pengguna / civitas akademika';

SET FOREIGN_KEY_CHECKS = 1;
