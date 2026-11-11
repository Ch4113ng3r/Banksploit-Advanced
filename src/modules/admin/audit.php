<?php

declare(strict_types=1);

/**
 * Audit log viewer — searchable / filterable table of audit_log entries.
 *
 * ============================================================================
 *  VULNERABILITIES
 * ----------------------------------------------------------------------------
 *  statements.g2 SQL injection (CWE-89): the action / user filters are
 *      concatenated into the query at vuln levels (see admin_audit_search).
 *      Try ?action=x' UNION SELECT id,customer_id,password_md5,role,pan,1,1,NOW() FROM users --
 *  core.l18 Verbose / info disclosure (CWE-209/200): each entry's full
 *      request_body is shown verbatim, including any sensitive fields that were
 *      posted (passwords, amounts, PII). DB errors are shown raw too.
 *  admin.l4: reachable by any logged-in user at vuln levels.
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

admin_guard();

$engine = engine();
$pdo    = db();
$url    = htmlspecialchars(APP_URL);

$filters = [
    'action'    => (string) input('action', ''),
    'user'      => (string) input('user', ''),
    'from_date' => (string) input('from_date', ''),
    'to_date'   => (string) input('to_date', ''),
];
$res = admin_audit_search($pdo, $engine, $filters);

$errBox = $res['error'] !== null
    ? '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm font-mono">' . htmlspecialchars($res['error']) . '</div>'
    : '';

$rows = '';
foreach ($res['rows'] as $e) {
    $when  = htmlspecialchars(substr((string) ($e['created_at'] ?? ''), 0, 19));
    $uid   = htmlspecialchars((string) ($e['user_id'] ?? '—'));
    $act   = htmlspecialchars((string) ($e['action'] ?? ''));
    $rsrc  = htmlspecialchars((string) ($e['resource'] ?? ''));
    $ip    = htmlspecialchars((string) ($e['ip_address'] ?? ''));
    $code  = htmlspecialchars((string) ($e['response_code'] ?? ''));
    // core.l18: the full request body is shown verbatim.
    $body  = htmlspecialchars((string) ($e['request_body'] ?? ''));
    $rows .= <<<HTML
        <tr class="border-t border-slate-800 align-top">
            <td class="py-2 text-slate-500 text-xs whitespace-nowrap">{$when}</td>
            <td class="py-2 text-slate-400 text-xs">{$uid}</td>
            <td class="py-2 text-slate-200 text-sm">{$act}<div class="text-xs text-slate-500">{$rsrc}</div></td>
            <td class="py-2 text-slate-500 text-xs font-mono">{$ip}</td>
            <td class="py-2 text-slate-500 text-xs">{$code}</td>
            <td class="py-2 text-slate-400 text-xs font-mono break-all max-w-xs">{$body}</td>
        </tr>
HTML;
}
if ($rows === '') {
    $rows = '<tr><td colspan="6" class="py-3 text-slate-500 text-sm">No entries match.</td></tr>';
}

$fAction = htmlspecialchars($filters['action']);
$fUser   = htmlspecialchars($filters['user']);
$fFrom   = htmlspecialchars($filters['from_date']);
$fTo     = htmlspecialchars($filters['to_date']);

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">Audit Log</h2>
        <a href="{$url}/admin" class="text-sm text-slate-400 hover:text-slate-200">← Admin</a>
    </div>

    <form method="get" action="{$url}/admin/audit" class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4 mb-5 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs text-slate-400 mb-1">Action</label>
            <input name="action" value="{$fAction}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100" placeholder="login">
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">User ID</label>
            <input name="user" value="{$fUser}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100" placeholder="1">
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">From</label>
            <input type="date" name="from_date" value="{$fFrom}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">To</label>
            <input type="date" name="to_date" value="{$fTo}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
        </div>
        <button class="rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold px-4 py-2">Search</button>
    </form>

    {$errBox}

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4 overflow-x-auto">
        <table class="w-full">
            <thead><tr class="text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">Time</th><th class="pb-2">User</th><th class="pb-2">Action</th><th class="pb-2">IP</th><th class="pb-2">Code</th><th class="pb-2">Request body</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
HTML;

$content .= lab_hint_banner(['statements.g2', 'core.l18', 'admin.l4']);

render_layout('Audit Log', $content, ['active' => 'admin-audit']);
