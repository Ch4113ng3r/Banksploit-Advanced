<?php

declare(strict_types=1);

/**
 * Forgot-password journey (HTML) — a FOUR-stage state machine, one endpoint per
 * stage so each page can be opened in its own browser tab:
 *
 *   /forgot-password            Step 1 — enter Customer ID   -> recovery_uid
 *   /forgot-password/otp        Step 2 — enter OTP           -> recovery_stage = 1
 *   /forgot-password/questions  Step 3 — 5 security questions -> recovery_stage = 2
 *   /forgot-password/reset      Step 4 — set a new password for recovery_uid
 *
 * Progress is tracked by two session values that are SEPARATE from the auth
 * session ($_SESSION['user']):
 *   recovery_uid    — which user the reset is currently "for"
 *   recovery_stage  — how far the flow has progressed (1 = OTP ok, 2 = answers ok)
 *
 * VULNERABILITIES
 * ----------------------------------------------------------------------------
 *  auth.l25  SESSION PUZZLING (CWE-841) — graduated:
 *    L0  Step 1 overloads the AUTH key ($_SESSION['user']) directly, so entering
 *        a victim's Customer ID authenticates you as them (browse /dashboard).
 *    L1/L2  Step 1 blindly OVERWRITES recovery_uid while LEAVING recovery_stage
 *        intact. So: complete the flow once for your own account (stage -> 2),
 *        then open Step 1 in a new tab, submit a victim's Customer ID — the uid
 *        is swapped but the stage stays 2 — and load /forgot-password/reset to
 *        set the VICTIM's password with no OTP and no questions.
 *    L3  Step 1 resets the stage whenever the Customer ID changes, and the reset
 *        gate binds the stage to the exact uid that earned it (recovery_stage_uid).
 *  auth.l13  Predictable reset link leaked in an HTML comment on the reset page.
 *  auth.l16  Account enumeration: distinct "no account" message at vuln levels.
 *
 * Session-viewer (no auth) to watch the puzzle:  /debug/session
 */

require_once __DIR__ . '/_shared.php';

$engine = engine();
$pdo    = db();

// Which stage/endpoint are we on? (routes all include this file)
$reqPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/forgot-password', PHP_URL_PATH) ?: '/forgot-password');
if (str_contains($reqPath, '/forgot-password/otp')) {
    $step = 'otp';
} elseif (str_contains($reqPath, '/forgot-password/questions')) {
    $step = 'questions';
} elseif (str_contains($reqPath, '/forgot-password/reset')) {
    $step = 'reset';
} else {
    $step = 'userid';
}

$l25Level   = $engine->getVulnLevel('auth', 'l25');
// The stage-carryover "swap" is an L1/L2-only technique. L0's vuln is the auth
// overload (step 1); L3 is secure. Both L0 and L3 reset the stage on a target
// change, so neither is swappable — each level shows exactly one behaviour.
$swapVuln = ($l25Level === 1 || $l25Level === 2);

$error  = null;
$notice = null;
$leakComment = null;

$recoveryUid   = (int) Session::get('recovery_uid', 0);
$recoveryStage = (int) Session::get('recovery_stage', 0);
$stageUid      = (int) Session::get('recovery_stage_uid', 0);  // who earned the stage

// Idle timeout: a recovery journey must not live forever. Expire the whole
// recovery state after 10 minutes with no request in the flow, then send the
// user back to the start. Runs before POST handling so a stale submission is
// rejected too. Any request within the flow (below) refreshes the activity clock.
const RECOVERY_IDLE_TTL = 600; // seconds (10 minutes)
if ($recoveryUid > 0) {
    $rts = (int) Session::get('recovery_ts', 0);
    if ($rts > 0 && (time() - $rts) > RECOVERY_IDLE_TTL) {
        Session::remove('recovery_uid');
        Session::remove('recovery_stage');
        Session::remove('recovery_stage_uid');
        Session::remove('recovery_ts');
        flash('error', 'Your password-recovery session timed out after 10 minutes of inactivity. Please start again.');
        redirect('/forgot-password');
    }
    Session::set('recovery_ts', time()); // mark activity
    $recoveryStage = (int) Session::get('recovery_stage', 0);
}

/** Look up a user row by internal id (for rendering / l13). */
$userById = static function (int $id) use ($pdo): ?array {
    if ($id <= 0) { return null; }
    $s = $pdo->prepare('SELECT id, customer_id, name, role, email FROM users WHERE id = :i LIMIT 1');
    $s->execute([':i' => $id]);
    $u = $s->fetch();
    return $u ?: null;
};

// ---------------------------------------------------------------- POST ------

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) input('action', $step);

    // "Cancel & sign out" — tear down the whole recovery + any auth session.
    if ($action === 'cancel') {
        Session::remove('user');
        Session::remove('recovery_uid');
        Session::remove('recovery_stage');
        Session::remove('recovery_stage_uid');
        Session::remove('recovery_ts');
        Session::remove('l25_takeover_flag');
        redirect('/login');
    }

    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';

    // -------- Step 1: identify the user by Customer ID --------
    } elseif ($action === 'userid') {
        $customerId = trim((string) input('customer_id', ''));
        $st = $pdo->prepare('SELECT id, customer_id, name, role, email FROM users WHERE customer_id = :c LIMIT 1');
        $st->execute([':c' => $customerId]);
        $u = $st->fetch();

        if (!$u) {
            // auth.l16: enumeration — distinct message at vuln levels.
            $error = $engine->isVulnerable('auth', 'l16')
                ? 'No account with that Customer ID.'
                : 'If that Customer ID is registered, you can verify it next.';
        } else {
            $uid = (int) $u['id'];

            if ($l25Level === 0) {
                // @vuln-start: [L0] the recovery step signs the session straight in as the looked-up account (auth.l25)
                // Knowing a Customer ID is enough to become that user — no password,
                // OTP or security questions are verified before this login is granted.
                Session::set('user', [
                    'id'          => $uid,
                    'customer_id' => $u['customer_id'],
                    'name'        => $u['name'],
                    'role'        => $u['role'],
                ]);
                // @vuln-end
            }

            // CTF plumbing (not shown in the lesson): the L0 flag is handed over on the
            // dashboard, keyed to the signed-in account.
            if ($l25Level === 0 && flags_delivered()) {
                $_SESSION['l25_takeover_flag'] = Flags::generate('auth.l25', $uid);
            }

            Session::set('recovery_ts', time());   // start/refresh the idle clock

            // @vuln-start: [L1-2] the account this reset is for, from the submitted Customer ID (auth.l25)
            // @fix-start: the account this reset is for, from the submitted Customer ID (auth.l25)
            // This can be (re)set on any visit to step 1, at any point in the flow.
            Session::set('recovery_uid', $uid);
            // @vuln-end
            // @fix-end

            // Issue a reset OTP (same OTP machinery as login — purpose 'reset').
            auth_generate_otp($pdo, $uid, 'reset');
            redirect('/forgot-password/otp');
        }

    // -------- Step 2: verify the OTP --------
    } elseif ($action === 'otp') {
        if ($recoveryUid <= 0) {
            redirect('/forgot-password');
        }
        if (auth_verify_otp($pdo, $engine, $recoveryUid, (string) input('otp', ''), 'reset')) {
            Session::set('recovery_stage', 1);
            redirect('/forgot-password/questions');
        }
        $error = 'Invalid or expired OTP.';

    // -------- Step 3: verify the 5 security questions --------
    } elseif ($action === 'answers') {
        if ($recoveryUid <= 0 || $recoveryStage < 1) {
            redirect('/forgot-password');
        }
        $answers = [];
        for ($i = 1; $i <= 5; $i++) {
            $answers[$i] = (string) input('ans_' . $i, '');
        }
        if (auth_check_security_answers($pdo, $recoveryUid, $answers)) {
            // @vuln-start: [L1-2] mark the identity checks complete (auth.l25)
            // @fix-start: mark the identity checks complete (auth.l25)
            Session::set('recovery_stage', 2);
            // @vuln-end
            // @fix-end
            // @fix-start: also record WHICH account completed the checks, so the reset can be tied to it (auth.l25)
            Session::set('recovery_stage_uid', $recoveryUid);
            // @fix-end
            redirect('/forgot-password/reset');
        }
        $error = 'One or more answers are incorrect.';

    // -------- Step 4: set the new password --------
    } elseif ($action === 'reset') {
        if ($swapVuln) {
            // @vuln-start: [L1-2] open the reset once the verified stage is reached (auth.l25)
            // Weakness: this trusts recovery_stage on its own. It never checks that the
            // stage was completed for the same account that is now being reset
            // (recovery_uid), so progress earned for one account unlocks a reset for
            // whatever account is in the session now.
            $gateOpen = $recoveryStage >= 2 && $recoveryUid > 0;
            // @vuln-end
        } else {
            // @fix-start: [L0,L3] open the reset only for the account that completed the checks (auth.l25)
            // recovery_stage alone is not enough: the stage must have been earned by
            // this exact account (recovery_stage_uid === recovery_uid).
            $gateOpen = $recoveryStage >= 2 && $recoveryUid > 0 && $stageUid === $recoveryUid;
            // @fix-end
        }

        if (!$gateOpen) {
            redirect('/forgot-password');
        }
        $password = (string) input('password', '');
        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Update both hashes so the account logs in at any level.
            $pdo->prepare(
                'UPDATE users SET password_hash = :ph, password_md5 = :pmd5,
                        failed_attempts = 0, is_locked = 0, locked_until = NULL
                  WHERE id = :id'
            )->execute([
                ':ph'   => password_hash($password, PASSWORD_BCRYPT),
                ':pmd5' => md5($password),
                ':id'   => $recoveryUid,
            ]);
            Session::remove('user');
            Session::remove('recovery_uid');
            Session::remove('recovery_stage');
            Session::remove('recovery_stage_uid');
            Session::remove('recovery_ts');
            flash('ok', 'Password reset. You can now sign in.');
            redirect('/login');
        }
    }

    // Re-read after any mutations for the render below.
    $recoveryUid   = (int) Session::get('recovery_uid', 0);
    $recoveryStage = (int) Session::get('recovery_stage', 0);
    $stageUid      = (int) Session::get('recovery_stage_uid', 0);
}

// ---------------------------------------------------------- GET guards ------
// On a plain GET, send the browser to the right stage if it loaded one it has
// not reached yet. (Step 1 is always open so a new tab can re-enter it — that
// openness is exactly what the L1/L2 puzzle rides on.)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if ($step === 'otp' && $recoveryUid <= 0) {
        redirect('/forgot-password');
    }
    if ($step === 'questions' && $recoveryStage < 1) {
        redirect($recoveryUid > 0 ? '/forgot-password/otp' : '/forgot-password');
    }
    if ($step === 'reset') {
        // Same gate as the POST handler: at secure levels the stage must have been
        // earned by the current target, so a swapped-in victim never even sees the form.
        $ownerOk = $swapVuln || $stageUid === $recoveryUid;
        if (!($recoveryStage >= 2 && $recoveryUid > 0 && $ownerOk)) {
            redirect($recoveryUid > 0 ? '/forgot-password/otp' : '/forgot-password');
        }
    }
}

// ---------------------------------------------------------------- render ----

$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();

$alert = '';
if ($error !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">'
        . htmlspecialchars($error) . '</div>';
} elseif ($notice !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">'
        . htmlspecialchars($notice) . '</div>';
}
// One-shot flashes (e.g. the 10-minute idle-timeout notice set before redirect).
foreach (take_flashes() as $f) {
    $cls = ($f['type'] ?? '') === 'error'
        ? 'border-red-500/40 bg-red-500/10 text-red-300'
        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300';
    $alert .= '<div class="mb-4 rounded-lg border ' . $cls . ' px-3 py-2 text-sm">'
        . htmlspecialchars((string) ($f['message'] ?? '')) . '</div>';
}

$current = $userById($recoveryUid);
$who = $current ? htmlspecialchars((string) $current['customer_id']) : '';

// Show which Customer ID the recovery is currently for, at every stage — so the
// active principal (and any mid-flow swap) is always visible.
$uidBadge = $who !== ''
    ? '<div class="mb-4 inline-flex items-center gap-2 rounded-lg bg-slate-800/70 ring-1 ring-slate-700 px-3 py-1.5 text-xs text-slate-300">Recovering account: <span class="font-mono text-indigo-300">' . $who . '</span></div>'
    : '';

$cancelForm = <<<HTML
    <form method="post" action="{$url}/forgot-password/reset" class="mt-3">
        {$csrf}
        <input type="hidden" name="action" value="cancel">
        <button class="w-full rounded-lg border border-slate-700 bg-slate-800/60 hover:bg-slate-700 text-slate-200 font-medium py-2.5 transition">Cancel &amp; sign out</button>
    </form>
HTML;

if ($step === 'otp') {
    $body = <<<HTML
        <h2 class="text-xl font-bold text-white mb-2">Verify OTP</h2>
        {$uidBadge}
        <p class="text-sm text-slate-400 mb-4">Enter the 4-digit OTP sent to the phone on file for <span class="font-mono text-slate-200">{$who}</span>.</p>
        {$alert}
        <form method="post" action="{$url}/forgot-password/otp">
            {$csrf}
            <input type="hidden" name="action" value="otp">
            <label class="block text-xs font-medium text-slate-400 mb-1">One-Time Password</label>
            <input name="otp" inputmode="numeric" autocomplete="one-time-code" autofocus maxlength="4"
                   class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100 tracking-[0.4em] font-mono text-center"
                   placeholder="0000">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Verify OTP</button>
        </form>
        <div class="mt-4 flex justify-center">
            <button type="button" onclick="showResetOTP()" class="text-xs font-semibold rounded-lg bg-emerald-500/15 ring-1 ring-emerald-500/30 hover:bg-emerald-500/25 px-3 py-1.5 text-emerald-300 transition">📱 View OTP Code</button>
        </div>
        {$cancelForm}
        <script>
        function showResetOTP() {
            fetch('/get-reset-otp', { method: 'GET' })
                .then(r => r.json())
                .then(data => {
                    if (data.otp) {
                        alert('📱 Your Reset OTP Code:\\n\\n' + data.otp + '\\n\\nEnter this 4-digit code above and click "Verify OTP"');
                    } else {
                        alert('No pending OTP found. Please try again.');
                    }
                })
                .catch(err => alert('Could not retrieve OTP. Error: ' + err.message));
        }
        </script>
HTML;

} elseif ($step === 'questions') {
    $questions = auth_security_questions($pdo, $recoveryUid);
    $qHtml = '';
    foreach ($questions as $q) {
        $pos = (int) $q['position'];
        $qHtml .= '<label class="block text-xs font-medium text-slate-400 mb-1 mt-3">'
            . htmlspecialchars((string) $q['question']) . '</label>'
            . '<input name="ans_' . $pos . '" autocomplete="off" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100">';
    }
    $body = <<<HTML
        <h2 class="text-xl font-bold text-white mb-2">Verify your identity</h2>
        {$uidBadge}
        <p class="text-sm text-slate-400 mb-4">Answer the security questions for <span class="font-mono text-slate-200">{$who}</span>.</p>
        {$alert}
        <form method="post" action="{$url}/forgot-password/questions">
            {$csrf}
            <input type="hidden" name="action" value="answers">
            {$qHtml}
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Verify &amp; continue</button>
        </form>
        {$cancelForm}
HTML;

} elseif ($step === 'reset') {
    // auth.l13: leak the predictable reset link in an HTML comment on this page.
    if ($current && $engine->isVulnerable('auth', 'l13')) {
        $blob = auth_reset_blob((string) $current['customer_id'], (string) $current['email']);
        $leakComment = APP_URL . '/reset-password?token=' . $blob;
    }
    // CTF (auth.l25): the reset page is open for a user whose gates were cleared
    // by a DIFFERENT account — the session-puzzling takeover. Deliver the flag
    // ON THIS PAGE, keyed to the account that earned the stage (the one you can
    // sign in as to submit it).
    $flagScript = '';
    if ($swapVuln && flags_delivered() && $recoveryStage >= 2
        && $stageUid > 0 && $stageUid !== $recoveryUid) {
        $f  = Flags::generate('auth.l25', $stageUid);
        $fj = json_encode($f);
        header('X-CTF-Flag: ' . $f);
        $flagScript = <<<JS
        <script>
        (function(){
            function s(){ if (typeof displayFlag === 'function') displayFlag({$fj}, 'auth.l25'); }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', s);
            else setTimeout(s, 150);
        })();
        </script>
JS;
    }
    $body = <<<HTML
        <h2 class="text-xl font-bold text-white mb-2">Set a new password</h2>
        {$uidBadge}
        <p class="text-sm text-slate-400 mb-4">Choose a new password for <span class="font-mono text-slate-200">{$who}</span>.</p>
        {$alert}
        <form method="post" action="{$url}/forgot-password/reset">
            {$csrf}
            <input type="hidden" name="action" value="reset">
            <label class="block text-xs font-medium text-slate-400 mb-1">New password</label>
            <input name="password" type="password" autofocus class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="••••••••">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Reset password</button>
        </form>
        {$cancelForm}
        {$flagScript}
HTML;

} else { // userid
    $body = <<<HTML
        <h2 class="text-xl font-bold text-white mb-6">Forgot password</h2>
        {$alert}
        <form method="post" action="{$url}/forgot-password">
            {$csrf}
            <input type="hidden" name="action" value="userid">
            <label class="block text-xs font-medium text-slate-400 mb-1">Customer ID</label>
            <input name="customer_id" autocomplete="off" autofocus class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="Enter your Customer ID">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Continue</button>
        </form>
        <p class="mt-4 text-xs text-slate-500"><a class="hover:text-slate-300" href="{$url}/login">Back to sign in</a></p>
HTML;
}

$content = $body;
if ($leakComment !== null) {
    $content .= "\n<!-- dev: password reset link (auth.l13) -> " . htmlspecialchars($leakComment) . " -->\n";
}

// Participant-facing CTF hints (instructor-controlled via the 'hints' feature).
// Shown only when hints are on AND auth.l25 is selected into the CTF (ctf_active).
$content .= ctf_hint_banner(['auth.l25'], 'side');

render_layout('Forgot password', $content, ['auth_shell' => true]);
