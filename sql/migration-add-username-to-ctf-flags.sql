-- Add username column to ctf_flags table if it doesn't exist
-- This script is for databases that already have the ctf_flags table without the username column

-- Check if column exists before adding it
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'ctf_flags'
    AND TABLE_SCHEMA = DATABASE()
    AND COLUMN_NAME = 'username'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE ctf_flags ADD COLUMN username VARCHAR(50) NOT NULL DEFAULT "" AFTER user_id',
    'SELECT "Column username already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Populate username from users table (safe to run multiple times)
UPDATE ctf_flags cf
SET cf.username = (SELECT u.customer_id FROM users u WHERE u.id = cf.user_id)
WHERE cf.username = '';
