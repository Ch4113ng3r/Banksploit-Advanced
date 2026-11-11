<?php

declare(strict_types=1);

/**
 * Cards JSON API.
 *
 *   GET /api/v1/cards               list cards
 *   GET /api/v1/cards/{id}          card detail   (cards.l24 IDOR + plaintext)
 *   PUT /api/v1/cards/{id}/block    block/unblock (IDOR)
 *   PUT /api/v1/cards/{id}/pin      change PIN     (old PIN not required at L0)
 *   PUT /api/v1/cards/{id}/limit    set limit      (mass assignment)
 *
 * cards.l24 governs the module: vuln (0-2) returns full PAN+CVV and skips
 * ownership/old-PIN/limit checks; secure (3) masks, enforces ownership, and
 * requires the old PIN.
 */

require_once __DIR__ . '/cards_vuln.php';
require_once __DIR__ . '/cards_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$in     = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$secure = !$engine->isVulnerable('cards', 'l24');

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$cardId = (int) (route_param('id') ?? 0);

// ---- collection: GET /api/v1/cards ----
if ($path === '/api/v1/cards' && $method === 'GET') {
    $cards = $secure ? cards_list_secure($pdo, $uid) : cards_list_vuln($pdo, $uid);
    json_response(['ok' => true, 'cards' => $cards]);
}

// ---- PUT sub-actions (write operations — require session, not JWT) ----
if (str_ends_with($path, '/block')) {
    auth()->requireSession();
    if ($method !== 'PUT' && $method !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
    $blocked = (bool) ($in['blocked'] ?? true);
    if ($secure) {
        if (!CSRF::validateToken($in['csrf_token'] ?? null)) {
            json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
        }
        $ok = cards_set_blocked_secure($pdo, $uid, $cardId, $blocked);
        json_response($ok ? ['ok' => true] : ['ok' => false, 'error' => 'Card not found.'], $ok ? 200 : 404);
    }
    cards_set_blocked_vuln($pdo, $cardId, $blocked);   // IDOR: any card
    json_response(['ok' => true, 'is_blocked' => $blocked]);
}

if (str_ends_with($path, '/pin')) {
    auth()->requireSession();
    if ($method !== 'PUT' && $method !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
    $newPin = (string) ($in['new_pin'] ?? '');
    if ($secure) {
        if (!CSRF::validateToken($in['csrf_token'] ?? null)) {
            json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
        }
        $res = cards_change_pin_secure($pdo, $uid, $cardId, (string) ($in['old_pin'] ?? ''), $newPin);
        json_response($res['ok'] ? ['ok' => true] : ['ok' => false, 'error' => $res['error']], $res['ok'] ? 200 : 400);
    }
    // VULN: no old PIN required, any card id.
    cards_change_pin_vuln($pdo, $cardId, $newPin);
    json_response(['ok' => true, 'note' => 'PIN changed without old PIN (cards L0).']);
}

if (str_ends_with($path, '/limit')) {
    auth()->requireSession();
    if ($method !== 'PUT' && $method !== 'POST') {
        json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
    }
    $limit = (float) ($in['spending_limit'] ?? $in['limit'] ?? 0);
    if ($secure) {
        if (!CSRF::validateToken($in['csrf_token'] ?? null)) {
            json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
        }
        $res = cards_set_limit_secure($pdo, $uid, $cardId, $limit);
        json_response($res['ok'] ? ['ok' => true, 'spending_limit' => $limit] : ['ok' => false, 'error' => $res['error']], $res['ok'] ? 200 : 400);
    }
    // VULN: trust any value, any card id.
    cards_set_limit_vuln($pdo, $cardId, $limit);
    json_response(['ok' => true, 'spending_limit' => $limit]);
}

// ---- detail: GET /api/v1/cards/{id} ----
if ($cardId > 0 && $method === 'GET') {
    $card = $secure ? cards_get_secure($pdo, $uid, $cardId) : cards_get_vuln($pdo, $cardId);
    if ($card === null) {
        json_response(['ok' => false, 'error' => 'Card not found or access denied.'], 404);
    }
    json_response(['ok' => true, 'card' => $card]);
}

json_response(['ok' => false, 'error' => 'Unsupported.'], 405);
