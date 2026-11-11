<?php

declare(strict_types=1);

/**
 * CTF Scoring — point values, difficulty breakdown, and scoring rationale.
 * This is the reference page for how points are assigned per vulnerability.
 */

// Build scoring data from Flags::points()
$scoringData = [
    'Easy (100 pts)' => [
        'color' => 'emerald',
        'description' => 'Straightforward, single-step exploits. Requires careful observation of loaded resources.',
        'vulns' => [
            ['key' => 'auth.l11', 'id' => 'L11', 'title' => 'OTP Leaked in JavaScript', 'detail' => 'The OTP is embedded in a JavaScript file loaded with the OTP page. Inspect Sources/Network tab in DevTools.'],
        ],
    ],
    'Medium (200 pts)' => [
        'color' => 'sky',
        'description' => 'Requires basic security tooling, scripting, or understanding of common web vulnerabilities.',
        'vulns' => [
            ['key' => 'auth.g1', 'id' => 'G1', 'title' => 'SQL Injection — Login Bypass', 'detail' => "Classic authentication bypass using SQL injection in the customer_id field. Requires SQL knowledge."],
            ['key' => 'auth.l10', 'id' => 'L10', 'title' => 'OTP Brute-Force', 'detail' => 'No rate limiting on OTP verification. Script 10,000 attempts (0000-9999) to guess the 4-digit code.'],
            ['key' => 'statements.l1', 'id' => 'SL1', 'title' => 'IDOR — View Other Statements', 'detail' => "Discover other account numbers from beneficiaries page, then change ?account= in the Statements URL. Server doesn't verify ownership."],
        ],
    ],
    'Hard (300 pts)' => [
        'color' => 'amber',
        'description' => 'Multi-step exploits requiring deeper understanding of protocols, data formats, or chained techniques.',
        'vulns' => [
            ['key' => 'transfer.l1', 'id' => 'L1', 'title' => 'IDOR — Transfer from Other Account', 'detail' => "The 'From account' field is greyed out. Use DevTools to un-disable it and change to another user's account ID. Complete the full transfer wizard."],
            ['key' => 'auth.l12', 'id' => 'L12', 'title' => 'JWT Algorithm None — Privilege Escalation', 'detail' => 'Forge a JWT token with alg:none and role:admin. Requires understanding JWT structure (header.payload.signature).'],
            ['key' => 'statements.g2', 'id' => 'G2', 'title' => 'SQL Injection — Data Extraction', 'detail' => "UNION-based SQL injection in statement search. Must match column count and extract data from other tables."],
            ['key' => 'statements.g9', 'id' => 'G9', 'title' => 'Command Injection — PDF Export', 'detail' => 'Shell metacharacters in the export filename parameter. Requires understanding of OS command execution context.'],
        ],
    ],
];

// Reference totals (the tier cards below are a base-points explainer for the 8
// headline challenges, priced at level 0).
$totalPoints = 0;
$totalVulns = 0;
foreach ($scoringData as $tier) {
    foreach ($tier['vulns'] as $v) {
        $totalPoints += Flags::points($v['key'], 0);
        $totalVulns++;
    }
}

// Headline "score to achieve" = the vulnerabilities actually selected for the
// CTF (ctf_active), each priced at the level it is set to run. This follows the
// CTF Vulnerabilities selection, so changing the selection or a level updates it.
$engine = engine();
$eventScore = 0;
$eventCount = 0;
foreach ($engine->getAllVulns()['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        if (empty($v['ctf_active'])) {
            continue;
        }
        $eventCount++;
        $eventScore += Flags::points($mod . '.' . strtolower((string) $vid), (int) ($v['level'] ?? 0));
    }
}

// Build tier cards
$tierCards = '';
foreach ($scoringData as $tierName => $tier) {
    $color = $tier['color'];
    $tierPts = 0;
    foreach ($tier['vulns'] as $v) {
        $tierPts += Flags::points($v['key'], 0);
    }
    $vulnCount = count($tier['vulns']);

    $vulnRows = '';
    foreach ($tier['vulns'] as $v) {
        $pts = Flags::points($v['key'], 0);
        $vulnRows .= <<<HTML
            <div class="rounded-lg bg-gray-50 dark:bg-slate-950/50 p-3 border border-gray-200 dark:border-slate-800">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-{$color}-100 dark:bg-{$color}-500/20 text-{$color}-800 dark:text-{$color}-300">{$v['id']}</span>
                        <span class="text-sm text-gray-900 dark:text-white font-medium">{$v['title']}</span>
                    </div>
                    <span class="font-mono text-sm text-{$color}-700 dark:text-{$color}-300 font-bold">+{$pts}</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-slate-500 mt-1 leading-relaxed">{$v['detail']}</p>
            </div>
HTML;
    }

    $tierCards .= <<<HTML
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-{$color}-200 dark:ring-{$color}-500/30 p-5 mb-6">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-{$color}-800 dark:text-{$color}-300 uppercase tracking-wide">{$tierName}</h3>
                    <p class="text-xs text-gray-500 dark:text-slate-500 mt-0.5">{$tier['description']}</p>
                </div>
                <div class="text-right">
                    <div class="text-xs text-gray-500 dark:text-slate-500">{$vulnCount} vuln(s)</div>
                    <div class="font-mono text-sm text-{$color}-700 dark:text-{$color}-300 font-bold">{$tierPts} pts</div>
                </div>
            </div>
            <div class="space-y-2 mt-4">
                {$vulnRows}
            </div>
        </div>
HTML;
}

$content = <<<HTML
    <!-- Event headline: selected vulnerabilities at their chosen levels -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="rounded-xl bg-amber-50 dark:bg-amber-500/10 ring-1 ring-amber-200 dark:ring-amber-500/30 p-4 text-center">
            <div class="text-xs text-amber-700 dark:text-amber-400 uppercase font-medium">Score to achieve</div>
            <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1">{$eventScore}</div>
            <div class="text-[11px] text-amber-700/70 dark:text-amber-400/70 mt-0.5">selected vulns × levels</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 text-center">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Vulns in CTF</div>
            <div class="text-2xl font-black text-gray-900 dark:text-white mt-1">{$eventCount}</div>
            <div class="text-[11px] text-gray-400 dark:text-slate-600 mt-0.5"><a href="/ctf-vulns" class="underline">manage selection</a></div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 text-center">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Difficulty Tiers</div>
            <div class="text-2xl font-black text-gray-900 dark:text-white mt-1">3</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 text-center">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Point Range</div>
            <div class="text-2xl font-black text-gray-900 dark:text-white mt-1">100–400</div>
        </div>
    </div>

    <!-- Scoring Rationale -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5 mb-8">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase tracking-wide mb-3">📐 Scoring Rationale</h3>
        <div class="text-sm text-gray-600 dark:text-slate-400 space-y-2">
            <p>A vulnerability's <strong class="text-gray-900 dark:text-white">complexity</strong> sets its starting (level 0) score: easy 100, medium 200, hard 300. The score then <strong class="text-gray-900 dark:text-white">rises with the level</strong> (+50 per level), because a harder configuration means more mitigation to bypass.</p>
            <p>The <strong class="text-gray-900 dark:text-white">Score to achieve</strong> above is the sum of the vulnerabilities selected for the CTF, each priced at the level it is set to run. The tier cards below are a reference, priced at level 0.</p>
        </div>
    </div>

    <!-- Tier breakdown -->
    {$tierCards}

    <!-- Quick Reference Table -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase tracking-wide mb-3">📋 Quick Reference</h3>
        <table class="w-full text-sm">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">ID</th><th class="pb-2">Vulnerability</th><th class="pb-2">Tier</th><th class="pb-2 text-right">Points</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
HTML;

// Build quick-reference rows
foreach ($scoringData as $tierName => $tier) {
    $color = $tier['color'];
    foreach ($tier['vulns'] as $v) {
        $pts = Flags::points($v['key'], 0);
        $shortTier = explode(' ', $tierName)[0]; // "Easy", "Medium", etc.
        $content .= '<tr>'
            . '<td class="py-1.5 font-mono text-xs text-gray-700 dark:text-slate-300">' . $v['id'] . '</td>'
            . '<td class="py-1.5 text-gray-700 dark:text-slate-300">' . htmlspecialchars($v['title']) . '</td>'
            . '<td class="py-1.5"><span class="px-1.5 py-0.5 rounded text-xs bg-' . $color . '-100 dark:bg-' . $color . '-500/20 text-' . $color . '-800 dark:text-' . $color . '-300">' . $shortTier . '</span></td>'
            . '<td class="py-1.5 text-right font-mono font-bold text-' . $color . '-700 dark:text-' . $color . '-300">' . $pts . '</td>'
            . '</tr>';
    }
}

$content .= <<<HTML
            </tbody>
            <tfoot><tr class="border-t-2 border-gray-200 dark:border-slate-700">
                <td colspan="3" class="py-2 font-semibold text-gray-900 dark:text-white">Total (all vulnerabilities)</td>
                <td class="py-2 text-right font-mono font-bold text-amber-700 dark:text-amber-300">{$totalPoints}</td>
            </tr></tfoot>
        </table>
    </div>
HTML;

sa_render('Scoring & Points', $content, 'scoring');
