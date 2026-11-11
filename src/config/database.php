<?php

declare(strict_types=1);

/**
 * Database connection.
 *
 * Reads connection settings from environment variables and returns a
 * configured PDO instance.
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'banksploit';
$user = getenv('DB_USER') ?: 'banksploit';
$pass = getenv('DB_PASS') ?: 'banksploit_pass';

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Deliberately verbose for training purposes.
    http_response_code(500);
    die('Database connection failed: ' . $e->getMessage());
}

return $pdo;
