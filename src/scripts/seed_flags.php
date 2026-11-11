<?php

/**
 * Seed CTF flags for all users.
 *
 * Plants only the flags that are discoverable via their respective vulnerabilities:
 *   - ctf_flags table: ONLY the G2 (SQLi extraction) flag per user.
 *   - accounts.ctf_flag column: ONLY the L1 (IDOR) flag per user.
 *
 * This prevents cross-vulnerability flag leakage. Discovering the ctf_flags
 * table via SQLi gives you the G2 flag — not all 10 flags at once.
 *
 * Usage: php src/scripts/seed_flags.php
 *   OR: called automatically by start-app.sh setup
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/TrainingEngine.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Flags.php';

$pdo = require __DIR__ . '/../config/database.php';
$engine = TrainingEngine::getInstance();
bs_register('engine', $engine);

// Get all users
$users = $pdo->query('SELECT id, customer_id FROM users')->fetchAll(PDO::FETCH_ASSOC);

echo "Seeding CTF flags for " . count($users) . " users...\n";

// --- ctf_flags table: ONLY the G2 (SQLi) flag ---
// This is the table players discover via UNION injection.
$stmt = $pdo->prepare(
    'REPLACE INTO ctf_flags (user_id, username, vuln_key, flag, description)
     VALUES (:uid, :username, :vk, :flag, :desc)'
);

// Clear any old flags from previous (all-vulns) seeding
$pdo->exec('DELETE FROM ctf_flags WHERE vuln_key != \'statements.g2\'');

$count = 0;
foreach ($users as $user) {
    $flag = Flags::generate('statements.g2', (int) $user['id']);
    $stmt->execute([
        ':uid'      => (int) $user['id'],
        ':username' => (string) $user['customer_id'],
        ':vk'       => 'statements.g2',
        ':flag'     => $flag,
        ':desc'     => "SQL Injection flag (user {$user['customer_id']})",
    ]);
    $count++;
}

echo "✓ Seeded {$count} G2 flags (ctf_flags table). L1 IDOR flags are generated on-the-fly.\n";
