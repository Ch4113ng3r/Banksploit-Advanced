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
        // A fixed (L3) vulnerability has no flag, so it cannot be part of the CTF
        // — it's shown greyed-out and uncheckable regardless of any stored flag.
        $active = !empty($v['ctf_active']) && !$secure;
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
        $warn = $secure
            ? '<div class="text-xs text-gray-500 dark:text-slate-500 mt-0.5">Fixed (L3) — set a vulnerable level in <a href="/vuln-config" class="underline">Vuln Config</a> to include it in the CTF.</div>'
            : '';

        // Fixed vulns: disabled + greyed checkbox; otherwise the normal control.
        $checkbox = $secure
            ? '<input type="checkbox" disabled title="Fixed (L3) — not available for the CTF" class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 opacity-40 cursor-not-allowed">'
            : '<input type="checkbox" onchange="setActive(\'' . $modE . '\',\'' . $vidE . '\',this.checked)"' . $checked . ' class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-amber-600 focus:ring-amber-500">';
        $rowCls = $secure ? ' opacity-50' : '';

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
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 mb-4">
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

    {$moduleBlocks}

    <div id="status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <script>
    const CSRF = "{$csrf}";
    function setStatus(msg, ok) {
        const el = document.getElementById('status');
        el.textContent = (ok ? '✓ ' : '✗ ') + msg;
    }
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
