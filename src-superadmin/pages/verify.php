<?php

declare(strict_types=1);

/**
 * Verification — a manual checklist for working through every vulnerability at
 * every level and marking it "verified working".
 *
 * This is a worksheet for the instructor, not an app control: it changes no
 * behaviour. It reads the vulnerability list from vuln_config.json and keeps the
 * ticks in a plain JSON file on disk (NOT the database), so progress survives
 * restarts and can be read/edited as a file:
 *
 *     data/verify_progress.json
 *
 * Levels: L0 fully vulnerable · L1 weak mitigation · L2 partial fix · L3 secure.
 */

$engine = engine();

$stateFile = dirname(__DIR__, 2) . '/data/verify_progress.json';

/** @return array{cells: array<string,mixed>, notes: array<string,string>} */
function vp_load(string $file): array
{
    $def = ['cells' => [], 'notes' => []];
    if (!is_file($file)) {
        return $def;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return $def;
    }
    return [
        'cells' => is_array($data['cells'] ?? null) ? $data['cells'] : [],
        'notes' => is_array($data['notes'] ?? null) ? $data['notes'] : [],
        // 'batch' = keys highlighted as the active rework batch. null = never set
        // (seed defaults); an explicit array (even empty) is respected as-is.
        'batch' => array_key_exists('batch', $data) && is_array($data['batch']) ? $data['batch'] : null,
    ];
}

/** Default highlighted set — the planned rework batch (docs/VULN_LEVEL_PLAN.md). */
function vp_default_batch(): array
{
    $keys = [
        'auth.l25', 'statements.g2', 'statements.g4', 'statements.g9',
        'transfer.l1', 'loans.g10', 'auth.l12', 'sessions.g12',
        'loans.g6', 'admin.l4', 'beneficiary.g3',
    ];
    return array_fill_keys($keys, true);
}

function vp_save(string $file, array $data): bool
{
    @mkdir(dirname($file), 0775, true);
    return file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

// ---- POST actions (JSON) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }

    $state  = vp_load($stateFile);
    $action = (string) ($input['action'] ?? '');

    if ($action === 'toggle') {
        $key   = (string) ($input['key'] ?? '');   // e.g. "auth.g1"
        $level = (string) (int) ($input['level'] ?? 0);
        $done  = (bool) ($input['done'] ?? false);
        $cellKey = $key . '|' . $level;
        if ($done) {
            $state['cells'][$cellKey] = ['done' => true, 'at' => date('c')];
        } else {
            unset($state['cells'][$cellKey]);
        }
        echo json_encode(['ok' => vp_save($stateFile, $state), 'key' => $key, 'level' => $level, 'done' => $done]);
        exit;
    }

    if ($action === 'batch') {
        $key = (string) ($input['key'] ?? '');
        $on  = (bool) ($input['on'] ?? false);
        $batch = is_array($state['batch'] ?? null) ? $state['batch'] : vp_default_batch();
        if ($on) {
            $batch[$key] = true;
        } else {
            unset($batch[$key]);
        }
        $state['batch'] = $batch;
        echo json_encode(['ok' => vp_save($stateFile, $state), 'key' => $key, 'on' => $on]);
        exit;
    }

    if ($action === 'note') {
        $key  = (string) ($input['key'] ?? '');
        $text = trim((string) ($input['text'] ?? ''));
        if ($text === '') {
            unset($state['notes'][$key]);
        } else {
            $state['notes'][$key] = mb_substr($text, 0, 2000);
        }
        echo json_encode(['ok' => vp_save($stateFile, $state), 'key' => $key]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// ---- GET: render ----
$state    = vp_load($stateFile);
$batch    = is_array($state['batch'] ?? null) ? $state['batch'] : vp_default_batch();
$allVulns = $engine->getAllVulns();
$csrf     = csrf_token();

$levelLabels = ['Vulnerable', 'Weak', 'Partial', 'Secure'];
$levelHelp   = ['Fully vulnerable', 'Weak/blacklist mitigation', 'Partial fix', 'Secure — verify fixed, no flag'];

$totalCells = 0;
$doneCells  = 0;
$rowsByModule = [];

foreach ($allVulns['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        $key   = $mod . '.' . strtolower($vid);
        $title = htmlspecialchars((string) ($v['title'] ?? $vid));
        $id    = htmlspecialchars((string) ($v['id'] ?? strtoupper((string) $vid)));
        $cwe   = htmlspecialchars((string) ($v['cwe_id'] ?? ''));
        $owasp = htmlspecialchars((string) ($v['owasp_category'] ?? ''));
        $keyE  = htmlspecialchars($key, ENT_QUOTES);
        $note  = htmlspecialchars((string) ($state['notes'][$key] ?? ''));

        $isBatch      = !empty($batch[$key]);
        $batchChecked = $isBatch ? ' checked' : '';
        $rowHlClass   = $isBatch ? ' batch-hl' : '';

        // 4 level checkboxes
        $cells = '';
        for ($lvl = 0; $lvl <= 3; $lvl++) {
            $totalCells++;
            $done = !empty($state['cells'][$key . '|' . $lvl]['done']);
            if ($done) {
                $doneCells++;
            }
            $checked = $done ? ' checked' : '';
            $cells .= <<<HTML
                <td class="py-2 text-center">
                    <input type="checkbox" data-cell onchange="toggle('{$keyE}',{$lvl},this.checked)"{$checked}
                           class="h-4 w-4 rounded border-gray-300 dark:border-slate-600 text-emerald-600 focus:ring-emerald-500">
                </td>
HTML;
        }

        $rowDone = 0;
        for ($lvl = 0; $lvl <= 3; $lvl++) {
            if (!empty($state['cells'][$key . '|' . $lvl]['done'])) {
                $rowDone++;
            }
        }
        $rowBadge = $rowDone === 4
            ? '<span class="text-xs px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">done</span>'
            : '<span class="text-xs text-gray-400 dark:text-slate-500">' . $rowDone . '/4</span>';

        $rowsByModule[$mod][] = <<<HTML
            <tr class="border-t border-gray-100 dark:border-slate-800{$rowHlClass}" data-row="{$keyE}">
                <td class="py-2 text-center">
                    <input type="checkbox" data-batch onchange="toggleBatch('{$keyE}',this.checked)"{$batchChecked}
                           title="Highlight as part of the active rework batch"
                           class="h-4 w-4 rounded border-red-300 dark:border-red-600 text-red-500 focus:ring-red-400">
                </td>
                <td class="py-2"><span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300">{$id}</span></td>
                <td class="py-2 text-gray-800 dark:text-slate-200 text-sm">{$title}
                    <div class="text-xs text-gray-400 dark:text-slate-500">{$owasp} · {$cwe} · <span class="font-mono">{$keyE}</span></div>
                </td>
                {$cells}
                <td class="py-2 text-center" data-rowstatus>{$rowBadge}</td>
                <td class="py-2 pl-2">
                    <input type="text" value="{$note}" placeholder="notes…"
                           onblur="saveNote('{$keyE}',this.value)"
                           class="w-full text-xs rounded border border-gray-200 dark:border-slate-700 bg-transparent px-2 py-1 text-gray-700 dark:text-slate-300 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                </td>
            </tr>
HTML;
    }
}

$headCells = '';
for ($lvl = 0; $lvl <= 3; $lvl++) {
    $lbl = $levelLabels[$lvl];
    $hlp = htmlspecialchars($levelHelp[$lvl], ENT_QUOTES);
    $headCells .= '<th class="pb-2 text-center" title="' . $hlp . '">L' . $lvl . '<div class="font-normal normal-case text-gray-400 dark:text-slate-500">' . $lbl . '</div></th>';
}

$moduleBlocks = '';
foreach ($rowsByModule as $mod => $rows) {
    $modE = htmlspecialchars((string) $mod);
    $body = implode('', $rows);
    $count = count($rows);
    $moduleBlocks .= <<<HTML
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 mb-4">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-slate-500 font-semibold mb-2">{$modE} <span class="text-gray-400 dark:text-slate-600">· {$count}</span></div>
            <table class="w-full">
                <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                    <th class="pb-2 text-center" title="Highlight as part of the active rework batch">★</th><th class="pb-2">ID</th><th class="pb-2">Vulnerability</th>{$headCells}<th class="pb-2 text-center">Row</th><th class="pb-2 pl-2">Notes</th>
                </tr></thead>
                <tbody>{$body}</tbody>
            </table>
        </div>
HTML;
}

$pct = $totalCells > 0 ? (int) round($doneCells / $totalCells * 100) : 0;

$content = <<<HTML
    <p class="text-sm text-gray-600 dark:text-slate-400 mb-5">
        Work through each vulnerability at each level, confirm it behaves as expected, and tick the box.
        Ticks are saved to a file (<span class="font-mono text-xs">data/verify_progress.json</span>) — no database — so they survive restarts.
        Levels: <strong>L0</strong> fully vulnerable · <strong>L1</strong> weak mitigation · <strong>L2</strong> partial fix · <strong>L3</strong> secure (verify it's fixed, no flag).
        Set each vulnerability's live level on <a href="/vuln-config" class="text-amber-700 dark:text-amber-400 underline">Vuln Config</a>.
    </p>

    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-200 dark:ring-emerald-500/30 px-4 py-3 mb-5">
        <div class="flex flex-wrap items-center gap-6 text-sm">
            <div><span class="text-gray-600 dark:text-slate-400">Verified:</span> <span id="doneCount" class="font-semibold text-emerald-800 dark:text-emerald-300">{$doneCells}</span> / {$totalCells} cells</div>
            <div class="flex-1 min-w-[160px]">
                <div class="h-2 rounded-full bg-emerald-100 dark:bg-emerald-900/40 overflow-hidden">
                    <div id="pctBar" class="h-full bg-emerald-500 transition-all" style="width: {$pct}%"></div>
                </div>
            </div>
            <div><span id="pctText" class="font-semibold text-emerald-800 dark:text-emerald-300">{$pct}</span>%</div>
        </div>
    </div>

    {$moduleBlocks}

    <div id="status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <style>
        tr.batch-hl { background-color: rgba(239, 68, 68, 0.10); box-shadow: inset 3px 0 0 0 #ef4444; }
    </style>
    <script>
    const CSRF = "{$csrf}";
    const TOTAL = {$totalCells};
    function setStatus(msg, ok) {
        document.getElementById('status').textContent = (ok ? '✓ ' : '✗ ') + msg;
    }
    function refreshCounts() {
        const boxes = document.querySelectorAll('input[data-cell]');
        let done = 0;
        boxes.forEach(b => { if (b.checked) done++; });
        document.getElementById('doneCount').textContent = done;
        const pct = TOTAL ? Math.round(done / TOTAL * 100) : 0;
        document.getElementById('pctBar').style.width = pct + '%';
        document.getElementById('pctText').textContent = pct;
        document.querySelectorAll('tr[data-row]').forEach(tr => {
            const cells = tr.querySelectorAll('input[data-cell]');
            let n = 0; cells.forEach(c => { if (c.checked) n++; });
            const cell = tr.querySelector('[data-rowstatus]');
            if (cell) {
                cell.innerHTML = n === 4
                    ? '<span class="text-xs px-1.5 py-0.5 rounded bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">done</span>'
                    : '<span class="text-xs text-gray-400 dark:text-slate-500">' + n + '/4</span>';
            }
        });
    }
    async function post(body) {
        const r = await fetch('/verify', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({...body, csrf_token: CSRF})});
        return r.json();
    }
    async function toggle(key, level, done) {
        const d = await post({action:'toggle', key, level, done});
        if (d.ok) { refreshCounts(); setStatus(key + ' L' + level + (done ? ' marked verified' : ' cleared'), true); }
        else setStatus(d.error || 'Failed to save', false);
    }
    async function saveNote(key, text) {
        const d = await post({action:'note', key, text});
        if (!d.ok) setStatus(d.error || 'Failed to save note', false);
    }
    async function toggleBatch(key, on) {
        const d = await post({action:'batch', key, on});
        if (d.ok) {
            const tr = document.querySelector('tr[data-row="' + key + '"]');
            if (tr) tr.classList.toggle('batch-hl', on);
            setStatus(key + (on ? ' highlighted' : ' unhighlighted'), true);
        } else setStatus(d.error || 'Failed to save', false);
    }
    </script>
HTML;

sa_render('Verification · ' . $doneCells . '/' . $totalCells, $content, 'verify');
