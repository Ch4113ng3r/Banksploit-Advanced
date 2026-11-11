#!/usr/bin/env python3
"""Add remaining 84 users (BS100416-BS100499) to the database"""

import mysql.connector
import hashlib
import random

# MySQL connection
cnx = mysql.connector.connect(
    host="127.0.0.1",
    user="root",
    password="root",
    database="banksploit"
)
cursor = cnx.cursor()

# Names for users
first_names = [
    'Ajay', 'Akbar', 'Akil', 'Akira', 'Akshara', 'Akul', 'Aman', 'Amar', 'Amara', 'Amba',
    'Ambi', 'Ambu', 'Amish', 'Amita', 'Amitava', 'Amitosh', 'Amitraj', 'Amitya', 'Amiya', 'Amlan',
    'Amleen', 'Ammara', 'Amin', 'Amrin', 'Amrita', 'Amrit', 'Amru', 'Amutha', 'Amyas', 'Amylyn',
    'An', 'Ana', 'Anaba', 'Anadah', 'Anadee', 'Anadi', 'Anadira', 'Anaheim', 'Anail', 'Anais',
    'Anak', 'Anakaren', 'Anakena', 'Anakina', 'Anaksimander', 'Anaksilaus', 'Anaksos', 'Analabelle', 'Analais', 'Analana',
    'Anal', 'Analeia', 'Analea', 'Analeah', 'Analeigh', 'Analena', 'Analey', 'Anali', 'Analicia', 'Analid',
    'Analina', 'Analisa', 'Analise', 'Analisha', 'Analisses', 'Analisse', 'Analita', 'Analitha', 'Analiyah', 'Analjit',
    'Anall', 'Analley', 'Analley', 'Anallia', 'Anallie', 'Anallos', 'Anallyn', 'Anallyson', 'Anally', 'Analma',
    'Analman', 'Analme', 'Analmire', 'Analo', 'Analoa', 'Analog', 'Analoge', 'Analogue', 'Analogy', 'Analoh',
    'Analoi', 'Analoie', 'Analoing', 'Analok', 'Analon', 'Analonie', 'Analonna', 'Analons', 'Analony', 'Analope'
]

last_names = [
    'Singh', 'Khan', 'Patel', 'Sharma', 'Verma', 'Desai', 'Iyer', 'Nair', 'Malhotra', 'Bhat',
    'Reddy', 'Chopra', 'Gupta', 'Joshi', 'Rao', 'Menon', 'Pillai', 'Bhandari', 'Misra', 'Kumar',
    'Das', 'Roy', 'Sinha', 'Srivastava', 'Awasthi', 'Saxena', 'Dutta', 'Mukherjee', 'Chatterjee', 'Banerjee',
    'Ghosh', 'Bose', 'Pal', 'Sen', 'De', 'Sanyal', 'Chottopadhyay', 'Mazumdar', 'Bhowmick', 'Mukherjee',
    'Paul', 'Nath', 'Lahiri', 'Sehgal', 'Mallik', 'Bansal', 'Talwar', 'Sahni', 'Mitter', 'Sengupta',
    'Thakur', 'Raj', 'Lal', 'Naidu', 'Murthy', 'Raman', 'Ranganathan', 'Subramaniam', 'Srikanthan', 'Sundaram',
    'Swamy', 'Narasimhan', 'Subramanian', 'Krishnan', 'Nambiar', 'Jayaraman', 'Seshadri', 'Srinivasan', 'Srinivas', 'Srinivasa',
    'Krishnamurthy', 'Kutty', 'Menon', 'Namboothiri', 'Thaloor', 'Ezhuthachan', 'Varkey', 'Kochupillai', 'Nambudirpad', 'Panicker',
    'Pandit', 'Pandey', 'Pande', 'Pandita', 'Pandith', 'Pandith', 'Pandith', 'Pandiy', 'Pandit', 'Pandy',
    'Pani', 'Panigrahi', 'Panigrahee', 'Panigrahy', 'Panigrahi', 'Panigrahee', 'Panigrahey', 'Panigrahy', 'Pani', 'Paninder'
]

locations = [
    'MG Road', 'Indiranagar', 'Koramangala', 'Whitefield', 'Marathahalli', 'JP Nagar', 'Hebbal', 'Jayanagar',
    'Yeshwanthpur', 'Malleswaram', 'Rajajinagar', 'Basavanagudi', 'Vijayanagar', 'KR Puram', 'Sadashivnagar', 'Fort',
    'Banashankari', 'Ulsoor', 'Ramamurthy Nagar', 'Tank Bund Road', 'Oxford School Road', 'Varthur', 'Sarjapur', 'Bellandur',
    'Kundalahalli', 'Carmelaram', 'Cessna Business Park', 'Infopark', 'Bagmane Tech Park', 'Embassy Manyata'
]

# Add 84 users (BS100416-BS100499)
print("Adding 84 remaining users...")
for i in range(416, 500):
    customer_id = f"BS{i}"
    first = first_names[(i - 416) % len(first_names)]
    last = last_names[(i - 416) % len(last_names)]
    location = locations[(i - 416) % len(locations)]

    name = f"{first} {last}"
    email = f"{first.lower()}.{last.lower()}.{customer_id}@banksploit.local"
    phone = f"+9199000{str(i).zfill(5)}"
    password = f"{customer_id}B4rc@123"
    password_hash = f"$2y$10${hashlib.sha1(f'pass_{i}'.encode()).hexdigest()[:53]}"
    password_md5 = hashlib.md5(password.encode()).hexdigest()
    aadhaar = f"XXXX-XXXX-{str((i % 10000)).zfill(4)}"
    pan = f"{first[:3].upper()}{last[:3].upper()}{str((i % 10000)).zfill(4)}"
    address = f"{(i % 100) + 1} {location}, Bangalore"
    ip = f"192.168.{2 + ((i - 416) // 256)}.{((i - 416) % 256) + 1}"

    query = """
    INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended,
     failed_attempts, last_login_ip, replacement_status, created_at)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
    """

    cursor.execute(query, (
        customer_id, name, email, phone, password_hash, password_md5,
        aadhaar, pan, address, 'user', 0, 0, 0, ip, 'available'
    ))

    if (i - 415) % 20 == 0:
        print(f"  Added {i - 415} users...")

print("Committing users...")
cnx.commit()

# Add bank accounts (2 per user: Savings + Current)
print("\nAdding bank accounts...")
query = """
INSERT IGNORE INTO accounts
(user_id, account_type, account_number, ifsc_code, balance, currency, is_primary, created_at, updated_at)
SELECT u.id, 'Savings', CONCAT('BSPL', LPAD(u.id, 12, '0')), 'BSLC0000001',
       ROUND(50000 + RAND() * 450000, 2), 'INR', 1, NOW(), NOW()
FROM users u
WHERE u.customer_id LIKE 'BS1004%'
AND NOT EXISTS (SELECT 1 FROM accounts WHERE user_id = u.id AND account_type = 'Savings')
"""
cursor.execute(query)

query = """
INSERT IGNORE INTO accounts
(user_id, account_type, account_number, ifsc_code, balance, currency, is_primary, created_at, updated_at)
SELECT u.id, 'Current', CONCAT('BSPL', LPAD(u.id + 1000000, 12, '0')), 'BSLC0000001',
       ROUND(20000 + RAND() * 180000, 2), 'INR', 0, NOW(), NOW()
FROM users u
WHERE u.customer_id LIKE 'BS1004%'
AND (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) = 1
"""
cursor.execute(query)
print("  Bank accounts created")

# Add cards (2 per user: Debit + Credit)
print("Adding cards...")
query = """
INSERT IGNORE INTO cards
(user_id, card_type, card_number, cvv, expiry_month, expiry_year, name_on_card, is_active, created_at, updated_at)
SELECT u.id, 'Debit', CONCAT('4532', LPAD(FLOOR(RAND() * 1000000000000), 12, '0')),
       LPAD(FLOOR(RAND() * 1000), 3, '0'), LPAD(FLOOR(RAND() * 12) + 1, 2, '0'),
       YEAR(NOW()) + FLOOR(RAND() * 5), u.name, 1, NOW(), NOW()
FROM users u
WHERE u.customer_id LIKE 'BS1004%'
AND NOT EXISTS (SELECT 1 FROM cards WHERE user_id = u.id AND card_type = 'Debit')
"""
cursor.execute(query)

query = """
INSERT IGNORE INTO cards
(user_id, card_type, card_number, cvv, expiry_month, expiry_year, name_on_card, is_active, created_at, updated_at)
SELECT u.id, 'Credit', CONCAT('5412', LPAD(FLOOR(RAND() * 1000000000000), 12, '0')),
       LPAD(FLOOR(RAND() * 1000), 3, '0'), LPAD(FLOOR(RAND() * 12) + 1, 2, '0'),
       YEAR(NOW()) + FLOOR(RAND() * 5), u.name, 1, NOW(), NOW()
FROM users u
WHERE u.customer_id LIKE 'BS1004%'
AND (SELECT COUNT(*) FROM cards WHERE user_id = u.id) = 1
"""
cursor.execute(query)
print("  Cards created")

# Add security questions (5 per user)
print("Adding security questions...")
questions = [
    "What is your mother's maiden name?",
    "In what city were you born?",
    "What is your childhood pet's name?",
    "What is your favorite movie?",
    "What is your favorite food?"
]

for q_idx, question in enumerate(questions):
    query = """
    INSERT IGNORE INTO security_questions (user_id, question, answer, created_at)
    SELECT u.id, %s, CONCAT('Answer', FLOOR(RAND() * 1000)), NOW()
    FROM users u
    WHERE u.customer_id LIKE 'BS1004%'
    AND NOT EXISTS (SELECT 1 FROM security_questions WHERE user_id = u.id AND question = %s)
    """
    cursor.execute(query, (question, question))

print("  Security questions created")

# Add audit logs
print("Adding audit logs...")
query = """
INSERT IGNORE INTO audit_logs (user_id, action, ip_address, user_agent, created_at)
SELECT u.id, 'login', CONCAT('192.168.', FLOOR(RAND() * 256), '.', FLOOR(RAND() * 256)),
       'Mozilla/5.0', DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY)
FROM users u
WHERE u.customer_id LIKE 'BS1004%'
"""
cursor.execute(query)
print("  Audit logs created")

print("\nCommitting all changes...")
cnx.commit()

# Show verification
print("\nVerification:")
cursor.execute("SELECT 'New Users (BS1004xx)' as metric, COUNT(*) as value FROM users WHERE customer_id LIKE 'BS1004%' UNION ALL SELECT 'Total Users in System', COUNT(*) FROM users UNION ALL SELECT 'Bank Accounts', COUNT(*) FROM accounts a INNER JOIN users u ON a.user_id = u.id WHERE u.customer_id LIKE 'BS1004%' UNION ALL SELECT 'Cards', COUNT(*) FROM cards c INNER JOIN users u ON c.user_id = u.id WHERE u.customer_id LIKE 'BS1004%' UNION ALL SELECT 'Security Questions', COUNT(*) FROM security_questions sq INNER JOIN users u ON sq.user_id = u.id WHERE u.customer_id LIKE 'BS1004%'")
for row in cursor.fetchall():
    print(f"  {row[0]}: {row[1]}")

cursor.close()
cnx.close()
print("\nDone!")
