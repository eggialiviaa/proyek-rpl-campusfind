-- =============================================================
-- Campus Find — Migration 009: Claim & Verification Schema Upgrade
-- =============================================================

-- 1. Modify claims status ENUM
ALTER TABLE `claims` 
    MODIFY COLUMN `status` ENUM(
        'pending', 
        'under_review', 
        'need_more_info', 
        'approved', 
        'rejected', 
        'completed', 
        'closed_duplicate', 
        'cancelled'
    ) NOT NULL DEFAULT 'pending';

-- 2. Add structured claim detail columns
ALTER TABLE `claims`
    ADD COLUMN IF NOT EXISTS `claim_reason` TEXT NOT NULL AFTER `item_id`,
    ADD COLUMN IF NOT EXISTS `ownership_proof_description` TEXT NOT NULL AFTER `claim_reason`,
    ADD COLUMN IF NOT EXISTS `unique_features` TEXT NOT NULL AFTER `ownership_proof_description`,
    ADD COLUMN IF NOT EXISTS `additional_notes` TEXT NULL AFTER `unique_features`,
    ADD COLUMN IF NOT EXISTS `rejection_reason` TEXT NULL AFTER `reviewer_notes`,
    ADD COLUMN IF NOT EXISTS `handover_notes` TEXT NULL AFTER `handed_over_at`,
    ADD COLUMN IF NOT EXISTS `handed_over_by_user_id` INT UNSIGNED NULL AFTER `handover_notes`;

-- 3. Add FK for handed_over_by_user_id (if not exists)
-- (Handled safely in run_009.php to prevent duplicate constraint errors)

-- 4. Add performance indexes
ALTER TABLE `claims`
    ADD INDEX IF NOT EXISTS `idx_claims_item_status` (`item_id`, `status`),
    ADD INDEX IF NOT EXISTS `idx_claims_found_status` (`found_report_id`, `status`),
    ADD INDEX IF NOT EXISTS `idx_claims_claimant_status` (`claimant_user_id`, `status`);
