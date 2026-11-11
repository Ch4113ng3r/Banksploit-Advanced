-- ============================================================================
--  BankSploit :: Bulk Users (100 participants) — Stage 3
--
--  This file generates 100 complete user accounts with all related data:
--  - Users (100)
--  - Accounts per user: 2 per user (1 savings + 1 current)
--  - Beneficiaries: 4 per user (cross-references to other users)
--  - Cards: 2 per user (1 debit + 1 credit)
--  - Transactions: 5-8 per user (salary credits, bill payments, transfers)
--  - Security questions: 5 per user
--  - Audit log: login entries per user
--
--  Naming pattern: Indian names (first, last), realistic customer IDs (BS100300-BS100399).
--  Passwords auto-generated per bulk-import template: {customerid}B4rc@123
--  Email: {firstname}.{lastname}.{customerid}@banksploit.local
--
--  All records use subqueries to fetch user IDs, so this seed is idempotent.
--  Runs after 01-schema.sql and 02-seed-data.sql.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Indian names (100 participants) with realistic data
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
VALUES
    ('BS100300', 'Arjun Verma', 'arjun.verma.BS100300@banksploit.local', '+919900000300',
     '$2y$10$xL3M8nKpQjR2dFgHvN7z4u5B6cDwEfG9hIjKlMnOpQrStUvWxYzAq', MD5('BS100300B4rc@123'),
     'XXXX-XXXX-0300', 'ARPVA1234A', '123 MG Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.100', DATE_SUB(NOW(), INTERVAL 30 DAY)),
    ('BS100301', 'Bhavna Patel', 'bhavna.patel.BS100301@banksploit.local', '+919900000301',
     '$2y$10$yM4N9oLqRsT3eGhIwO8z5v6C7dExFgH0iJkLmNoPqRsUvWxYzAr', MD5('BS100301B4rc@123'),
     'XXXX-XXXX-0301', 'BRPPB1234B', '456 Indiranagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.101', DATE_SUB(NOW(), INTERVAL 29 DAY)),
    ('BS100302', 'Chirag Sharma', 'chirag.sharma.BS100302@banksploit.local', '+919900000302',
     '$2y$10$zN5O0pMrStU4fHiJxP9z6w7D8eF0gHi1jKlMnOpQrStUvWxYzBb', MD5('BS100302B4rc@123'),
     'XXXX-XXXX-0302', 'CHSSC1234C', '789 Koramangala, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.102', DATE_SUB(NOW(), INTERVAL 28 DAY)),
    ('BS100303', 'Divya Nair', 'divya.nair.BS100303@banksploit.local', '+919900000303',
     '$2y$10$aO6P1qNsUvV5gIjKyQ0z7x8E9fG1hIj2kLmNoPqRsStUvWxYzCc', MD5('BS100303B4rc@123'),
     'XXXX-XXXX-0303', 'DIVND1234D', '321 Whitefield, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.103', DATE_SUB(NOW(), INTERVAL 27 DAY)),
    ('BS100304', 'Eshan Gupta', 'eshan.gupta.BS100304@banksploit.local', '+919900000304',
     '$2y$10$bP7Q2rOtWwW6hJkLzR1z8y9F0gH2iIk3lMnOpQrStUvWxYzDd', MD5('BS100304B4rc@123'),
     'XXXX-XXXX-0304', 'ESIGE1234E', '654 Indiranagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.104', DATE_SUB(NOW(), INTERVAL 26 DAY)),
    ('BS100305', 'Falguni Rao', 'falguni.rao.BS100305@banksploit.local', '+919900000305',
     '$2y$10$cQ8R3sPuXxX7iKlMaS2z9z0G1hI3jJl4mNoOpQrStUvWxYzEe', MD5('BS100305B4rc@123'),
     'XXXX-XXXX-0305', 'FALRF1234F', '987 Marathahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.105', DATE_SUB(NOW(), INTERVAL 25 DAY)),
    ('BS100306', 'Gaurav Singh', 'gaurav.singh.BS100306@banksploit.local', '+919900000306',
     '$2y$10$dR9S4tQvYyY8jLmNbT3z0a1H2iJ4kKm5nOpOpQrStUvWxYzFf', MD5('BS100306B4rc@123'),
     'XXXX-XXXX-0306', 'GAUSIG1234G', '111 JP Nagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.106', DATE_SUB(NOW(), INTERVAL 24 DAY)),
    ('BS100307', 'Hina Bhat', 'hina.bhat.BS100307@banksploit.local', '+919900000307',
     '$2y$10$eS0T5uRwZzZ9kMnOcU4z1b2I3jK5lLn6oOpOpQrStUvWxYzGg', MD5('BS100307B4rc@123'),
     'XXXX-XXXX-0307', 'HINBH1234H', '222 Hebbal, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.107', DATE_SUB(NOW(), INTERVAL 23 DAY)),
    ('BS100308', 'Isha Khan', 'isha.khan.BS100308@banksploit.local', '+919900000308',
     '$2y$10$fT1U6vSxAaA0lNoPhV5z2c3J4kL6mMo7pOpOpQrStUvWxYzHh', MD5('BS100308B4rc@123'),
     'XXXX-XXXX-0308', 'ISKAKN1234I', '333 Jayanagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.108', DATE_SUB(NOW(), INTERVAL 22 DAY)),
    ('BS100309', 'Jitendra Jain', 'jitendra.jain.BS100309@banksploit.local', '+919900000309',
     '$2y$10$gU2V7wTyBbB1mOpQiW6z3d4K5lM7nNp8qOpOpQrStUvWxYzIi', MD5('BS100309B4rc@123'),
     'XXXX-XXXX-0309', 'JITJN1234J', '444 Yeshwanthpur, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.109', DATE_SUB(NOW(), INTERVAL 21 DAY)),

    ('BS100310', 'Kavya Desai', 'kavya.desai.BS100310@banksploit.local', '+919900000310',
     '$2y$10$hV3W8xUzCcC2nPrRjX7z4e5L6mN8oOq9rOpOpQrStUvWxYzJj', MD5('BS100310B4rc@123'),
     'XXXX-XXXX-0310', 'KAVDES1234K', '555 Malleswaram, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.110', DATE_SUB(NOW(), INTERVAL 20 DAY)),
    ('BS100311', 'Lokesh Kumar', 'lokesh.kumar.BS100311@banksploit.local', '+919900000311',
     '$2y$10$iW4X9yVaDdD3oQsSkY8z5f6M7nO9pPr0sOpOpQrStUvWxYzKk', MD5('BS100311B4rc@123'),
     'XXXX-XXXX-0311', 'LOKUK1234L', '666 Rajajinagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.111', DATE_SUB(NOW(), INTERVAL 19 DAY)),
    ('BS100312', 'Meera Chopra', 'meera.chopra.BS100312@banksploit.local', '+919900000312',
     '$2y$10$jX5Y0zWeEeE4pRtTlZ9z6g7N8oP0qQs1tOpOpQrStUvWxYzLl', MD5('BS100312B4rc@123'),
     'XXXX-XXXX-0312', 'MERCHP1234M', '777 Basavanagudi, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.112', DATE_SUB(NOW(), INTERVAL 18 DAY)),
    ('BS100313', 'Nikhil Reddy', 'nikhil.reddy.BS100313@banksploit.local', '+919900000313',
     '$2y$10$kY6Z1aXfFfF5qSuUmA0z7h8O9pQ1rRt2uOpOpQrStUvWxYzMm', MD5('BS100313B4rc@123'),
     'XXXX-XXXX-0313', 'NIKRD1234N', '888 Vijayanagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.113', DATE_SUB(NOW(), INTERVAL 17 DAY)),
    ('BS100314', 'Omkar Pillai', 'omkar.pillai.BS100314@banksploit.local', '+919900000314',
     '$2y$10$lZ7A2bYgGgG6rTvVnB1z8i9P0qR2sUu3vOpOpQrStUvWxYzNn', MD5('BS100314B4rc@123'),
     'XXXX-XXXX-0314', 'OMKPIL1234O', '999 KR Puram, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.114', DATE_SUB(NOW(), INTERVAL 16 DAY)),
    ('BS100315', 'Priyanka Singh', 'priyanka.singh.BS100315@banksploit.local', '+919900000315',
     '$2y$10$mA8B3cZhHhH7sUwWoC2z9j0Q1rS3tVv4wOpOpQrStUvWxYzOo', MD5('BS100315B4rc@123'),
     'XXXX-XXXX-0315', 'PRSSG1234P', '1010 Sadashivnagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.115', DATE_SUB(NOW(), INTERVAL 15 DAY)),
    ('BS100316', 'Quincy Thomas', 'quincy.thomas.BS100316@banksploit.local', '+919900000316',
     '$2y$10$nB9C4daIiI8tVxXpD3z0k1R2sT4uWw5xOpOpQrStUvWxYzPp', MD5('BS100316B4rc@123'),
     'XXXX-XXXX-0316', 'QUITH1234Q', '1111 Fort, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.116', DATE_SUB(NOW(), INTERVAL 14 DAY)),
    ('BS100317', 'Rajesh Bhandari', 'rajesh.bhandari.BS100317@banksploit.local', '+919900000317',
     '$2y$10$oC0D5ebJjJ9uWyYqE4z1l2S3tU5vXx6yOpOpQrStUvWxYzQq', MD5('BS100317B4rc@123'),
     'XXXX-XXXX-0317', 'RAJBHN1234R', '1212 Banashankari, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.117', DATE_SUB(NOW(), INTERVAL 13 DAY)),
    ('BS100318', 'Sneha Iyer', 'sneha.iyer.BS100318@banksploit.local', '+919900000318',
     '$2y$10$pD1E6fcKkK0vXzZrF5z2m3T4uV6wYy7zOpOpQrStUvWxYzRr', MD5('BS100318B4rc@123'),
     'XXXX-XXXX-0318', 'SNEIYR1234S', '1313 Ulsoor, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.118', DATE_SUB(NOW(), INTERVAL 12 DAY)),
    ('BS100319', 'Tarun Misra', 'tarun.misra.BS100319@banksploit.local', '+919900000319',
     '$2y$10$qE2F7gdLlL1wAaAsG6z3n4U5vW7xZz8AOpOpOpQrStUvWxYzSs', MD5('BS100319B4rc@123'),
     'XXXX-XXXX-0319', 'TARDMS1234T', '1414 Ramamurthy Nagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.119', DATE_SUB(NOW(), INTERVAL 11 DAY));

-- Continuing from BS100320 to BS100399 (80 more users)
-- Using the same pattern for bcrypt hashes (all salted, realistic)

INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
VALUES
    ('BS100320', 'Uma Sharma', 'uma.sharma.BS100320@banksploit.local', '+919900000320',
     '$2y$10$rF3G8heOmM2xBbBtH7z4o5V6wX8yAa9BOpOpOpQrStUvWxYzTt', MD5('BS100320B4rc@123'),
     'XXXX-XXXX-0320', 'UMASM1234U', '1515 Shivajinagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.120', DATE_SUB(NOW(), INTERVAL 10 DAY)),
    ('BS100321', 'Vikram Chopra', 'vikram.chopra.BS100321@banksploit.local', '+919900000321',
     '$2y$10$sG4H9ifPnN3yCcCuI8z5p6W7xY9zBb0COpOpOpQrStUvWxYzUu', MD5('BS100321B4rc@123'),
     'XXXX-XXXX-0321', 'VIKCHP1234V', '1616 Chikhalthana, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.121', DATE_SUB(NOW(), INTERVAL 9 DAY)),
    ('BS100322', 'Wanda Khan', 'wanda.khan.BS100322@banksploit.local', '+919900000322',
     '$2y$10$tH5I0jgQoO4zDdDvJ9z6q7X8yZ0aCc1DOpOpOpQrStUvWxYzVv', MD5('BS100322B4rc@123'),
     'XXXX-XXXX-0322', 'WANDKN1234W', '1717 Sanjaynagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.122', DATE_SUB(NOW(), INTERVAL 8 DAY)),
    ('BS100323', 'Xenon Patel', 'xenon.patel.BS100323@banksploit.local', '+919900000323',
     '$2y$10$uI6J1khRpP5aEeEwK0z7r8Y9zA1bDd2EOpOpOpQrStUvWxYzWw', MD5('BS100323B4rc@123'),
     'XXXX-XXXX-0323', 'XENPT1234X', '1818 Domlur, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.123', DATE_SUB(NOW(), INTERVAL 7 DAY)),
    ('BS100324', 'Yati Rao', 'yati.rao.BS100324@banksploit.local', '+919900000324',
     '$2y$10$vJ7K2liSqQ6bFfFxL1z8s9Z0aB2cEe3FOpOpOpQrStUvWxYzXx', MD5('BS100324B4rc@123'),
     'XXXX-XXXX-0324', 'YATRAO1234Y', '1919 Cunningham Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.124', DATE_SUB(NOW(), INTERVAL 6 DAY)),
    ('BS100325', 'Zainab Mirza', 'zainab.mirza.BS100325@banksploit.local', '+919900000325',
     '$2y$10$wK8L3mjTrR7cGgGyM2z9t0A1bC3dFf4GOpOpOpQrStUvWxYzYy', MD5('BS100325B4rc@123'),
     'XXXX-XXXX-0325', 'ZAINMRZ1234Z', '2020 Cox Town, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.125', DATE_SUB(NOW(), INTERVAL 5 DAY)),
    ('BS100326', 'Aarav Kumar', 'aarav.kumar.BS100326@banksploit.local', '+919900000326',
     '$2y$10$xL9M4nkUsS8dHhHzN3z0u1B2cD4eGg5HOpOpOpQrStUvWxYzZz', MD5('BS100326B4rc@123'),
     'XXXX-XXXX-0326', 'AARKU1234A2', '2121 Broadway, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.126', DATE_SUB(NOW(), INTERVAL 4 DAY)),
    ('BS100327', 'Bhumi Patel', 'bhumi.patel.BS100327@banksploit.local', '+919900000327',
     '$2y$10$yM0N5oLvTt9eIiIaO4z1v2C3dE5fHh6IOpOpOpQrStUvWxYzAa', MD5('BS100327B4rc@123'),
     'XXXX-XXXX-0327', 'BHUMI1234B2', '2222 Commercial Street, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.127', DATE_SUB(NOW(), INTERVAL 3 DAY)),
    ('BS100328', 'Chetan Singh', 'chetan.singh.BS100328@banksploit.local', '+919900000328',
     '$2y$10$zN1O6pMwUu0fJjJbP5z2w3D4eF6gIi7JOpOpOpQrStUvWxYzBb', MD5('BS100328B4rc@123'),
     'XXXX-XXXX-0328', 'CHETSG1234C2', '2323 Residency Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.128', DATE_SUB(NOW(), INTERVAL 2 DAY)),
    ('BS100329', 'Diya Nair', 'diya.nair.BS100329@banksploit.local', '+919900000329',
     '$2y$10$aO2P7qNxVv1gKkKcQ6z3x4E5fG7hJj8KOpOpOpQrStUvWxYzCc', MD5('BS100329B4rc@123'),
     'XXXX-XXXX-0329', 'DIYAND1234D2', '2424 Infantry Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.129', DATE_SUB(NOW(), INTERVAL 1 DAY));

-- BS100330-BS100399 (70 more users) — continuing pattern
INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
VALUES
    ('BS100330', 'Esha Gupta', 'esha.gupta.BS100330@banksploit.local', '+919900000330',
     '$2y$10$bP3Q8rOyWw2hLlLdR7z4y5F6gH8iKk9LOpOpOpQrStUvWxYzDd', MD5('BS100330B4rc@123'),
     'XXXX-XXXX-0330', 'ESHAGU1234E2', '2525 Brigade Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.130', NOW()),
    ('BS100331', 'Farah Khan', 'farah.khan.BS100331@banksploit.local', '+919900000331',
     '$2y$10$cQ4R9sPzXx3iMmMeS8z5z6G7hI9jLl0MOpOpOpQrStUvWxYzEe', MD5('BS100331B4rc@123'),
     'XXXX-XXXX-0331', 'FARAHKN1234F2', '2626 Palace Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.131', NOW()),
    ('BS100332', 'Gaurav Tiwari', 'gaurav.tiwari.BS100332@banksploit.local', '+919900000332',
     '$2y$10$dR5S0tQaYy4jNnNfT9z6a7H8iJ0kMm1NOpOpOpQrStUvWxYzFf', MD5('BS100332B4rc@123'),
     'XXXX-XXXX-0332', 'GAUTW1234G2', '2727 St Marks Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.132', NOW()),
    ('BS100333', 'Hema Rao', 'hema.rao.BS100333@banksploit.local', '+919900000333',
     '$2y$10$eS6T1uRbZz5kOoOgU0z7b8I9jK1lNn2OOpOpOpQrStUvWxYzGg', MD5('BS100333B4rc@123'),
     'XXXX-XXXX-0333', 'HEMRAO1234H2', '2828 Blossoms, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.133', NOW()),
    ('BS100334', 'Ishaan Desai', 'ishaan.desai.BS100334@banksploit.local', '+919900000334',
     '$2y$10$fT7U2vScAa6lPpPhV1z8c9J0kL2mOo3POpOpOpQrStUvWxYzHh', MD5('BS100334B4rc@123'),
     'XXXX-XXXX-0334', 'ISHDES1234I2', '2929 Ulsoor Lake, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.134', NOW()),
    ('BS100335', 'Jyoti Singh', 'jyoti.singh.BS100335@banksploit.local', '+919900000335',
     '$2y$10$gU8V3wTdBb7mQqQiW2z9d0K1lM3nPp4QOpOpOpQrStUvWxYzIi', MD5('BS100335B4rc@123'),
     'XXXX-XXXX-0335', 'JYOTSG1234J2', '3030 Ulsoor South, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.135', NOW()),
    ('BS100336', 'Karan Malhotra', 'karan.malhotra.BS100336@banksploit.local', '+919900000336',
     '$2y$10$hV9W4xUeCc8nRrRjX3z0e1L2mM4oQq5ROpOpOpQrStUvWxYzJj', MD5('BS100336B4rc@123'),
     'XXXX-XXXX-0336', 'KARAMH1234K2', '3131 Tank Bund Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.136', NOW()),
    ('BS100337', 'Leela Menon', 'leela.menon.BS100337@banksploit.local', '+919900000337',
     '$2y$10$iW0X5yVfDd9oSsSkY4z1f2M3nN5pRr6SOpOpOpQrStUvWxYzKk', MD5('BS100337B4rc@123'),
     'XXXX-XXXX-0337', 'LEELMN1234L2', '3232 Oxford School Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.137', NOW()),
    ('BS100338', 'Manish Bhat', 'manish.bhat.BS100338@banksploit.local', '+919900000338',
     '$2y$10$jX1Y6zWeEe0pTtTlZ5z2g3N4oO6qSs7TOpOpOpQrStUvWxYzLl', MD5('BS100338B4rc@123'),
     'XXXX-XXXX-0338', 'MANIBH1234M2', '3333 Whitefield Main Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.138', NOW()),
    ('BS100339', 'Nisha Verma', 'nisha.verma.BS100339@banksploit.local', '+919900000339',
     '$2y$10$kY2Z7aXfFf1qUuUmA6z3h4O5pP7rTt8UOpOpOpQrStUvWxYzMm', MD5('BS100339B4rc@123'),
     'XXXX-XXXX-0339', 'NISHVM1234N2', '3434 ITPL Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.139', NOW()),

    ('BS100340', 'Omkar Singh', 'omkar.singh.BS100340@banksploit.local', '+919900000340',
     '$2y$10$lZ3A8bYgGg2rVvVnB7z4i5P6qQ8sUu9VOpOpOpQrStUvWxYzNn', MD5('BS100340B4rc@123'),
     'XXXX-XXXX-0340', 'OMKSG1234O2', '3535 Devarabisanahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.140', NOW()),
    ('BS100341', 'Priya Chopra', 'priya.chopra.BS100341@banksploit.local', '+919900000341',
     '$2y$10$mA4B9cZhHh3sWwWoC8z5j6Q7rR9tVv0WOpOpOpQrStUvWxYzOo', MD5('BS100341B4rc@123'),
     'XXXX-XXXX-0341', 'PRYACHP1234P2', '3636 Varthur Main Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.141', NOW()),
    ('BS100342', 'Quentin Patel', 'quentin.patel.BS100342@banksploit.local', '+919900000342',
     '$2y$10$nB5C0daIiI4tXxXpD9z6k7R8sS0uWw1XOpOpOpQrStUvWxYzPp', MD5('BS100342B4rc@123'),
     'XXXX-XXXX-0342', 'QUETPT1234Q2', '3737 Sarjapur Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.142', NOW()),
    ('BS100343', 'Rohan Joshi', 'rohan.joshi.BS100343@banksploit.local', '+919900000343',
     '$2y$10$oC6D1ebJjJ5uYyYqE0z7l8S9tT1vXx2YOpOpOpQrStUvWxYzQq', MD5('BS100343B4rc@123'),
     'XXXX-XXXX-0343', 'ROHJOS1234R2', '3838 Marathahalli Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.143', NOW()),
    ('BS100344', 'Shweta Bhandari', 'shweta.bhandari.BS100344@banksploit.local', '+919900000344',
     '$2y$10$pD7E2fcKkK6vZzZrF1z8m9T0uU2wYy3ZOpOpOpQrStUvWxYzRr', MD5('BS100344B4rc@123'),
     'XXXX-XXXX-0344', 'SHWBHN1234S2', '3939 Sarjapur Outer Ring Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.144', NOW()),
    ('BS100345', 'Tarun Kumar', 'tarun.kumar.BS100345@banksploit.local', '+919900000345',
     '$2y$10$qE8F3gdLlL7wAaAsG2z9n0U1vV3xZz0AOpOpOpQrStUvWxYzSs', MD5('BS100345B4rc@123'),
     'XXXX-XXXX-0345', 'TARUK1234T2', '4040 Bellandur, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.145', NOW()),
    ('BS100346', 'Usha Patel', 'usha.patel.BS100346@banksploit.local', '+919900000346',
     '$2y$10$rF9G4heOmM8xBbBtH3z0o1V2wW4yAa1BOpOpOpQrStUvWxYzTt', MD5('BS100346B4rc@123'),
     'XXXX-XXXX-0346', 'USHPT1234U2', '4141 Bellandur Main Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.146', NOW()),
    ('BS100347', 'Varun Malhi', 'varun.malhi.BS100347@banksploit.local', '+919900000347',
     '$2y$10$sG0H5ifPnN9yCcCuI4z1p2W3xX5zBb2COpOpOpQrStUvWxYzUu', MD5('BS100347B4rc@123'),
     'XXXX-XXXX-0347', 'VARUMLH1234V2', '4242 Sungate South, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.147', NOW()),
    ('BS100348', 'Wanda Desai', 'wanda.desai.BS100348@banksploit.local', '+919900000348',
     '$2y$10$tH1I6jgQoO0zDdDvJ5z2q3X4yY6aCc3DOpOpOpQrStUvWxYzVv', MD5('BS100348B4rc@123'),
     'XXXX-XXXX-0348', 'WANDES1234W2', '4343 Ispat Township, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.148', NOW()),
    ('BS100349', 'Xenophon Pillai', 'xenophon.pillai.BS100349@banksploit.local', '+919900000349',
     '$2y$10$uI2J7khRpP1aEeEwK6z3r4Y5zZ7bDd4EOpOpOpQrStUvWxYzWw', MD5('BS100349B4rc@123'),
     'XXXX-XXXX-0349', 'XENPIL1234X2', '4444 Outer Ring Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.149', NOW()),

    ('BS100350', 'Yara Khan', 'yara.khan.BS100350@banksploit.local', '+919900000350',
     '$2y$10$vJ3K8liSqQ2bFfFxL7z4s5Z6aA8cEe5FOpOpOpQrStUvWxYzXx', MD5('BS100350B4rc@123'),
     'XXXX-XXXX-0350', 'YARAKN1234Y2', '4545 Kundalahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.150', NOW());

-- Continue with BS100351-BS100399 (49 more users)
-- Pattern: generate names, IDs, and hashes incrementally

INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
VALUES
    ('BS100351', 'Zara Sharma', 'zara.sharma.BS100351@banksploit.local', '+919900000351',
     '$2y$10$wK4L9mjTrR3cGgGyM8z5t6A7bB9dFf6GOpOpOpQrStUvWxYzYy', MD5('BS100351B4rc@123'),
     'XXXX-XXXX-0351', 'ZARASM1234Z2', '4646 Carmelaram, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.151', NOW()),
    ('BS100352', 'Abhinav Rao', 'abhinav.rao.BS100352@banksploit.local', '+919900000352',
     '$2y$10$xL5M0nkUsS4dHhHzN9z6u7B8cC0eGg7HOpOpOpQrStUvWxYzZz', MD5('BS100352B4rc@123'),
     'XXXX-XXXX-0352', 'ABHRAO1234A3', '4747 Whitespace, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.152', NOW()),
    ('BS100353', 'Anjali Singh', 'anjali.singh.BS100353@banksploit.local', '+919900000353',
     '$2y$10$yM6N1oLvTt5eIiIaO0z7v8C9dD1fHh8IOpOpOpQrStUvWxYzAa', MD5('BS100353B4rc@123'),
     'XXXX-XXXX-0353', 'ANJSG1234B3', '4848 Cessna Business Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.153', NOW()),
    ('BS100354', 'Aryan Verma', 'aryan.verma.BS100354@banksploit.local', '+919900000354',
     '$2y$10$zN7O2pMwUu6fJjJbP1z8w9D0eE2gIi9JOpOpOpQrStUvWxYzBb', MD5('BS100354B4rc@123'),
     'XXXX-XXXX-0354', 'ARYVM1234C3', '4949 Infopark IT Hub, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.154', NOW()),
    ('BS100355', 'Bhavesh Patel', 'bhavesh.patel.BS100355@banksploit.local', '+919900000355',
     '$2y$10$aO8P3qNxVv7gKkKcQ2z9x0E1fF3hJj0KOpOpOpQrStUvWxYzCc', MD5('BS100355B4rc@123'),
     'XXXX-XXXX-0355', 'BHAVPT1234D3', '5050 Bagmane Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.155', NOW()),
    ('BS100356', 'Chavi Desai', 'chavi.desai.BS100356@banksploit.local', '+919900000356',
     '$2y$10$bP9Q4rOyWw8hLlLdR3z0y1F2gG4iKk1LOpOpOpQrStUvWxYzDd', MD5('BS100356B4rc@123'),
     'XXXX-XXXX-0356', 'CHAVDES1234E3', '5151 Embassy MANYATA, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.156', NOW()),
    ('BS100357', 'Deepak Kumar', 'deepak.kumar.BS100357@banksploit.local', '+919900000357',
     '$2y$10$cQ0R5sPzXx9iMmMeS4z1z2G3hH5jLl2MOpOpOpQrStUvWxYzEe', MD5('BS100357B4rc@123'),
     'XXXX-XXXX-0357', 'DEEPUK1234F3', '5252 Prestige Ozone, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.157', NOW()),
    ('BS100358', 'Deepika Iyer', 'deepika.iyer.BS100358@banksploit.local', '+919900000358',
     '$2y$10$dR1S6tQaYy0jNnNfT5z2a3H4iI6kMm3NOpOpOpQrStUvWxYzFf', MD5('BS100358B4rc@123'),
     'XXXX-XXXX-0358', 'DEEPIYR1234G3', '5353 Prestige Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.158', NOW()),
    ('BS100359', 'Disha Bhat', 'disha.bhat.BS100359@banksploit.local', '+919900000359',
     '$2y$10$eS2T7uRbZz1kOoOgU6z3b4I5jJ7lNn4OOpOpOpQrStUvWxYzGg', MD5('BS100359B4rc@123'),
     'XXXX-XXXX-0359', 'DISABH1234H3', '5454 Nexus Bengaluru, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.159', NOW()),
    ('BS100360', 'Eshita Chopra', 'eshita.chopra.BS100360@banksploit.local', '+919900000360',
     '$2y$10$fT3U8vScAa2lPpPhV7z4c5J6kK8mOo5POpOpOpQrStUvWxYzHh', MD5('BS100360B4rc@123'),
     'XXXX-XXXX-0360', 'ESHCHP1234I3', '5555 Kalyani Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.160', NOW()),

    ('BS100361', 'Farida Khan', 'farida.khan.BS100361@banksploit.local', '+919900000361',
     '$2y$10$gU4V9wTdBb3mQqQiW8z5d6K7lL9nPp6QOpOpOpQrStUvWxYzIi', MD5('BS100361B4rc@123'),
     'XXXX-XXXX-0361', 'FARIDKN1234J3', '5656 Marathon Infinity, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.161', NOW()),
    ('BS100362', 'Garima Singh', 'garima.singh.BS100362@banksploit.local', '+919900000362',
     '$2y$10$hV5W0xUeCc4nRrRjX9z6e7L8mM0oQq7ROpOpOpQrStUvWxYzJj', MD5('BS100362B4rc@123'),
     'XXXX-XXXX-0362', 'GARISG1234K3', '5757 Salarpuria Greenage, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.162', NOW()),
    ('BS100363', 'Harsh Malhotra', 'harsh.malhotra.BS100363@banksploit.local', '+919900000363',
     '$2y$10$iW6X1yVfDd5oSsSkY0z7f8M9nN1pRr8SOpOpOpQrStUvWxYzKk', MD5('BS100363B4rc@123'),
     'XXXX-XXXX-0363', 'HARSHMH1234L3', '5858 Tech Park Bengaluru, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.163', NOW()),
    ('BS100364', 'Harsh Reddy', 'harsh.reddy.BS100364@banksploit.local', '+919900000364',
     '$2y$10$jX7Y2zWeEe6pTtTlZ1z8g9N0oO2qSs9TOpOpOpQrStUvWxYzLl', MD5('BS100364B4rc@123'),
     'XXXX-XXXX-0364', 'HARSRD1234M3', '5959 Manyata Espire, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.164', NOW()),
    ('BS100365', 'Hema Singh', 'hema.singh.BS100365@banksploit.local', '+919900000365',
     '$2y$10$kY8Z3aXfFf7qUuUmA2z9h0O1pP3rTt0UOpOpOpQrStUvWxYzMm', MD5('BS100365B4rc@123'),
     'XXXX-XXXX-0365', 'HEMSG1234N3', '6060 Cessna Business Park II, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.165', NOW()),
    ('BS100366', 'Hrithik Sharma', 'hrithik.sharma.BS100366@banksploit.local', '+919900000366',
     '$2y$10$lZ9A4bYgGg8rVvVnB3z0i1P2qQ4sUu1VOpOpOpQrStUvWxYzNn', MD5('BS100366B4rc@123'),
     'XXXX-XXXX-0366', 'HRITSM1234O3', '6161 Prestige Deccan, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.166', NOW()),
    ('BS100367', 'Isha Kapoor', 'isha.kapoor.BS100367@banksploit.local', '+919900000367',
     '$2y$10$mA0B5cZhHh9sWwWoC4z1j2Q3rR5tVv2WOpOpOpQrStUvWxYzOo', MD5('BS100367B4rc@123'),
     'XXXX-XXXX-0367', 'ISHAKP1234P3', '6262 Godrej Excelencia, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.167', NOW()),
    ('BS100368', 'Ishita Bansal', 'ishita.bansal.BS100368@banksploit.local', '+919900000368',
     '$2y$10$nB1C6daIiI0tXxXpD5z2k3R4sS6uWw3XOpOpOpQrStUvWxYzPp', MD5('BS100368B4rc@123'),
     'XXXX-XXXX-0368', 'ISHTBN1234Q3', '6363 Godrej E City, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.168', NOW()),
    ('BS100369', 'Ishwar Singh', 'ishwar.singh.BS100369@banksploit.local', '+919900000369',
     '$2y$10$oC2D7ebJjJ1uYyYqE6z3l4S5tT7vXx4YOpOpOpQrStUvWxYzQq', MD5('BS100369B4rc@123'),
     'XXXX-XXXX-0369', 'ISHWSG1234R3', '6464 Salarpuria Sattva Essence, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.169', NOW()),
    ('BS100370', 'Javed Ahmed', 'javed.ahmed.BS100370@banksploit.local', '+919900000370',
     '$2y$10$pD3E8fcKkK2vZzZrF7z4m5T6uU8wYy5ZOpOpOpQrStUvWxYzRr', MD5('BS100370B4rc@123'),
     'XXXX-XXXX-0370', 'JAVAHD1234S3', '6565 Salarpuria Sattva Greenwood, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.170', NOW()),

    ('BS100371', 'Jaya Menon', 'jaya.menon.BS100371@banksploit.local', '+919900000371',
     '$2y$10$qE4F9gdLlL3wAaAsG8z5n6U7vV9xZz6AOpOpOpQrStUvWxYzSs', MD5('BS100371B4rc@123'),
     'XXXX-XXXX-0371', 'JAYAMN1234T3', '6666 Prestige Kensington, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.171', NOW()),
    ('BS100372', 'Joydeep Sengupta', 'joydeep.sengupta.BS100372@banksploit.local', '+919900000372',
     '$2y$10$rF5G0heOmM4xBbBtH9z6o7V8wW0yAa7BOpOpOpQrStUvWxYzTt', MD5('BS100372B4rc@123'),
     'XXXX-XXXX-0372', 'JOYSG1234U3', '6767 Manyata HELIX, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.172', NOW()),
    ('BS100373', 'Jyotsna Rao', 'jyotsna.rao.BS100373@banksploit.local', '+919900000373',
     '$2y$10$sG6H1ifPnN5yCcCuI0z7p8W9xX1zBb8COpOpOpQrStUvWxYzUu', MD5('BS100373B4rc@123'),
     'XXXX-XXXX-0373', 'JYTAO1234V3', '6868 Bagmane City Centre, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.173', NOW()),
    ('BS100374', 'Kamal Verma', 'kamal.verma.BS100374@banksploit.local', '+919900000374',
     '$2y$10$tH7I2jgQoO6zDdDvJ1z8q9X0yY2aCc9DOpOpOpQrStUvWxYzVv', MD5('BS100374B4rc@123'),
     'XXXX-XXXX-0374', 'KAMVM1234W3', '6969 Olympia Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.174', NOW()),
    ('BS100375', 'Kanishk Joshi', 'kanishk.joshi.BS100375@banksploit.local', '+919900000375',
     '$2y$10$uI8J3khRpP7aEeEwK2z9r0Y1zZ3bDd0EOpOpOpQrStUvWxYzWw', MD5('BS100375B4rc@123'),
     'XXXX-XXXX-0375', 'KANJOS1234X3', '7070 Prestige Phoenix, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.175', NOW()),
    ('BS100376', 'Kapil Desai', 'kapil.desai.BS100376@banksploit.local', '+919900000376',
     '$2y$10$vJ9K4liSqQ8bFfFxL3z0s1Z2aA4cEe1FOpOpOpQrStUvWxYzXx', MD5('BS100376B4rc@123'),
     'XXXX-XXXX-0376', 'KAPDES1234Y3', '7171 Hiranandani Gardens, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.176', NOW()),
    ('BS100377', 'Karan Chopra', 'karan.chopra.BS100377@banksploit.local', '+919900000377',
     '$2y$10$wK0L5mjTrR9cGgGyM4z1t2A3bB5dFf2GOpOpOpQrStUvWxYzYy', MD5('BS100377B4rc@123'),
     'XXXX-XXXX-0377', 'KARCHP1234Z3', '7272 The Address, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.177', NOW()),
    ('BS100378', 'Kartik Singh', 'kartik.singh.BS100378@banksploit.local', '+919900000378',
     '$2y$10$xL1M6nkUsS0dHhHzN5z2u3B4cC6eGg3HOpOpOpQrStUvWxYzZz', MD5('BS100378B4rc@123'),
     'XXXX-XXXX-0378', 'KARTSG1234A4', '7373 Adarsh Palm Retreat, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.178', NOW()),
    ('BS100379', 'Kavi Menon', 'kavi.menon.BS100379@banksploit.local', '+919900000379',
     '$2y$10$yM2N7oLvTt1eIiIaO6z3v4C5dD7fHh4IOpOpOpQrStUvWxYzAa', MD5('BS100379B4rc@123'),
     'XXXX-XXXX-0379', 'KAVMN1234B4', '7474 Prestige Sunrise, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.179', NOW()),
    ('BS100380', 'Kavya Sharma', 'kavya.sharma.BS100380@banksploit.local', '+919900000380',
     '$2y$10$zN3O8pMwUu2fJjJbP7z4w5D6eE8gIi5JOpOpOpQrStUvWxYzBb', MD5('BS100380B4rc@123'),
     'XXXX-XXXX-0380', 'KAVSM1234C4', '7575 Prestige Sunrise Suites, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.180', NOW()),

    ('BS100381', 'Kaylee Patel', 'kaylee.patel.BS100381@banksploit.local', '+919900000381',
     '$2y$10$aO4P9qNxVv3gKkKcQ8z5x6E7fF9hJj6KOpOpOpQrStUvWxYzCc', MD5('BS100381B4rc@123'),
     'XXXX-XXXX-0381', 'KAYPT1234D4', '7676 Prestige Central, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.181', NOW()),
    ('BS100382', 'Keira Iyer', 'keira.iyer.BS100382@banksploit.local', '+919900000382',
     '$2y$10$bP5Q0rOyWw4hLlLdR9z6y7F8gG0iKk7LOpOpOpQrStUvWxYzDd', MD5('BS100382B4rc@123'),
     'XXXX-XXXX-0382', 'KEIIYR1234E4', '7777 Prestige Leela Suites, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.182', NOW()),
    ('BS100383', 'Kelvin Nair', 'kelvin.nair.BS100383@banksploit.local', '+919900000383',
     '$2y$10$cQ6R1sPzXx5iMmMeS0z7z8G9hH1jLl8MOpOpOpQrStUvWxYzEe', MD5('BS100383B4rc@123'),
     'XXXX-XXXX-0383', 'KELNR1234F4', '7878 Skyscape Bangalore, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.183', NOW()),
    ('BS100384', 'Kendall Bhat', 'kendall.bhat.BS100384@banksploit.local', '+919900000384',
     '$2y$10$dR7S2tQaYy6jNnNfT1z8a9H0iI2kMm9NOpOpOpQrStUvWxYzFf', MD5('BS100384B4rc@123'),
     'XXXX-XXXX-0384', 'KENBH1234G4', '7979 Tata Value Homes, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.184', NOW()),
    ('BS100385', 'Kendrick Rao', 'kendrick.rao.BS100385@banksploit.local', '+919900000385',
     '$2y$10$eS8T3uRbZz7kOoOgU2z9b0I1jJ3lNn0OOpOpOpQrStUvWxYzGg', MD5('BS100385B4rc@123'),
     'XXXX-XXXX-0385', 'KENDRD1234H4', '8080 Sobha City, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.185', NOW()),
    ('BS100386', 'Kennedy Singh', 'kennedy.singh.BS100386@banksploit.local', '+919900000386',
     '$2y$10$fT9U4vScAa8lPpPhV3z0c1J2kK4mOo1POpOpOpQrStUvWxYzHh', MD5('BS100386B4rc@123'),
     'XXXX-XXXX-0386', 'KENSG1234I4', '8181 Esteem Enclave, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.186', NOW()),
    ('BS100387', 'Kenzo Khan', 'kenzo.khan.BS100387@banksploit.local', '+919900000387',
     '$2y$10$gU0V5wTdBb9mQqQiW4z1d2K3lL5nPp2QOpOpOpQrStUvWxYzIi', MD5('BS100387B4rc@123'),
     'XXXX-XXXX-0387', 'KENZN1234J4', '8282 Mahindra Luminare, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.187', NOW()),
    ('BS100388', 'Kerry Verma', 'kerry.verma.BS100388@banksploit.local', '+919900000388',
     '$2y$10$hV1W6xUeCc0nRrRjX5z2e3L4mM6oQq3ROpOpOpQrStUvWxYzJj', MD5('BS100388B4rc@123'),
     'XXXX-XXXX-0388', 'KERVM1234K4', '8383 Prestige Gardenias, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.188', NOW()),
    ('BS100389', 'Keshav Desai', 'keshav.desai.BS100389@banksploit.local', '+919900000389',
     '$2y$10$iW2X7yVfDd1oSsSkY6z3f4M5nN7pRr4SOpOpOpQrStUvWxYzKk', MD5('BS100389B4rc@123'),
     'XXXX-XXXX-0389', 'KESDES1234L4', '8484 Prestige Gardenias II, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.189', NOW()),
    ('BS100390', 'Kevin Chopra', 'kevin.chopra.BS100390@banksploit.local', '+919900000390',
     '$2y$10$jX3Y8zWeEe2pTtTlZ7z4g5N6oO8qSs5TOpOpOpQrStUvWxYzLl', MD5('BS100390B4rc@123'),
     'XXXX-XXXX-0390', 'KEVCHP1234M4', '8585 Prestige Green Oasis, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.190', NOW()),

    ('BS100391', 'Keyaan Bhat', 'keyaan.bhat.BS100391@banksploit.local', '+919900000391',
     '$2y$10$kY4Z9aXfFf3qUuUmA8z5h6O7pP9rTt6UOpOpOpQrStUvWxYzMm', MD5('BS100391B4rc@123'),
     'XXXX-XXXX-0391', 'KEYBH1234N4', '8686 Prestige Song of Life, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.191', NOW()),
    ('BS100392', 'Khyati Singh', 'khyati.singh.BS100392@banksploit.local', '+919900000392',
     '$2y$10$lZ5A0bYgGg4rVvVnB9z6i7P8qQ0sUu7VOpOpOpQrStUvWxYzNn', MD5('BS100392B4rc@123'),
     'XXXX-XXXX-0392', 'KHYSG1234O4', '8787 Prestige Oasis, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.192', NOW()),
    ('BS100393', 'Kia Khan', 'kia.khan.BS100393@banksploit.local', '+919900000393',
     '$2y$10$mA6B1cZhHh5sWwWoC0z7j8Q9rR1tVv8WOpOpOpQrStUvWxYzOo', MD5('BS100393B4rc@123'),
     'XXXX-XXXX-0393', 'KIAKN1234P4', '8888 Prestige Raintree Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.193', NOW()),
    ('BS100394', 'Kimaya Patel', 'kimaya.patel.BS100394@banksploit.local', '+919900000394',
     '$2y$10$nB7C2daIiI6tXxXpD1z8k9R0sS2uWw9XOpOpOpQrStUvWxYzPp', MD5('BS100394B4rc@123'),
     'XXXX-XXXX-0394', 'KIMPT1234Q4', '8989 Concorde Exotica, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.194', NOW()),
    ('BS100395', 'Kinal Iyer', 'kinal.iyer.BS100395@banksploit.local', '+919900000395',
     '$2y$10$oC8D3ebJjJ7uYyYqE2z9l0S1tT3vXx0YOpOpOpQrStUvWxYzQq', MD5('BS100395B4rc@123'),
     'XXXX-XXXX-0395', 'KINIYR1234R4', '9090 Concorde Spring Meadows, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.195', NOW()),
    ('BS100396', 'Kiram Menon', 'kiram.menon.BS100396@banksploit.local', '+919900000396',
     '$2y$10$pD9E4fcKkK8vZzZrF3z0m1T2uU4wYy1ZOpOpOpQrStUvWxYzRr', MD5('BS100396B4rc@123'),
     'XXXX-XXXX-0396', 'KIRMEN1234S4', '9191 Concorde Marathahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.196', NOW()),
    ('BS100397', 'Kira Verma', 'kira.verma.BS100397@banksploit.local', '+919900000397',
     '$2y$10$qE0F5gdLlL9wAaAsG4z1n2U3vV5xZz2AOpOpOpQrStUvWxYzSs', MD5('BS100397B4rc@123'),
     'XXXX-XXXX-0397', 'KIRVM1234T4', '9292 Prestige Primrose, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.197', NOW()),
    ('BS100398', 'Kiri Desai', 'kiri.desai.BS100398@banksploit.local', '+919900000398',
     '$2y$10$rF1G6heOmM0xBbBtH5z2o3V4wW6yAa3BOpOpOpQrStUvWxYzTt', MD5('BS100398B4rc@123'),
     'XXXX-XXXX-0398', 'KIRDES1234U4', '9393 Prestige Mulberry, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.198', NOW()),
    ('BS100399', 'Kishan Sharma', 'kishan.sharma.BS100399@banksploit.local', '+919900000399',
     '$2y$10$sG2H7ifPnN1yCcCuI6z1p2W3xX7zBb4COpOpOpQrStUvWxYzUu', MD5('BS100399B4rc@123'),
     'XXXX-XXXX-0399', 'KISHSM1234V4', '9494 Prestige Sunrise, Bangalore', 'user', FALSE, FALSE, 0, '192.168.1.199', NOW());

-- ---------------------------------------------------------------------------
-- Accounts: 2 per user (1 savings + 1 current)
--   Account number pattern:
--     Savings: 5010010000 + last 4 digits of customer_id (e.g. BS100300 -> 50100100000300)
--     Current: 5020010000 + last 4 digits of customer_id (e.g. BS100300 -> 50200100000300)
--   This makes account numbers PREDICTABLE for beneficiary cross-references.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO accounts (user_id, account_number, account_type, balance, daily_limit, is_active)
SELECT u.id,
       CONCAT('5010010000', SUBSTRING(u.customer_id, 5)),
       'savings',
       ROUND(100000 + (CONV(SUBSTRING(MD5(u.customer_id), 1, 8), 16, 10) % 900000), 2),
       200000.00,
       TRUE
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

INSERT IGNORE INTO accounts (user_id, account_number, account_type, balance, daily_limit, is_active)
SELECT u.id,
       CONCAT('5020010000', SUBSTRING(u.customer_id, 5)),
       'current',
       ROUND(50000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'cur')), 1, 8), 16, 10) % 450000), 2),
       500000.00,
       TRUE
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ---------------------------------------------------------------------------
-- Beneficiaries: 3 per user (cross-references to OTHER bulk users' accounts)
--   Each user gets beneficiaries pointing to the NEXT 3 users' real accounts.
--   This enables IDOR attacks: participants can see other users' account numbers.
--   Beneficiary names are the actual names of the target users.
--   One beneficiary per user is still in cooling period (for bypass testing).
-- ---------------------------------------------------------------------------

-- Beneficiary 1: points to the next user's savings account (verified)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       (SELECT name FROM users WHERE customer_id = CONCAT('BS', LPAD(CAST(SUBSTRING(u.customer_id, 3) AS UNSIGNED) + 1, 6, '0'))),
       CONCAT('5010010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 1, 4, '0')),
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'SBIN0001234'
           WHEN 1 THEN 'HDFC0000456'
           WHEN 2 THEN 'ICIC0001789'
           ELSE 'UTIB0002345'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'State Bank of India'
           WHEN 1 THEN 'HDFC Bank'
           WHEN 2 THEN 'ICICI Bank'
           ELSE 'Axis Bank'
       END,
       TRUE,
       NULL
FROM users u
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100398';

-- Beneficiary 2: points to user+2's current account (verified)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       (SELECT name FROM users WHERE customer_id = CONCAT('BS', LPAD(CAST(SUBSTRING(u.customer_id, 3) AS UNSIGNED) + 2, 6, '0'))),
       CONCAT('5020010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 2, 4, '0')),
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 3)
           WHEN 0 THEN 'HDFC0000789'
           WHEN 1 THEN 'SBIN0005678'
           ELSE 'ICIC0003456'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 3)
           WHEN 0 THEN 'HDFC Bank'
           WHEN 1 THEN 'State Bank of India'
           ELSE 'ICICI Bank'
       END,
       TRUE,
       NULL
FROM users u
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100397';

-- Beneficiary 3: points to user+3's savings account (IN COOLING PERIOD — bypass target)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       (SELECT name FROM users WHERE customer_id = CONCAT('BS', LPAD(CAST(SUBSTRING(u.customer_id, 3) AS UNSIGNED) + 3, 6, '0'))),
       CONCAT('5010010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 3, 4, '0')),
       'UTIB0004567',
       'Axis Bank',
       TRUE,
       DATE_ADD(NOW(), INTERVAL 2 DAY)
FROM users u
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100396';

-- Beneficiary 4: points to one of the base seed users (Rahul/Priya/Vikram/Aditya)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'Rahul Sharma'
           WHEN 1 THEN 'Priya Menon'
           WHEN 2 THEN 'Vikram Singh'
           ELSE 'Aditya Rao'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN '50100100000001'
           WHEN 1 THEN '50100100000002'
           WHEN 2 THEN '50100100000003'
           ELSE '50100100000004'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'SBIN0001234'
           WHEN 1 THEN 'HDFC0000456'
           WHEN 2 THEN 'HDFC0000789'
           ELSE 'ICIC0001789'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'State Bank of India'
           WHEN 1 THEN 'HDFC Bank'
           WHEN 2 THEN 'HDFC Bank'
           ELSE 'ICICI Bank'
       END,
       TRUE,
       NULL
FROM users u
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ---------------------------------------------------------------------------
-- Cards: 2 per user (1 debit + 1 credit)
--   Card numbers are deterministic (based on customer_id) so they're predictable
--   CVVs are 3-digit values derived from MD5 hash
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cards (user_id, card_number_encrypted, card_type, cvv_encrypted, expiry_date, spending_limit, is_blocked, pin_hash)
SELECT u.id,
       CONCAT('4123', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 5600, 12, '0')),
       'debit',
       LPAD((CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'cvv1')), 1, 6), 16, 10) % 900) + 100, 3, '0'),
       '08/28',
       100000.00,
       FALSE,
       MD5(CONCAT(u.customer_id, 'pin1'))
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

INSERT IGNORE INTO cards (user_id, card_number_encrypted, card_type, cvv_encrypted, expiry_date, spending_limit, is_blocked, pin_hash)
SELECT u.id,
       CONCAT('5234', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 1100, 12, '0')),
       'credit',
       LPAD((CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'cvv2')), 1, 6), 16, 10) % 900) + 100, 3, '0'),
       '11/27',
       300000.00,
       FALSE,
       MD5(CONCAT(u.customer_id, 'pin2'))
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ---------------------------------------------------------------------------
-- Transactions: multiple per user
--   1. Salary credit (NEFT) to savings — 3 months
--   2. Bill payment (IMPS) from savings
--   3. Transfer to another user (IMPS) with OTP
-- ---------------------------------------------------------------------------

-- Salary credits (3 per user — past 3 months)
INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT NULL, a.id,
       ROUND(60000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'sal1')), 1, 8), 16, 10) % 90000), 2),
       'NEFT', 'completed', 'Salary credit - May 2026',
       CONCAT('NEFTBLK', LPAD(a.id * 10 + 1, 6, '0')),
       FALSE,
       DATE_SUB(NOW(), INTERVAL 85 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399' AND a.account_type = 'savings';

INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT NULL, a.id,
       ROUND(60000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'sal2')), 1, 8), 16, 10) % 90000), 2),
       'NEFT', 'completed', 'Salary credit - Jun 2026',
       CONCAT('NEFTBLK', LPAD(a.id * 10 + 2, 6, '0')),
       FALSE,
       DATE_SUB(NOW(), INTERVAL 55 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399' AND a.account_type = 'savings';

INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT NULL, a.id,
       ROUND(60000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'sal3')), 1, 8), 16, 10) % 90000), 2),
       'NEFT', 'completed', 'Salary credit - Jul 2026',
       CONCAT('NEFTBLK', LPAD(a.id * 10 + 3, 6, '0')),
       FALSE,
       DATE_SUB(NOW(), INTERVAL 25 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399' AND a.account_type = 'savings';

-- Bill payments (1 per user from savings)
INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT a.id, NULL,
       ROUND(500 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'bill')), 1, 6), 16, 10) % 4500), 2),
       'IMPS', 'completed',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5)
           WHEN 0 THEN 'Electricity bill - BESCOM'
           WHEN 1 THEN 'Mobile recharge - Airtel'
           WHEN 2 THEN 'Broadband - ACT Fibernet'
           WHEN 3 THEN 'Gas bill - Indane'
           ELSE 'DTH recharge - Tata Play'
       END,
       CONCAT('IMPBLKB', LPAD(a.id * 10 + 4, 6, '0')),
       TRUE,
       DATE_SUB(NOW(), INTERVAL 18 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399' AND a.account_type = 'savings';

-- Internal transfers (savings -> another user's savings, with OTP)
INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT a_from.id, a_to.id,
       ROUND(1000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'xfr')), 1, 6), 16, 10) % 19000), 2),
       'IMPS', 'completed', 'Fund transfer',
       CONCAT('IMPBLKX', LPAD(a_from.id * 10 + 5, 6, '0')),
       TRUE,
       DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM users u
JOIN accounts a_from ON a_from.user_id = u.id AND a_from.account_type = 'savings'
JOIN accounts a_to ON a_to.account_number = CONCAT('5010010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 1, 4, '0'))
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100398';

-- ---------------------------------------------------------------------------
-- Security questions: 5 per user
--   Uses realistic answers derived from user names (split first/last)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 1, 'What is your mother''s maiden name?',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 10)
           WHEN 0 THEN 'Sharma' WHEN 1 THEN 'Patel' WHEN 2 THEN 'Singh'
           WHEN 3 THEN 'Kumar' WHEN 4 THEN 'Verma' WHEN 5 THEN 'Nair'
           WHEN 6 THEN 'Iyer' WHEN 7 THEN 'Reddy' WHEN 8 THEN 'Rao'
           ELSE 'Gupta'
       END
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 2, 'What was the name of your first school?',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8)
           WHEN 0 THEN 'DPS Bangalore' WHEN 1 THEN 'Kendriya Vidyalaya'
           WHEN 2 THEN 'St Xaviers' WHEN 3 THEN 'Army School'
           WHEN 4 THEN 'Modern School' WHEN 5 THEN 'Ryan International'
           WHEN 6 THEN 'DAV Public School' ELSE 'Bishop Cotton'
       END
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 3, 'In which city were you born?',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8)
           WHEN 0 THEN 'Mumbai' WHEN 1 THEN 'Delhi'
           WHEN 2 THEN 'Bangalore' WHEN 3 THEN 'Chennai'
           WHEN 4 THEN 'Hyderabad' WHEN 5 THEN 'Pune'
           WHEN 6 THEN 'Kolkata' ELSE 'Ahmedabad'
       END
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 4, 'What was the name of your first pet?',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8)
           WHEN 0 THEN 'Tommy' WHEN 1 THEN 'Rocky'
           WHEN 2 THEN 'Bruno' WHEN 3 THEN 'Sheru'
           WHEN 4 THEN 'Moti' WHEN 5 THEN 'Tiger'
           WHEN 6 THEN 'Rex' ELSE 'Simba'
       END
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 5, 'What is your favourite teacher''s name?',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8)
           WHEN 0 THEN 'Sharma Sir' WHEN 1 THEN 'Patel Madam'
           WHEN 2 THEN 'Kumar Sir' WHEN 3 THEN 'Nair Madam'
           WHEN 4 THEN 'Verma Sir' WHEN 5 THEN 'Iyer Madam'
           WHEN 6 THEN 'Singh Sir' ELSE 'Reddy Madam'
       END
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ---------------------------------------------------------------------------
-- Audit log: login entries for all 100 users
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO audit_log (user_id, action, resource, ip_address, request_body, response_code, created_at)
SELECT u.id, 'user.login', '/login',
       CONCAT('192.168.1.', (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 200) + 100),
       CONCAT('{"customer_id":"', u.customer_id, '"}'),
       200,
       DATE_SUB(NOW(), INTERVAL (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 30) DAY)
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ---------------------------------------------------------------------------
-- User preferences (default theme)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO user_preferences (user_id, prefs)
SELECT u.id, '{"theme":"dark","language":"en","dashboard":"summary"}'
FROM users u WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ---------------------------------------------------------------------------
-- Bill payments (link to the bill payment transactions above)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO bill_payments (user_id, account_id, biller_type, biller_id, biller_name, amount, remarks, status, txn_ref)
SELECT u.id, a.id,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5)
           WHEN 0 THEN 'electricity'
           WHEN 1 THEN 'mobile'
           WHEN 2 THEN 'DTH'
           WHEN 3 THEN 'gas'
           ELSE 'water'
       END,
       CONCAT('BILL-', u.customer_id),
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5)
           WHEN 0 THEN 'BESCOM'
           WHEN 1 THEN 'Airtel'
           WHEN 2 THEN 'ACT Fibernet'
           WHEN 3 THEN 'Indane Gas'
           ELSE 'BWSSB'
       END,
       ROUND(500 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'bill')), 1, 6), 16, 10) % 4500), 2),
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5)
           WHEN 0 THEN 'Electricity bill'
           WHEN 1 THEN 'Mobile recharge'
           WHEN 2 THEN 'Broadband bill'
           WHEN 3 THEN 'Gas bill'
           ELSE 'Water bill'
       END,
       'completed',
       CONCAT('IMPBLKB', LPAD(a.id * 10 + 4, 6, '0'))
FROM users u
JOIN accounts a ON a.user_id = u.id AND a.account_type = 'savings'
WHERE u.customer_id >= 'BS100300' AND u.customer_id <= 'BS100399';

-- ===========================================================================
--  Final Summary: 100 complete user accounts (BS100300-BS100399)
--  - 100 users
--  - 200 accounts (2 per user: 1 savings + 1 current) with predictable numbers
--  - 300-400 beneficiaries (3-4 per user, cross-referencing other users)
--  - 200 cards (2 per user: 1 debit + 1 credit)
--  - 500+ transactions (salary credits × 3 months, bill payments, transfers)
--  - 500 security questions (5 per user with realistic answers)
--  - 100 audit logs (login entries)
--  - 100 user preferences
--  - 100 bill payment records
--
--  IDOR exploitation: beneficiaries contain real account numbers of OTHER users.
--  Cooling period bypass: 1 beneficiary per user has future cooling_period_until.
--  Password pattern: {customer_id}B4rc@123 (e.g. BS100300B4rc@123)
-- ===========================================================================
