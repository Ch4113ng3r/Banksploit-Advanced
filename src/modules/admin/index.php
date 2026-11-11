<?php

declare(strict_types=1);

/**
 * Admin dashboard — stats cards + user management table.
 *
 * admin.l4: admin_guard() lets ANY logged-in user in at levels 0-2 (the admin
 * link is merely hidden from non-admins in the sidebar). At level 3 it enforces
 * the admin role. Visit /admin directly as a regular user to demonstrate.
 */

require_once __DIR__ . '/_shared.php';

admin_guard();

$engine = engine();
$pdo    = db();

// Stats.
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended = 0")->fetchColumn();
$totalTxns   = (int) $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$prog        = admin_progress($pdo);
$found       = $prog['exploited'];

$url   = htmlspecialchars(APP_URL);

// User list (non-admin users only — admin account is hidden from this view)
$users = $pdo->query("SELECT id, name, customer_id, email, role, is_locked, is_suspended FROM users WHERE role != 'admin' ORDER BY id")->fetchAll();

// Banner if a non-admin reached this page (the L4 exploit in action). Teaching
// aid only — show it solely in Lab Mode so it isn't visible with Lab OFF.
$exploitBanner = (lab_mode() && !admin_is_real_admin())
    ? '<div class="mb-6 rounded-lg border border-red-500/50 bg-red-500/10 text-red-300 px-4 py-3 text-sm">'
      . '&#9888; You are <strong>not an admin</strong> but you are viewing the admin panel — '
      . 'Broken Function-Level Authorization (admin.l4).</div>'
    : '';


$content = <<<HTML
    <h2 class="text-2xl font-bold text-white mb-6">Admin Dashboard</h2>
    {$exploitBanner}

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <div class="text-xs text-slate-500 uppercase tracking-wide">Active users</div>
            <div class="text-3xl font-black text-white mt-1">{$activeUsers}</div>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <div class="text-xs text-slate-500 uppercase tracking-wide">Total transactions</div>
            <div class="text-3xl font-black text-white mt-1">{$totalTxns}</div>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <div class="text-xs text-slate-500 uppercase tracking-wide">Vulns exploited</div>
            <div class="text-3xl font-black text-white mt-1">{$found}<span class="text-base text-slate-500">/{$prog['total']}</span></div>
        </div>
    </div>

    <div class="flex gap-2 mb-4 text-sm flex-wrap">
        <a href="{$url}/admin/audit" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-slate-200">Audit Log</a>
    </div>

    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Registered Users</h3>
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">User</th><th class="pb-2">Customer ID</th><th class="pb-2">Role</th><th class="pb-2">Status</th>
            </tr></thead>
            <tbody>
HTML;

foreach ($users as $u) {
    $name  = htmlspecialchars((string) $u['name']);
    $email = htmlspecialchars((string) $u['email']);
    $cid   = htmlspecialchars((string) $u['customer_id']);
    $role   = htmlspecialchars((string) $u['role']);
    $locked    = (int) $u['is_locked'] === 1;
    $suspended = (int) $u['is_suspended'] === 1;
    $roleBadge = '<span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300">' . $role . '</span>';
    $status = $suspended
        ? '<span class="text-xs text-red-300">suspended</span>'
        : ($locked ? '<span class="text-xs text-amber-300">locked</span>' : '<span class="text-xs text-emerald-300">active</span>');

    $content .= <<<HTML
        <tr class="border-t border-slate-800">
            <td class="py-2 text-slate-200 text-sm">{$name}<div class="text-xs text-slate-500">{$email}</div></td>
            <td class="py-2 text-slate-400 text-sm font-mono">{$cid}</td>
            <td class="py-2">{$roleBadge}</td>
            <td class="py-2">{$status}</td>
        </tr>
HTML;
}

$content .= <<<HTML
            </tbody>
        </table>
    </div>

HTML;

$content .= lab_hint_banner(['admin.l4']);

render_layout('Admin', $content, ['active' => 'admin-dashboard']);
