-- =============================================================================
-- CTF flags table: stores ONLY the G2 (SQL Injection — Data Extraction) flag
-- per user. This table is the "hidden treasure" that players discover via
-- UNION-based SQL injection in the statements filter.
--
-- Design: Only the G2 flag lives here so that discovering this table via SQLi
-- rewards the player with the G2 flag — NOT all 10 flags at once.
-- All other flags are delivered through their respective vulnerability endpoints.
-- =============================================================================

CREATE TABLE IF NOT EXISTS ctf_flags (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    username    VARCHAR(50) NOT NULL DEFAULT '',
    vuln_key    VARCHAR(50) NOT NULL,
    flag        VARCHAR(100) NOT NULL,
    description VARCHAR(200) DEFAULT '',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_vuln (user_id, vuln_key),
    KEY ix_vuln (vuln_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Also add a ctf_flag column to accounts table for IDOR (transfer.l1) discovery.
-- This column stores ONLY the L1 (IDOR) flag for that user.
-- MySQL 8.0 doesn't support ADD COLUMN IF NOT EXISTS, so we use a procedure.
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'ctf_flag');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE accounts ADD COLUMN ctf_flag VARCHAR(100) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Flags are seeded by seed_flags.php on first boot / setup.
