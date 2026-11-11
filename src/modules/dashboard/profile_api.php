<?php

declare(strict_types=1);

/**
 * Profile JSON API — READ-ONLY.
 *
 *   GET /api/v1/profile -> current user's profile (no IDOR, always session user)
 *   PUT/POST            -> rejected (profile editing is disabled)
 *
 * No vulnerability surface. Always returns the session user's own data.
 */

auth()->requireAuth();

$pdo    = db();
$me     = auth()->getCurrentUser();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Block all write operations.
if ($method === 'PUT' || $method === 'POST' || $method === 'PATCH') {
    json_response(['ok' => false, 'error' => 'Profile editing is disabled. Contact the bank to update your details.'], 403);
}

// GET — always the session user, no user_id/id parameter honoured.
$stmt = $pdo->prepare(
    'SELECT id, customer_id, name, email, phone, address, aadhaar_masked,
            pan, role
       FROM users WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => (int) $me['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    json_response(['ok' => false, 'error' => 'Profile not found.'], 404);
}
json_response(['ok' => true, 'profile' => $profile]);
