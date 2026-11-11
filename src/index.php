<?php

declare(strict_types=1);

/**
 * BankSploit front controller.
 *
 * All non-file requests are routed here by .htaccess.
 */

require __DIR__ . '/config/app.php';
require __DIR__ . '/core/Router.php';
require __DIR__ . '/core/TrainingEngine.php';
require __DIR__ . '/core/Session.php';
require __DIR__ . '/core/Auth.php';
require __DIR__ . '/core/CSRF.php';
require __DIR__ . '/core/RequestLogger.php';
require __DIR__ . '/core/Flags.php';
require __DIR__ . '/core/Modes.php';
require __DIR__ . '/core/Sandbox.php';
require __DIR__ . '/core/helpers.php';

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// --- Bootstrap the framework ------------------------------------------------

$pdo    = require __DIR__ . '/config/database.php';
$engine = TrainingEngine::getInstance();         // loads vuln_config.json once
$auth   = new Auth($pdo, $engine);

// Session must start before anything reads/writes it. Hardening (httponly,
// id regeneration) is applied by Session based on the auth.l14 level.
Session::start();

// Make shared services reachable from route handler files.
bs_register('db', $pdo);
bs_register('engine', $engine);
bs_register('auth', $auth);

// Seed a CLIENT-VISIBLE, EDITABLE `is_admin=false` cookie on every response.
// This is bait for the admin.l4 level-1 weakness: the cookie ships as false,
// so a curious user can flip it to true (devtools / proxy) and discover that
// the naive server-side gate trusts it. It is NOT httponly (so JS/devtools can
// see and change it) and is harmless everywhere except admin.l4 level 1, where
// admin_client_claims_admin() reads it. Only set it for authenticated users
// who aren't already admins (a real admin doesn't need the bait).
if ($auth->isAuthenticated() && !$auth->isAdmin() && !isset($_COOKIE['is_admin'])) {
    setcookie('is_admin', 'false', ['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
}

// --- Request logging middleware (DISABLED) ---------------------------------
// Capture the whole response in an output buffer and log it (plus duration)
// on shutdown — this also fires for handlers that exit() during a redirect.
// DISABLED: uncomment to enable request inspector logging

// $logger = new RequestLogger($pdo);
// $logger->begin();
// ob_start();
// register_shutdown_function(static function () use ($logger): void {
//     $body = ob_get_contents();
//     $logger->finish(is_string($body) ? $body : '', http_response_code() ?: 200);
//     // Buffer is flushed by PHP after shutdown functions run.
// });

$router = new Router();

// --- Feature toggle middleware (check before routing) ---
// This intercepts requests for disabled features
if (in_array($_SERVER['REQUEST_URI'] ?? '', ['/lab', '/training', '/training/code', '/inspector'], true)) {
    if (!is_feature_enabled('training_lab')) {
        http_response_code(403);
        render_layout('Feature Disabled', '<div class="text-center py-16"><h2 class="text-2xl font-bold text-red-400 mb-4">🔒 Training Lab is Disabled</h2><p class="text-slate-400">The instructor has temporarily disabled this feature. Please check back later.</p></div>', ['active' => '']);
        exit;
    }
}

if (strpos($_SERVER['REQUEST_URI'] ?? '', '/leaderboard') === 0) {
    if (!is_feature_enabled('leaderboard')) {
        http_response_code(403);
        render_layout('Feature Disabled', '<div class="text-center py-16"><h2 class="text-2xl font-bold text-red-400 mb-4">🔒 Leaderboard is Disabled</h2><p class="text-slate-400">The instructor has temporarily disabled this feature. Please check back later.</p></div>', ['active' => '']);
        exit;
    }
}

if (strpos($_SERVER['REQUEST_URI'] ?? '', '/swagger-ui') === 0) {
    if (!is_feature_enabled('swagger_docs')) {
        http_response_code(403);
        json_response(['ok' => false, 'error' => 'API documentation is currently disabled'], 403);
        exit;
    }
}

if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/v1/training/submit-flag') === 0) {
    if (!is_feature_enabled('flag_submission')) {
        http_response_code(403);
        json_response(['ok' => false, 'error' => 'Flag submission is currently disabled'], 403);
        exit;
    }
}

// Welcome page.
$router->get('/', function (): void {
    welcome_page();
});

// --- Dynamic JS (auth.l11 OTP leak in JS file) ----------------------------
$router->get('/public/js/otp-validation.js', 'modules/auth/otp_js.php');

// --- Auth module (HTML pages) ----------------------------------------------
$router->get('/otp-inbox', 'modules/auth/otp_inbox.php');
$router->get('/login', 'modules/auth/login.php');
$router->post('/login', 'modules/auth/login.php');
$router->get('/forgot-password', 'modules/auth/forgot.php');
$router->post('/forgot-password', 'modules/auth/forgot.php');
$router->get('/forgot-password/otp', 'modules/auth/forgot.php');
$router->post('/forgot-password/otp', 'modules/auth/forgot.php');
$router->get('/forgot-password/questions', 'modules/auth/forgot.php');
$router->post('/forgot-password/questions', 'modules/auth/forgot.php');
$router->get('/forgot-password/reset', 'modules/auth/forgot.php');
$router->post('/forgot-password/reset', 'modules/auth/forgot.php');
$router->get('/reset-password', 'modules/auth/reset.php');
$router->post('/reset-password', 'modules/auth/reset.php');
$router->get('/logout', 'modules/auth/logout.php');
$router->post('/logout', 'modules/auth/logout.php');

// --- Auth module (JSON API) ------------------------------------------------
// Removed: /api/v1/auth/login (SQLi vulnerability - use HTML form only)
$router->post('/api/v1/auth/verify-otp', 'modules/auth/api.php');
$router->post('/api/v1/auth/forgot-password', 'modules/auth/api.php');
$router->post('/api/v1/auth/reset-password', 'modules/auth/api.php');
$router->get('/api/v1/auth/vault', 'modules/auth/ctf_vault.php');

// --- Dashboard / Profile / Statements / Leaderboard (HTML pages) -----------
$router->get('/dashboard', 'modules/dashboard/index.php');
$router->get('/profile', 'modules/dashboard/profile.php');
$router->post('/profile', 'modules/dashboard/profile.php');
$router->get('/statements', 'modules/statements/index.php');
$router->get('/leaderboard', 'modules/leaderboard/index.php');

// --- Profile API -----------------------------------------------------------
$router->get('/api/v1/profile', 'modules/dashboard/profile_api.php');
$router->put('/api/v1/profile', 'modules/dashboard/profile_api.php');
$router->post('/api/v1/profile', 'modules/dashboard/profile_api.php');

// --- Leaderboard API ---------------------------------------------------------
$router->get('/api/v1/leaderboard', 'modules/leaderboard/api.php');

// --- Accounts API (HTML form handling only - no direct API calls) -----------
// Export endpoints route through HTML forms only, not direct JSON API.
$router->get('/api/v1/accounts/{id}/export', 'modules/accounts/api.php');
$router->get('/api/v1/accounts/{id}/export-pdf', 'modules/accounts/api.php');

// --- Transfer + Beneficiaries (HTML pages) ---------------------------------
$router->get('/transfer', 'modules/transfer/index.php');
$router->post('/transfer', 'modules/transfer/index.php');
$router->get('/beneficiaries', 'modules/transfer/beneficiary.php');
$router->post('/beneficiaries', 'modules/transfer/beneficiary.php');

// --- Transfer API ----------------------------------------------------------
$router->post('/api/v1/transfer/initiate', 'modules/transfer/api.php');
$router->post('/api/v1/transfer/confirm', 'modules/transfer/api.php');
$router->post('/api/v1/transfer/verify-otp', 'modules/transfer/api.php');
$router->post('/api/v1/transfer/execute', 'modules/transfer/api.php');
$router->get('/api/v1/transfer/history', 'modules/transfer/api.php');
$router->get('/api/v1/transfer/{id}/receipt', 'modules/transfer/api.php');

// --- Beneficiary API -------------------------------------------------------
$router->get('/api/v1/beneficiaries', 'modules/transfer/beneficiary.php');
$router->post('/api/v1/beneficiaries', 'modules/transfer/beneficiary.php');
$router->get('/api/v1/beneficiaries/{id}', 'modules/transfer/beneficiary.php');
$router->delete('/api/v1/beneficiaries/{id}', 'modules/transfer/beneficiary.php');

// --- Cards (HTML + API) ----------------------------------------------------
$router->get('/cards', 'modules/cards/index.php');
$router->get('/api/v1/cards', 'modules/cards/api.php');
$router->get('/api/v1/cards/{id}', 'modules/cards/api.php');
$router->put('/api/v1/cards/{id}/block', 'modules/cards/api.php');
$router->put('/api/v1/cards/{id}/pin', 'modules/cards/api.php');
$router->put('/api/v1/cards/{id}/limit', 'modules/cards/api.php');

// --- Loans (HTML + API) ----------------------------------------------------
$router->get('/loans', 'modules/loans/index.php');
$router->post('/loans', 'modules/loans/index.php');
$router->post('/api/v1/loans/apply', 'modules/loans/api.php');
$router->get('/api/v1/loans', 'modules/loans/api.php');
$router->get('/api/v1/loans/emi-calculator', 'modules/loans/api.php');
$router->get('/api/v1/loans/{id}', 'modules/loans/api.php');
$router->post('/api/v1/loans/{id}/documents', 'modules/loans/api.php');

// --- Payments (HTML + API) -------------------------------------------------
$router->get('/payments', 'modules/payments/index.php');
$router->post('/payments', 'modules/payments/index.php');
$router->post('/api/v1/payments/pay', 'modules/payments/api.php');
$router->get('/api/v1/payments/history', 'modules/payments/api.php');

// --- Admin (HTML pages) — stripped for CTF; only user management + audit ----
$router->get('/admin', 'modules/admin/index.php');
$router->get('/admin/audit', 'modules/admin/audit.php');
$router->get('/admin/vault', 'modules/admin/vault.php');

// --- Admin API — user management + audit only (vuln/config/progress blocked) --
$router->get('/api/v1/admin/users', 'modules/admin/api.php');
$router->post('/api/v1/admin/users/{id}/suspend', 'modules/admin/api.php');
$router->post('/api/v1/admin/users/{id}/unlock', 'modules/admin/api.php');
$router->post('/api/v1/admin/users/{id}/reset-password', 'modules/admin/api.php');
$router->get('/api/v1/admin/audit-log', 'modules/admin/api.php');
// Blocked endpoints still route to api.php (returns 403 with message)
$router->get('/api/v1/admin/config', 'modules/admin/api.php');
$router->put('/api/v1/admin/config', 'modules/admin/api.php');
$router->get('/api/v1/admin/progress', 'modules/admin/api.php');
$router->put('/api/v1/admin/progress', 'modules/admin/api.php');
$router->put('/api/v1/admin/vuln-config', 'modules/admin/api.php');
$router->post('/api/v1/admin/reset-db', 'modules/admin/api.php');
$router->get('/api/v1/admin/download-source', 'modules/admin/api.php');

// --- Pages (LFI g11) / Preferences (deserialization g12) -------------------
$router->get('/pages', 'modules/pages/index.php');
$router->get('/preferences', 'modules/prefs/index.php');
$router->post('/preferences', 'modules/prefs/index.php');

// --- Debug endpoints (info disclosure l18/l19) -----------------------------
$router->get('/api/v1/debug/config', 'modules/debug/api.php');
$router->get('/api/v1/debug/phpinfo', 'modules/debug/api.php');
$router->get('/api/v1/debug/whoami', 'modules/debug/api.php');
// Unauthenticated session viewer — teaching aid for session puzzling (auth.l25).
$router->get('/debug/session', 'modules/debug/session.php');

// --- Helper endpoints for OTP display ------------------------------------------
$router->get('/get-transfer-otp', function() {
    auth()->requireAuth();
    $me = auth()->getCurrentUser();
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT otp_code FROM otp_tokens
          WHERE user_id = :uid AND purpose = :p AND is_used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':uid' => (int) $me['id'], ':p' => 'transfer']);
    $otpRow = $stmt->fetch();

    header('Content-Type: application/json');
    if ($otpRow) {
        echo json_encode(['ok' => true, 'otp' => $otpRow['otp_code']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No pending OTP']);
    }
});

$router->get('/get-login-otp', function() {
    // For login OTP, user is not yet fully authenticated (only pending_login session)
    // So we just check the session state without requiring full auth
    if (!Session::has('pending_login')) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No pending login']);
        return;
    }

    $pending = Session::get('pending_login');
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT otp_code FROM otp_tokens
          WHERE user_id = :uid AND purpose = :p AND is_used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':uid' => (int) $pending['id'], ':p' => 'login']);
    $otpRow = $stmt->fetch();

    header('Content-Type: application/json');
    if ($otpRow) {
        echo json_encode(['ok' => true, 'otp' => $otpRow['otp_code']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No pending OTP']);
    }
});

$router->get('/get-reset-otp', function() {
    // Forgot-password OTP: the user is mid-reset-flow (only a 'pwreset' session),
    // not authenticated. Mirror /get-login-otp but for purpose 'reset'.
    if (!Session::has('recovery_uid')) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'No reset in progress']);
        return;
    }

    $uid = (int) Session::get('recovery_uid', 0);
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT otp_code FROM otp_tokens
          WHERE user_id = :uid AND purpose = :p AND is_used = 0 AND expires_at > NOW()
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':uid' => $uid, ':p' => 'reset']);
    $otpRow = $stmt->fetch();

    header('Content-Type: application/json');
    if ($otpRow) {
        echo json_encode(['ok' => true, 'otp' => $otpRow['otp_code']]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No pending OTP']);
    }
});

// --- API docs --------------------------------------------------------------
$router->get('/swagger-ui', 'modules/misc/swagger.php');
$router->get('/api/swagger.json', 'modules/misc/swagger.php');

// --- Training platform (HTML pages) ----------------------------------------
$router->get('/lab', 'training/lab.php');
$router->get('/training/code', 'training/code_compare.php');
$router->get('/inspector', 'training/inspector.php');

// --- Training API ----------------------------------------------------------
$router->get('/api/v1/training/hints', 'training/api.php');
$router->get('/api/v1/training/progress', 'training/api.php');
$router->post('/api/v1/training/progress', 'training/api.php');
$router->post('/api/v1/training/reset', 'training/api.php');
$router->post('/api/v1/training/submit-flag', 'training/api.php');
$router->post('/api/v1/training/set-level', 'training/api.php');
$router->get('/api/v1/training/requests', 'training/api.php');
$router->get('/api/v1/training/requests/{id}', 'training/api.php');
$router->post('/api/v1/training/requests/clear', 'training/api.php');

// Fallback for unknown routes. IMPORTANT: a 404 must NOT log the user out.
// The session is untouched here; when authenticated we render the "not found"
// message *inside the app shell* (so the sidebar/session stay intact and the
// user can navigate on), and only link to /login when genuinely logged out.
$router->setNotFound(function (string $method, string $path) use ($auth): void {
    $where = htmlspecialchars($method . ' ' . $path);

    // JSON clients get a JSON 404 (don't dump HTML into an API consumer).
    if (function_exists('wants_json') && wants_json()) {
        json_response(['ok' => false, 'error' => 'Not found', 'path' => $path], 404);
    }

    if ($auth->isAuthenticated()) {
        // Authenticated: stay in the app, keep the session, link to dashboard.
        $url = htmlspecialchars(APP_URL);
        $content = <<<HTML
            <div class="text-center py-16">
                <div class="text-6xl font-black text-slate-700 mb-4">404</div>
                <h2 class="text-2xl font-bold text-white mb-2">Page not found</h2>
                <p class="text-slate-400 mb-1">This page isn't built yet (or the link is wrong).</p>
                <p class="text-slate-500 text-sm mb-6"><code>{$where}</code></p>
                <a href="{$url}/dashboard" class="inline-block rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold px-5 py-2.5 transition">Back to dashboard</a>
                <p class="text-slate-600 text-xs mt-4">You are still signed in.</p>
            </div>
HTML;
        render_layout('Not found', $content, ['active' => '']);
        return;
    }

    // Unauthenticated: plain page linking to login.
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'
        . APP_NAME . ' &middot; 404</title></head><body style="font-family:system-ui;background:#0b1020;color:#e6e6e6;text-align:center;padding:4rem">'
        . '<h1>404 &mdash; Not Found</h1>'
        . '<p><code>' . $where . '</code> did not match any route.</p>'
        . '<p><a style="color:#7aa2f7" href="' . APP_URL . '/login">Go to login</a></p>'
        . '</body></html>';
});

// Global error handling. core.l18: at vuln levels an uncaught exception dumps
// the full message + stack trace + file paths; at the secure level it shows a
// generic page and logs the detail server-side only.
try {
    $router->dispatch();
} catch (Throwable $e) {
    http_response_code(500);
    $verbose = $engine->isVulnerable('core', 'l18');
    if (wants_json()) {
        $payload = ['ok' => false, 'error' => 'Internal server error'];
        if ($verbose) {
            $payload['exception'] = $e->getMessage();
            $payload['file']      = $e->getFile() . ':' . $e->getLine();
            $payload['trace']     = explode("\n", $e->getTraceAsString());
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    } elseif ($verbose) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><body style="font-family:monospace;background:#0b1020;color:#f7768e;padding:2rem">'
            . '<h1>Unhandled Exception</h1><p style="color:#e6e6e6">' . htmlspecialchars($e->getMessage()) . '</p>'
            . '<pre style="color:#9aa5c4;white-space:pre-wrap">' . htmlspecialchars($e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()) . '</pre></body>';
    } else {
        error_log('[banksploit] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><body style="font-family:system-ui;background:#0b1020;color:#e6e6e6;text-align:center;padding:4rem">'
            . '<h1>Something went wrong</h1><p>Please try again later.</p></body>';
    }
}

/**
 * Render the welcome page.
 */
function welcome_page(): void
{
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(APP_NAME) ?> is running</title>
    <style>
        :root { color-scheme: dark; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #0b1020;
            color: #e6e6e6;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #131a30;
            border: 1px solid #233056;
            border-radius: 16px;
            padding: 2.5rem 3rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        h1 { margin: 0 0 .25rem; font-size: 2rem; }
        .tag { color: #7aa2f7; font-weight: 600; letter-spacing: .04em; }
        .meta { margin-top: 1.25rem; font-size: .9rem; color: #9aa5c4; }
        .meta code { color: #c0caf5; }
        .warn {
            margin-top: 1.5rem;
            font-size: .8rem;
            color: #f7768e;
            border-top: 1px solid #233056;
            padding-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="tag">&#128176; <?= htmlspecialchars(APP_NAME) ?> is running</div>
        <h1>Welcome to <?= htmlspecialchars(APP_NAME) ?></h1>
        <div class="meta">
            Version <code><?= htmlspecialchars(APP_VERSION) ?></code> &middot;
            Env <code><?= htmlspecialchars(APP_ENV) ?></code><br>
            <code><?= htmlspecialchars(APP_URL) ?></code>
        </div>
        <p style="margin-top:1.5rem">
            <a style="color:#7aa2f7" href="<?= htmlspecialchars(APP_URL) ?>/login">Go to login &rarr;</a>
        </p>
        <div class="warn">
            &#9888; Intentionally vulnerable application &mdash; for local security training only.
        </div>
    </div>
</body>
</html>
    <?php
}
