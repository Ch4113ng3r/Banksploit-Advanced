<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'src/core/TrainingEngine.php';
require_once 'src/core/Auth.php';
require_once 'src/core/Session.php';

$pdo = new PDO('mysql:host=localhost;dbname=banksploit', 'root', 'root');
$engine = new TrainingEngine($pdo);
$auth = new Auth($pdo, $engine);

// Simulate Bearer token in header
$token = 'eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0.eyJzdWIiOiAiQlMxMDAwMDEiLCAicm9sZSI6ICJhZG1pbiJ9.';

// Manual test
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

$user = $auth->getCurrentUser();

echo "=== JWT Verification Debug ===\n";
echo "Token: " . substr($token, 0, 50) . "...\n";
echo "User data:\n";
print_r($user);
echo "\nIs Admin? " . ($auth->isAdmin() ? 'YES' : 'NO') . "\n";
echo "Role: " . ($user['role'] ?? 'NONE') . "\n";
