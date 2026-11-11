<?php

declare(strict_types=1);

/**
 * System configuration — tunable settings (OTP expiry, session timeout, daily
 * transfer limit, max failed logins, beneficiary cooling period).
 *
 * admin.l4: at vuln levels admin_guard() admits any logged-in user, so a
 * regular user can read AND change these via the page or PUT /api/v1/admin/config.
 */

require_once __DIR__ . '/_shared.php';

admin_guard();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();

$notice = null;
$error  = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        admin_config_set($pdo, request_input());
        admin_audit($pdo, (int) $me['id'], 'admin.config.update', '/admin/config', request_input());
        $notice = 'Configuration saved.';
    }
}

$cfg  = admin_config_get($pdo);
$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();

$alert = '';
if ($error !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">' . htmlspecialchars($error) . '</div>';
} elseif ($notice !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">' . htmlspecialchars($notice) . '</div>';
}

$fields = [
    'otp_expiry_seconds'        => 'OTP expiry (seconds)',
    'session_timeout_minutes'   => 'Session timeout (minutes)',
    'daily_transfer_limit'      => 'Daily transfer limit (INR)',
    'max_failed_logins'         => 'Max failed login attempts',
    'beneficiary_cooling_hours' => 'Beneficiary cooling period (hours)',
];

$inputs = '';
foreach ($fields as $key => $label) {
    $val = htmlspecialchars((string) ($cfg[$key] ?? ''));
    $lbl = htmlspecialchars($label);
    $inputs .= <<<HTML
        <label class="block text-xs text-slate-400 mb-1 mt-3">{$lbl}</label>
        <input name="{$key}" value="{$val}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
HTML;
}

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">System Configuration</h2>
        <a href="{$url}/admin" class="text-sm text-slate-400 hover:text-slate-200">← Admin</a>
    </div>

    {$alert}

    <div class="max-w-lg rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
        <form method="post" action="{$url}/admin/config">
            {$csrf}
            {$inputs}
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Save configuration</button>
        </form>
    </div>
HTML;

$content .= lab_hint_banner(['admin.l4']);

render_layout('System Config', $content, ['active' => 'admin']);
