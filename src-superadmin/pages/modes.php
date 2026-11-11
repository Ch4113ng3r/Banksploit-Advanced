<?php

declare(strict_types=1);

/**
 * Modes — pick CTF / Training / Pentest, or fine-tune individual features.
 *
 * A mode is a preset over the feature toggles. Picking a mode sets every switch
 * at once; flipping a single switch moves the page to "Custom". The switches
 * are the real state the CTF app enforces (via feature_toggles).
 */

$pdo = db();

// ---- POST actions (JSON) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }

    $action = (string) ($input['action'] ?? '');

    if ($action === 'apply-mode') {
        $mode = (string) ($input['mode'] ?? '');
        if (Modes::apply($pdo, $mode)) {
            echo json_encode(['ok' => true, 'mode' => $mode]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Unknown mode.']);
        }
        exit;
    }

    if ($action === 'toggle') {
        $feature = (string) ($input['feature'] ?? '');
        $enabled = (bool) ($input['enabled'] ?? false);
        Modes::setEnabled($pdo, $feature, $enabled);
        // A manual change usually breaks the preset — store the resulting label.
        Modes::set($pdo, Modes::detect($pdo));
        echo json_encode(['ok' => true, 'feature' => $feature, 'enabled' => $enabled, 'mode' => Modes::detect($pdo)]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// ---- GET: render ----
$csrf        = csrf_token();
$liveMode    = Modes::detect($pdo);
$labels      = Modes::labels();
$descriptions = Modes::descriptions();
$presets     = Modes::presets();

// Friendly names + which mode-group each feature belongs to, for display.
$featureMeta = [
    'leaderboard'     => ['Leaderboard', 'CTF'],
    'flag_submission' => ['Flag submission', 'CTF'],
    'flag_delivery'   => ['Flag delivery (modal / header / OTP embed)', 'CTF'],
    'training_lab'    => ['Lab dashboard & challenge list', 'CTF'],
    'hints'           => ['Participant hint banners', 'Training'],
    'code_viewer'     => ['Code viewer — vulnerable vs secure source (participants)', 'Training'],
    'otp_display'     => ['Show OTP on login (dev)', 'Training'],
    'code_inspector'  => ['Request Inspector', 'Training'],
    'swagger_docs'    => ['Swagger / API docs', 'Shared'],
    'request_logging' => ['Request logging', 'Shared'],
    'debug'           => ['Debug mode — live session viewer (/debug/session)', 'Debug'],
];

// Build the feature list from the live table so every toggle shows, even one
// added later that isn't in $featureMeta (it falls into an "Other" group).
$groupOrder = ['CTF' => 0, 'Training' => 1, 'Shared' => 2, 'Debug' => 3, 'Other' => 4];
$features = [];
foreach ($pdo->query('SELECT feature_key, is_enabled, description FROM feature_toggles')->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $key = (string) $t['feature_key'];
    [$name, $group] = $featureMeta[$key] ?? [ucwords(str_replace('_', ' ', $key)), 'Other'];
    $features[] = [
        'key'   => $key,
        'name'  => $name,
        'group' => $group,
        'on'    => (bool) $t['is_enabled'],
        'desc'  => (string) ($t['description'] ?? ''),
    ];
}
usort($features, static function (array $a, array $b) use ($groupOrder): int {
    return [$groupOrder[$a['group']] ?? 9, $a['name']] <=> [$groupOrder[$b['group']] ?? 9, $b['name']];
});

// Mode cards
$modeCards = '';
foreach (['ctf', 'training', 'pentest'] as $m) {
    $isLive = $liveMode === $m;
    $ring = $isLive ? 'ring-2 ring-amber-500 dark:ring-amber-400' : 'ring-1 ring-gray-200 dark:ring-slate-800';
    $badge = $isLive
        ? '<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-300 font-semibold">ACTIVE</span>'
        : '';
    $label = htmlspecialchars($labels[$m]);
    $desc  = htmlspecialchars($descriptions[$m]);
    $btn = $isLive
        ? '<span class="inline-block mt-3 text-xs text-gray-400 dark:text-slate-500">Currently active</span>'
        : '<button onclick="applyMode(\'' . $m . '\')" class="mt-3 w-full rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold py-2 text-sm">Switch to ' . $label . '</button>';
    $modeCards .= <<<HTML
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none {$ring} p-5">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">{$label}</h3>
                {$badge}
            </div>
            <p class="text-sm text-gray-500 dark:text-slate-400 leading-relaxed">{$desc}</p>
            {$btn}
        </div>
HTML;
}

// Custom banner
$customNote = $liveMode === 'custom'
    ? '<div class="rounded-xl bg-sky-50 dark:bg-sky-500/10 ring-1 ring-sky-200 dark:ring-sky-500/30 px-4 py-3 mb-6 text-sm text-sky-800 dark:text-sky-300">You are in a <strong>Custom</strong> configuration — the switches below don\'t match any single mode. Pick a mode above to snap them to a preset.</div>'
    : '';

// Feature switches — show the live value and which preset each mode wants.
$rows = '';
foreach ($features as $f) {
    $key   = $f['key'];
    $group = $f['group'];
    $nameE = htmlspecialchars($f['name']);
    $keyE  = htmlspecialchars($key);
    $descE = htmlspecialchars($f['desc']);
    $grpCls = ['CTF' => 'amber', 'Training' => 'emerald', 'Shared' => 'slate', 'Debug' => 'rose', 'Other' => 'slate'][$group] ?? 'slate';
    $checked = $f['on'] ? ' checked' : '';
    // show preset expectation dots (what each mode sets this to)
    $dots = '';
    foreach (['ctf', 'training', 'pentest'] as $m) {
        $want = $presets[$m][$key] ?? null;
        $sym = $want === true ? '●' : ($want === false ? '○' : '–');
        $dots .= '<span class="font-mono text-xs text-gray-400 dark:text-slate-500" title="' . $m . '">' . $sym . '</span>';
    }
    $sub = $descE !== '' ? $descE : $keyE;
    $rows .= <<<HTML
        <tr class="border-t border-gray-100 dark:border-slate-800">
            <td class="py-2 pr-2"><span class="text-xs px-1.5 py-0.5 rounded bg-{$grpCls}-50 dark:bg-{$grpCls}-500/20 text-{$grpCls}-700 dark:text-{$grpCls}-300">{$group}</span></td>
            <td class="py-2 text-gray-800 dark:text-slate-200 text-sm">{$nameE}<div class="text-xs text-gray-400 dark:text-slate-500">{$sub} <span class="font-mono">· {$keyE}</span></div></td>
            <td class="py-2 text-center whitespace-nowrap">{$dots}</td>
            <td class="py-2 text-right">
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" onchange="toggleFeature('{$keyE}',this.checked)"{$checked} class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-amber-600 focus:ring-amber-500">
                </label>
            </td>
        </tr>
HTML;
}

$content = <<<HTML
    <p class="text-sm text-gray-600 dark:text-slate-400 mb-5">
        Pick a mode to set every feature at once, or fine-tune individual switches below. Switches are what the CTF app enforces; the vulnerabilities themselves are set on
        <a href="/vuln-config" class="text-amber-700 dark:text-amber-400 underline">Vuln Config</a> and chosen for the event on
        <a href="/ctf-vulns" class="text-amber-700 dark:text-amber-400 underline">CTF Vulnerabilities</a>.
    </p>

    <div class="grid gap-4 md:grid-cols-3 mb-6">{$modeCards}</div>

    {$customNote}

    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-300 uppercase tracking-wide">Features</h3>
            <div class="text-xs text-gray-400 dark:text-slate-500">preset columns: ● on &nbsp; ○ off &nbsp;<span class="ml-2">CTF · Training · Pentest</span></div>
        </div>
        <table class="w-full">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">Group</th><th class="pb-2">Feature</th><th class="pb-2 text-center">C·T·P</th><th class="pb-2 text-right">Enabled</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <div id="status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <script>
    const CSRF = "{$csrf}";
    async function post(body) {
        const r = await fetch('/modes', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({...body, csrf_token: CSRF})});
        return r.json();
    }
    async function applyMode(mode) {
        if (!confirm('Switch the app to ' + mode.toUpperCase() + ' mode? This changes what participants see.')) return;
        const d = await post({action:'apply-mode', mode});
        if (d.ok) location.reload(); else document.getElementById('status').textContent = '✗ ' + (d.error || 'Failed');
    }
    async function toggleFeature(feature, enabled) {
        const d = await post({action:'toggle', feature, enabled});
        if (d.ok) location.reload(); else document.getElementById('status').textContent = '✗ ' + (d.error || 'Failed');
    }
    </script>
HTML;

$liveLabel = htmlspecialchars($labels[$liveMode] ?? 'Custom');
sa_render('Modes · ' . $liveLabel, $content, 'modes');
