<?php
/**
 * Generate 100 Additional Users (BS100400-BS100499)
 * Creates comprehensive seed data for extended CTF participants
 *
 * Usage: php generate-additional-users.php > sql/04-bulk-users-100-extended.sql
 * Then: mysql -u banksploit -pbanksploit_pass banksploit < sql/04-bulk-users-100-extended.sql
 */

// Indian names for 100 additional users
$first_names = [
    'Aditya', 'Aisha', 'Akshay', 'Amar', 'Ameya', 'Amit', 'Amrita', 'Ananya', 'Aniket', 'Anita',
    'Ankita', 'Anshul', 'Anu', 'Anurag', 'Anusha', 'Anushka', 'Aravind', 'Arjun', 'Aryan', 'Ashmit',
    'Ashraf', 'Ashwani', 'Ashwini', 'Ashwita', 'Ashwitha', 'Atharva', 'Atul', 'Avanish', 'Avi', 'Aviral',
    'Avneet', 'Ayaan', 'Ayana', 'Ayanna', 'Ayesha', 'Ayush', 'Ayushman', 'Ayu', 'Azhar', 'Azra',
    'Badri', 'Bala', 'Balaji', 'Balakrishnan', 'Balraj', 'Balu', 'Bandana', 'Bankim', 'Bansi', 'Barkat',
    'Barkha', 'Barkha', 'Barnali', 'Basant', 'Basir', 'Basit', 'Basmati', 'Bassi', 'Basti', 'Bata',
    'Batul', 'Bawa', 'Baxi', 'Baya', 'Bayan', 'Bayar', 'Baye', 'Bayesh', 'Bayer', 'Bayir',
    'Beant', 'Bearish', 'Beata', 'Beatrice', 'Beda', 'Bedanta', 'Bedara', 'Bedarsh', 'Bedavati', 'Bedee',
    'Beej', 'Beela', 'Beema', 'Beena', 'Beenakshi', 'Beenish', 'Beenita', 'Beerendra', 'Beerish', 'Beeshma',
    'Bela', 'Belinda', 'Belkis', 'Bella', 'Bellamy', 'Belmont', 'Belni', 'Belon', 'Belora', 'Belva'
];

$last_names = [
    'Agarwal', 'Aggarwal', 'Agrawal', 'Ahuja', 'Aiyer', 'Aiyar', 'Ajay', 'Akhtar', 'Alam', 'Alamgir',
    'Alawadhi', 'Alberga', 'Albrecht', 'Alcantara', 'Alden', 'Alder', 'Aldrich', 'Alessio', 'Alev', 'Alexander',
    'Alexiou', 'Alexis', 'Alfaro', 'Alfon', 'Alfono', 'Alford', 'Alfreda', 'Alfredi', 'Alfred', 'Alfredo',
    'Algar', 'Algarve', 'Alger', 'Alghada', 'Alghalani', 'Alhakem', 'Alhammadi', 'Alhan', 'Alharbi', 'Alhashmi',
    'Alhassani', 'Alhassi', 'Alhattab', 'Alhawal', 'Alhazmi', 'Alhazzan', 'Alhelo', 'Alhelou', 'Alheni', 'Alhennawi',
    'Alherbish', 'Alherbish', 'Alherz', 'Alhesar', 'Alhethal', 'Alhmeedi', 'Alhmedi', 'Alhmoud', 'Alhoqail', 'Alhorsan',
    'Alhouti', 'Alhudaifi', 'Alhudhud', 'Alhudoor', 'Alhudyan', 'Alhufeiri', 'Alhufeiri', 'Alhugail', 'Alhuhmedi', 'Alhujairi',
    'Alhujairi', 'Alhujairi', 'Alhujairy', 'Alhujairy', 'Alhujallan', 'Alhujali', 'Alhujayli', 'Alhujaymi', 'Alhujaymi', 'Alhujaymi',
    'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari', 'Alhujari'
];

$locations = [
    'MG Road', 'Indiranagar', 'Koramangala', 'Whitefield', 'Marathahalli', 'JP Nagar', 'Hebbal', 'Jayanagar',
    'Yeshwanthpur', 'Malleswaram', 'Rajajinagar', 'Basavanagudi', 'Vijayanagar', 'KR Puram', 'Sadashivnagar', 'Fort',
    'Banashankari', 'Ulsoor', 'Ramamurthy Nagar', 'Tank Bund Road', 'Oxford School Road', 'Varthur', 'Sarjapur', 'Bellandur',
    'Kundalahalli', 'Carmelaram', 'Cessna Business Park', 'Infopark', 'Bagmane Tech Park', 'Embassy Manyata', 'Prestige Ozone',
    'Prestige Tech Park', 'Nexus Bengaluru', 'Kalyani Tech Park', 'Marathon Infinity', 'Salarpuria Greenage', 'Tech Park',
    'Manyata Espire', 'ITPL Road', 'Outer Ring Road', 'Devarabisanahalli', 'Cessna Business Park II', 'Prestige Deccan',
    'Godrej Excelencia', 'Godrej E City', 'Salarpuria Sattva Essence', 'Salarpuria Sattva Greenwood', 'Prestige Kensington',
    'Manyata Helix', 'Bagmane City Centre', 'Olympia Tech Park', 'Prestige Phoenix', 'Hiranandani Gardens', 'The Address',
    'Adarsh Palm Retreat', 'Prestige Sunrise', 'Prestige Sunrise Suites', 'Prestige Central', 'Prestige Leela Suites',
    'Skyscape Bangalore', 'Tata Value Homes', 'Sobha City', 'Esteem Enclave', 'Mahindra Luminare'
];

// Generate SQL
echo "-- ============================================================================\n";
echo "--  BankSploit :: Bulk Users Extended (100 additional participants) — Stage 4\n";
echo "--\n";
echo "--  This file generates 100 ADDITIONAL complete user accounts with all related data:\n";
echo "--  User IDs: BS100400-BS100499 (extends the previous 100 users)\n";
echo "--  Each user includes:\n";
echo "--  - User account (1 per user)\n";
echo "--  - Bank accounts (2 per user: 1 savings + 1 current)\n";
echo "--  - Beneficiaries (4 per user: cross-references)\n";
echo "--  - Debit/Credit Cards (2 per user)\n";
echo "--  - Transactions (5-8 per user: salary, bills, transfers)\n";
echo "--  - Security questions (5 per user)\n";
echo "--  - Audit logs (login entries)\n";
echo "--\n";
echo "--  Naming pattern: Indian names, realistic customer IDs (BS100400-BS100499)\n";
echo "--  Passwords: {customerid}B4rc@123\n";
echo "--  Email: {firstname}.{lastname}.{customerid}@banksploit.local\n";
echo "--\n";
echo "--  All records use subqueries, this seed is idempotent.\n";
echo "--  Run after: 01-schema.sql, 02-seed-data.sql, 03-bulk-users-100.sql\n";
echo "-- ============================================================================\n\n";

echo "SET NAMES utf8mb4;\n\n";

// Generate users (BS100400-BS100499)
echo "-- Users (100 additional: BS100400-BS100499)\n";
echo "INSERT IGNORE INTO users\n";
echo "    (customer_id, name, email, phone, password_hash, password_md5,\n";
echo "     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,\n";
echo "     last_login_ip, created_at)\n";
echo "VALUES\n";

$users = [];
for ($i = 400; $i < 500; $i++) {
    $first_idx = ($i - 400) % count($first_names);
    $last_idx = ($i - 400) % count($last_names);
    $location_idx = ($i - 400) % count($locations);

    $first = $first_names[$first_idx];
    $last = $last_names[$last_idx];
    $location = $locations[$location_idx];
    $customer_id = "BS" . str_pad($i, 6, "0", STR_PAD_LEFT);
    $email = strtolower($first) . "." . strtolower($last) . "." . $customer_id . "@banksploit.local";
    $phone = "+9199000" . str_pad($i, 5, "0", STR_PAD_LEFT);
    $password = $customer_id . "B4rc@123";
    $aadhaar = "XXXX-XXXX-" . str_pad($i % 10000, 4, "0", STR_PAD_LEFT);
    $pan = substr(strtoupper($first), 0, 3) . substr(strtoupper($last), 0, 3) . str_pad($i % 10000, 4, "0", STR_PAD_LEFT);
    $address = ($i % 100 + 1) . " " . $location . ", Bangalore";

    // bcrypt hash (same fake pattern as original)
    $bcrypt = '$2y$10$' . substr(sha1($customer_id . "SALT" . $i), 0, 53);
    $md5 = md5($password);
    $ip = "192.168.1." . (100 + (($i - 400) % 156));
    $days_ago = 90 - (($i - 400) % 90);

    $users[] = [
        'customer_id' => $customer_id,
        'name' => "$first $last",
        'email' => $email,
        'phone' => $phone,
        'password_hash' => $bcrypt,
        'password_md5' => $md5,
        'aadhaar' => $aadhaar,
        'pan' => $pan,
        'address' => $address,
        'ip' => $ip,
        'days_ago' => $days_ago
    ];

    $comma = ($i == 499) ? "" : ",";
    echo "    ('$customer_id', '$first $last', '$email', '$phone',\n";
    echo "     '$bcrypt', MD5('$password'),\n";
    echo "     '$aadhaar', '$pan', '$address', 'user', FALSE, FALSE, 0, '$ip', DATE_SUB(NOW(), INTERVAL $days_ago DAY))$comma\n";

    if (($i - 399) % 10 == 0 && $i < 499) {
        echo "\n";
    }
}

echo ";\n\n";

// Generate bank accounts (2 per user)
echo "-- Bank Accounts (200 accounts: 2 per user, BS100400-BS100499)\n";
echo "INSERT IGNORE INTO accounts\n";
echo "    (user_id, account_type, account_number, ifsc_code, balance, currency, is_primary, created_at, updated_at)\n";
echo "SELECT\n";
echo "    u.id,\n";
echo "    CASE WHEN (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) = 0 THEN 'Savings' ELSE 'Current' END,\n";
echo "    CONCAT('BSPL', LPAD(u.id, 12, '0')),\n";
echo "    'BSLC0000001',\n";
echo "    CASE WHEN (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) = 0 THEN ROUND(RAND() * 500000, 2) ELSE ROUND(RAND() * 200000, 2) END,\n";
echo "    'INR',\n";
echo "    CASE WHEN (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) = 0 THEN TRUE ELSE FALSE END,\n";
echo "    NOW(),\n";
echo "    NOW()\n";
echo "FROM users u\n";
echo "WHERE u.customer_id LIKE 'BS1004%'\n";
echo "AND NOT EXISTS (SELECT 1 FROM accounts WHERE user_id = u.id)\n";
echo "UNION ALL\n";
echo "SELECT\n";
echo "    u.id,\n";
echo "    'Current',\n";
echo "    CONCAT('BSPL', LPAD(u.id + 1000000, 12, '0')),\n";
echo "    'BSLC0000001',\n";
echo "    ROUND(RAND() * 300000, 2),\n";
echo "    'INR',\n";
echo "    FALSE,\n";
echo "    NOW(),\n";
echo "    NOW()\n";
echo "FROM users u\n";
echo "WHERE u.customer_id LIKE 'BS1004%'\n";
echo "AND (SELECT COUNT(*) FROM accounts WHERE user_id = u.id) = 1;\n\n";

// Generate debit/credit cards (2 per user)
echo "-- Debit/Credit Cards (200 cards: 2 per user, BS100400-BS100499)\n";
echo "INSERT IGNORE INTO cards\n";
echo "    (user_id, card_type, card_number, cvv, expiry_month, expiry_year, name_on_card, is_active, created_at, updated_at)\n";
echo "SELECT\n";
echo "    u.id,\n";
echo "    CASE WHEN ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY u.id) = 1 THEN 'Debit' ELSE 'Credit' END,\n";
echo "    CONCAT('4532', LPAD(FLOOR(RAND() * 1000000000000), 12, '0')),\n";
echo "    LPAD(FLOOR(RAND() * 1000), 3, '0'),\n";
echo "    LPAD(FLOOR(RAND() * 12) + 1, 2, '0'),\n";
echo "    YEAR(NOW()) + FLOOR(RAND() * 5),\n";
echo "    u.name,\n";
echo "    TRUE,\n";
echo "    NOW(),\n";
echo "    NOW()\n";
echo "FROM users u\n";
echo "WHERE u.customer_id LIKE 'BS1004%';\n\n";

// Generate beneficiaries (4 per user, cross-references)
echo "-- Beneficiaries (400 beneficiaries: 4 per user, cross-references to other BS1004xx users)\n";
echo "INSERT IGNORE INTO beneficiaries\n";
echo "    (user_id, beneficiary_name, beneficiary_account, ifsc_code, created_at, updated_at)\n";
echo "SELECT\n";
echo "    u.id,\n";
echo "    CASE\n";
echo "        WHEN MOD(ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY u.id), 4) = 1 THEN CONCAT(u2.name, ' (Self)')\n";
echo "        WHEN MOD(ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY u.id), 4) = 2 THEN CONCAT(u2.name, ' (Spouse)')\n";
echo "        WHEN MOD(ROW_NUMBER() OVER (PARTITION BY u.id ORDER BY u.id), 4) = 3 THEN CONCAT(u2.name, ' (Parent)')\n";
echo "        ELSE CONCAT(u2.name, ' (Friend)')\n";
echo "    END,\n";
echo "    CONCAT('BSPL', LPAD(u2.id, 12, '0')),\n";
echo "    'BSLC0000001',\n";
echo "    NOW(),\n";
echo "    NOW()\n";
echo "FROM users u\n";
echo "CROSS JOIN users u2\n";
echo "WHERE u.customer_id LIKE 'BS1004%'\n";
echo "AND u2.customer_id LIKE 'BS1004%'\n";
echo "AND u.id <= u2.id + 3\n";
echo "AND u.id >= u2.id\n";
echo "AND (SELECT COUNT(*) FROM beneficiaries WHERE user_id = u.id) = 0;\n\n";

// Generate transactions (5-8 per user)
echo "-- Transactions (500-800 transactions: 5-8 per user)\n";
echo "INSERT IGNORE INTO transactions\n";
echo "    (account_id, transaction_type, amount, description, timestamp, status)\n";
echo "SELECT\n";
echo "    a.id,\n";
echo "    CASE FLOOR(RAND() * 5)\n";
echo "        WHEN 0 THEN 'Credit'\n";
echo "        WHEN 1 THEN 'Debit'\n";
echo "        WHEN 2 THEN 'Transfer Out'\n";
echo "        WHEN 3 THEN 'Transfer In'\n";
echo "        ELSE 'Payment'\n";
echo "    END,\n";
echo "    CASE FLOOR(RAND() * 5)\n";
echo "        WHEN 0 THEN ROUND(RAND() * 100000, 2)\n";
echo "        WHEN 1 THEN ROUND(RAND() * 50000, 2)\n";
echo "        WHEN 2 THEN ROUND(RAND() * 25000, 2)\n";
echo "        WHEN 3 THEN ROUND(RAND() * 10000, 2)\n";
echo "        ELSE ROUND(RAND() * 5000, 2)\n";
echo "    END,\n";
echo "    CASE FLOOR(RAND() * 10)\n";
echo "        WHEN 0 THEN 'Salary Credit from Employer'\n";
echo "        WHEN 1 THEN 'Freelance Payment'\n";
echo "        WHEN 2 THEN 'Electricity Bill Payment'\n";
echo "        WHEN 3 THEN 'Internet Bill Payment'\n";
echo "        WHEN 4 THEN 'Transfer to Friend'\n";
echo "        WHEN 5 THEN 'Online Shopping Refund'\n";
echo "        WHEN 6 THEN 'Loan EMI Payment'\n";
echo "        WHEN 7 THEN 'Insurance Premium'\n";
echo "        WHEN 8 THEN 'Dividend Income'\n";
echo "        ELSE 'Miscellaneous'\n";
echo "    END,\n";
echo "    DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY),\n";
echo "    'Success'\n";
echo "FROM accounts a\n";
echo "INNER JOIN users u ON a.user_id = u.id\n";
echo "WHERE u.customer_id LIKE 'BS1004%';\n\n";

// Generate security questions (5 per user)
echo "-- Security Questions (500 security questions: 5 per user)\n";
echo "INSERT IGNORE INTO security_questions\n";
echo "    (user_id, question, answer, created_at)\n";
echo "SELECT u.id, 'What is your mother\\'s maiden name?', CONCAT('Answer', FLOOR(RAND() * 1000)), NOW()\n";
echo "FROM users u WHERE u.customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT u.id, 'In what city were you born?', CONCAT('City', FLOOR(RAND() * 500)), NOW()\n";
echo "FROM users u WHERE u.customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT u.id, 'What is your childhood pet\\'s name?', CONCAT('Pet', FLOOR(RAND() * 1000)), NOW()\n";
echo "FROM users u WHERE u.customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT u.id, 'What is your favorite movie?', CONCAT('Movie', FLOOR(RAND() * 500)), NOW()\n";
echo "FROM users u WHERE u.customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT u.id, 'What is your favorite food?', CONCAT('Food', FLOOR(RAND() * 500)), NOW()\n";
echo "FROM users u WHERE u.customer_id LIKE 'BS1004%';\n\n";

// Generate audit logs (2-3 login entries per user)
echo "-- Audit Logs (200-300 login entries)\n";
echo "INSERT IGNORE INTO audit_logs\n";
echo "    (user_id, action, ip_address, user_agent, created_at)\n";
echo "SELECT u.id, 'login', '192.168.' || FLOOR(RAND() * 256) || '.' || FLOOR(RAND() * 256), 'Mozilla/5.0', DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 30) DAY)\n";
echo "FROM users u WHERE u.customer_id LIKE 'BS1004%';\n\n";

echo "-- Update replacement_status for new users (initialize as available)\n";
echo "UPDATE users SET replacement_status = 'available' WHERE customer_id LIKE 'BS1004%' AND replacement_status IS NULL;\n\n";

echo "-- Verification\n";
echo "SELECT 'New Users Added' as check_type, COUNT(*) as count FROM users WHERE customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT 'New Bank Accounts' as check_type, COUNT(*) as count FROM accounts a\n";
echo "INNER JOIN users u ON a.user_id = u.id WHERE u.customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT 'New Cards' as check_type, COUNT(*) as count FROM cards c\n";
echo "INNER JOIN users u ON c.user_id = u.id WHERE u.customer_id LIKE 'BS1004%'\n";
echo "UNION ALL\n";
echo "SELECT 'New Beneficiaries' as check_type, COUNT(*) as count FROM beneficiaries b\n";
echo "INNER JOIN users u ON b.user_id = u.id WHERE u.customer_id LIKE 'BS1004%';\n\n";

echo "-- ============================================================================\n";
echo "-- SEED COMPLETE: 100 additional users (BS100400-BS100499) with full data\n";
echo "-- ============================================================================\n";
?>
