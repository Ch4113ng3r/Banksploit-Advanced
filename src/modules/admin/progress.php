<?php

declare(strict_types=1);

/**
 * Learner progress — status of every vulnerability + an overall score
 * (percentage exploited). Reads training_progress.
 */

require_once __DIR__ . '/_shared.php';

admin_guard();

$pdo  = db();
$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::generateToken();
$p    = admin_progress($pdo);

$statusColor = [
    'hunting'   => 'bg-slate-700 text-slate-300',
    'found'     => 'bg-sky-500/20 text-sky-300',
    'exploited' => 'bg-amber-500/20 text-amber-300',
    'reviewed'  => 'bg-emerald-500/20 text-emerald-300',
];

$rows = '';
foreach ($p['rows'] as $r) {
    $vid    = htmlspecialchars((string) $r['vuln_id']);
    $status = (string) $r['status'];
    $cls    = $statusColor[$status] ?? 'bg-slate-700 text-slate-300';
    $found  = $r['found_at'] ? htmlspecialchars(substr((string) $r['found_at'], 0, 16)) : '—';
    $opts = '';
    foreach (['hunting', 'found', 'exploited', 'reviewed'] as $s) {
        $sel = $s === $status ? ' selected' : '';
        $opts .= "<option value=\"{$s}\"{$sel}>{$s}</option>";
    }
    $rows .= <<<HTML
        <tr class="border-t border-slate-800">
            <td class="py-2 text-slate-300 text-sm font-mono">{$vid}</td>
            <td class="py-2"><span class="px-2 py-0.5 rounded text-xs {$cls}">{$status}</span></td>
            <td class="py-2 text-slate-500 text-xs">{$found}</td>
            <td class="py-2 text-right">
                <select onchange="setStatus('{$vid}',this.value)" class="rounded-lg bg-slate-950 ring-1 ring-slate-700 px-2 py-1 text-slate-100 text-sm">{$opts}</select>
            </td>
        </tr>
HTML;
}

$score = $p['score'];

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">Learner Progress</h2>
        <a href="{$url}/admin" class="text-sm text-slate-400 hover:text-slate-200">← Admin</a>
    </div>

    <div class="rounded-2xl bg-gradient-to-br from-indigo-600/30 to-sky-500/20 ring-1 ring-indigo-500/30 p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-indigo-200">Overall score</div>
                <div class="text-4xl font-black text-white mt-1">{$score}%</div>
                <div class="text-xs text-slate-400 mt-1">{$p['exploited']} of {$p['total']} vulnerabilities exploited</div>
            </div>
            <button onclick="resetProgress()" class="rounded-lg bg-slate-800/80 hover:bg-slate-700 px-4 py-2 text-slate-200 text-sm">Reset progress</button>
        </div>
        <div class="mt-4 h-2 rounded-full bg-slate-800 overflow-hidden">
            <div class="h-full bg-emerald-400" style="width:{$score}%"></div>
        </div>
    </div>

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">Vulnerability</th><th class="pb-2">Status</th><th class="pb-2">Found</th><th class="pb-2 text-right">Set</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <script>
    const CSRF = "{$csrf}";
    async function setStatus(vuln, status) {
        // progress updates piggyback on the vuln-config endpoint's sibling; use a dedicated call
        await fetch("{$url}/api/v1/admin/progress", {method:'PUT', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({vuln_id: vuln, status, csrf_token: CSRF})});
        location.reload();
    }
    async function resetProgress() {
        if (!confirm('Reset all progress to hunting?')) return;
        await fetch("{$url}/api/v1/admin/progress", {method:'PUT', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({reset:true, csrf_token: CSRF})});
        location.reload();
    }
    </script>
HTML;

$content .= lab_hint_banner(['admin.l4']);

render_layout('Progress', $content, ['active' => 'admin']);
