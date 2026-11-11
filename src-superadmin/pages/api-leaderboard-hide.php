<?php

declare(strict_types=1);

/**
 * Superadmin API — Toggle user visibility on the CTF leaderboard.
 *
 * POST /api/leaderboard-hide
 * Body (JSON): { "user_id": int, "hidden": bool }
 * CSRF token required via X-CSRF-Token header.
 *
 * Stores hidden user IDs in data/ctf-hidden-users.json (no DB changes).
 */

header('Content-Type: application/json; charset=utf-8');

// Only POST allowed
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_verify($csrfHeader)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse body
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['user_id']) || !isset($body['hidden'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing user_id or hidden field']);
    exit;
}

$userId = (int) $body['user_id'];
$hidden = (bool) $body['hidden'];

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user_id']);
    exit;
}

// Verify user exists and is a regular user (not admin)
$pdo = db();
$check = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$check->execute([$userId]);
$user = $check->fetch();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

if ($user['role'] !== 'user') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot hide admin users']);
    exit;
}

// --- Read current hidden list ---
$hiddenFile = dirname(__DIR__, 2) . '/data/ctf-hidden-users.json';
$dataDir = dirname($hiddenFile);

// Ensure data directory exists
if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cannot create data directory']);
        exit;
    }
}

$hiddenIds = [];
if (is_file($hiddenFile)) {
    $raw = @file_get_contents($hiddenFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $hiddenIds = array_map('intval', $decoded);
        }
    }
}

// --- Update the list ---
if ($hidden) {
    // Add user to hidden list (if not already there)
    if (!in_array($userId, $hiddenIds, true)) {
        $hiddenIds[] = $userId;
    }
} else {
    // Remove user from hidden list
    $hiddenIds = array_values(array_filter($hiddenIds, fn(int $id) => $id !== $userId));
}

// --- Write back ---
$json = json_encode(array_values($hiddenIds), JSON_PRETTY_PRINT);
if (@file_put_contents($hiddenFile, $json) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write hidden users file']);
    exit;
}

echo json_encode([
    'success'    => true,
    'user_id'    => $userId,
    'ctf_hidden' => $hidden,
]);
