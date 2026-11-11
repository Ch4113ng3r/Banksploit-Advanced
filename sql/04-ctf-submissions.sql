-- =============================================================================
-- CTF submissions table: tracks which user captured which flag, when, and points.
-- Used for the leaderboard on the Instructor Portal.
-- =============================================================================

CREATE TABLE IF NOT EXISTS ctf_submissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    vuln_key    VARCHAR(50) NOT NULL,
    points      INT NOT NULL DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_vuln (user_id, vuln_key),
    KEY ix_user (user_id),
    KEY ix_points (points DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
