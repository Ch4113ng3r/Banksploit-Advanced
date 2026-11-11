<?php

declare(strict_types=1);

/**
 * Admin JSON API.
 *
 *   GET  /api/v1/admin/users
 *   POST /api/v1/admin/users/{id}/suspend
 *   POST /api/v1/admin/users/{id}/unlock
 *   POST /api/v1/admin/users/{id}/reset-password
 *   GET  /api/v1/admin/audit-log
 *   GET  /api/v1/admin/config
 *   PUT  /api/v1/admin/config
 *   GET  /api/v1/admin/progress
 *   PUT  /api/v1/admin/vuln-config
 *
 * EVERY endpoint calls admin_guard(true) first. admin.l4: at levels 0-2 that
 * guard only requires login (NO role check) — the Broken Function-Level
 * Authorization vuln — so any authenticated user can call these. At level 3 it
 * enforces the admin role.
 */

require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/_reset.php';

admin_guard(true);   // <-- the L4 gate (login-only at vuln levels)

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$in     = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$id   = (int) (route_param('id') ?? 0);

// CSRF is enforced for state-changing calls only when admin.l4 is secured
// (mirrors the rest of the app: real protections appear together at level 3).
$secure = !$engine->isVulnerable('admin', 'l4');
$needCsrf = static function () use ($secure, $in): void {
    if ($secure && !CSRF::validateToken($in['csrf_token'] ?? null)) {
        json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
    }
};

// ---- BLOCKED: management-only actions (moved to CTF Control Panel) ----------
if ($path === '/api/v1/admin/reset-db' && $method === 'POST') {
    json_response(['ok' => false, 'error' => 'Database reset is available on the CTF Control Panel only.'], 403);
}
if ($path === '/api/v1/admin/download-source' && $method === 'GET') {
    json_response(['ok' => false, 'error' => 'Source download is available on the CTF Control Panel only.'], 403);
}

// ---- users (read-only, admin user hidden) -----------------------------------
if ($path === '/api/v1/admin/users' && $method === 'GET') {
    $stmt = $pdo->query("SELECT id, name, customer_id, email, role, is_locked, is_suspended FROM users WHERE role != 'admin' ORDER BY id");
    json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
}

// ---- BLOCKED: user management actions (moved to CTF Control Panel) ----------
if (str_ends_with($path, '/suspend') && $method === 'POST') {
    json_response(['ok' => false, 'error' => 'User management actions are available on the CTF Control Panel only.'], 403);
}
if (str_ends_with($path, '/unlock') && $method === 'POST') {
    json_response(['ok' => false, 'error' => 'User management actions are available on the CTF Control Panel only.'], 403);
}
if (str_ends_with($path, '/reset-password') && $method === 'POST') {
    json_response(['ok' => false, 'error' => 'User management actions are available on the CTF Control Panel only.'], 403);
}

// ---- audit log -------------------------------------------------------------
if ($path === '/api/v1/admin/audit-log' && $method === 'GET') {
    $res = admin_audit_search($pdo, $engine, $in);
    $out = ['ok' => $res['error'] === null, 'entries' => $res['rows']];
    if ($res['error'] !== null) {
        $out['error'] = $res['error'];   // verbose at core.l18
    }
    json_response($out);
}

// ---- BLOCKED: system config (management-only) -------------------------------
if ($path === '/api/v1/admin/config') {
    json_response(['ok' => false, 'error' => 'System config is available on the CTF Control Panel only.'], 403);
}

// ---- BLOCKED: progress management (management-only) -------------------------
if ($path === '/api/v1/admin/progress') {
    json_response(['ok' => false, 'error' => 'Progress management is available on the CTF Control Panel only.'], 403);
}

// ---- BLOCKED: vuln config (management-only) ---------------------------------
if ($path === '/api/v1/admin/vuln-config' && ($method === 'PUT' || $method === 'POST')) {
    json_response(['ok' => false, 'error' => 'Vulnerability config is available on the CTF Control Panel only.'], 403);
}

json_response(['ok' => false, 'error' => 'Unknown admin endpoint.'], 404);
