-- =============================================================
-- Campus Find — Migration 010: Internal Chat System
-- =============================================================

-- 1. Tabel Ruang Chat (chat_rooms)
CREATE TABLE IF NOT EXISTS `chat_rooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_type` ENUM('claim', 'lost_report', 'found_report') NOT NULL DEFAULT 'claim',
    `claim_id` INT UNSIGNED NULL,
    `lost_report_id` INT UNSIGNED NULL,
    `found_report_id` INT UNSIGNED NULL,
    `title` VARCHAR(255) NOT NULL,
    `created_by_user_id` INT UNSIGNED NOT NULL,
    `status` ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    `last_message_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,

    CONSTRAINT `fk_cr_claim` FOREIGN KEY (`claim_id`) REFERENCES `claims` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_lost_report` FOREIGN KEY (`lost_report_id`) REFERENCES `lost_reports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_found_report` FOREIGN KEY (`found_report_id`) REFERENCES `found_reports` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cr_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_cr_claim` ON `chat_rooms` (`claim_id`);
CREATE INDEX IF NOT EXISTS `idx_cr_lost` ON `chat_rooms` (`lost_report_id`);
CREATE INDEX IF NOT EXISTS `idx_cr_found` ON `chat_rooms` (`found_report_id`);
CREATE INDEX IF NOT EXISTS `idx_cr_last_msg` ON `chat_rooms` (`last_message_at`);

-- 2. Tabel Partisipan Chat (chat_participants)
CREATE TABLE IF NOT EXISTS `chat_participants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role_in_room` ENUM('claimant', 'reporter', 'petugas', 'admin') NOT NULL,
    `last_read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,

    CONSTRAINT `fk_cp_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `uk_room_user` UNIQUE (`room_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_cp_user` ON `chat_participants` (`user_id`);

-- 3. Tabel Pesan Chat (chat_messages)
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT UNSIGNED NOT NULL,
    `sender_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,

    CONSTRAINT `fk_cm_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_cm_room_created` ON `chat_messages` (`room_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_cm_sender_read` ON `chat_messages` (`sender_id`, `is_read`);
