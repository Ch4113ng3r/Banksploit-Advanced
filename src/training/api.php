<?php

declare(strict_types=1);

/**
 * Training-platform JSON API.
 *
 *   GET  /api/v1/training/hints?vuln_id=...&level=N   progressive hint
 *   GET  /api/v1/training/progress                    all progress + stats + radar
 *   POST /api/v1/training/progress                    update one vuln's status
 *   POST /api/v1/training/reset                        reset all progress
 *   POST /api/v1/training/submit-flag                  submit a CTF flag
 *   GET  /api/v1/training/requests                     recent request_log (inspector AJAX)
 *   POST /api/v1/training/requests/clear               clear the request log
 *
 * Requires login (training is for an authenticated learner). These endpoints
 * are training instrumentation, not part of the vulnerable banking surface.
 */

require_once __DIR__ . '/_shared.php';

$pdo    = db();
$in     = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');

// set-level is the difficulty dial — disabled on CTF port; use CTF Control Panel.
if ($path === '/api/v1/training/set-level' && $method === 'POST') {
    json_response(['ok' => false, 'error' => 'Level changes disabled. Use the CTF Control Panel (port 8888).'], 403);
    $level = max(0, min(3, (int) ($in['level'] ?? 0)));
    $engine = engine();
    $targets = [];
    if (!empty($in['all'])) {
        foreach (tr_all_vulns() as $v) {
            $targets[] = $v['key'];
        }
    } else {
        foreach ((array) ($in['vulns'] ?? []) as $ref) {
            $v = tr_find_vuln((string) $ref);
            if ($v !== null) {
                $targets[] = $v['key'];
            }
        }
    }
    $applied = 0;
    foreach ($targets as $key) {
        [$mod, $vid] = explode('.', $key, 2);
        $engine->setVulnLevel($mod, $vid, $level);
        $applied++;
    }
    json_response(['ok' => true, 'level' => $level, 'applied' => $applied]);
}

// ---- submit flag -----------------------------------------------------------
// Handled BEFORE requireAuth(): auth.g1 (SQLi login bypass) delivers its flag on
// the OTP screen, where the login is only half-complete. requireAuth() would 302
// that POST to /login, and the flag modal's claim button — which expects JSON —
// would read the login page's HTML as a rejection. tr_submit_flag() does its own
// identity check (tr_submitting_user_id) and always answers in JSON.
if ($path === '/api/v1/training/submit-flag' && $method === 'POST') {
    $flag = (string) ($in['flag'] ?? '');
    $res = tr_submit_flag($pdo, $flag);
    json_response($res + ['stats' => tr_stats($pdo)]);
}

auth()->requireAuth();

// ---- hints (admin-only in CTF mode) ----------------------------------------
if ($path === '/api/v1/training/hints' && $method === 'GET') {
    if (!auth()->isAdmin()) {
        json_response(['ok' => false, 'error' => 'Admin role required.'], 403);
    }
    $vuln = (string) ($in['vuln_id'] ?? '');
    $idx  = (int) ($in['level'] ?? $in['index'] ?? 1);
    if ($idx < 1) { $idx = 1; }
    json_response(tr_get_hint($pdo, $vuln, $idx));
}

// ---- progress --------------------------------------------------------------
if ($path === '/api/v1/training/progress') {
    if ($method === 'GET') {
        json_response([
            'ok'       => true,
            'stats'    => tr_stats($pdo),
            'radar'    => tr_skill_radar($pdo),
            'progress' => tr_progress_map($pdo),
        ]);
    }
    if ($method === 'POST' || $method === 'PUT') {
        $vuln   = (string) ($in['vuln_id'] ?? '');
        $status = (string) ($in['status'] ?? '');
        $v = tr_find_vuln($vuln);
        if ($v === null) {
            json_response(['ok' => false, 'error' => 'Unknown vulnerability.'], 404);
        }
        if (!tr_set_status($pdo, $v['key'], $status)) {
            json_response(['ok' => false, 'error' => 'Invalid status.'], 400);
        }
        json_response(['ok' => true, 'vuln_id' => $v['key'], 'status' => $status, 'stats' => tr_stats($pdo)]);
    }
}

// ---- reset -----------------------------------------------------------------
if ($path === '/api/v1/training/reset' && $method === 'POST') {
    tr_reset($pdo);
    json_response(['ok' => true, 'stats' => tr_stats($pdo)]);
}

// (submit-flag and set-level are handled above, before requireAuth, so they
//  work pre-login too.)

// ---- inspector: recent requests -------------------------------------------
if ($path === '/api/v1/training/requests' && $method === 'GET') {
    $q      = (string) ($in['q'] ?? '');
    $mfil   = strtoupper((string) ($in['method'] ?? ''));
    $sfil   = (string) ($in['status'] ?? '');
    $sql = 'SELECT id, method, url, response_code, duration_ms, created_at
              FROM request_log WHERE 1=1';
    $bind = [];
    if ($q !== '')    { $sql .= ' AND url LIKE :q';     $bind[':q'] = '%' . $q . '%'; }
    if ($mfil !== '') { $sql .= ' AND method = :m';     $bind[':m'] = $mfil; }
    if ($sfil !== '') { $sql .= ' AND response_code = :c'; $bind[':c'] = (int) $sfil; }
    $sql .= ' ORDER BY id DESC LIMIT 100';
    $st = $pdo->prepare($sql);
    $st->execute($bind);
    json_response(['ok' => true, 'requests' => $st->fetchAll()]);
}

// ---- inspector: one request's full detail ---------------------------------
if (preg_match('#^/api/v1/training/requests/(\d+)$#', $path, $m) && $method === 'GET') {
    $st = $pdo->prepare('SELECT * FROM request_log WHERE id = :id');
    $st->execute([':id' => (int) $m[1]]);
    $row = $st->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Not found.'], 404);
    }
    json_response(['ok' => true, 'request' => $row]);
}

// ---- inspector: clear log --------------------------------------------------
if ($path === '/api/v1/training/requests/clear' && $method === 'POST') {
    $pdo->exec('DELETE FROM request_log');
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Unknown training endpoint.'], 404);
