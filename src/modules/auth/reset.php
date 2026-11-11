<?php

declare(strict_types=1);

/**
 * Reset-password page (HTML) — consumes a reset token and sets a new password.
 *
 * The token's (in)security is decided at issue time (auth.l13, see
 * forgot_*.php). Here we validate/consume it via auth_consume_reset_token()
 * and update both password columns so the account works at every level.
 */

require_once __DIR__ . '/_shared.php';

$engine = engine();
$pdo    = db();
$error  = null;
$notice = null;

$token = (string) input('token', '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        $token    = (string) input('token', '');
        $password = (string) input('password', '');

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // auth.l13: at vuln levels a predictable base64url(cid:md5(email))
            // blob is accepted; otherwise a real stored token is consumed.
            $userId = auth_resolve_reset_token($pdo, $engine, $token);
            if ($userId === null) {
                $error = 'Invalid or expired reset token.';
            } else {
                // Update both hashes so the account logs in at any level.
                $pdo->prepare(
                    'UPDATE users SET password_hash = :ph, password_md5 = :pmd5,
                            failed_attempts = 0, is_locked = 0, locked_until = NULL
                      WHERE id = :id'
                )->execute([
                    ':ph'   => password_hash($password, PASSWORD_BCRYPT),
                    ':pmd5' => md5($password),
                    ':id'   => $userId,
                ]);
                flash('ok', 'Password reset. You can now sign in.');
                redirect('/login');
            }
        }
    }
}

// ---------------------------------------------------------------- render ----

$url   = htmlspecialchars(APP_URL);
$csrf  = CSRF::getHiddenField();
$tokEsc = htmlspecialchars($token);

$alert = $error !== null
    ? '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">'
      . htmlspecialchars($error) . '</div>'
    : '';

$content = <<<HTML
    <h2 class="text-xl font-bold text-white mb-6">Set a new password</h2>
    {$alert}
    <form method="post" action="{$url}/reset-password">
        {$csrf}
        <label class="block text-xs font-medium text-slate-400 mb-1">Reset token</label>
        <input name="token" value="{$tokEsc}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 font-mono text-xs break-all" readonly>
        <label class="block text-xs font-medium text-slate-400 mb-1 mt-4">New password</label>
        <input name="password" type="password" autofocus class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="••••••••">
        <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Reset password</button>
    </form>
    <p class="mt-4 text-xs text-slate-500"><a class="hover:text-slate-300" href="{$url}/login">Back to sign in</a></p>
HTML;

render_layout('Reset password', $content, ['auth_shell' => true]);
