<?php

declare(strict_types=1);

/**
 * Login page (HTML) — dispatcher + two-phase OTP flow.
 *
 * Phase 1: customer_id + password  -> credential check (vuln or secure)
 * Phase 2: otp                     -> verify the login OTP, finish sign-in
 *
 * The dispatcher checks TrainingEngine->getVulnLevel('auth','g1') and routes
 * to auth_login_vuln() (levels 0-2) or auth_login_secure() (level 3). Shared
 * OTP logic (incl. the auth.l11 leak and auth.l10 no-rate-limit) is in
 * _shared.php so the JSON API behaves identically.
 */

require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/login_vuln.php';
require_once __DIR__ . '/login_secure.php';

$engine = engine();
$pdo    = db();
$error  = null;
$otpLeak = null;                       // shown on-page at vuln levels (l11)
$phase  = Session::has('pending_login') ? 'otp' : 'credentials';

// OTP-stage timeout: the half-authenticated "pending_login" state must not live
// forever. The window is instructor-tunable via System Config
// (otp_expiry_seconds); it expires on that window — or immediately if the state
// predates this timestamping (a stale session has no 'ts'). Runs before POST
// handling so an expired OTP submission is rejected too. On expiry the WHOLE
// session is destroyed (like logout), not just the pending_login key.
$otpTtl = config_int('otp_expiry_seconds', 300);
if (Session::has('pending_login')) {
    $pl  = Session::get('pending_login');
    $ts  = (int) ($pl['ts'] ?? 0);
    if ($ts === 0 || (time() - $ts) > $otpTtl) {
        Session::destroy();    // tear down every session variable
        Session::start();      // fresh session...
        Session::regenerate(); // ...with a new id/cookie, to carry the notice
        flash('error', 'Your verification session timed out. Please sign in again.');
        redirect('/login');
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // CSRF is a no-op at level 0 (transfer.g5); enforced at level 3.
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } elseif (input('otp') !== null && Session::has('pending_login')) {
        // ---- Phase 2: OTP verification ----
        $pending = Session::get('pending_login');
        $ok = auth_verify_otp($pdo, $engine, (int) $pending['id'], (string) input('otp'), 'login');
        if ($ok) {
            // CTF (auth.l10): no rate limit on OTP verification — award flag.
            // We return a JSON response here (not a redirect) so the brute-force
            // script can read the flag from the response body. This makes both
            // the HTML form POST path and fetch() work for flag capture.
            $l10Flag = null;
            if ($engine->isVulnerable('auth', 'l10')) {
                $l10Flag = Flags::generate('auth.l10', (int) $pending['id']);
            }

            Session::remove('pending_login');
            // @vuln-start: [L0] the normal login also writes $_SESSION['user'] (auth.l25)
            // @fix-start: [L0] the auth session is created ONLY here, after verification (auth.l25)
            // This is the same session key the forgot-password step sets — but login
            // verifies the password AND the OTP first. The recovery flow must never
            // create it; completing a reset should send the user back here to log in.
            Session::set('user', [
                'id'          => (int) $pending['id'],
                'customer_id' => $pending['customer_id'],
                'name'        => $pending['name'],
                'role'        => $pending['role'],
            ]);
            // @vuln-end
            // @fix-end
            Session::onLogin();        // regenerates id at secure level (l14)

            // Issue a JWT and drop it in a browser-visible `access_token` cookie
            // so the whole auth.l12 / auth.l17 flow is reproducible purely in the
            // browser: decode the cookie to read its claims (PII at vuln levels —
            // auth.l17), or forge it (alg:none at vuln levels — auth.l12: change
            // role to admin, then delete the PHPSESSID cookie so the JWT path
            // authenticates you). The cookie is intentionally NOT HttpOnly so
            // DevTools/JS can read and tamper it. At level 3 it's HS256-signed.
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
            setcookie('access_token', auth()->generateJWT($claims), [
                'path' => '/', 'httponly' => false, 'samesite' => 'Lax',
            ]);

            // If this is a fetch/XHR request (brute-force script), return JSON
            // so the flag is readable. If it's a normal browser form submit,
            // redirect as usual.
            $isAjax = (
                str_contains(($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                || str_contains(($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
            );
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                $resp = ['ok' => true, 'redirect' => APP_URL . '/dashboard'];
                if ($l10Flag && flags_delivered()) {
                    $resp['ctf_flag'] = $l10Flag;
                    $resp['ctf_vuln'] = 'auth.l10';
                }
                echo json_encode($resp);
                exit;
            }

            // Normal browser form POST — redirect to dashboard
            if ($l10Flag) {
                $_SESSION['login_ctf_flag'] = $l10Flag;
                $_SESSION['login_ctf_vuln'] = 'auth.l10';
                if (flags_delivered()) { header('X-CTF-Flag: ' . $l10Flag); }
            }
            redirect('/dashboard');
        }
        $error = 'Invalid or expired OTP.';
        $phase = 'otp';
    } else {
        // ---- Phase 1: credential check ----
        $customerId = (string) input('customer_id', '');
        $password   = (string) input('password', '');

        $level  = $engine->getVulnLevel('auth', 'g1');
        $result = $level >= 3
            ? auth_login_secure($pdo, $engine, $customerId, $password)
            : auth_login_vuln($pdo, $engine, $customerId, $password);

        if ($result['ok']) {
            $user = $result['user'];

            // Starting a fresh login abandons any in-progress password-recovery
            // journey — clear its state (and any un-consumed L0 takeover flag from
            // auth.l25) so it can't leak into this session or fire a stale flag.
            foreach (['l25_takeover_flag', 'recovery_uid', 'recovery_stage', 'recovery_stage_uid', 'recovery_ts'] as $staleKey) {
                Session::remove($staleKey);
            }

            // CTF (auth.g1): SQLi detection for HTML path.
            // Legitimate customer IDs are always alphanumeric (e.g. BS100234).
            // If the input is NOT alphanumeric yet still returned a valid user
            // row from the database, that's conclusive proof of SQL injection.
            // Store flag in session to display on OTP page.
            if ($engine->isVulnerable('auth', 'g1') && !ctype_alnum($customerId)) {
                $_SESSION['login_ctf_flag'] = Flags::generate('auth.g1', (int) $user['id']);
                $_SESSION['login_ctf_vuln'] = 'auth.g1';
                if (flags_delivered()) { header('X-CTF-Flag: ' . $_SESSION['login_ctf_flag']); }
            }

            // Generate the login OTP and stash the half-authenticated user.
            $code = auth_generate_otp($pdo, (int) $user['id'], 'login');
            Session::set('pending_login', [
                'id'          => (int) $user['id'],
                'customer_id' => $user['customer_id'],
                'name'        => $user['name'],
                'role'        => $user['role'],
                'ts'          => time(),   // OTP-stage timeout anchor (System Config: otp_expiry_seconds)
            ]);
            // auth.l11: at vuln levels the OTP leaks to the client. On the HTML
            // page we surface the leaked value in a banner (mirrors the API).
            $leak = auth_otp_leak_payload($engine, $code);
            if ($leak !== []) {
                $otpLeak = $code;
            }
            $phase = 'otp';
        } else {
            $error = $result['error'] ?? 'Invalid credentials.';
            $phase = 'credentials';
        }
    }
}

// ---------------------------------------------------------------- render ----

$g1Level = $engine->getVulnLevel('auth', 'g1');
$url     = htmlspecialchars(APP_URL);
$csrf    = CSRF::getHiddenField();

$alert = $error !== null
    ? '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">'
      . htmlspecialchars($error) . '</div>'
    : '';
// Surface one-shot flashes (e.g. the OTP-timeout notice set before redirect).
foreach (take_flashes() as $f) {
    $cls = ($f['type'] ?? '') === 'error'
        ? 'border-red-500/40 bg-red-500/10 text-red-300'
        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300';
    $alert .= '<div class="mb-4 rounded-lg border ' . $cls . ' px-3 py-2 text-sm">'
        . htmlspecialchars((string) ($f['message'] ?? '')) . '</div>';
}

// auth.l11 — OTP leaked via a JAVASCRIPT FILE loaded on the OTP page.
// The OTP is dynamically injected into /public/js/otp-validation.js by
// otp_js.php (the SESSION_INJECT placeholder is replaced per-session).
// Discoverable ONLY by reading the JS file in DevTools (Sources tab or
// Network tab) — NOT visible in the HTML source.
// L0: OTP in the _otp field of SESSION_DATA inside the JS file.
// L1: OTP disguised as _dbg in SESSION_DATA inside the JS file.
// L2/L3: not leaked (both fields null).
//
// The script tag is included on EVERY OTP page render (not just POST response)
// so participants can discover it on page load/refresh. The otp_js.php handler
// reads the session to decide whether to inject the OTP.
$otpRawLeak = '';
if ($phase === 'otp' && $engine->isVulnerable('auth', 'l11')) {
    $otpRawLeak = "\n<script src=\"/public/js/otp-validation.js\"></script>\n";
}

// No visible OTP banner on page — the OTP leaks ONLY via the JS file
// (auth.l11). Participants must inspect the loaded script in DevTools.
$otpBanner = '';

if ($phase === 'otp') {
    $pending = Session::get('pending_login');
    $who = $pending ? htmlspecialchars((string) $pending['customer_id']) : '';

    // Check for CTF flags to display
    $ctfFlag = $_SESSION['login_ctf_flag'] ?? null;
    $ctfVuln = $_SESSION['login_ctf_vuln'] ?? null;
    $flagScript = '';
    if ($ctfFlag !== null && flags_delivered()) {
        $ctfFlagEscaped = htmlspecialchars($ctfFlag);
        $ctfVulnEscaped = $ctfVuln ? '\'' . htmlspecialchars($ctfVuln) . '\'' : 'null';
        $flagScript = <<<JS
        <script>
        (function() {
            const flag = '$ctfFlagEscaped';
            const vulnId = $ctfVulnEscaped;
            function showFlag() {
                if (typeof displayFlag === 'function') {
                    displayFlag(flag, vulnId);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', showFlag);
            } else {
                setTimeout(showFlag, 100);
            }
        })();
        </script>
JS;
        unset($_SESSION['login_ctf_flag']);
        unset($_SESSION['login_ctf_vuln']);
    }

    $form = <<<HTML
        {$alert}
        {$otpRawLeak}
        <p class="text-sm text-slate-400 mb-4">Enter the 4-digit OTP sent to the phone on file for <span class="font-mono text-slate-200">{$who}</span>.</p>
        <!-- OTP timeout info: hidden from display, generic message only -->
        <div class="mb-4 rounded-lg bg-amber-500/10 border border-amber-500/30 p-3">
            <p class="text-xs text-amber-200">⚠️ The OTP remains valid for 24 hours max.</p>
        </div>
        <form method="post" action="{$url}/login" data-no-intercept="true">
            {$csrf}
            <label class="block text-xs font-medium text-slate-400 mb-1">One-Time Password</label>
            <input name="otp" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="4"
                   class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100 tracking-[0.4em] font-mono text-center"
                   placeholder="0000">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Verify &amp; Sign In</button>
        </form>
        <div class="mt-4 flex justify-center">
            <button type="button" id="login-otp-btn" onclick="showLoginOTP()" class="text-xs font-semibold rounded-lg bg-emerald-500/15 ring-1 ring-emerald-500/30 hover:bg-emerald-500/25 px-3 py-1.5 text-emerald-300 transition">📱 View OTP Code</button>
        </div>
        <div class="mt-3 flex justify-center">
            <a href="{$url}/logout" class="text-xs font-semibold rounded-lg bg-slate-800 hover:bg-rose-500 hover:text-white px-3 py-1.5 text-slate-300 transition">Log out</a>
        </div>
        <script>
        function showLoginOTP() {
            // Fetch the pending login OTP from server
            fetch('/get-login-otp', { method: 'GET' })
                .then(r => r.json())
                .then(data => {
                    if (data.otp) {
                        // Show in an alert
                        const otpCode = data.otp;
                        const msg = '📱 Your Login OTP Code:\\n\\n' + otpCode + '\\n\\nEnter this 4-digit code above and click "Verify & Sign In"';
                        alert(msg);
                    } else {
                        alert('No pending OTP found. Please try again.');
                    }
                })
                .catch(err => {
                    alert('Could not retrieve OTP. Error: ' + err.message);
                });
        }
        </script>
        {$flagScript}
HTML;
} else {
    $form = <<<HTML
        {$alert}
        <form method="post" action="{$url}/login">
            {$csrf}
            <label class="block text-xs font-medium text-slate-400 mb-1">Customer ID</label>
            <input name="customer_id" autocomplete="off" autofocus
                   class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100"
                   placeholder="Enter your Customer ID">
            <label class="block text-xs font-medium text-slate-400 mb-1 mt-4">Password</label>
            <input name="password" type="password" autocomplete="off"
                   class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100"
                   placeholder="••••••••">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Sign In</button>
        </form>
        <div class="mt-4 flex justify-center text-xs text-slate-500">
            <a class="hover:text-slate-300" href="{$url}/forgot-password">Forgot password?</a>
        </div>
HTML;
}

$heading = $phase === 'otp' ? 'Two-Factor Verification' : 'Sign in to BankSploit';

// Participant-facing CTF hints (controlled by instructor via superadmin panel).
// Credentials phase: only SQLi (G1) is relevant.
// OTP phase: brute-force (L10) and sensitive data exposure (L11) are the vulns here.
$ctfHints = $phase === 'otp'
    ? ctf_hint_banner(['auth.l10', 'auth.l11'], 'side')
    : ctf_hint_banner(['auth.g1'], 'side');

// Lab hint banner (also renders the inline code-reveal modal — see lab_hint_banner()).
$labBanner = lab_hint_banner(['auth.g1', 'auth.l10', 'auth.l11', 'auth.l14']);

$content = '<h2 class="text-xl font-bold text-white mb-6">' . $heading . '</h2>'
    . $form . $labBanner . $ctfHints;

render_layout($phase === 'otp' ? 'Verify OTP' : 'Login', $content, ['auth_shell' => true]);
