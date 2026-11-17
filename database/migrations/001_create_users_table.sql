-- =============================================================
-- Campus Find — Migration 001
-- Tabel: users
-- Status: STUB — Akan diisi penuh pada Prompt Auth
-- =============================================================

CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(100)    NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `password_hash` VARCHAR(255)    NOT NULL,
    `role`          ENUM('student','staff','admin','lnf_officer') NOT NULL DEFAULT 'student',
    `avatar`        VARCHAR(255)    NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `email_verified_at` TIMESTAMP   NULL DEFAULT NULL,
    `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Tabel pengguna Campus Find';
