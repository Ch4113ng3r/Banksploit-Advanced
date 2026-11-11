<?php
/**
 * Truncate ctf_flags table.
 * Flags auto-regenerate per-user on their next statements page visit.
 *
 * Run: php reset-ctf.php
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'banksploit';
$user = getenv('DB_USER') ?: 'banksploit';
$pass = getenv('DB_PASS') ?: 'banksploit_pass';

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec('TRUNCATE TABLE ctf_flags');
echo "[✓] ctf_flags truncated\n";
