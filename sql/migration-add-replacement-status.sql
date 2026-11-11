-- ============================================================================
--  Migration: Add replacement_status column to users table
--  Purpose: Track which pre-created users (BS1003xx) have been replaced
--  Format: ENUM('available', 'replaced')
--  Default: 'available' for pre-created users, NULL for others
-- ============================================================================

ALTER TABLE users ADD COLUMN replacement_status
ENUM('available', 'replaced')
DEFAULT NULL
COMMENT 'Tracks if pre-created user (BS10030X) can be auto-replaced. NULL for non-pre-created users.';

-- Initialize all BS1003xx users as 'available'
UPDATE users
SET replacement_status = 'available'
WHERE customer_id LIKE 'BS1003%' AND replacement_status IS NULL;

-- All other users have NULL (Format B allows replacing any user)
UPDATE users
SET replacement_status = NULL
WHERE customer_id NOT LIKE 'BS1003%' AND replacement_status IS NOT NULL;

-- Verify
SELECT COUNT(*) as available_count,
       COALESCE(replacement_status, 'null') as status
FROM users
GROUP BY replacement_status
ORDER BY status DESC;
