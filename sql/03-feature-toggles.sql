-- ============================================================================
-- BankSploit :: Feature Toggles (Stage 3)
-- Instructor-controlled feature flags that affect the CTF app on port 9443
-- ============================================================================

CREATE TABLE IF NOT EXISTS feature_toggles (
    feature_key VARCHAR(50) PRIMARY KEY,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    description VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert default features
INSERT IGNORE INTO feature_toggles (feature_key, is_enabled, description) VALUES
('training_lab', TRUE, 'Enable/disable /training and /lab pages'),
('leaderboard', TRUE, 'Enable/disable /leaderboard page'),
('flag_submission', TRUE, 'Enable/disable flag submission API endpoints'),
('code_inspector', TRUE, 'Enable/disable /inspector page'),
('swagger_docs', TRUE, 'Enable/disable /swagger-ui page'),
('otp_display', TRUE, 'Show OTP codes on login page (Lab Mode)'),
('request_logging', TRUE, 'Log all requests to requests_log table'),
('flag_delivery', TRUE, 'Deliver CTF flags to participants (modal, X-CTF-Flag header, OTP JS embed)'),
('hints', TRUE, 'Show participant vulnerability hint banners'),
('code_viewer', FALSE, 'Open the vulnerable-vs-secure code viewer to participants (Training)'),
('debug', FALSE, 'Debug mode — expose the live session viewer (/debug/session). Instructor-controlled, off by default.');
