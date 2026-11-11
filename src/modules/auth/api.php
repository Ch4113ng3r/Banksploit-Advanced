<?php

declare(strict_types=1);

/**
 * JSON API for the auth module.
 *
 * One handler for all /api/v1/auth/* endpoints; it switches on the request
 * path and reuses the SAME vuln/secure functions as the HTML pages, so a
 * level change affects both surfaces identically.
 *
 *   POST /api/v1/auth/login            credential check (+ OTP issue)
 *   POST /api/v1/auth/verify-otp       verify login OTP -> issue JWT
 *   POST /api/v1/auth/register         create account
 *   POST /api/v1/auth/forgot-password  request reset token
 *   POST /api/v1/auth/reset-password   consume token, set password
 *
 * Vulns surfaced here: auth.l11 (OTP in response), auth.l12 (JWT alg:none),
 * auth.l17 (PII in JWT), auth.l13 (predictable reset token), auth.l16 (enum).
 */

require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/login_vuln.php';
require_once __DIR__ . '/login_secure.php';
require_once __DIR__ . '/register_vuln.php';
require_once __DIR__ . '/register_secure.php';
require_once __DIR__ . '/forgot_vuln.php';
require_once __DIR__ . '/forgot_secure.php';

$engine = engine();
$pdo    = db();
$in     = request_input();

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$path = rtrim($path, '/');

switch ($path) {

    // ----------------------------------------------------------- login -----
    case '/api/v1/auth/login': {
        $customerId = (string) ($in['customer_id'] ?? '');
        $password   = (string) ($in['password'] ?? '');

        $level  = $engine->getVulnLevel('auth', 'g1');
        $result = $level >= 3
            ? auth_login_secure($pdo, $engine, $customerId, $password)
            : auth_login_vuln($pdo, $engine, $customerId, $password);

        if (!$result['ok']) {
            json_response(['ok' => false, 'error' => $result['error']], 401);
        }

        $user = $result['user'];

        // CTF (auth.g1): If the login was achieved via SQLi (i.e. the vuln is
        // active AND the customer_id contained injection characters), plant the
        // flag in the response. This rewards the SQLi bypass.
        $g1Flag = null;
        if ($engine->isVulnerable('auth', 'g1') && preg_match("/['\";#-]/", $customerId)) {
            $g1Flag = Flags::generate('auth.g1', (int) $user['id']);
        }

        $code = auth_generate_otp($pdo, (int) $user['id'], 'login');
        Session::set('pending_login', [
            'id'          => (int) $user['id'],
            'customer_id' => $user['customer_id'],
            'name'        => $user['name'],
            'role'        => $user['role'],
        ]);

        // auth.l11: merge the leaked OTP into the response at vuln levels.
        $resp = ['ok' => true, 'otp_required' => true, 'customer_id' => $user['customer_id']];
        $resp = array_merge($resp, auth_otp_leak_payload($engine, $code, (int) $user['id']));
        // auth.l11 L2: OTP hash + sealed flag ship in response headers, not the body.
        foreach (auth_otp_leak_headers($engine, $code, (int) $user['id']) as $hk => $hv) {
            header($hk . ': ' . $hv);
        }
        // CTF (auth.g1): include flag when SQLi was used
        if ($g1Flag !== null && flags_delivered()) {
            $resp['ctf_flag'] = $g1Flag;
        }
        json_response($resp);
        break;
    }

    // ------------------------------------------------------ verify-otp -----
    case '/api/v1/auth/verify-otp': {
        $pending = Session::get('pending_login');
        if (!is_array($pending)) {
            json_response(['ok' => false, 'error' => 'No login in progress.'], 400);
        }
        $code = (string) ($in['otp'] ?? '');
        if (!auth_verify_otp($pdo, $engine, (int) $pending['id'], $code, 'login')) {
            json_response(['ok' => false, 'error' => 'Invalid or expired OTP.'], 401);
        }

        // CTF (auth.l10): flag for successful OTP verification (proving brute-force)
        $l10Flag = null;
        if ($engine->isVulnerable('auth', 'l10')) {
            $l10Flag = Flags::generate('auth.l10', (int) $pending['id']);
        }

        Session::remove('pending_login');
        Session::set('user', [
            'id'          => (int) $pending['id'],
            'customer_id' => $pending['customer_id'],
            'name'        => $pending['name'],
            'role'        => $pending['role'],
        ]);
        Session::onLogin();

        // Build JWT claims. auth.l17: at vuln levels we stuff PII (pan/aadhaar/
        // balance) into the token; Auth::generateJWT strips them at level 3.
        $claims = [
            'sub'  => $pending['customer_id'],
            'role' => $pending['role'],
            'iat'  => time(),
            'exp'  => time() + 3600,
        ];
        if ($engine->isVulnerable('auth', 'l17')) {
            $extra = $pdo->prepare(
                'SELECT u.pan, u.aadhaar_masked, COALESCE(SUM(a.balance),0) AS balance
                   FROM users u LEFT JOIN accounts a ON a.user_id = u.id
                  WHERE u.id = :id GROUP BY u.id'
            );
            $extra->execute([':id' => (int) $pending['id']]);
            if ($row = $extra->fetch()) {
                $claims['pan']     = $row['pan'];
                $claims['aadhaar'] = $row['aadhaar_masked'];
                $claims['balance'] = (float) $row['balance'];
            }
        }

        // auth.l12: generateJWT issues alg=none at vuln levels, HS256 at L3.
        $token = auth()->generateJWT($claims);

        $otpResp = [
            'ok'    => true,
            'token' => $token,
            'user'  => [
                'customer_id' => $pending['customer_id'],
                'name'        => $pending['name'],
                'role'        => $pending['role'],
            ],
        ];
        // CTF (auth.l10): include flag proving OTP brute-force
        if ($l10Flag !== null && flags_delivered()) {
            $otpResp['ctf_flag'] = $l10Flag;
        }
        json_response($otpResp);
        break;
    }

    // -------------------------------------------------------- register -----
    case '/api/v1/auth/register': {
        $level  = $engine->getVulnLevel('profile', 'l5');
        $result = $level >= 3
            ? auth_register_secure($pdo, $engine, $in)
            : auth_register_vuln($pdo, $engine, $in);

        if (!$result['ok']) {
            json_response(['ok' => false, 'error' => $result['error']], 400);
        }
        json_response([
            'ok'      => true,
            'user_id' => $result['user_id'],
            'role'    => $result['role'],     // reflects mass-assignment at vuln levels
            'upload'  => $result['upload'],
        ], 201);
        break;
    }

    // ------------------------------------------------- forgot-password -----
    case '/api/v1/auth/forgot-password': {
        $level  = $engine->getVulnLevel('auth', 'l13');
        $result = $level >= 3
            ? auth_forgot_secure($pdo, $engine, $in)
            : auth_forgot_vuln($pdo, $engine, $in);

        $resp = ['ok' => $result['ok'], 'message' => $result['message']];
        // Vuln levels leak the predictable token in the body.
        if (!empty($result['token'])) {
            $resp['reset_token'] = $result['token'];
        }
        json_response($resp, $result['ok'] || $result['enumerated'] ? 200 : 200);
        break;
    }

    // -------------------------------------------------- reset-password -----
    case '/api/v1/auth/reset-password': {
        $token    = (string) ($in['token'] ?? '');
        $password = (string) ($in['password'] ?? '');
        if (strlen($password) < 6) {
            json_response(['ok' => false, 'error' => 'Password too short.'], 400);
        }
        // Use the vuln-aware resolver (accepts predictable tokens at vuln levels)
        $userId = auth_resolve_reset_token($pdo, $engine, $token);
        if ($userId === null) {
            json_response(['ok' => false, 'error' => 'Invalid or expired token.'], 400);
        }
        $pdo->prepare(
            'UPDATE users SET password_hash = :ph, password_md5 = :pmd5,
                    failed_attempts = 0, is_locked = 0, locked_until = NULL
              WHERE id = :id'
        )->execute([
            ':ph'   => password_hash($password, PASSWORD_BCRYPT),
            ':pmd5' => md5($password),
            ':id'   => $userId,
        ]);
        // auth.l13 removed from CTF — no flag awarded for reset token exploitation.
        $resetResp = ['ok' => true, 'message' => 'Password updated.'];
        json_response($resetResp);
        break;
    }

    default:
        json_response(['ok' => false, 'error' => 'Unknown auth endpoint.'], 404);
}
