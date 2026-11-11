<?php

declare(strict_types=1);

/**
 * CTF Flag Vault — JWT-protected endpoint.
 *
 * GET /api/v1/auth/vault
 *
 * Returns the user's auth.l12 flag ONLY when accessed with a JWT that has
 * role=admin. At vuln levels (auth.l12 active), the server accepts alg:none
 * so the player can forge a token with {"role":"admin"} and get the flag.
 *
 * This is the proof-of-exploitation for JWT alg:none — you must forge a
 * token to access this "admin-only" resource.
 */

$engine = engine();
$pdo    = db();

// This endpoint ONLY accepts JWT auth (Bearer token), not session cookies.
// This forces the player to actually use a JWT rather than just being logged in.
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($authHeader, 'Bearer ')) {
    json_response([
        'ok' => false,
        'error' => 'This vault requires Bearer JWT authentication.',
        'hint'  => 'Send Authorization: Bearer <your-jwt-token>',
    ], 401);
}

$token = substr($authHeader, 7);
$claims = auth()->verifyJWT($token);

if ($claims === null) {
    json_response(['ok' => false, 'error' => 'Invalid or expired token.'], 401);
}

// The vault is "admin-only" — player must forge role=admin in the JWT
$role = (string) ($claims['role'] ?? '');
if ($role !== 'admin') {
    json_response([
        'ok'   => false,
        'error' => 'Access denied. Admin role required.',
        'your_role' => $role,
        'hint' => 'Only tokens with role=admin can access the vault.',
    ], 403);
}

// Resolve the user from the JWT sub claim (customer_id)
$sub = (string) ($claims['sub'] ?? '');
$stmt = $pdo->prepare('SELECT id, customer_id, name FROM users WHERE customer_id = :cid LIMIT 1');
$stmt->execute([':cid' => $sub]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['ok' => false, 'error' => 'User not found for this token.'], 404);
}

$userId = (int) $user['id'];
$flag = Flags::generate('auth.l12', $userId);

// Set flag in response header for the modal system in layout.php
if (flags_delivered()) { header('X-CTF-Flag: ' . $flag); }
header('X-CTF-Vuln-Id: auth.l12');

$vaultResp = [
    'ok'      => true,
    'message' => '🔓 Vault unlocked! You forged a valid admin JWT.',
    'user'    => $user['customer_id'],
];
if (flags_delivered()) {
    $vaultResp['flag'] = $flag;
}
json_response($vaultResp);
