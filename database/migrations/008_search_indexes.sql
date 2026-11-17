-- =============================================================
-- Campus Find — Migration 008: Search Performance Indexes
-- Safe to run multiple times (uses IF NOT EXISTS pattern via
-- procedure to avoid "Duplicate key name" errors)
-- =============================================================

-- lost_reports indexes
ALTER TABLE `lost_reports`
    ADD INDEX IF NOT EXISTS `idx_lr_status`       (`status`),
    ADD INDEX IF NOT EXISTS `idx_lr_category`     (`category_id`),
    ADD INDEX IF NOT EXISTS `idx_lr_location`     (`last_seen_location_id`),
    ADD INDEX IF NOT EXISTS `idx_lr_lost_date`    (`lost_date`),
    ADD INDEX IF NOT EXISTS `idx_lr_created_at`   (`created_at`);

-- found_reports indexes
ALTER TABLE `found_reports`
    ADD INDEX IF NOT EXISTS `idx_fr_status`       (`status`),
    ADD INDEX IF NOT EXISTS `idx_fr_category`     (`category_id`),
    ADD INDEX IF NOT EXISTS `idx_fr_location`     (`location_id`),
    ADD INDEX IF NOT EXISTS `idx_fr_found_date`   (`found_date`),
    ADD INDEX IF NOT EXISTS `idx_fr_created_at`   (`created_at`);
