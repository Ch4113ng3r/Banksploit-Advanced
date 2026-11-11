<?php

declare(strict_types=1);

/**
 * Vulnerability configuration panel — view/change the level (0-3) of every
 * vulnerability, plus presets. Writes through TrainingEngine::setVulnLevel()
 * (which persists to vuln_config.json with LF + LOCK_EX).
 *
 * Guarded by admin_guard() (admin.l4). NOTE: this panel is the one place where
 * being able to reach it as a non-admin is especially dangerous — it controls
 * every other vuln's level.
 */

require_once __DIR__ . '/_shared.php';

admin_guard();

$engine = engine();
$pdo    = db();
$all    = $engine->getAllVulns();
$url    = htmlspecialchars(APP_URL);
$csrf   = CSRF::generateToken();

// Flatten to rows for the table.
$rows = '';
foreach ($all['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        $level = (int) ($v['level'] ?? 0);
        $title = htmlspecialchars((string) ($v['title'] ?? $vid));
        $id    = htmlspecialchars((string) ($v['id'] ?? strtoupper($vid)));
        $owasp = htmlspecialchars((string) ($v['owasp_category'] ?? ''));
        $cwe   = htmlspecialchars((string) ($v['cwe_id'] ?? ''));
        $modE  = htmlspecialchars($mod);
        $vidE  = htmlspecialchars($vid);
        $opts = '';
        foreach ([0, 1, 2, 3] as $lv) {
            $sel = $lv === $level ? ' selected' : '';
            $label = ['Vulnerable', 'Weak', 'Partial', 'Secure'][$lv];
            $opts .= "<option value=\"{$lv}\"{$sel}>{$lv} · {$label}</option>";
        }
        $badge = $level >= 3 ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300';
        $rows .= <<<HTML
            <tr class="border-t border-slate-800" data-mod="{$modE}" data-vuln="{$vidE}">
                <td class="py-2"><span class="font-mono text-xs px-1.5 py-0.5 rounded {$badge}">{$id}</span></td>
                <td class="py-2 text-slate-300 text-xs">{$modE}</td>
                <td class="py-2 text-slate-200 text-sm">{$title}<div class="text-xs text-slate-500">{$owasp} · {$cwe}</div></td>
                <td class="py-2 text-right">
                    <select onchange="setLevel('{$modE}','{$vidE}',this.value)" class="rounded-lg bg-slate-950 ring-1 ring-slate-700 px-2 py-1 text-slate-100 text-sm">{$opts}</select>
                </td>
            </tr>
HTML;
    }
}

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">Vulnerability Configuration</h2>
        <a href="{$url}/admin" class="text-sm text-slate-400 hover:text-slate-200">← Admin</a>
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
        <button onclick="preset('beginner')"     class="rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-200 px-3 py-2 text-sm">Beginner (all 0)</button>
        <button onclick="preset('intermediate')" class="rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 px-3 py-2 text-sm">Intermediate (mix)</button>
        <button onclick="preset('advanced')"      class="rounded-lg bg-orange-500/20 hover:bg-orange-500/30 text-orange-200 px-3 py-2 text-sm">Advanced (1-2)</button>
        <button onclick="preset('secure')"        class="rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 px-3 py-2 text-sm">Secure (all 3)</button>
        <span class="flex-1"></span>
        <button onclick="setAll(0)" class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-2 text-sm">Reset all → 0</button>
        <button onclick="setAll(3)" class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 px-3 py-2 text-sm">Set all → 3</button>
    </div>

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">ID</th><th class="pb-2">Module</th><th class="pb-2">Vulnerability</th><th class="pb-2 text-right">Level</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <script>
    const CSRF = "{$csrf}";
    const VURL = "{$url}/api/v1/admin/vuln-config";
    async function postChanges(changes) {
        await fetch(VURL, {method:'PUT', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({changes, csrf_token: CSRF})});
        location.reload();
    }
    function setLevel(mod, vuln, level) { postChanges([{module:mod, vuln, level:Number(level)}]); }
    function allRows() { return [...document.querySelectorAll('tr[data-mod]')].map(r=>({module:r.dataset.mod, vuln:r.dataset.vuln})); }
    function setAll(level) { postChanges(allRows().map(r=>({...r, level}))); }
    function preset(name) {
        const rows = allRows();
        let changes;
        if (name==='beginner') changes = rows.map(r=>({...r, level:0}));
        else if (name==='secure') changes = rows.map(r=>({...r, level:3}));
        else if (name==='advanced') changes = rows.map((r,i)=>({...r, level: (i%2)+1}));
        else changes = rows.map((r,i)=>({...r, level: [0,3,1,2][i%4]})); // intermediate mix
        postChanges(changes);
    }
    </script>
HTML;

$content .= lab_hint_banner(['admin.l4']);

render_layout('Vuln Config', $content, ['active' => 'admin']);
