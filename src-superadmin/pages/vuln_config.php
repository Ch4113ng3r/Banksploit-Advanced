<?php

declare(strict_types=1);

$engine = engine();

// Handle POST — set levels
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }

    $action = $input['action'] ?? '';

    if ($action === 'set-level') {
        $module = (string) ($input['module'] ?? '');
        $vuln   = (string) ($input['vuln'] ?? '');
        $level  = max(0, min(3, (int) ($input['level'] ?? 0)));
        $engine->setVulnLevel($module, $vuln, $level);
        echo json_encode(['ok' => true, 'module' => $module, 'vuln' => $vuln, 'level' => $level]);
        exit;
    }

    if ($action === 'preset') {
        $preset = (string) ($input['preset'] ?? '');
        $allVulns = $engine->getAllVulns();
        $level = match ($preset) {
            'beginner' => 0,
            'secure'   => 3,
            default    => null,
        };
        if ($level !== null) {
            foreach ($allVulns['modules'] as $mod => $vulns) {
                foreach ($vulns as $vid => $v) {
                    $engine->setVulnLevel($mod, strtolower($vid), $level);
                }
            }
        }
        echo json_encode(['ok' => true, 'preset' => $preset]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// GET — render page
$allVulns = $engine->getAllVulns();
$csrf = csrf_token();

$rows = '';
foreach ($allVulns['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        $level = (int) ($v['level'] ?? 0);
        $title = htmlspecialchars((string) ($v['title'] ?? $vid));
        $id    = htmlspecialchars((string) ($v['id'] ?? strtoupper($vid)));
        $owasp = htmlspecialchars((string) ($v['owasp_category'] ?? ''));
        $cwe   = htmlspecialchars((string) ($v['cwe_id'] ?? ''));
        $modE  = htmlspecialchars($mod);
        $vidE  = htmlspecialchars(strtolower($vid));
        $opts = '';
        foreach ([0, 1, 2, 3] as $lv) {
            $sel = $lv === $level ? ' selected' : '';
            $label = ['Vulnerable', 'Weak', 'Partial', 'Secure'][$lv];
            $opts .= "<option value=\"{$lv}\"{$sel}>{$lv} · {$label}</option>";
        }
        $badge = $level >= 3
            ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-0'
            : 'bg-red-50 dark:bg-red-500/20 text-red-700 dark:text-red-300 ring-1 ring-red-200 dark:ring-0';
        $rows .= <<<HTML
            <tr class="border-t border-gray-100 dark:border-slate-800">
                <td class="py-2"><span class="font-mono text-xs px-1.5 py-0.5 rounded {$badge}">{$id}</span></td>
                <td class="py-2 text-gray-500 dark:text-slate-400 text-xs">{$modE}</td>
                <td class="py-2 text-gray-800 dark:text-slate-200 text-sm">{$title}<div class="text-xs text-gray-400 dark:text-slate-500">{$owasp} · {$cwe}</div></td>
                <td class="py-2 text-right">
                    <select onchange="setLevel('{$modE}','{$vidE}',this.value)"
                            class="rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-2 py-1 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none">{$opts}</select>
                </td>
            </tr>
HTML;
    }
}

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">Set difficulty levels for each vulnerability. Changes take effect immediately on the CTF app.</p>
        </div>
        <div class="flex gap-2">
            <button onclick="applyPreset('beginner')" class="rounded-lg bg-red-50 dark:bg-red-600/30 hover:bg-red-100 dark:hover:bg-red-600/50 ring-1 ring-red-200 dark:ring-0 px-3 py-2 text-xs text-red-700 dark:text-red-200 font-medium">All Vulnerable</button>
            <button onclick="applyPreset('secure')" class="rounded-lg bg-emerald-50 dark:bg-emerald-600/30 hover:bg-emerald-100 dark:hover:bg-emerald-600/50 ring-1 ring-emerald-200 dark:ring-0 px-3 py-2 text-xs text-emerald-700 dark:text-emerald-200 font-medium">All Secure</button>
        </div>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">ID</th><th class="pb-2">Module</th><th class="pb-2">Vulnerability</th><th class="pb-2 text-right">Level</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <div id="status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <script>
    const CSRF = "{$csrf}";
    async function setLevel(mod, vuln, level) {
        const r = await fetch('/vuln-config', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'set-level', module: mod, vuln, level: Number(level), csrf_token: CSRF})
        });
        const d = await r.json();
        document.getElementById('status').textContent = d.ok
            ? '✓ ' + mod + '.' + vuln + ' → Level ' + level
            : '✗ ' + (d.error || 'Failed');
    }
    async function applyPreset(preset) {
        if (!confirm('Apply preset "' + preset + '" to ALL vulnerabilities?')) return;
        const r = await fetch('/vuln-config', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'preset', preset, csrf_token: CSRF})
        });
        const d = await r.json();
        if (d.ok) location.reload();
    }
    </script>
HTML;

sa_render('Vulnerability Config', $content, 'vuln-config');
