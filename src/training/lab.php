<?php

declare(strict_types=1);

/**
 * Flag Submission — the participant's CTF dashboard.
 *
 *  - Flag submission input (always visible).
 *  - Stats: flags captured, points earned, max possible.
 *  - CTF vulnerability table with per-row flag submission.
 */

require_once __DIR__ . '/_shared.php';

auth()->requireAuth();

$pdo   = db();
$url   = htmlspecialchars(APP_URL);
$csrf  = CSRF::generateToken();
$user  = auth()->getCurrentUser();
$userId = (int) $user['id'];

// CTF challenges are whatever the instructor has SELECTED into the current CTF
// (ctf_active) AND is still vulnerable (level < 3, i.e. actually capturable). This
// keeps the Flag Submission page in sync with the live selection and levels — a
// deselected or secured vuln neither shows nor scores. Points/titles come from the
// live config so they always reflect the current level.
$engine   = engine();
$ctfVulns = [];
foreach (tr_all_vulns() as $v) {
    if ($engine->isCtfActive($v['module'], $v['vid']) && $v['level'] < 3) {
        $ctfVulns[] = [
            'key'      => $v['key'],
            'id'       => $v['id'],
            'title'    => $v['title'],
            'category' => $v['category'],
            'level'    => $v['level'],
        ];
    }
}

// Get this user's CTF submissions
$capturedMap = [];
try {
    $stmt = $pdo->prepare('SELECT vuln_key, points, submitted_at FROM ctf_submissions WHERE user_id = :uid');
    $stmt->execute([':uid' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $capturedMap[$row['vuln_key']] = [
            'points' => (int) $row['points'],
            'at'     => $row['submitted_at'],
        ];
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

// Compute stats
$totalVulns = count($ctfVulns);
$flagsCaptured = 0;
$pointsEarned = 0;
$maxPoints = 0;
foreach ($ctfVulns as $v) {
    $pts = Flags::points($v['key'], $v['level']);
    $maxPoints += $pts;
    if (isset($capturedMap[$v['key']])) {
        $flagsCaptured++;
        $pointsEarned += $capturedMap[$v['key']]['points'];
    }
}
$pctComplete = $totalVulns > 0 ? (int) round($flagsCaptured / $totalVulns * 100) : 0;

// Tier color for a given points value.
$tierColor = function (int $pts): string {
    if ($pts >= 400) return 'text-red-300';
    if ($pts >= 300) return 'text-amber-300';
    if ($pts >= 200) return 'text-sky-300';
    return 'text-emerald-300';
};

// Show a "View code" link per challenge when the instructor has opened the
// code viewer to participants (Training).
$showCode = is_feature_enabled('code_viewer');

// Build vuln rows
$rows = '';
foreach ($ctfVulns as $v) {
    $key = htmlspecialchars($v['key']);
    $pts = Flags::points($v['key'], $v['level']);
    $codeLink = '';
    if ($showCode) {
        [$cvMod, $cvVid] = array_pad(explode('.', $v['key'], 2), 2, '');
        $cvHref = $url . '/training/code?module=' . urlencode($cvMod) . '&vuln=' . urlencode($cvVid);
        $codeLink = '<a href="' . $cvHref . '" class="text-xs text-indigo-300 hover:text-indigo-200">View code →</a>';
    }
    $captured = isset($capturedMap[$v['key']]);
    // What you have BANKED for this vuln, versus what the live level is worth.
    // They differ once a vuln is re-levelled after capture — show the banked
    // figure (that is what the total is built from) and offer the top-up.
    $banked   = $captured ? (int) $capturedMap[$v['key']]['points'] : 0;
    $topUp    = $captured ? max(0, $pts - $banked) : 0;
    $badge = $captured
        ? 'bg-emerald-500/20 text-emerald-300'
        : 'bg-red-500/20 text-red-300';
    $statusHtml = $captured
        ? '<span class="px-2 py-0.5 rounded text-xs bg-emerald-500/20 text-emerald-300">captured</span>'
        : '<span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300">pending</span>';

    $submitBox = '<div class="flex gap-1.5 justify-end"><input id="flag-' . $key . '" placeholder="BANKSPLOIT{...}" class="w-40 rounded-md bg-slate-950 ring-1 ring-slate-700 px-2 py-1 text-slate-100 font-mono text-xs focus:ring-amber-500 focus:outline-none"><button onclick="submitVulnFlag(\'' . $key . '\')" class="rounded-md bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold px-2.5 py-1 text-xs">Submit</button></div>';
    if (!$captured) {
        $flagCell = $submitBox;
    } elseif ($topUp > 0) {
        // Re-solving at the harder level is worth more than what is banked, so
        // keep the box open — resubmitting the same flag claims the difference.
        $flagCell = '<div class="text-[11px] text-amber-300 mb-1">✓ Captured — re-submit at L' . (int) $v['level'] . ' for +' . $topUp . '</div>' . $submitBox;
    } else {
        $flagCell = '<span class="text-xs text-emerald-300 font-medium">✓ Captured</span>';
    }

    // Points column: banked value for a captured vuln, live price otherwise.
    $shownPts = $captured ? $banked : $pts;
    $ptsColor = $tierColor($shownPts);
    $ptsCell  = (string) $shownPts
        . ($topUp > 0 ? '<div class="text-[11px] font-normal text-amber-400">↑ ' . $pts . ' at L' . (int) $v['level'] . '</div>' : '');

    $rows .= <<<HTML
        <tr class="border-t border-slate-800 hover:bg-slate-800/40">
            <td class="py-2.5"><span class="font-mono text-xs px-1.5 py-0.5 rounded {$badge}">{$v['id']}</span></td>
            <td class="py-2.5 text-slate-200 text-sm">{$v['title']}<div class="text-xs text-slate-500">{$v['category']}</div>{$codeLink}</td>
            <td class="py-2.5 font-mono text-sm {$ptsColor} font-bold">{$ptsCell}</td>
            <td class="py-2.5">{$statusHtml}</td>
            <td class="py-2.5 text-right" id="fc-{$key}">{$flagCell}</td>
        </tr>
HTML;
}

if ($rows === '') {
    $rows = '<tr><td colspan="5" class="py-6 text-center text-slate-500 text-sm">No vulnerabilities are currently selected for the CTF.</td></tr>';
}

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">Flag Submission</h2>
    </div>

    <!-- flag submission — always visible -->
    <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-500/30 p-4 mb-6">
        <div class="text-sm text-amber-200 font-semibold mb-2">🚩 Submit Flag</div>
        <p class="text-xs text-amber-100/80 mb-3">Exploit a vulnerability to recover its unique flag (format <code>BANKSPLOIT{...}</code>). Each flag is unique to your account — sharing won't work.</p>
        <div class="flex gap-2">
            <input id="flagInput" placeholder="BANKSPLOIT{...}" class="flex-1 rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 font-mono text-sm focus:ring-amber-500 focus:outline-none">
            <button onclick="submitFlag()" class="rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-semibold px-4 py-2 text-sm">Submit</button>
        </div>
        <div id="flagResult" class="mt-2 text-sm"></div>
    </div>

    <!-- stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
            <div class="text-xs text-slate-500 uppercase">Flags Captured</div>
            <div class="text-2xl font-black text-amber-300 mt-1">{$flagsCaptured}<span class="text-base text-slate-500">/{$totalVulns}</span></div>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
            <div class="text-xs text-slate-500 uppercase">Points Earned</div>
            <div class="text-2xl font-black text-white mt-1">{$pointsEarned}</div>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
            <div class="text-xs text-slate-500 uppercase">Max Points</div>
            <div class="text-2xl font-black text-slate-400 mt-1">{$maxPoints}</div>
        </div>
        <div class="rounded-xl bg-gradient-to-br from-indigo-600/30 to-sky-500/20 ring-1 ring-indigo-500/30 p-4">
            <div class="text-xs text-indigo-200 uppercase">Progress</div>
            <div class="text-2xl font-black text-white mt-1">{$pctComplete}%</div>
        </div>
    </div>

    <!-- vuln table -->
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">ID</th><th class="pb-2">Vulnerability</th><th class="pb-2">Points</th><th class="pb-2">Status</th><th class="pb-2 text-right">Flag</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>

    <script>
    const CSRF = "{$csrf}";
    const API = "{$url}/api/v1/training";

    async function submitFlag() {
        const flag = document.getElementById('flagInput').value.trim();
        if (!flag) return;
        const r = await fetch(API + "/submit-flag", {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({flag})});
        const d = await r.json();
        const el = document.getElementById('flagResult');
        el.textContent = d.message;
        el.className = "mt-2 text-sm " + (d.correct ? "text-emerald-300" : "text-red-300");
        if (d.correct) setTimeout(()=>location.reload(), 1200);
    }

    async function submitVulnFlag(vulnKey) {
        const input = document.getElementById('flag-' + vulnKey);
        const flag = input.value.trim();
        if (!flag) { input.focus(); return; }
        const r = await fetch(API + "/submit-flag", {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({flag})});
        const d = await r.json();
        const cell = document.getElementById('fc-' + vulnKey);
        if (d.correct) {
            // d.correct is true for a repeat submission too. Report what was
            // actually awarded, so a no-op re-submit can't look like a capture.
            const note = d.points
                ? ((d.upgraded ? '✓ Levelled up +' : '✓ Captured +') + d.points + ' pts')
                : '✓ Already captured — no additional points';
            cell.innerHTML = '<span class="text-xs text-emerald-300 font-medium">' + note + '</span>';
            setTimeout(()=>location.reload(), 1800);
        } else {
            cell.insertAdjacentHTML('beforeend', '<div class="text-xs text-red-300 mt-1">' + (d.message || 'Incorrect flag') + '</div>');
            setTimeout(() => { const err = cell.querySelector('.text-red-300'); if (err) err.remove(); }, 3000);
        }
    }
    </script>
HTML;

render_layout('Flag Submission', $content, ['active' => 'lab']);
