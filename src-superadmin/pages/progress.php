<?php

declare(strict_types=1);

$pdo = db();
$engine = engine();

// Handle POST — reset progress (requires password re-entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF invalid.']);
        exit;
    }
    $password = (string) ($input['password'] ?? '');
    if ($password === '' || !SuperAdminAuth::verifyPassword($password)) {
        echo json_encode(['ok' => false, 'error' => 'Incorrect password. Reset aborted.']);
        exit;
    }
    $pdo->exec("UPDATE training_progress SET status='hunting', hints_used=0, found_at=NULL");
    echo json_encode(['ok' => true, 'message' => 'All progress reset.']);
    exit;
}

// Flatten vulns
$allVulns = $engine->getAllVulns();
$vulns = [];
foreach ($allVulns['modules'] as $mod => $vs) {
    foreach ($vs as $vid => $v) {
        $vulns[] = [
            'key' => $mod . '.' . strtolower($vid),
            'id'  => $v['id'] ?? strtoupper($vid),
            'title' => $v['title'] ?? $vid,
            'module' => $mod,
        ];
    }
}

// Load progress
$stmt = $pdo->query('SELECT vuln_id, status, hints_used, found_at FROM training_progress');
$progress = [];
foreach ($stmt->fetchAll() as $r) {
    $progress[$r['vuln_id']] = $r;
}

// Stats
$total = count($vulns);
$exploited = 0;
foreach ($progress as $p) {
    if (in_array($p['status'], ['exploited', 'reviewed'], true)) $exploited++;
}

$csrf = csrf_token();

$rows = '';
foreach ($vulns as $v) {
    $p = $progress[$v['key']] ?? null;
    $status = $p['status'] ?? 'hunting';
    $hints  = $p ? (int) $p['hints_used'] : 0;
    $foundAt = $p['found_at'] ?? '—';

    $statusCls = match ($status) {
        'exploited' => 'text-amber-700 dark:text-amber-300 font-medium',
        'reviewed'  => 'text-emerald-700 dark:text-emerald-300 font-medium',
        'found'     => 'text-sky-700 dark:text-sky-300',
        default     => 'text-gray-400 dark:text-slate-500',
    };

    $rows .= '<tr class="border-t border-gray-100 dark:border-slate-800 text-sm">'
        . '<td class="py-2"><span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-300">' . htmlspecialchars($v['id']) . '</span></td>'
        . '<td class="py-2 text-gray-700 dark:text-slate-300">' . htmlspecialchars($v['title']) . '</td>'
        . '<td class="py-2 text-gray-500 dark:text-slate-400 text-xs">' . htmlspecialchars($v['module']) . '</td>'
        . '<td class="py-2 ' . $statusCls . '">' . $status . '</td>'
        . '<td class="py-2 text-gray-500 dark:text-slate-400 text-xs">' . $hints . '</td>'
        . '<td class="py-2 text-gray-400 dark:text-slate-500 text-xs">' . htmlspecialchars((string) $foundAt) . '</td>'
        . '</tr>';
}

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-600 dark:text-slate-400">CTF progress tracker. <strong class="text-amber-700 dark:text-amber-300">{$exploited}/{$total}</strong> vulnerabilities exploited.</p>
        </div>
        <button onclick="resetProgress()" class="rounded-lg bg-red-50 dark:bg-red-600/30 hover:bg-red-100 dark:hover:bg-red-600/50 ring-1 ring-red-200 dark:ring-0 px-3 py-2 text-xs text-red-700 dark:text-red-200 font-medium">Reset All Progress</button>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">ID</th><th class="pb-2">Vulnerability</th><th class="pb-2">Module</th><th class="pb-2">Status</th><th class="pb-2">Hints</th><th class="pb-2">Found At</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <script>
    async function resetProgress() {
        const pw = prompt('Enter your password to confirm reset:');
        if (!pw) return;
        const r = await fetch('/progress', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({csrf_token: "{$csrf}", password: pw})
        });
        const d = await r.json();
        if (d.ok) {
            alert('Progress reset successfully.');
            location.reload();
        } else {
            alert(d.error || 'Reset failed.');
        }
    }
    </script>
HTML;

sa_render('CTF Progress', $content, 'progress');
