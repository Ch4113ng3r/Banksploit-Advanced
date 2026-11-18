<?php

declare(strict_types=1);

/**
 * CTF Vulnerabilities — select which vulnerabilities are part of the CTF event.
 *
 * This is separate from Vuln Config (which sets each vuln's difficulty level).
 * Here the instructor marks each vulnerability active/inactive for the event;
 * the selection is stored as `ctf_active` in vuln_config.json. The running
 * "score available" is indicative, from Flags::points() (flat per vuln today);
 * it will follow the per-level scoring once that lands.
 */

$engine = engine();

// ---- POST actions (JSON) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }

    $action = (string) ($input['action'] ?? '');

    if ($action === 'set-active') {
        $module = (string) ($input['module'] ?? '');
        $vuln   = (string) ($input['vuln'] ?? '');
        $active = (bool) ($input['active'] ?? false);
        // A fixed (L3) vulnerability cannot be selected into the CTF.
        $entry = $engine->getAllVulns()['modules'][$module][$vuln] ?? null;
        if ($active && is_array($entry) && (int) ($entry['level'] ?? 0) >= 3) {
            echo json_encode(['ok' => false, 'error' => 'Fixed (L3) vulnerabilities cannot be added to the CTF. Lower its level in Vuln Config first.']);
            exit;
        }
        $engine->setCtfActive($module, $vuln, $active);
        echo json_encode(['ok' => true, 'module' => $module, 'vuln' => $vuln, 'active' => $active]);
        exit;
    }

    // Bulk: all | none | scored (the 8 point-bearing challenges)
    if ($action === 'bulk') {
        $which   = (string) ($input['which'] ?? '');
        $scored  = ['auth.g1', 'auth.l10', 'auth.l11', 'auth.l12', 'statements.g2', 'statements.g9', 'statements.l1', 'transfer.l1'];
        foreach ($engine->getAllVulns()['modules'] as $mod => $vulns) {
            foreach ($vulns as $vid => $v) {
                $key = $mod . '.' . strtolower($vid);
                $active = match ($which) {
                    'all'    => true,
                    'none'   => false,
                    'scored' => in_array($key, $scored, true),
                    default  => null,
                };
                // Never activate a fixed (L3) vuln — it has no flag.
                if ($active === true && (int) ($v['level'] ?? 0) >= 3) {
                    $active = false;
                }
                if ($active !== null) {
                    $engine->setCtfActive($mod, strtolower($vid), $active);
                }
            }
        }
        echo json_encode(['ok' => true, 'which' => $which]);
        exit;
    }

    // Deliver unselected vulns in fixed mode: set every vuln NOT in the CTF to
    // level 3 (secure). Selected vulns are left at whatever level the instructor
    // chose on Vuln Config — this only fixes the ones not in the event.
    if ($action === 'fix-unselected') {
        $fixed = 0;
        foreach ($engine->getAllVulns()['modules'] as $mod => $vulns) {
            foreach ($vulns as $vid => $v) {
                if (empty($v['ctf_active'])) {
                    $engine->setVulnLevel($mod, strtolower($vid), 3);
                    $fixed++;
                }
            }
        }
        echo json_encode(['ok' => true, 'fixed' => $fixed]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// ---- GET: render ----
$allVulns = $engine->getAllVulns();
$csrf = csrf_token();

$levelLabels = ['Vulnerable', 'Weak', 'Partial', 'Secure'];
$rowsByModule = [];
$activeCount = 0;
$activePoints = 0;
$totalCount = 0;

foreach ($allVulns['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        $totalCount++;
        $key    = $mod . '.' . strtolower($vid);
        $level  = (int) ($v['level'] ?? 0);
        $secure = $level >= 3;
        // A training/pentest-only vuln (ctf_eligible = false) can never be in a CTF.
        $trainingOnly = array_key_exists('ctf_eligible', $v) && !$v['ctf_eligible'];
        // A fixed (L3) vulnerability has no flag, so it cannot be part of the CTF
        // — it's shown greyed-out and uncheckable regardless of any stored flag.
        $active = !empty($v['ctf_active']) && !$secure && !$trainingOnly;
        $points = Flags::points($key, $level); // priced at the level that will run
        if ($active) {
            $activeCount++;
            $activePoints += $points;
        }

        $title = htmlspecialchars((string) ($v['title'] ?? $vid));
        $id    = htmlspecialchars((string) ($v['id'] ?? strtoupper($vid)));
        $cwe   = htmlspecialchars((string) ($v['cwe_id'] ?? ''));
        $owasp = htmlspecialchars((string) ($v['owasp_category'] ?? ''));
        $modE  = htmlspecialchars($mod);
        $vidE  = htmlspecialchars(strtolower($vid));
        $checked  = $active ? ' checked' : '';
        $lvlBadge = $secure
            ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300'
            : 'bg-amber-50 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300';
        $lvlName  = $levelLabels[$level] ?? (string) $level;
        if ($trainingOnly) {
            $warn = '<div class="text-xs text-indigo-500 dark:text-indigo-400 mt-0.5">Training / pentest only — demonstrated in the labs with its full level ladder; not part of the CTF and never scored.</div>';
        } elseif ($secure) {
            $warn = '<div class="text-xs text-gray-500 dark:text-slate-500 mt-0.5">Fixed (L3) — set a vulnerable level in <a href="/vuln-config" class="underline">Vuln Config</a> to include it in the CTF.</div>';
        } else {
            $warn = '';
        }

        // Training-only or fixed (L3) vulns: disabled + greyed checkbox; otherwise the normal control.
        if ($trainingOnly) {
            $checkbox = '<input type="checkbox" disabled title="Training / pentest only — not available for the CTF" class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 opacity-40 cursor-not-allowed">';
        } elseif ($secure) {
            $checkbox = '<input type="checkbox" disabled title="Fixed (L3) — not available for the CTF" class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 opacity-40 cursor-not-allowed">';
        } else {
            $checkbox = '<input type="checkbox" onchange="setActive(\'' . $modE . '\',\'' . $vidE . '\',this.checked)"' . $checked . ' class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-amber-600 focus:ring-amber-500">';
        }
        $rowCls = ($secure || $trainingOnly) ? ' opacity-50' : '';

        $rowsByModule[$mod][] = <<<HTML
            <tr class="border-t border-gray-100 dark:border-slate-800{$rowCls}" data-key="{$key}">
                <td class="py-2 pl-1">{$checkbox}</td>
                <td class="py-2"><span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300">{$id}</span></td>
                <td class="py-2 text-gray-800 dark:text-slate-200 text-sm">{$title}
                    <div class="text-xs text-gray-400 dark:text-slate-500">{$owasp} · {$cwe}</div>{$warn}
                </td>
                <td class="py-2"><span class="text-xs px-1.5 py-0.5 rounded {$lvlBadge}">L{$level} · {$lvlName}</span></td>
                <td class="py-2 text-right text-gray-500 dark:text-slate-400 text-sm font-mono">{$points}</td>
            </tr>
HTML;
    }
}

$moduleBlocks = '';
foreach ($rowsByModule as $mod => $rows) {
    $modE = htmlspecialchars($mod);
    $body = implode('', $rows);
    $moduleBlocks .= <<<HTML
        <div data-modcard class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 mb-4">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-500 font-semibold mb-2">{$modE}</div>
            <table class="w-full">
                <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                    <th class="pb-2 pl-1 w-8">In CTF</th><th class="pb-2">ID</th><th class="pb-2">Vulnerability</th><th class="pb-2">Level</th><th class="pb-2 text-right">Points</th>
                </tr></thead>
                <tbody>{$body}</tbody>
            </table>
        </div>
HTML;
}

// Planning groups are created on the Vuln Config page; here they're a read-only
// lens to filter the list. Selecting one NEVER changes ctf_active.
$groupsJson = json_encode(vuln_groups_load(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

$content = <<<HTML
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <p class="text-sm text-gray-600 dark:text-slate-400">
            Choose which vulnerabilities are part of the CTF event. This is separate from
            <a href="/vuln-config" class="text-amber-700 dark:text-amber-400 underline">Vuln Config</a>, which sets each one's difficulty level.
        </p>
        <div class="flex gap-2">
            <button onclick="bulk('all')" class="rounded-lg bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 px-3 py-2 text-xs font-medium">Select all</button>
            <button onclick="bulk('none')" class="rounded-lg bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 px-3 py-2 text-xs font-medium">Clear all</button>
            <button onclick="bulk('scored')" class="rounded-lg bg-amber-50 dark:bg-amber-600/30 hover:bg-amber-100 dark:hover:bg-amber-600/50 px-3 py-2 text-xs text-amber-700 dark:text-amber-200 font-medium">Default 8 scored</button>
        </div>
    </div>

    <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 ring-1 ring-amber-200 dark:ring-amber-500/30 px-4 py-3 mb-5 flex flex-wrap gap-6 text-sm">
        <div><span class="text-gray-600 dark:text-slate-400">Active in CTF:</span> <span id="activeCount" class="font-semibold text-amber-800 dark:text-amber-300">{$activeCount}</span> / {$totalCount}</div>
        <div><span class="text-gray-600 dark:text-slate-400">Score available:</span> <span id="activePoints" class="font-semibold text-amber-800 dark:text-amber-300">{$activePoints}</span> pts</div>
        <div class="text-xs text-gray-500 dark:text-slate-500 self-center">Points are indicative (flat per vuln today); per-level scoring is planned.</div>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 ring-1 ring-gray-200 dark:ring-slate-800 px-4 py-3 mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-gray-600 dark:text-slate-400 max-w-2xl">
            <span class="font-medium text-gray-800 dark:text-slate-200">Deliver the event:</span>
            optionally set every vulnerability <em>not</em> selected above to <strong>Fixed (L3)</strong>, so only the selected ones are exploitable. Selected vulnerabilities keep the level you chose on <a href="/vuln-config" class="text-amber-700 dark:text-amber-400 underline">Vuln Config</a>.
        </div>
        <button onclick="fixUnselected()" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 text-xs font-semibold whitespace-nowrap">Set unselected → Fixed</button>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 ring-1 ring-gray-200 dark:ring-slate-800 px-4 py-3 mb-5">
        <div class="text-sm font-medium text-gray-800 dark:text-slate-200 mb-1">Planning groups</div>
        <div class="text-xs text-gray-500 dark:text-slate-500 mb-3">The same groups you build on <a href="/vuln-config" class="text-amber-700 dark:text-amber-400 underline">Vuln Config</a>. Select one to show only its vulnerabilities, in order, so you can manage the event around it. This is just a filter — it does <b>not</b> add anything to the CTF; you still tick each one yourself.</div>
        <div id="groupsBar" class="flex flex-wrap gap-2"></div>
    </div>

    <div id="groupView" class="hidden rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-amber-200 dark:ring-amber-500/30 p-4 mb-4">
        <div class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300 font-semibold mb-2">Group · <span id="groupViewName"></span></div>
        <table class="w-full">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2 pl-1 w-8">In CTF</th><th class="pb-2">ID</th><th class="pb-2">Vulnerability</th><th class="pb-2">Level</th><th class="pb-2 text-right">Points</th>
            </tr></thead>
            <tbody id="groupViewBody"></tbody>
        </table>
        <div id="groupViewEmpty" class="hidden text-xs text-gray-400 dark:text-slate-500 py-2">None of this group's vulnerabilities exist in the current config.</div>
    </div>

    {$moduleBlocks}

    <div id="status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <script>
    const CSRF = "{$csrf}";
    const GROUPS = {$groupsJson};
    const STORE_KEY = 'bs_ctfvulns_group';
    function setStatus(msg, ok) {
        const el = document.getElementById('status');
        el.textContent = (ok ? '✓ ' : '✗ ') + msg;
    }
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
    }

    // ---- Planning groups: read-only filter. Selecting one only shows/orders its
    // vulns; it never changes any ctf_active state. ----
    const MODCARDS    = Array.prototype.slice.call(document.querySelectorAll('[data-modcard]'));
    const MOD_TBODIES = MODCARDS.map(function(c){ return c.querySelector('tbody'); });
    MOD_TBODIES.forEach(function(tb){ tb._origRows = Array.prototype.slice.call(tb.querySelectorAll('tr[data-key]')); });
    const ROW_BY_KEY = {};
    MOD_TBODIES.forEach(function(tb){ tb._origRows.forEach(function(tr){ ROW_BY_KEY[tr.getAttribute('data-key')] = tr; }); });
    const GV     = document.getElementById('groupView');
    const GVBODY = document.getElementById('groupViewBody');

    let activeGroup = null;
    try { activeGroup = localStorage.getItem(STORE_KEY) || null; } catch (e) {}
    if (activeGroup && !GROUPS.some(function(g){ return g.id === activeGroup; })) activeGroup = null;

    function restoreRows() {
        // Move every row back to its module table in the original order.
        MOD_TBODIES.forEach(function(tb){ tb._origRows.forEach(function(tr){ tr.style.display = ''; tb.appendChild(tr); }); });
    }
    function applyFilter() {
        const g = GROUPS.find(function(x){ return x.id === activeGroup; });
        restoreRows();
        if (!g) {
            MODCARDS.forEach(function(c){ c.style.display = ''; });
            GV.classList.add('hidden');
            return;
        }
        GVBODY.innerHTML = '';
        let count = 0;
        (g.members || []).forEach(function(m){
            const tr = ROW_BY_KEY[m.key];
            if (!tr) return;
            tr.style.display = '';
            GVBODY.appendChild(tr);   // moves the real row (with its working checkbox) here, in order
            count++;
        });
        MODCARDS.forEach(function(c){ c.style.display = 'none'; });
        document.getElementById('groupViewName').textContent = g.name + ' (' + count + ')';
        document.getElementById('groupViewEmpty').classList.toggle('hidden', count > 0);
        GV.classList.remove('hidden');
    }
    function selectGroup(id) {
        activeGroup = id;
        try { if (id) localStorage.setItem(STORE_KEY, id); else localStorage.removeItem(STORE_KEY); } catch (e) {}
        applyFilter(); renderGroupsBar();
    }
    function renderGroupsBar() {
        const bar = document.getElementById('groupsBar');
        let html = '<button onclick="selectGroup(null)" class="rounded-lg px-3 py-1 text-xs font-medium '
            + (activeGroup === null ? 'bg-amber-600 text-white' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200 hover:bg-gray-200 dark:hover:bg-slate-700')
            + '">All vulnerabilities</button>';
        if (!GROUPS.length) {
            html += '<span class="text-xs text-gray-400 dark:text-slate-500 self-center">No groups yet — create one on <a href="/vuln-config" class="underline">Vuln Config</a>.</span>';
        }
        html += GROUPS.map(function(g){
            const n = g.members ? g.members.length : 0;
            const on = g.id === activeGroup;
            return '<button onclick="selectGroup(\'' + g.id + '\')" class="rounded-lg px-3 py-1 text-xs font-medium ring-1 '
                + (on ? 'bg-amber-50 dark:bg-amber-500/15 ring-amber-300 dark:ring-amber-500/40 text-amber-800 dark:text-amber-200' : 'ring-gray-200 dark:ring-slate-700 bg-gray-50 dark:bg-slate-800/60 text-gray-800 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700')
                + '">' + esc(g.name) + ' <span class="text-[10px] text-gray-400 dark:text-slate-500">(' + n + ')</span></button>';
        }).join('');
        bar.innerHTML = html;
    }
    renderGroupsBar();
    applyFilter();

    async function fixUnselected() {
        if (!confirm('Set every UNSELECTED vulnerability to Fixed (L3)? Selected vulnerabilities keep the level you configured.')) return;
        const r = await fetch('/ctf-vulns', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'fix-unselected', csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) { setStatus(d.fixed + ' unselected vulnerabilities set to Fixed (L3).', true); }
        else { setStatus(d.error || 'Failed', false); }
    }
    async function setActive(mod, vuln, active) {
        const r = await fetch('/ctf-vulns', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'set-active', module: mod, vuln, active, csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) {
            setStatus(mod + '.' + vuln + (active ? ' added to CTF' : ' removed from CTF'), true);
            location.reload();
        } else {
            setStatus(d.error || 'Failed', false);
        }
    }
    async function bulk(which) {
        const labels = {all: 'select ALL', none: 'clear ALL', scored: 'select the default 8 scored'};
        if (!confirm('This will ' + labels[which] + ' vulnerabilities for the CTF. Continue?')) return;
        const r = await fetch('/ctf-vulns', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'bulk', which, csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) location.reload(); else setStatus(d.error || 'Failed', false);
    }
    </script>
HTML;

sa_render('CTF Vulnerabilities', $content, 'ctf-vulns');
