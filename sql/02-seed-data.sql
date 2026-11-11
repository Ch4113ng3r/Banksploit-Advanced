-- ============================================================================
--  BankSploit :: Seed data (Stage 2) — realistic Indian banking data.
--
--  CREDENTIALS (plaintext for training):
--    Rahul Sharma  BS100234  password123   (user)
--    Priya Menon   BS100235  priya@123     (user)
--    Vikram Singh  BS100236  vikram@123    (user)
--    Aditya Rao    BS100237  aditya@123    (user)
--    Ankit         BS100238  1234          (user)
--    Admin User    ADMIN001  admin@123     (admin)
--
--  password_hash = bcrypt, password_md5 = md5 (insecure, deliberate).
--  bcrypt hashes were generated with PHP password_hash(); md5 is computed
--  inline with MD5() so it always matches the plaintext above.
--
--  Runs after 01-schema.sql on a fresh volume.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- users  (5)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, role, is_locked, failed_attempts, created_at)
VALUES
    ('BS100234', 'Rahul Sharma', 'rahul.sharma@example.in', '+919812345671',
     '$2y$10$YMUE6pDH5rDzgY8iMWZZbuaQRR1Pg.45mnEe4OOkEeZSUjnT9QGeu', MD5('password123'),
     'XXXX-XXXX-4521', 'ABCPS1234F', 'user', FALSE, 0, DATE_SUB(NOW(), INTERVAL 400 DAY)),

    ('BS100235', 'Priya Menon', 'priya.menon@example.in', '+919812345672',
     '$2y$10$tNC9NO.5NyESISY77y6VNucH8RpHQ0XqJ5rH7GAipjzAJBWh/.oEO', MD5('priya@123'),
     'XXXX-XXXX-7832', 'AKMPM5678K', 'user', FALSE, 0, DATE_SUB(NOW(), INTERVAL 365 DAY)),

    ('BS100236', 'Vikram Singh', 'vikram.singh@example.in', '+919812345673',
     '$2y$10$Hf6B.uJyvggHaUFK/ZScf.yweRMxD/Cq0DLQ4.laO/dN5NSgMzmZi', MD5('vikram@123'),
     'XXXX-XXXX-9043', 'BNZPS9012V', 'user', FALSE, 0, DATE_SUB(NOW(), INTERVAL 320 DAY)),

    ('BS100237', 'Aditya Rao', 'aditya.rao@example.in', '+919812345674',
     '$2y$10$7yPA5tjGRFOGjWGlZhOhP.VLxhpMLKeLl6K6SFwpacIzclaVALFZK', MD5('aditya@123'),
     'XXXX-XXXX-3310', 'CRTPR3456A', 'user', FALSE, 0, DATE_SUB(NOW(), INTERVAL 280 DAY)),

    ('ADMIN001', 'Admin User', 'admin@banksploit.local', '+919800000000',
     '$2y$10$nAxuz4qwju9TyEPXSdyVHepqjaI9re3gC8.P1W0KKN4ktGpeeAoka', MD5('admin@123'),
     'XXXX-XXXX-0001', 'ADMPA0001Z', 'admin', FALSE, 0, DATE_SUB(NOW(), INTERVAL 500 DAY));

-- ---------------------------------------------------------------------------
-- accounts  (10: 2 per regular user, +1 FD for Rahul to cover the 'fd' type,
--            admin 1 savings)
--   Fresh-load AUTO_INCREMENT ids (relied on by transactions below):
--     1 Rahul savings   2 Rahul current   3 Rahul fd
--     4 Priya savings   5 Priya current
--     6 Vikram savings  7 Vikram current
--     8 Aditya savings  9 Aditya current
--    10 Admin savings
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO accounts (user_id, account_number, account_type, balance, daily_limit, is_active) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100234'), '50100100000001', 'savings', 845000.50, 200000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100234'), '50200100000001', 'current', 234500.00, 500000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100234'), '50300100000001', 'fd',      500000.00,      0.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100235'), '50100100000002', 'savings', 612300.75, 200000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100235'), '50200100000002', 'current', 156000.00, 500000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100236'), '50100100000003', 'savings', 923450.00, 200000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100236'), '50200100000003', 'current', 445600.25, 500000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100237'), '50100100000004', 'savings', 378900.00, 200000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100237'), '50200100000004', 'current', 198750.50, 500000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='ADMIN001'), '50100100000005', 'savings', 1000000.00, 200000.00, TRUE);

-- ---------------------------------------------------------------------------
-- beneficiaries  (20: 4 per regular user)
--   Each user's beneficiaries include account numbers belonging to OTHER seeded
--   users. This enables IDOR discovery: participants see real internal account
--   numbers in their beneficiary list and can use them on the Statements page.
--   Meena Iyer is intentionally still inside her cooling period (future
--   cooling_period_until) for a later cooling-period-bypass scenario.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until) VALUES
    -- Rahul's beneficiaries (BS100234): includes Priya's, Vikram's, Aditya's accounts
    ((SELECT id FROM users WHERE customer_id='BS100234'), 'Priya Menon',    '50100100000002', 'SBIN0001234', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100234'), 'Vikram Singh',   '50200100000003', 'HDFC0000456', 'HDFC Bank',          TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100234'), 'Aditya Rao',     '50100100000004', 'ICIC0001789', 'ICICI Bank',         TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100234'), 'Meena Iyer',     '50100200000012', 'ICIC0001789', 'ICICI Bank',         TRUE, DATE_ADD(NOW(), INTERVAL 2 DAY)),
    -- Priya's beneficiaries (BS100235): includes Rahul's, Vikram's, Ankit's accounts
    ((SELECT id FROM users WHERE customer_id='BS100235'), 'Rahul Sharma',   '50100100000001', 'SBIN0001234', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100235'), 'Vikram Singh',   '50100100000003', 'HDFC0000789', 'HDFC Bank',          TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100235'), 'Ankit Kumar',    '50100100000006', 'SBIN0005678', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100235'), 'Aditya Rao',     '50200100000004', 'ICIC0002345', 'ICICI Bank',         TRUE, NULL),
    -- Vikram's beneficiaries (BS100236): includes Rahul's, Priya's, Aditya's accounts
    ((SELECT id FROM users WHERE customer_id='BS100236'), 'Rahul Sharma',   '50200100000001', 'SBIN0001234', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100236'), 'Priya Menon',    '50200100000002', 'HDFC0000456', 'HDFC Bank',          TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100236'), 'Aditya Rao',     '50100100000004', 'ICIC0002345', 'ICICI Bank',         TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100236'), 'Ankit Kumar',    '50200100000006', 'SBIN0005678', 'State Bank of India', TRUE, NULL),
    -- Aditya's beneficiaries (BS100237): includes Rahul's, Priya's, Vikram's accounts
    ((SELECT id FROM users WHERE customer_id='BS100237'), 'Rahul Sharma',   '50100100000001', 'SBIN0001234', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100237'), 'Priya Menon',    '50100100000002', 'HDFC0000789', 'HDFC Bank',          TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100237'), 'Vikram Singh',   '50200100000003', 'HDFC0001122', 'HDFC Bank',          TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100237'), 'Ankit Kumar',    '50100100000006', 'SBIN0003344', 'State Bank of India', TRUE, NULL);

-- ---------------------------------------------------------------------------
-- cards  (8: 1 debit + 1 credit per regular user). card_number / cvv PLAINTEXT.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cards (user_id, card_number_encrypted, card_type, cvv_encrypted, expiry_date, spending_limit, is_blocked, pin_hash) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100234'), '4123560078901234', 'debit',  '312', '08/27', 100000.00, FALSE, MD5('4571')),
    ((SELECT id FROM users WHERE customer_id='BS100234'), '5234110099887766', 'credit', '887', '11/26', 300000.00, FALSE, MD5('8830')),
    ((SELECT id FROM users WHERE customer_id='BS100235'), '4123560078905678', 'debit',  '145', '03/28', 100000.00, FALSE, MD5('1290')),
    ((SELECT id FROM users WHERE customer_id='BS100235'), '5234110099881122', 'credit', '642', '07/27', 250000.00, FALSE, MD5('6643')),
    ((SELECT id FROM users WHERE customer_id='BS100236'), '4123560078909012', 'debit',  '908', '12/27', 100000.00, FALSE, MD5('3372')),
    ((SELECT id FROM users WHERE customer_id='BS100236'), '5234110099883344', 'credit', '211', '05/26', 500000.00, TRUE,  MD5('9981')),
    ((SELECT id FROM users WHERE customer_id='BS100237'), '4123560078903456', 'debit',  '530', '09/28', 100000.00, FALSE, MD5('2204')),
    ((SELECT id FROM users WHERE customer_id='BS100237'), '5234110099885566', 'credit', '778', '02/27', 250000.00, FALSE, MD5('7715'));

-- ---------------------------------------------------------------------------
-- loans  (1 disbursed personal for Rahul, 1 applied home for Priya)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO loans (user_id, loan_type, amount, tenure_months, interest_rate, status, documents, created_at) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100234'), 'personal',  500000.00, 48, 11.50, 'disbursed',
     'salary_slip.pdf,pan_card.pdf,bank_statement.pdf', DATE_SUB(NOW(), INTERVAL 120 DAY)),
    ((SELECT id FROM users WHERE customer_id='BS100235'), 'home',     4500000.00, 240, 8.75, 'applied',
     'property_deed.pdf,income_proof.pdf,form16.pdf', DATE_SUB(NOW(), INTERVAL 10 DAY));

-- ---------------------------------------------------------------------------
-- transactions  (30 over the past ~3 months)
--   account ids per the map above (1..10). External counterparties are NULL.
--   #16 is a FAILED bill payment; #24 is a PENDING transfer (otp_verified=0).
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO transactions
    (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
VALUES
    -- salary credits (external -> savings), NEFT
    (NULL, 1, 125000.00, 'NEFT', 'completed', 'Salary credit - Mar 2026', 'NEFTSAL00001', FALSE, DATE_SUB(NOW(), INTERVAL 89 DAY)),
    (NULL, 1, 125000.00, 'NEFT', 'completed', 'Salary credit - Apr 2026', 'NEFTSAL00002', FALSE, DATE_SUB(NOW(), INTERVAL 59 DAY)),
    (NULL, 1, 132000.00, 'NEFT', 'completed', 'Salary credit - May 2026', 'NEFTSAL00003', FALSE, DATE_SUB(NOW(), INTERVAL 29 DAY)),
    (NULL, 4,  98000.00, 'NEFT', 'completed', 'Salary credit - Mar 2026', 'NEFTSAL00004', FALSE, DATE_SUB(NOW(), INTERVAL 88 DAY)),
    (NULL, 4,  98000.00, 'NEFT', 'completed', 'Salary credit - Apr 2026', 'NEFTSAL00005', FALSE, DATE_SUB(NOW(), INTERVAL 58 DAY)),
    (NULL, 4, 101500.00, 'NEFT', 'completed', 'Salary credit - May 2026', 'NEFTSAL00006', FALSE, DATE_SUB(NOW(), INTERVAL 28 DAY)),
    (NULL, 6, 145000.00, 'NEFT', 'completed', 'Salary credit - Mar 2026', 'NEFTSAL00007', FALSE, DATE_SUB(NOW(), INTERVAL 90 DAY)),
    (NULL, 6, 145000.00, 'NEFT', 'completed', 'Salary credit - Apr 2026', 'NEFTSAL00008', FALSE, DATE_SUB(NOW(), INTERVAL 60 DAY)),
    (NULL, 6, 150000.00, 'NEFT', 'completed', 'Salary credit - May 2026', 'NEFTSAL00009', FALSE, DATE_SUB(NOW(), INTERVAL 30 DAY)),
    (NULL, 8,  87000.00, 'NEFT', 'completed', 'Salary credit - Mar 2026', 'NEFTSAL00010', FALSE, DATE_SUB(NOW(), INTERVAL 87 DAY)),
    (NULL, 8,  87000.00, 'NEFT', 'completed', 'Salary credit - Apr 2026', 'NEFTSAL00011', FALSE, DATE_SUB(NOW(), INTERVAL 57 DAY)),
    (NULL, 8,  90500.00, 'NEFT', 'completed', 'Salary credit - May 2026', 'NEFTSAL00012', FALSE, DATE_SUB(NOW(), INTERVAL 27 DAY)),

    -- bill payments (account -> external), IMPS
    (1, NULL,  3450.00, 'IMPS', 'completed', 'Electricity bill - BESCOM',  'IMPSBIL00001', TRUE,  DATE_SUB(NOW(), INTERVAL 80 DAY)),
    (4, NULL,   799.00, 'IMPS', 'completed', 'Mobile recharge - Airtel',   'IMPSBIL00002', TRUE,  DATE_SUB(NOW(), INTERVAL 70 DAY)),
    (6, NULL,  1499.00, 'IMPS', 'completed', 'Broadband - ACT Fibernet',   'IMPSBIL00003', TRUE,  DATE_SUB(NOW(), INTERVAL 55 DAY)),
    (8, NULL,  1150.00, 'IMPS', 'failed',    'Gas bill - Indane',          'IMPSBIL00004', FALSE, DATE_SUB(NOW(), INTERVAL 40 DAY)),
    (1, NULL,   499.00, 'IMPS', 'completed', 'DTH recharge - Tata Play',   'IMPSBIL00005', TRUE,  DATE_SUB(NOW(), INTERVAL 22 DAY)),
    (4, NULL,   670.00, 'IMPS', 'completed', 'Water bill - BWSSB',         'IMPSBIL00006', TRUE,  DATE_SUB(NOW(), INTERVAL 12 DAY)),

    -- transfers (account -> account)
    (1, 4, 15000.00, 'IMPS', 'completed', 'Rent share',        'IMPSXFR00001', TRUE,  DATE_SUB(NOW(), INTERVAL 65 DAY)),
    (6, 8,  8000.00, 'IMPS', 'completed', 'Dinner split',      'IMPSXFR00002', TRUE,  DATE_SUB(NOW(), INTERVAL 50 DAY)),
    (4, 1,  5000.00, 'IMPS', 'completed', 'Loan repay',        'IMPSXFR00003', TRUE,  DATE_SUB(NOW(), INTERVAL 35 DAY)),
    (8, 6, 12000.00, 'RTGS', 'completed', 'Purchase',          'RTGSXFR00004', TRUE,  DATE_SUB(NOW(), INTERVAL 20 DAY)),
    (1, 6, 25000.00, 'RTGS', 'completed', 'Investment',        'RTGSXFR00005', TRUE,  DATE_SUB(NOW(), INTERVAL 8 DAY)),
    (10, 1, 50000.00, 'NEFT', 'pending',  'Refund processing', 'NEFTXFR00006', FALSE, DATE_SUB(NOW(), INTERVAL 2 DAY)),

    -- EMIs (account -> external), NEFT
    (1, NULL, 13100.00, 'NEFT', 'completed', 'Personal loan EMI',       'NEFTEMI00001', TRUE, DATE_SUB(NOW(), INTERVAL 75 DAY)),
    (1, NULL, 13100.00, 'NEFT', 'completed', 'Personal loan EMI',       'NEFTEMI00002', TRUE, DATE_SUB(NOW(), INTERVAL 45 DAY)),
    (1, NULL, 13100.00, 'NEFT', 'completed', 'Personal loan EMI',       'NEFTEMI00003', TRUE, DATE_SUB(NOW(), INTERVAL 15 DAY)),
    (6, NULL, 18500.00, 'NEFT', 'completed', 'Car loan EMI',            'NEFTEMI00004', TRUE, DATE_SUB(NOW(), INTERVAL 50 DAY)),
    (6, NULL, 18500.00, 'NEFT', 'completed', 'Car loan EMI',            'NEFTEMI00005', TRUE, DATE_SUB(NOW(), INTERVAL 20 DAY)),
    (8, NULL,  9200.00, 'NEFT', 'completed', 'Consumer durable EMI',    'NEFTEMI00006', TRUE, DATE_SUB(NOW(), INTERVAL 25 DAY));

-- ---------------------------------------------------------------------------
-- last_login_ip (column added after the original seed; harmless if absent)
-- ---------------------------------------------------------------------------
UPDATE users SET last_login_ip = '203.0.113.10'  WHERE customer_id = 'ADMIN001';
UPDATE users SET last_login_ip = '198.51.100.22' WHERE customer_id = 'BS100234';

-- ---------------------------------------------------------------------------
-- training_progress: one row per vulnerability (36), all 'hunting'.
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO training_progress (session_name,vuln_id,status,hints_used) VALUES
('default','auth.g1','hunting',0),
('default','auth.l10','hunting',0),
('default','auth.l11','hunting',0),
('default','auth.l12','hunting',0),
('default','auth.l13','hunting',0),
('default','auth.l14','hunting',0),
('default','auth.l15','hunting',0),
('default','auth.l16','hunting',0),
('default','auth.l17','hunting',0),
('default','auth.l25','hunting',0),
('default','statements.g2','hunting',0),
('default','statements.g4','hunting',0),
('default','statements.g8','hunting',0),
('default','statements.g9','hunting',0),
('default','statements.l1','hunting',0),
('default','beneficiary.g3','hunting',0),
('default','beneficiary.l3','hunting',0),
('default','beneficiary.l8','hunting',0),
('default','transfer.g5','hunting',0),
('default','transfer.l1','hunting',0),
('default','transfer.l2','hunting',0),
('default','transfer.l6','hunting',0),
('default','transfer.l7','hunting',0),
('default','transfer.l9','hunting',0),
('default','transfer.l20','hunting',0),
('default','transfer.l21','hunting',0),
('default','transfer.l23','hunting',0),
('default','loans.g6','hunting',0),
('default','loans.g7','hunting',0),
('default','loans.g10','hunting',0),
('default','loans.l22','hunting',0),
('default','templates.g11','hunting',0),
('default','sessions.g12','hunting',0),
('default','admin.l4','hunting',0),
('default','profile.l5','hunting',0),
('default','accounts.l19','hunting',0),
('default','cards.l24','hunting',0),
('default','core.l18','hunting',0);

-- ---------------------------------------------------------------------------
-- audit_log: a few sample entries so the admin audit viewer isn't empty.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO audit_log (user_id, action, resource, ip_address, request_body, response_code, created_at) VALUES
 (5,'admin.login','/login','203.0.113.10','{"customer_id":"ADMIN001"}',200,DATE_SUB(NOW(),INTERVAL 5 DAY)),
 (1,'user.login','/login','198.51.100.22','{"customer_id":"BS100234","password":"password123"}',200,DATE_SUB(NOW(),INTERVAL 4 DAY)),
 (1,'transfer.execute','/api/v1/transfer/execute','198.51.100.22','{"from_account_id":1,"beneficiary_id":1,"amount":15000}',200,DATE_SUB(NOW(),INTERVAL 3 DAY)),
 (2,'profile.update','/api/v1/profile','198.51.100.40','{"name":"Priya Menon"}',200,DATE_SUB(NOW(),INTERVAL 2 DAY)),
 (3,'login.failed','/login','203.0.113.99','{"customer_id":"BS100236","password":"wrong"}',401,DATE_SUB(NOW(),INTERVAL 6 HOUR));

-- ===========================================================================
--  Ankit (added user) — customer BS100238 / password 1234
--    Self-contained block: every statement looks up ids via subquery, so it
--    loads correctly on a fresh volume AND can be re-applied to a running DB
--    without disturbing the carefully-mapped account ids 1..10 above.
--    On a fresh load Ankit is user id 6, accounts 11 (savings) / 12 (current).
-- ===========================================================================
INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
VALUES
    ('BS100238', 'Ankit', 'ankit.rai@example.in', '+919812345675',
     '$2y$10$1H5OTItGT1UXEKW3iX7PAeC5I4rIoWCYe4yT5nLAk7uHla1reUOHG', MD5('1234'),
     'XXXX-XXXX-5588', 'AKTPA1234R', 'No. 12, MG Road, Bengaluru, KA', 'user',
     FALSE, FALSE, 0, '198.51.100.55', DATE_SUB(NOW(), INTERVAL 45 DAY));

-- accounts (savings + current)
INSERT IGNORE INTO accounts (user_id, account_number, account_type, balance, daily_limit, is_active) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'), '50100100000006', 'savings', 750000.00, 200000.00, TRUE),
    ((SELECT id FROM users WHERE customer_id='BS100238'), '50200100000006', 'current', 125000.00, 500000.00, TRUE);

-- beneficiaries (4): includes Rahul's, Priya's, Vikram's, Aditya's accounts
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'), 'Rahul Sharma',  '50200100000001', 'SBIN0001234', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100238'), 'Priya Menon',   '50100100000002', 'HDFC0002211', 'HDFC Bank',           TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100238'), 'Vikram Singh',  '50100100000003', 'SBIN0006677', 'State Bank of India', TRUE, NULL),
    ((SELECT id FROM users WHERE customer_id='BS100238'), 'Aditya Rao',    '50200100000004', 'ICIC0002345', 'ICICI Bank',          TRUE, NULL);

-- cards (1 debit + 1 credit) — card number / cvv stored plaintext, by design
INSERT IGNORE INTO cards (user_id, card_number_encrypted, card_type, cvv_encrypted, expiry_date, spending_limit, is_blocked, pin_hash) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'), '4123560078907890', 'debit',  '321', '10/28', 100000.00, FALSE, MD5('1212')),
    ((SELECT id FROM users WHERE customer_id='BS100238'), '5234110099887700', 'credit', '456', '06/27', 250000.00, FALSE, MD5('3434'));

-- loan (1 applied personal)
INSERT IGNORE INTO loans (user_id, loan_type, amount, tenure_months, interest_rate, status, documents, created_at) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'), 'personal', 300000.00, 36, 12.00, 'applied',
     'salary_slip.pdf,pan_card.pdf,aadhaar.pdf', DATE_SUB(NOW(), INTERVAL 7 DAY));

-- transactions (salary credits in, an electricity bill out, a transfer out to Rahul)
INSERT IGNORE INTO transactions
    (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
VALUES
    (NULL, (SELECT id FROM accounts WHERE account_number='50100100000006'),
        110000.00, 'NEFT', 'completed', 'Salary credit - Apr 2026', 'NEFTSAL00013', FALSE, DATE_SUB(NOW(), INTERVAL 58 DAY)),
    (NULL, (SELECT id FROM accounts WHERE account_number='50100100000006'),
        114000.00, 'NEFT', 'completed', 'Salary credit - May 2026', 'NEFTSAL00014', FALSE, DATE_SUB(NOW(), INTERVAL 28 DAY)),
    ((SELECT id FROM accounts WHERE account_number='50100100000006'), NULL,
        2450.00, 'IMPS', 'completed', 'Electricity bill - BESCOM', 'IMPSBIL00007', TRUE, DATE_SUB(NOW(), INTERVAL 14 DAY)),
    ((SELECT id FROM accounts WHERE account_number='50100100000006'),
        (SELECT id FROM accounts WHERE account_number='50100100000001'),
        5000.00, 'IMPS', 'completed', 'Gift to Rahul', 'IMPSXFR00007', TRUE, DATE_SUB(NOW(), INTERVAL 9 DAY));

-- bill_payments (links to the electricity-bill transaction above by txn_ref)
INSERT IGNORE INTO bill_payments (user_id, account_id, biller_type, biller_id, biller_name, amount, remarks, status, txn_ref) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'),
     (SELECT id FROM accounts WHERE account_number='50100100000006'),
     'electricity', 'BESCOM-BLR-0098', 'BESCOM', 2450.00, 'Electricity bill - May', 'completed', 'IMPSBIL00007');

-- user_preferences (server-side trusted copy; sessions.g12 sink)
INSERT IGNORE INTO user_preferences (user_id, prefs) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'), '{"theme":"dark","language":"en","dashboard":"summary"}');

-- audit_log (a sample login entry)
INSERT IGNORE INTO audit_log (user_id, action, resource, ip_address, request_body, response_code, created_at) VALUES
    ((SELECT id FROM users WHERE customer_id='BS100238'), 'user.login', '/login', '198.51.100.55',
     '{"customer_id":"BS100238","password":"1234"}', 200, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ---------------------------------------------------------------------------
-- security_questions  (5 per user — used by the forgot-password 2nd step)
--   Same 5 questions for everyone; answers differ per user (PLAINTEXT).
--   ANSWERS (for the lab):
--     BS100234 Rahul : Sharma | DPS Bangalore | Mumbai | Tommy | Verma
--     BS100235 Priya : Nair | Kendriya Vidyalaya | Chennai | Snowy | Iyer
--     BS100236 Vikram: Kaur | St Xaviers | Delhi | Rocky | Singh
--     BS100237 Aditya: Reddy | Army School | Hyderabad | Bruno | Rao
--     BS100238 Ankit : Rai | Modern School | Bengaluru | Sheru | Gupta
--     ADMIN001 Admin : Admin | BankSploit Academy | Pune | Max | Root
--   (These can be SKIPPED via the auth.l25 session-puzzling weakness.)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, q.position, q.question, q.answer FROM users u JOIN (
    SELECT 'BS100234' cid, 1 position, 'What is your mother''s maiden name?' question, 'Sharma' answer UNION ALL
    SELECT 'BS100234', 2, 'What was the name of your first school?', 'DPS Bangalore' UNION ALL
    SELECT 'BS100234', 3, 'In which city were you born?', 'Mumbai' UNION ALL
    SELECT 'BS100234', 4, 'What was the name of your first pet?', 'Tommy' UNION ALL
    SELECT 'BS100234', 5, 'What is your favourite teacher''s name?', 'Verma' UNION ALL
    SELECT 'BS100235', 1, 'What is your mother''s maiden name?', 'Nair' UNION ALL
    SELECT 'BS100235', 2, 'What was the name of your first school?', 'Kendriya Vidyalaya' UNION ALL
    SELECT 'BS100235', 3, 'In which city were you born?', 'Chennai' UNION ALL
    SELECT 'BS100235', 4, 'What was the name of your first pet?', 'Snowy' UNION ALL
    SELECT 'BS100235', 5, 'What is your favourite teacher''s name?', 'Iyer' UNION ALL
    SELECT 'BS100236', 1, 'What is your mother''s maiden name?', 'Kaur' UNION ALL
    SELECT 'BS100236', 2, 'What was the name of your first school?', 'St Xaviers' UNION ALL
    SELECT 'BS100236', 3, 'In which city were you born?', 'Delhi' UNION ALL
    SELECT 'BS100236', 4, 'What was the name of your first pet?', 'Rocky' UNION ALL
    SELECT 'BS100236', 5, 'What is your favourite teacher''s name?', 'Singh' UNION ALL
    SELECT 'BS100237', 1, 'What is your mother''s maiden name?', 'Reddy' UNION ALL
    SELECT 'BS100237', 2, 'What was the name of your first school?', 'Army School' UNION ALL
    SELECT 'BS100237', 3, 'In which city were you born?', 'Hyderabad' UNION ALL
    SELECT 'BS100237', 4, 'What was the name of your first pet?', 'Bruno' UNION ALL
    SELECT 'BS100237', 5, 'What is your favourite teacher''s name?', 'Rao' UNION ALL
    SELECT 'BS100238', 1, 'What is your mother''s maiden name?', 'Rai' UNION ALL
    SELECT 'BS100238', 2, 'What was the name of your first school?', 'Modern School' UNION ALL
    SELECT 'BS100238', 3, 'In which city were you born?', 'Bengaluru' UNION ALL
    SELECT 'BS100238', 4, 'What was the name of your first pet?', 'Sheru' UNION ALL
    SELECT 'BS100238', 5, 'What is your favourite teacher''s name?', 'Gupta' UNION ALL
    SELECT 'ADMIN001', 1, 'What is your mother''s maiden name?', 'Admin' UNION ALL
    SELECT 'ADMIN001', 2, 'What was the name of your first school?', 'BankSploit Academy' UNION ALL
    SELECT 'ADMIN001', 3, 'In which city were you born?', 'Pune' UNION ALL
    SELECT 'ADMIN001', 4, 'What was the name of your first pet?', 'Max' UNION ALL
    SELECT 'ADMIN001', 5, 'What is your favourite teacher''s name?', 'Root'
) q ON u.customer_id = q.cid;
