-- ============================================================================
--  BankSploit :: Bulk Users Extended (100 additional participants) — Stage 4
--
--  Adds 100 more users (BS100400-BS100499) with complete banking data.
--  Same pattern as Stage 3 (BS100300-BS100399):
--    Password: {customer_id}B4rc@123  (e.g. BS100400B4rc@123)
--    Email: {firstname}.{lastname}.{customer_id}@banksploit.local
--
--  Total after this: 206 users (6 base + 100 bulk + 100 extended)
--  All records use INSERT IGNORE for idempotency.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Users: BS100400-BS100499 (100 users)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
VALUES
    ('BS100400', 'Arjun Agarwal', 'arjun.agarwal.BS100400@banksploit.local', '+919900000400',
     '$2y$10$N8mQF5v7Xz3kL9pR2wTjHuYi4gBcDaEfG1hI2jK3lM4nO5pQ6rS7', MD5('BS100400B4rc@123'),
     'XXXX-XXXX-0400', 'ARJAG1234A', '1 MG Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.100', DATE_SUB(NOW(), INTERVAL 85 DAY)),
    ('BS100401', 'Bhavna Sharma', 'bhavna.sharma.BS100401@banksploit.local', '+919900000401',
     '$2y$10$O9nRG6w8Ya4lM0qS3xUkIvZj5hCdEbFgH2iJ3kL4mN5oP6qR7sT8', MD5('BS100401B4rc@123'),
     'XXXX-XXXX-0401', 'BHASM1234B', '2 Indiranagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.101', DATE_SUB(NOW(), INTERVAL 84 DAY)),
    ('BS100402', 'Chirag Verma', 'chirag.verma.BS100402@banksploit.local', '+919900000402',
     '$2y$10$P0oSH7x9Zb5mN1rT4yVlJwAk6iDeEcGhI3jK4lM5nO6pQ7rS8tU9', MD5('BS100402B4rc@123'),
     'XXXX-XXXX-0402', 'CHIVM1234C', '3 Koramangala, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.102', DATE_SUB(NOW(), INTERVAL 83 DAY)),
    ('BS100403', 'Divya Patel', 'divya.patel.BS100403@banksploit.local', '+919900000403',
     '$2y$10$Q1pTI8y0Ac6nO2sU5zWmKxBl7jEfFdHiJ4kL5mN6oP7qR8sT9uV0', MD5('BS100403B4rc@123'),
     'XXXX-XXXX-0403', 'DIVPT1234D', '4 Whitefield, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.103', DATE_SUB(NOW(), INTERVAL 82 DAY)),
    ('BS100404', 'Eshan Rao', 'eshan.rao.BS100404@banksploit.local', '+919900000404',
     '$2y$10$R2qUJ9z1Bd7oP3tV6aXnLyCm8kFgGeIjK5lM6nO7pQ8rS9tU0vW1', MD5('BS100404B4rc@123'),
     'XXXX-XXXX-0404', 'ESHRAO1234E', '5 Marathahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.104', DATE_SUB(NOW(), INTERVAL 81 DAY)),
    ('BS100405', 'Falguni Singh', 'falguni.singh.BS100405@banksploit.local', '+919900000405',
     '$2y$10$S3rVK0a2Ce8pQ4uW7bYoMzDn9lGhHfJkL6mN7oP8qR9sT0uV1wX2', MD5('BS100405B4rc@123'),
     'XXXX-XXXX-0405', 'FALSG1234F', '6 JP Nagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.105', DATE_SUB(NOW(), INTERVAL 80 DAY)),
    ('BS100406', 'Gaurav Khan', 'gaurav.khan.BS100406@banksploit.local', '+919900000406',
     '$2y$10$T4sWL1b3Df9qR5vX8cZpNaEo0mHiIgKlM7nO8pQ9rS0tU1vW2xY3', MD5('BS100406B4rc@123'),
     'XXXX-XXXX-0406', 'GAUKN1234G', '7 Hebbal, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.106', DATE_SUB(NOW(), INTERVAL 79 DAY)),
    ('BS100407', 'Hina Desai', 'hina.desai.BS100407@banksploit.local', '+919900000407',
     '$2y$10$U5tXM2c4Eg0rS6wY9dAqObFp1nIjJhLmN8oP9qR0sT1uV2wX3yZ4', MD5('BS100407B4rc@123'),
     'XXXX-XXXX-0407', 'HINDES1234H', '8 Jayanagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.107', DATE_SUB(NOW(), INTERVAL 78 DAY)),
    ('BS100408', 'Isha Nair', 'isha.nair.BS100408@banksploit.local', '+919900000408',
     '$2y$10$V6uYN3d5Fh1sT7xZ0eBrPcGq2oJkKiMnO9pQ0rS1tU2vW3xY4zA5', MD5('BS100408B4rc@123'),
     'XXXX-XXXX-0408', 'ISHNR1234I', '9 Yeshwanthpur, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.108', DATE_SUB(NOW(), INTERVAL 77 DAY)),
    ('BS100409', 'Jitendra Kumar', 'jitendra.kumar.BS100409@banksploit.local', '+919900000409',
     '$2y$10$W7vZO4e6Gi2tU8yA1fCsQdHr3pKlLjNoP0qR1sT2uV3wX4yZ5aB6', MD5('BS100409B4rc@123'),
     'XXXX-XXXX-0409', 'JITKM1234J', '10 Malleswaram, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.109', DATE_SUB(NOW(), INTERVAL 76 DAY)),

    ('BS100410', 'Kavya Iyer', 'kavya.iyer.BS100410@banksploit.local', '+919900000410',
     '$2y$10$X8wAP5f7Hj3uV9zB2gDtReIs4qLmMkOpQ1rS2tU3vW4xY5zA6bC7', MD5('BS100410B4rc@123'),
     'XXXX-XXXX-0410', 'KAVIYR1234K', '11 Rajajinagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.110', DATE_SUB(NOW(), INTERVAL 75 DAY)),
    ('BS100411', 'Lokesh Chopra', 'lokesh.chopra.BS100411@banksploit.local', '+919900000411',
     '$2y$10$Y9xBQ6g8Ik4vW0aC3hEuSfJt5rMnNlPqR2sT3uV4wX5yZ6aB7cD8', MD5('BS100411B4rc@123'),
     'XXXX-XXXX-0411', 'LOKCHP1234L', '12 Basavanagudi, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.111', DATE_SUB(NOW(), INTERVAL 74 DAY)),
    ('BS100412', 'Meera Bhat', 'meera.bhat.BS100412@banksploit.local', '+919900000412',
     '$2y$10$Z0yCR7h9Jl5wX1bD4iFvTgKu6sNoOmQrS3tU4vW5xY6zA7bC8dE9', MD5('BS100412B4rc@123'),
     'XXXX-XXXX-0412', 'MEEBH1234M', '13 Vijayanagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.112', DATE_SUB(NOW(), INTERVAL 73 DAY)),
    ('BS100413', 'Nikhil Reddy', 'nikhil.reddy.BS100413@banksploit.local', '+919900000413',
     '$2y$10$A1zDS8i0Km6xY2cE5jGwUhLv7tOpPnRsT4uV5wX6yZ7aB8cD9eF0', MD5('BS100413B4rc@123'),
     'XXXX-XXXX-0413', 'NIKRD1234N', '14 KR Puram, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.113', DATE_SUB(NOW(), INTERVAL 72 DAY)),
    ('BS100414', 'Omkar Menon', 'omkar.menon.BS100414@banksploit.local', '+919900000414',
     '$2y$10$B2aET9j1Ln7yZ3dF6kHxViMw8uPqQoStU5vW6xY7zA8bC9dE0fG1', MD5('BS100414B4rc@123'),
     'XXXX-XXXX-0414', 'OMKMN1234O', '15 Sadashivnagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.114', DATE_SUB(NOW(), INTERVAL 71 DAY)),
    ('BS100415', 'Priyanka Gupta', 'priyanka.gupta.BS100415@banksploit.local', '+919900000415',
     '$2y$10$C3bFU0k2Mo8zA4eG7lIyWjNx9vQrRpTuV6wX7yZ8aB9cD0eF1gH2', MD5('BS100415B4rc@123'),
     'XXXX-XXXX-0415', 'PRIGP1234P', '16 Fort, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.115', DATE_SUB(NOW(), INTERVAL 70 DAY)),
    ('BS100416', 'Quincy Thomas', 'quincy.thomas.BS100416@banksploit.local', '+919900000416',
     '$2y$10$D4cGV1l3Np9aB5fH8mJzXkOy0wRsSpUvW7xY8zA9bC0dE1fG2hI3', MD5('BS100416B4rc@123'),
     'XXXX-XXXX-0416', 'QUITH1234Q', '17 Banashankari, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.116', DATE_SUB(NOW(), INTERVAL 69 DAY)),
    ('BS100417', 'Rajesh Bhandari', 'rajesh.bhandari.BS100417@banksploit.local', '+919900000417',
     '$2y$10$E5dHW2m4Oq0bC6gI9nKaYlPz1xStTqVwX8yZ9aB0cD1eF2gH3iJ4', MD5('BS100417B4rc@123'),
     'XXXX-XXXX-0417', 'RAJBN1234R', '18 Ulsoor, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.117', DATE_SUB(NOW(), INTERVAL 68 DAY)),
    ('BS100418', 'Sneha Iyer', 'sneha.iyer.BS100418@banksploit.local', '+919900000418',
     '$2y$10$F6eIX3n5Pr1cD7hJ0oLbZmQa2yTuUrWxY9zA0bC1dE2fG3hI4jK5', MD5('BS100418B4rc@123'),
     'XXXX-XXXX-0418', 'SNEIYR1234S', '19 Ramamurthy Nagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.118', DATE_SUB(NOW(), INTERVAL 67 DAY)),
    ('BS100419', 'Tarun Misra', 'tarun.misra.BS100419@banksploit.local', '+919900000419',
     '$2y$10$G7fJY4o6Qs2dE8iK1pMcAnRb3zUvVsXyZ0aB1cD2eF3gH4iJ5kL6', MD5('BS100419B4rc@123'),
     'XXXX-XXXX-0419', 'TARMSRA1234T', '20 Shivajinagar, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.119', DATE_SUB(NOW(), INTERVAL 66 DAY)),

    ('BS100420', 'Uma Desai', 'uma.desai.BS100420@banksploit.local', '+919900000420',
     '$2y$10$H8gKZ5p7Rt3eF9jL2qNdBoSc4aVwWtYzA1bC2dE3fG4hI5jK6lM7', MD5('BS100420B4rc@123'),
     'XXXX-XXXX-0420', 'UMADES1234U', '21 Cox Town, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.120', DATE_SUB(NOW(), INTERVAL 65 DAY)),
    ('BS100421', 'Vikram Malhi', 'vikram.malhi.BS100421@banksploit.local', '+919900000421',
     '$2y$10$I9hLA6q8Su4fG0kM3rOeCpTd5bWxXuZaB2cD3eF4gH5iJ6kL7mN8', MD5('BS100421B4rc@123'),
     'XXXX-XXXX-0421', 'VIKMLH1234V', '22 Broadway, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.121', DATE_SUB(NOW(), INTERVAL 64 DAY)),
    ('BS100422', 'Wanda Pillai', 'wanda.pillai.BS100422@banksploit.local', '+919900000422',
     '$2y$10$J0iMB7r9Tv5gH1lN4sPfDqUe6cXyYvAbC3dE4fG5hI6jK7lM8nO9', MD5('BS100422B4rc@123'),
     'XXXX-XXXX-0422', 'WANPIL1234W', '23 Commercial Street, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.122', DATE_SUB(NOW(), INTERVAL 63 DAY)),
    ('BS100423', 'Xenon Khan', 'xenon.khan.BS100423@banksploit.local', '+919900000423',
     '$2y$10$K1jNC8s0Uw6hI2mO5tQgErVf7dYzZwBcD4eF5gH6iJ7kL8mN9oP0', MD5('BS100423B4rc@123'),
     'XXXX-XXXX-0423', 'XENKN1234X', '24 Residency Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.123', DATE_SUB(NOW(), INTERVAL 62 DAY)),
    ('BS100424', 'Yara Rao', 'yara.rao.BS100424@banksploit.local', '+919900000424',
     '$2y$10$L2kOD9t1Vx7iJ3nP6uRhFsWg8eZaAxCdE5fG6hI7jK8lM9nO0pQ1', MD5('BS100424B4rc@123'),
     'XXXX-XXXX-0424', 'YARRAO1234Y', '25 Infantry Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.124', DATE_SUB(NOW(), INTERVAL 61 DAY)),
    ('BS100425', 'Zara Singh', 'zara.singh.BS100425@banksploit.local', '+919900000425',
     '$2y$10$M3lPE0u2Wy8jK4oQ7vSiGtXh9fAbByDeF6gH7iJ8kL9mN0oP1qR2', MD5('BS100425B4rc@123'),
     'XXXX-XXXX-0425', 'ZARSG1234Z', '26 Brigade Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.125', DATE_SUB(NOW(), INTERVAL 60 DAY)),
    ('BS100426', 'Aarav Verma', 'aarav.verma.BS100426@banksploit.local', '+919900000426',
     '$2y$10$N4mQF1v3Xz9kL5pR8wTjHuYi0gBcDaEfG7hI8jK9lM0nO1pQ2rS3', MD5('BS100426B4rc@123'),
     'XXXX-XXXX-0426', 'AARVM1234A2', '27 Palace Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.126', DATE_SUB(NOW(), INTERVAL 59 DAY)),
    ('BS100427', 'Bhumi Patel', 'bhumi.patel.BS100427@banksploit.local', '+919900000427',
     '$2y$10$O5nRG2w4Ya0lM6qS9xUkIvZj1hCdEbFgH8iJ9kL0mN1oP2qR3sT4', MD5('BS100427B4rc@123'),
     'XXXX-XXXX-0427', 'BHUPT1234B2', '28 St Marks Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.127', DATE_SUB(NOW(), INTERVAL 58 DAY)),
    ('BS100428', 'Chetan Nair', 'chetan.nair.BS100428@banksploit.local', '+919900000428',
     '$2y$10$P6oSH3x5Zb1mN7rT0yVlJwAk2iDeEcGhI9jK0lM1nO2pQ3rS4tU5', MD5('BS100428B4rc@123'),
     'XXXX-XXXX-0428', 'CHETNR1234C2', '29 Oxford School Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.128', DATE_SUB(NOW(), INTERVAL 57 DAY)),
    ('BS100429', 'Diya Sharma', 'diya.sharma.BS100429@banksploit.local', '+919900000429',
     '$2y$10$Q7pTI4y6Ac2nO8sU1zWmKxBl3jEfFdHiJ0kL1mN2oP3qR4sT5uV6', MD5('BS100429B4rc@123'),
     'XXXX-XXXX-0429', 'DIYSM1234D2', '30 Whitefield Main Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.129', DATE_SUB(NOW(), INTERVAL 56 DAY)),

    ('BS100430', 'Esha Gupta', 'esha.gupta.BS100430@banksploit.local', '+919900000430',
     '$2y$10$R8qUJ5z7Bd3oP9tV2aXnLyCm4kFgGeIjK1lM2nO3pQ4rS5tU6vW7', MD5('BS100430B4rc@123'),
     'XXXX-XXXX-0430', 'ESHGP1234E2', '31 ITPL Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.130', DATE_SUB(NOW(), INTERVAL 55 DAY)),
    ('BS100431', 'Farah Khan', 'farah.khan.BS100431@banksploit.local', '+919900000431',
     '$2y$10$S9rVK6a8Ce4pQ0uW3bYoMzDn5lGhHfJkL2mN3oP4qR5sT6uV7wX8', MD5('BS100431B4rc@123'),
     'XXXX-XXXX-0431', 'FARKN1234F2', '32 Sarjapur Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.131', DATE_SUB(NOW(), INTERVAL 54 DAY)),
    ('BS100432', 'Gaurav Tiwari', 'gaurav.tiwari.BS100432@banksploit.local', '+919900000432',
     '$2y$10$T0sWL7b9Df5qR1vX4cZpNaEo6mHiIgKlM3nO4pQ5rS6tU7vW8xY9', MD5('BS100432B4rc@123'),
     'XXXX-XXXX-0432', 'GAUTW1234G2', '33 Bellandur, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.132', DATE_SUB(NOW(), INTERVAL 53 DAY)),
    ('BS100433', 'Hema Rao', 'hema.rao.BS100433@banksploit.local', '+919900000433',
     '$2y$10$U1tXM8c0Eg6rS2wY5dAqObFp7nIjJhLmN4oP5qR6sT7uV8wX9yZ0', MD5('BS100433B4rc@123'),
     'XXXX-XXXX-0433', 'HEMRAO1234H2', '34 Marathahalli Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.133', DATE_SUB(NOW(), INTERVAL 52 DAY)),
    ('BS100434', 'Ishaan Chopra', 'ishaan.chopra.BS100434@banksploit.local', '+919900000434',
     '$2y$10$V2uYN9d1Fh7sT3xZ6eBrPcGq8oJkKiMnO5pQ6rS7tU8vW9xY0zA1', MD5('BS100434B4rc@123'),
     'XXXX-XXXX-0434', 'ISHCHP1234I2', '35 Devarabisanahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.134', DATE_SUB(NOW(), INTERVAL 51 DAY)),
    ('BS100435', 'Jyoti Singh', 'jyoti.singh.BS100435@banksploit.local', '+919900000435',
     '$2y$10$W3vZO0e2Gi8tU4yA7fCsQdHr9pKlLjNoP6qR7sT8uV9wX0yZ1aB2', MD5('BS100435B4rc@123'),
     'XXXX-XXXX-0435', 'JYOSG1234J2', '36 Varthur Main Road, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.135', DATE_SUB(NOW(), INTERVAL 50 DAY)),
    ('BS100436', 'Karan Malhotra', 'karan.malhotra.BS100436@banksploit.local', '+919900000436',
     '$2y$10$X4wAP1f3Hj9uV5zB8gDtReIs0qLmMkOpQ7rS8tU9vW0xY1zA2bC3', MD5('BS100436B4rc@123'),
     'XXXX-XXXX-0436', 'KARMH1234K2', '37 Kundalahalli, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.136', DATE_SUB(NOW(), INTERVAL 49 DAY)),
    ('BS100437', 'Leela Menon', 'leela.menon.BS100437@banksploit.local', '+919900000437',
     '$2y$10$Y5xBQ2g4Ik0vW6aC9hEuSfJt1rMnNlPqR8sT9uV0wX1yZ2aB3cD4', MD5('BS100437B4rc@123'),
     'XXXX-XXXX-0437', 'LEEMN1234L2', '38 Carmelaram, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.137', DATE_SUB(NOW(), INTERVAL 48 DAY)),
    ('BS100438', 'Manish Bhat', 'manish.bhat.BS100438@banksploit.local', '+919900000438',
     '$2y$10$Z6yCR3h5Jl1wX7bD0iFvTgKu2sNoOmQrS9tU0vW1xY2zA3bC4dE5', MD5('BS100438B4rc@123'),
     'XXXX-XXXX-0438', 'MANBH1234M2', '39 Cessna Business Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.138', DATE_SUB(NOW(), INTERVAL 47 DAY)),
    ('BS100439', 'Nisha Verma', 'nisha.verma.BS100439@banksploit.local', '+919900000439',
     '$2y$10$A7zDS4i6Km2xY8cE1jGwUhLv3tOpPnRsT0uV1wX2yZ3aB4cD5eF6', MD5('BS100439B4rc@123'),
     'XXXX-XXXX-0439', 'NISVM1234N2', '40 Infopark IT Hub, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.139', DATE_SUB(NOW(), INTERVAL 46 DAY)),

    ('BS100440', 'Omkar Singh', 'omkar.singh.BS100440@banksploit.local', '+919900000440',
     '$2y$10$B8aET5j7Ln3yZ9dF2kHxViMw4uPqQoStU1vW2xY3zA4bC5dE6fG7', MD5('BS100440B4rc@123'),
     'XXXX-XXXX-0440', 'OMKSG1234O2', '41 Bagmane Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.140', DATE_SUB(NOW(), INTERVAL 45 DAY)),
    ('BS100441', 'Priya Chopra', 'priya.chopra.BS100441@banksploit.local', '+919900000441',
     '$2y$10$C9bFU6k8Mo4zA0eG3lIyWjNx5vQrRpTuV2wX3yZ4aB5cD6eF7gH8', MD5('BS100441B4rc@123'),
     'XXXX-XXXX-0441', 'PRICHP1234P2', '42 Embassy MANYATA, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.141', DATE_SUB(NOW(), INTERVAL 44 DAY)),
    ('BS100442', 'Quentin Joshi', 'quentin.joshi.BS100442@banksploit.local', '+919900000442',
     '$2y$10$D0cGV7l9Np5aB1fH4mJzXkOy6wRsSpUvW3xY4zA5bC6dE7fG8hI9', MD5('BS100442B4rc@123'),
     'XXXX-XXXX-0442', 'QUEJOS1234Q2', '43 Prestige Ozone, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.142', DATE_SUB(NOW(), INTERVAL 43 DAY)),
    ('BS100443', 'Rohan Desai', 'rohan.desai.BS100443@banksploit.local', '+919900000443',
     '$2y$10$E1dHW8m0Oq6bC2gI5nKaYlPz7xStTqVwX4yZ5aB6cD7eF8gH9iJ0', MD5('BS100443B4rc@123'),
     'XXXX-XXXX-0443', 'ROHDES1234R2', '44 Prestige Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.143', DATE_SUB(NOW(), INTERVAL 42 DAY)),
    ('BS100444', 'Shweta Kumar', 'shweta.kumar.BS100444@banksploit.local', '+919900000444',
     '$2y$10$F2eIX9n1Pr7cD3hJ6oLbZmQa8yTuUrWxY5zA6bC7dE8fG9hI0jK1', MD5('BS100444B4rc@123'),
     'XXXX-XXXX-0444', 'SHWKM1234S2', '45 Nexus Bengaluru, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.144', DATE_SUB(NOW(), INTERVAL 41 DAY)),
    ('BS100445', 'Tarun Kumar', 'tarun.kumar.BS100445@banksploit.local', '+919900000445',
     '$2y$10$G3fJY0o2Qs8dE4iK7pMcAnRb9zUvVsXyZ6aB7cD8eF9gH0iJ1kL2', MD5('BS100445B4rc@123'),
     'XXXX-XXXX-0445', 'TARKM1234T2', '46 Kalyani Tech Park, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.145', DATE_SUB(NOW(), INTERVAL 40 DAY)),
    ('BS100446', 'Usha Patel', 'usha.patel.BS100446@banksploit.local', '+919900000446',
     '$2y$10$H4gKZ1p3Rt9eF5jL8qNdBoSc0aVwWtYzA7bC8dE9fG0hI1jK2lM3', MD5('BS100446B4rc@123'),
     'XXXX-XXXX-0446', 'USHPT1234U2', '47 Marathon Infinity, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.146', DATE_SUB(NOW(), INTERVAL 39 DAY)),
    ('BS100447', 'Varun Sharma', 'varun.sharma.BS100447@banksploit.local', '+919900000447',
     '$2y$10$I5hLA2q4Su0fG6kM9rOeCpTd1bWxXuZaB8cD9eF0gH1iJ2kL3mN4', MD5('BS100447B4rc@123'),
     'XXXX-XXXX-0447', 'VARSM1234V2', '48 Salarpuria Greenage, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.147', DATE_SUB(NOW(), INTERVAL 38 DAY)),
    ('BS100448', 'Wanda Khan', 'wanda.khan.BS100448@banksploit.local', '+919900000448',
     '$2y$10$J6iMB3r5Tv1gH7lN0sPfDqUe2cXyYvAbC9dE0fG1hI2jK3lM4nO5', MD5('BS100448B4rc@123'),
     'XXXX-XXXX-0448', 'WANKN1234W2', '49 Tech Park Bengaluru, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.148', DATE_SUB(NOW(), INTERVAL 37 DAY)),
    ('BS100449', 'Xenon Iyer', 'xenon.iyer.BS100449@banksploit.local', '+919900000449',
     '$2y$10$K7jNC4s6Uw2hI8mO1tQgErVf3dYzZwBcD0eF1gH2iJ3kL4mN5oP6', MD5('BS100449B4rc@123'),
     'XXXX-XXXX-0449', 'XENIYR1234X2', '50 Manyata Espire, Bangalore', 'user', FALSE, FALSE, 0, '192.168.2.149', DATE_SUB(NOW(), INTERVAL 36 DAY));

-- BS100450-BS100499 (remaining 50 users via computed generation)
INSERT IGNORE INTO users
    (customer_id, name, email, phone, password_hash, password_md5,
     aadhaar_masked, pan, address, role, is_locked, is_suspended, failed_attempts,
     last_login_ip, created_at)
SELECT
    CONCAT('BS', 100450 + seq.n),
    CONCAT(
        ELT((seq.n % 25) + 1, 'Yara','Zainab','Abhinav','Anjali','Aryan','Bhavesh','Chavi','Deepak','Deepika','Disha','Eshita','Farida','Garima','Harsh','Hema','Hrithik','Isha','Ishita','Ishwar','Javed','Jaya','Joydeep','Jyotsna','Kamal','Kanishk'),
        ' ',
        ELT((seq.n % 20) + 1, 'Sharma','Patel','Singh','Kumar','Verma','Nair','Iyer','Reddy','Rao','Gupta','Khan','Desai','Chopra','Bhat','Menon','Pillai','Joshi','Bhandari','Malhotra','Tiwari')
    ),
    CONCAT(
        LOWER(ELT((seq.n % 25) + 1, 'yara','zainab','abhinav','anjali','aryan','bhavesh','chavi','deepak','deepika','disha','eshita','farida','garima','harsh','hema','hrithik','isha','ishita','ishwar','javed','jaya','joydeep','jyotsna','kamal','kanishk')),
        '.',
        LOWER(ELT((seq.n % 20) + 1, 'sharma','patel','singh','kumar','verma','nair','iyer','reddy','rao','gupta','khan','desai','chopra','bhat','menon','pillai','joshi','bhandari','malhotra','tiwari')),
        '.BS', 100450 + seq.n, '@banksploit.local'
    ),
    CONCAT('+91990000', LPAD(450 + seq.n, 4, '0')),
    CONCAT('$2y$10$', LEFT(SHA2(CONCAT('bspass_', 100450 + seq.n), 256), 50)),
    MD5(CONCAT('BS', 100450 + seq.n, 'B4rc@123')),
    CONCAT('XXXX-XXXX-', LPAD(450 + seq.n, 4, '0')),
    CONCAT(LEFT(UPPER(ELT((seq.n % 25) + 1, 'yara','zainab','abhinav','anjali','aryan','bhavesh','chavi','deepak','deepika','disha','eshita','farida','garima','harsh','hema','hrithik','isha','ishita','ishwar','javed','jaya','joydeep','jyotsna','kamal','kanishk')), 3), LPAD(450 + seq.n, 4, '0')),
    CONCAT((seq.n % 100) + 51, ' Bangalore Avenue, Bangalore'),
    'user', FALSE, FALSE, 0,
    CONCAT('192.168.2.', 150 + (seq.n % 100)),
    DATE_SUB(NOW(), INTERVAL (35 - (seq.n % 35)) DAY)
FROM (
    SELECT a.n + b.n * 10 AS n FROM
    (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
    CROSS JOIN
    (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) b
) seq WHERE seq.n < 50;

-- ---------------------------------------------------------------------------
-- Accounts: 2 per user (savings + current)
--   Pattern: 5010010000 + last 4 digits (e.g. BS100400 -> 50100100000400)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO accounts (user_id, account_number, account_type, balance, daily_limit, is_active)
SELECT u.id,
       CONCAT('5010010000', SUBSTRING(u.customer_id, 5)),
       'savings',
       ROUND(100000 + (CONV(SUBSTRING(MD5(u.customer_id), 1, 8), 16, 10) % 900000), 2),
       200000.00,
       TRUE
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO accounts (user_id, account_number, account_type, balance, daily_limit, is_active)
SELECT u.id,
       CONCAT('5020010000', SUBSTRING(u.customer_id, 5)),
       'current',
       ROUND(50000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'cur')), 1, 8), 16, 10) % 450000), 2),
       500000.00,
       TRUE
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

-- ---------------------------------------------------------------------------
-- Beneficiaries: 3-4 per user (cross-references to other users)
-- ---------------------------------------------------------------------------

-- Beneficiary 1: points to next user's savings account (verified)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       (SELECT name FROM users WHERE customer_id = CONCAT('BS', LPAD(CAST(SUBSTRING(u.customer_id, 3) AS UNSIGNED) + 1, 6, '0'))),
       CONCAT('5010010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 1, 4, '0')),
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'SBIN0001234' WHEN 1 THEN 'HDFC0000456'
           WHEN 2 THEN 'ICIC0001789' ELSE 'UTIB0002345'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'State Bank of India' WHEN 1 THEN 'HDFC Bank'
           WHEN 2 THEN 'ICICI Bank' ELSE 'Axis Bank'
       END,
       TRUE, NULL
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100498';

-- Beneficiary 2: points to user+2's current account (verified)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       (SELECT name FROM users WHERE customer_id = CONCAT('BS', LPAD(CAST(SUBSTRING(u.customer_id, 3) AS UNSIGNED) + 2, 6, '0'))),
       CONCAT('5020010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 2, 4, '0')),
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 3)
           WHEN 0 THEN 'HDFC0000789' WHEN 1 THEN 'SBIN0005678' ELSE 'ICIC0003456'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 3)
           WHEN 0 THEN 'HDFC Bank' WHEN 1 THEN 'State Bank of India' ELSE 'ICICI Bank'
       END,
       TRUE, NULL
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100497';

-- Beneficiary 3: points to user+3 (IN COOLING PERIOD — bypass target)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       (SELECT name FROM users WHERE customer_id = CONCAT('BS', LPAD(CAST(SUBSTRING(u.customer_id, 3) AS UNSIGNED) + 3, 6, '0'))),
       CONCAT('5010010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 3, 4, '0')),
       'UTIB0004567', 'Axis Bank', TRUE,
       DATE_ADD(NOW(), INTERVAL 2 DAY)
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100496';

-- Beneficiary 4: points to a BS100300-series user (cross-batch reference)
INSERT IGNORE INTO beneficiaries (user_id, name, account_number, ifsc_code, bank_name, is_verified, cooling_period_until)
SELECT u.id,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN 'Arjun Verma' WHEN 1 THEN 'Bhavna Patel'
           WHEN 2 THEN 'Chirag Sharma' ELSE 'Divya Nair'
       END,
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 4)
           WHEN 0 THEN '50100100000300' WHEN 1 THEN '50100100000301'
           WHEN 2 THEN '50100100000302' ELSE '50100100000303'
       END,
       'SBIN0001234', 'State Bank of India', TRUE, NULL
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

-- ---------------------------------------------------------------------------
-- Cards: 2 per user (debit + credit)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO cards (user_id, card_number_encrypted, card_type, cvv_encrypted, expiry_date, spending_limit, is_blocked, pin_hash)
SELECT u.id,
       CONCAT('4123', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 5600, 12, '0')),
       'debit',
       LPAD((CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'cvv1')), 1, 6), 16, 10) % 900) + 100, 3, '0'),
       '09/28', 100000.00, FALSE,
       MD5(CONCAT(u.customer_id, 'pin1'))
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO cards (user_id, card_number_encrypted, card_type, cvv_encrypted, expiry_date, spending_limit, is_blocked, pin_hash)
SELECT u.id,
       CONCAT('5234', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 1100, 12, '0')),
       'credit',
       LPAD((CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'cvv2')), 1, 6), 16, 10) % 900) + 100, 3, '0'),
       '12/27', 300000.00, FALSE,
       MD5(CONCAT(u.customer_id, 'pin2'))
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

-- ---------------------------------------------------------------------------
-- Transactions: salary credits + bill payment + transfer per user
-- ---------------------------------------------------------------------------

-- Salary credits (3 months)
INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT NULL, a.id,
       ROUND(60000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'sal1')), 1, 8), 16, 10) % 90000), 2),
       'NEFT', 'completed', 'Salary credit - May 2026',
       CONCAT('NEFTEX4', LPAD(a.id * 10 + 1, 6, '0')),
       FALSE, DATE_SUB(NOW(), INTERVAL 85 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499' AND a.account_type = 'savings';

INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT NULL, a.id,
       ROUND(60000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'sal2')), 1, 8), 16, 10) % 90000), 2),
       'NEFT', 'completed', 'Salary credit - Jun 2026',
       CONCAT('NEFTEX4', LPAD(a.id * 10 + 2, 6, '0')),
       FALSE, DATE_SUB(NOW(), INTERVAL 55 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499' AND a.account_type = 'savings';

INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT NULL, a.id,
       ROUND(60000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'sal3')), 1, 8), 16, 10) % 90000), 2),
       'NEFT', 'completed', 'Salary credit - Jul 2026',
       CONCAT('NEFTEX4', LPAD(a.id * 10 + 3, 6, '0')),
       FALSE, DATE_SUB(NOW(), INTERVAL 25 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499' AND a.account_type = 'savings';

-- Bill payments
INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT a.id, NULL,
       ROUND(500 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'bill')), 1, 6), 16, 10) % 4500), 2),
       'IMPS', 'completed',
       CASE (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5)
           WHEN 0 THEN 'Electricity bill - BESCOM'
           WHEN 1 THEN 'Mobile recharge - Jio'
           WHEN 2 THEN 'Broadband - ACT'
           WHEN 3 THEN 'Gas bill - HP Gas'
           ELSE 'Water bill - BWSSB'
       END,
       CONCAT('IMPEX4B', LPAD(a.id * 10 + 4, 6, '0')),
       TRUE, DATE_SUB(NOW(), INTERVAL 18 DAY)
FROM accounts a JOIN users u ON a.user_id = u.id
WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499' AND a.account_type = 'savings';

-- Internal transfers
INSERT IGNORE INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified, created_at)
SELECT a_from.id, a_to.id,
       ROUND(1000 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'xfr')), 1, 6), 16, 10) % 19000), 2),
       'IMPS', 'completed', 'Fund transfer',
       CONCAT('IMPEX4X', LPAD(a_from.id * 10 + 5, 6, '0')),
       TRUE, DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM users u
JOIN accounts a_from ON a_from.user_id = u.id AND a_from.account_type = 'savings'
JOIN accounts a_to ON a_to.account_number = CONCAT('5010010000', LPAD(CAST(SUBSTRING(u.customer_id, 5) AS UNSIGNED) + 1, 4, '0'))
WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100498';

-- ---------------------------------------------------------------------------
-- Security questions: 5 per user
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 1, 'What is your mother''s maiden name?',
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 10) + 1, 'Sharma','Patel','Singh','Kumar','Verma','Nair','Iyer','Reddy','Rao','Gupta')
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 2, 'What was the name of your first school?',
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8) + 1, 'DPS Bangalore','Kendriya Vidyalaya','St Xaviers','Army School','Modern School','Ryan International','DAV Public School','Bishop Cotton')
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 3, 'In which city were you born?',
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8) + 1, 'Mumbai','Delhi','Bangalore','Chennai','Hyderabad','Pune','Kolkata','Ahmedabad')
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 4, 'What was the name of your first pet?',
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8) + 1, 'Tommy','Rocky','Bruno','Sheru','Moti','Tiger','Rex','Simba')
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO security_questions (user_id, position, question, answer)
SELECT u.id, 5, 'What is your favourite teacher''s name?',
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 8) + 1, 'Sharma Sir','Patel Madam','Kumar Sir','Nair Madam','Verma Sir','Iyer Madam','Singh Sir','Reddy Madam')
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

-- ---------------------------------------------------------------------------
-- Audit log, user preferences, bill payments
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO audit_log (user_id, action, resource, ip_address, request_body, response_code, created_at)
SELECT u.id, 'user.login', '/login',
       CONCAT('192.168.2.', (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 200) + 100),
       CONCAT('{"customer_id":"', u.customer_id, '"}'),
       200,
       DATE_SUB(NOW(), INTERVAL (CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 30) DAY)
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO user_preferences (user_id, prefs)
SELECT u.id, '{"theme":"dark","language":"en","dashboard":"summary"}'
FROM users u WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

INSERT IGNORE INTO bill_payments (user_id, account_id, biller_type, biller_id, biller_name, amount, remarks, status, txn_ref)
SELECT u.id, a.id,
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5) + 1, 'electricity','mobile','DTH','gas','water'),
       CONCAT('BILL-', u.customer_id),
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5) + 1, 'BESCOM','Jio','Tata Play','HP Gas','BWSSB'),
       ROUND(500 + (CONV(SUBSTRING(MD5(CONCAT(u.customer_id,'bill')), 1, 6), 16, 10) % 4500), 2),
       ELT((CAST(SUBSTRING(u.customer_id, 7) AS UNSIGNED) % 5) + 1, 'Electricity bill','Mobile recharge','DTH recharge','Gas bill','Water bill'),
       'completed',
       CONCAT('IMPEX4B', LPAD(a.id * 10 + 4, 6, '0'))
FROM users u
JOIN accounts a ON a.user_id = u.id AND a.account_type = 'savings'
WHERE u.customer_id >= 'BS100400' AND u.customer_id <= 'BS100499';

-- ===========================================================================
--  Summary: 100 additional users (BS100400-BS100499) with complete data
--  - 200 accounts (savings + current per user)
--  - 300-400 beneficiaries (cross-user references, 1 in cooling period)
--  - 200 cards (debit + credit per user)
--  - 500+ transactions (salary × 3, bill payment, transfer)
--  - 500 security questions (5 per user)
--  - 100 audit log entries
--  - 100 user preferences
--  - 100 bill payment records
--
--  Password pattern: {customer_id}B4rc@123  (e.g. BS100400B4rc@123)
--  Account numbers: 5010010000XXXX (savings) / 5020010000XXXX (current)
--    where XXXX = last 4 of customer_id (e.g. 0400 for BS100400)
-- ===========================================================================
