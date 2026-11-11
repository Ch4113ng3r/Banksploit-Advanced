-- ============================================================================
--  BankSploit :: Schema (Stage 2)
--  All tables InnoDB / utf8mb4. Intentionally insecure design for training:
--    * users stores BOTH bcrypt (password_hash) AND md5 (password_md5)
--    * cards store card number / CVV in plaintext (column names say
--      "encrypted" but they are NOT — deliberate for Level 0)
--  This file runs automatically on a fresh DB volume (docker-entrypoint-initdb.d),
--  in alphabetical order, before 02-seed-data.sql.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id     VARCHAR(20)  NOT NULL,
    name            VARCHAR(128) NOT NULL,
    email           VARCHAR(128) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,          -- bcrypt
    password_md5    VARCHAR(32)  NOT NULL,          -- md5 (insecure, on purpose)
    aadhaar_masked  VARCHAR(20)  DEFAULT NULL,      -- XXXX-XXXX-1234
    pan             VARCHAR(10)  DEFAULT NULL,       -- ABCDE1234F
    address         VARCHAR(255) DEFAULT NULL,       -- profile field (stored XSS sink at low levels)
    role            ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_locked       BOOLEAN      NOT NULL DEFAULT FALSE,
    is_suspended    BOOLEAN      NOT NULL DEFAULT FALSE,   -- admin suspend action (distinct from lock)
    failed_attempts INT          NOT NULL DEFAULT 0,
    locked_until    DATETIME     DEFAULT NULL,        -- secure rate-limit lockout (auth.g1 L3)
    last_login_ip   VARCHAR(45)  DEFAULT NULL,        -- leaked by hidden API fields (accounts.l19)
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_customer_id (customer_id),
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- accounts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    account_number  VARCHAR(20)  NOT NULL,
    account_type    ENUM('savings','current','fd') NOT NULL DEFAULT 'savings',
    balance         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    daily_limit     DECIMAL(15,2) NOT NULL DEFAULT 200000.00,
    is_active       BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_account_number (account_number),
    KEY idx_accounts_user (user_id),
    CONSTRAINT fk_accounts_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- beneficiaries
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS beneficiaries (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id              INT UNSIGNED NOT NULL,
    name                 VARCHAR(128) NOT NULL,
    account_number       VARCHAR(20)  NOT NULL,
    ifsc_code            VARCHAR(11)  NOT NULL,
    bank_name            VARCHAR(128) NOT NULL,
    is_verified          BOOLEAN      NOT NULL DEFAULT TRUE,
    cooling_period_until DATETIME     DEFAULT NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_beneficiaries_user (user_id),
    CONSTRAINT fk_beneficiaries_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- transactions
--   from_account_id / to_account_id are nullable so external counterparties
--   (salary credits in, bill payments / EMIs out) can be represented as NULL.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_account_id INT UNSIGNED DEFAULT NULL,
    to_account_id   INT UNSIGNED DEFAULT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    mode            ENUM('IMPS','NEFT','RTGS') NOT NULL DEFAULT 'IMPS',
    status          ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
    remarks         VARCHAR(255) DEFAULT NULL,
    txn_ref         VARCHAR(40)  NOT NULL,
    otp_verified    BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_txn_ref (txn_ref),
    KEY idx_txn_from (from_account_id),
    KEY idx_txn_to (to_account_id),
    CONSTRAINT fk_txn_from FOREIGN KEY (from_account_id)
        REFERENCES accounts (id) ON DELETE SET NULL,
    CONSTRAINT fk_txn_to FOREIGN KEY (to_account_id)
        REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- otp_tokens
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS otp_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    otp_code    VARCHAR(6)   NOT NULL,
    purpose     ENUM('login','transfer','reset') NOT NULL,
    expires_at  DATETIME     NOT NULL,
    is_used     BOOLEAN      NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_user (user_id),
    CONSTRAINT fk_otp_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- sessions_log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED DEFAULT NULL,
    session_id  VARCHAR(128) NOT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    user_agent  VARCHAR(255) DEFAULT NULL,
    login_at    DATETIME     DEFAULT NULL,
    logout_at   DATETIME     DEFAULT NULL,
    is_active   BOOLEAN      NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- cards  (card_number / cvv stored in PLAINTEXT despite column names)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cards (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id               INT UNSIGNED NOT NULL,
    card_number_encrypted VARCHAR(32)  NOT NULL,     -- PLAINTEXT (insecure)
    card_type             ENUM('debit','credit') NOT NULL DEFAULT 'debit',
    cvv_encrypted         VARCHAR(8)   NOT NULL,      -- PLAINTEXT (insecure)
    expiry_date           VARCHAR(7)   NOT NULL,      -- MM/YY
    spending_limit        DECIMAL(15,2) NOT NULL DEFAULT 100000.00,
    is_blocked            BOOLEAN      NOT NULL DEFAULT FALSE,
    pin_hash              VARCHAR(255) DEFAULT NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cards_user (user_id),
    CONSTRAINT fk_cards_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- loans
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS loans (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    loan_type     ENUM('personal','home') NOT NULL,
    amount        DECIMAL(15,2) NOT NULL,
    tenure_months INT          NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    status        ENUM('applied','approved','rejected','disbursed') NOT NULL DEFAULT 'applied',
    documents     TEXT         DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loans_user (user_id),
    CONSTRAINT fk_loans_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- audit_log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED DEFAULT NULL,
    action        VARCHAR(128) NOT NULL,
    resource      VARCHAR(255) DEFAULT NULL,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    request_body  TEXT         DEFAULT NULL,
    response_code INT          DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- request_log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS request_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    method        VARCHAR(10)  NOT NULL,
    url           TEXT         NOT NULL,
    headers       TEXT         DEFAULT NULL,
    request_body  TEXT         DEFAULT NULL,
    response_body TEXT         DEFAULT NULL,
    response_code INT          DEFAULT NULL,
    duration_ms   INT          DEFAULT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- training_progress
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS training_progress (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_name VARCHAR(128) NOT NULL,
    vuln_id      VARCHAR(64)  NOT NULL,
    status       ENUM('hunting','found','exploited','reviewed') NOT NULL DEFAULT 'hunting',
    hints_used   INT          NOT NULL DEFAULT 0,
    found_at     TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_progress_session (session_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- password_reset_tokens
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(128) NOT NULL,
    expires_at  DATETIME     NOT NULL,
    is_used     BOOLEAN      NOT NULL DEFAULT FALSE,
    PRIMARY KEY (id),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- bill_payments
--   Dedicated record for utility/bill payments. The actual money movement is
--   still a debit row in `transactions` (linked by txn_ref); this table holds
--   the biller detail in proper columns. `biller_name` is the stored-XSS sink
--   (beneficiary.g3) — kept raw at vuln levels, escaped on output at level 3.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bill_payments (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    account_id   INT UNSIGNED DEFAULT NULL,
    biller_type  ENUM('electricity','water','gas','mobile','DTH') NOT NULL DEFAULT 'electricity',
    biller_id    VARCHAR(64)  DEFAULT NULL,
    biller_name  VARCHAR(255) NOT NULL,            -- stored-XSS sink (g3)
    amount       DECIMAL(15,2) NOT NULL,
    remarks      VARCHAR(255) DEFAULT NULL,
    status       ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
    txn_ref      VARCHAR(40)  NOT NULL,            -- links to transactions.txn_ref
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_bill_txn_ref (txn_ref),
    KEY idx_bill_user (user_id),
    CONSTRAINT fk_bill_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_bill_account FOREIGN KEY (account_id)
        REFERENCES accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- system_config
--   Key/value store for tunable system settings exposed by the admin config
--   panel (OTP expiry, session timeout, transfer limit, etc.). At admin.l4
--   level 0 these can be changed by any logged-in user via the API.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_config (
    config_key   VARCHAR(64)  NOT NULL,
    config_value VARCHAR(255) NOT NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO system_config (config_key, config_value) VALUES
    ('otp_expiry_seconds',     '300'),
    ('session_timeout_minutes','30'),
    ('daily_transfer_limit',   '200000'),
    ('max_failed_logins',      '5'),
    ('beneficiary_cooling_hours','24')
ON DUPLICATE KEY UPDATE config_value = config_value;

-- ---------------------------------------------------------------------------
-- user_preferences
--   Per-user UI preferences. The vulnerable path (sessions.g12) stores these
--   as a PHP-serialized blob in a cookie and unserialize()s it on read, which
--   enables PHP object injection. The secure path uses JSON. The DB row is the
--   trusted server-side copy.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id    INT UNSIGNED NOT NULL,
    prefs      TEXT         DEFAULT NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- security_questions
--   Knowledge-based answers used by the forgot-password journey's second step.
--   Answers are stored in PLAINTEXT (insecure, by design). The forgot flow's
--   SESSION PUZZLING weakness (auth.l25) means these questions can be skipped
--   entirely: the email step already overloads the auth session.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS security_questions (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id   INT UNSIGNED NOT NULL,
    position  TINYINT      NOT NULL,              -- 1..5
    question  VARCHAR(255) NOT NULL,
    answer    VARCHAR(255) NOT NULL,              -- PLAINTEXT (insecure, on purpose)
    PRIMARY KEY (id),
    KEY idx_secq_user (user_id),
    CONSTRAINT fk_secq_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
