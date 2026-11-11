<?php

declare(strict_types=1);

/**
 * Bill payments JSON API.
 *
 *   POST /api/v1/payments/pay       make a bill payment (beneficiary.g3 XSS sink)
 *   GET  /api/v1/payments/history   payment history
 */

require_once __DIR__ . '/payments_vuln.php';
require_once __DIR__ . '/payments_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$in     = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');

if ($path === '/api/v1/payments/history' && $method === 'GET') {
    json_response(['ok' => true, 'payments' => payments_history($pdo, $uid)]);
}

if ($path === '/api/v1/payments/pay' && $method === 'POST') {
    auth()->requireSession(); // JWT is read-only
    // CSRF enforced only when the (shared) G3 dial is secured; no-op otherwise.
    if (!$engine->isVulnerable('beneficiary', 'g3') && !CSRF::validateToken($in['csrf_token'] ?? null)) {
        json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
    }
    $res = payments_pay($pdo, $engine, $uid, $in);
    if (!$res['ok']) {
        json_response(['ok' => false, 'error' => $res['error']], 400);
    }
    json_response(['ok' => true, 'txn_id' => $res['txn_id'], 'txn_ref' => $res['txn_ref']], 201);
}

json_response(['ok' => false, 'error' => 'Unsupported.'], 405);
