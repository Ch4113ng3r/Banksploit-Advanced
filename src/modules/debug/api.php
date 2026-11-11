<?php

declare(strict_types=1);

/**
 * Debug endpoints — information disclosure (core.l18 / accounts.l19).
 *
 *   GET /api/v1/debug/config   full app config incl. DB creds + JWT secret
 *   GET /api/v1/debug/phpinfo  runs phpinfo()
 *   GET /api/v1/debug/whoami   the session user with ALL internal fields
 *
 * ============================================================================
 *  VULNERABILITY — core.l18 Information disclosure (CWE-209/200)
 * ----------------------------------------------------------------------------
 *  At vuln levels these "left on by the developers" debug endpoints dump the
 *  entire environment: database connection string, JWT signing secret, full
 *  phpinfo(), and user records with internal-only columns (role, password_md5,
 *  last_login_ip, ...). At level 3 they are DISABLED (404) entirely.
 * ============================================================================
 */

$engine = engine();
$pdo    = db();

// At the secure level, debug endpoints simply do not exist.
if (!$engine->isVulnerable('core', 'l18')) {
    json_response(['ok' => false, 'error' => 'Not found.'], 404);
}

// NOTE: deliberately NO auth check at vuln levels — these are "forgotten" debug
// routes, reachable by anyone (that's part of the disclosure problem).

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');

// @vuln-start: debug config dump — DB creds + JWT secret leaked (core.l18)
if ($path === '/api/v1/debug/config') {
    json_response([
        'ok'     => true,
        'app'    => [
            'name'    => defined('APP_NAME') ? APP_NAME : null,
            'env'     => getenv('APP_ENV'),
            'debug'   => getenv('APP_DEBUG'),
            'url'     => defined('APP_URL') ? APP_URL : null,
        ],
        'database' => [
            'host'     => getenv('DB_HOST'),
            'port'     => getenv('DB_PORT'),
            'name'     => getenv('DB_NAME'),
            'user'     => getenv('DB_USER'),
            'password' => getenv('DB_PASS'),               // <-- leaked
            'dsn'      => 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME'),
        ],
        'secrets' => [
            'jwt_secret' => getenv('JWT_SECRET'),          // <-- leaked
        ],
        'php_version' => PHP_VERSION,
        'loaded_extensions' => get_loaded_extensions(),
    ]);
}
// @vuln-end

if ($path === '/api/v1/debug/phpinfo') {
    // @vuln-start: phpinfo() exposed — full environment disclosure (core.l18)
    header('Content-Type: text/html; charset=utf-8');
    phpinfo();
    exit;
    // @vuln-end
}

if ($path === '/api/v1/debug/whoami') {
    $u = auth()->getCurrentUser();
    if ($u === null) {
        json_response(['ok' => false, 'error' => 'Not logged in.'], 401);
    }
    // Full row incl. internal columns (accounts.l19 — hidden API fields).
    $s = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $s->execute([':id' => (int) $u['id']]);
    json_response(['ok' => true, 'user' => $s->fetch()]);
}

json_response(['ok' => false, 'error' => 'Unknown debug endpoint.'], 404);
