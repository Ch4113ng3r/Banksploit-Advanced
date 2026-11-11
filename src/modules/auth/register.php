<?php

declare(strict_types=1);

/**
 * Registration page (HTML) — dispatcher.
 *
 * Branches on profile.l5 (mass assignment) level: vuln (0-2) vs secure (3).
 * Shares auth_register_vuln()/auth_register_secure() with the JSON API.
 */

require_once __DIR__ . '/register_vuln.php';
require_once __DIR__ . '/register_secure.php';

$engine = engine();
$pdo    = db();
$error  = null;
$notice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        $in = request_input();
        $level  = $engine->getVulnLevel('profile', 'l5');
        $result = $level >= 3
            ? auth_register_secure($pdo, $engine, $in)
            : auth_register_vuln($pdo, $engine, $in);

        if ($result['ok']) {
            $roleNote = $result['role'] === 'admin'
                ? ' Mass assignment succeeded — the new account is an ADMIN.'
                : '';
            flash('ok', 'Account created. You can now sign in.' . $roleNote);
            redirect('/login');
        }
        $error = $result['error'] ?? 'Registration failed.';
    }
}

// ---------------------------------------------------------------- render ----

$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();
$l5   = $engine->getVulnLevel('profile', 'l5');

$alert = $error !== null
    ? '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">'
      . htmlspecialchars($error) . '</div>'
    : '';

// At vuln levels, expose the role field so trainees can try the mass-assignment.
$roleField = $l5 < 3
    ? '<label class="block text-xs font-medium text-slate-400 mb-1 mt-4">Role '
      . '<span class="text-yellow-500">(should not be client-settable)</span></label>'
      . '<input name="role" value="user" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100 font-mono">'
    : '';

$content = <<<HTML
    <h2 class="text-xl font-bold text-white mb-6">Create your account</h2>
    {$alert}
    <form method="post" action="{$url}/register" enctype="multipart/form-data">
        {$csrf}
        <label class="block text-xs font-medium text-slate-400 mb-1">Full name</label>
        <input name="name" autofocus class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="Jane Doe">

        <label class="block text-xs font-medium text-slate-400 mb-1 mt-4">Email</label>
        <input name="email" type="email" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="jane@example.in">

        <label class="block text-xs font-medium text-slate-400 mb-1 mt-4">Phone</label>
        <input name="phone" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="+919812345678">

        <label class="block text-xs font-medium text-slate-400 mb-1 mt-4">Password</label>
        <input name="password" type="password" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="••••••••">
        {$roleField}

        <label class="block text-xs font-medium text-slate-400 mb-1 mt-4">KYC document</label>
        <input name="kyc_document" type="file" class="w-full text-sm text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-slate-200">

        <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Create account</button>
    </form>
    <p class="mt-4 text-xs text-slate-500">Already have an account? <a class="hover:text-slate-300" href="{$url}/login">Sign in</a></p>
HTML;

$content .= lab_hint_banner(['loans.g7']);

render_layout('Register', $content, ['auth_shell' => true]);
