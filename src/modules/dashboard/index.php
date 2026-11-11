<?php

declare(strict_types=1);

/**
 * BankSploit: post-login dashboard.
 *
 * Account overview (savings/current/FD), recent transactions, and quick
 * actions. The signed-in user's name is reflected via vuln_out() so a stored
 * XSS payload saved through the Profile module (profile.l5) fires here too.
 * All queries are parameterized — the SQLi sinks live in auth/statements.
 */

auth()->requireAuth();

$user = auth()->getCurrentUser();
$pdo  = db();

// This user's accounts.
$stmt = $pdo->prepare(
    'SELECT id, account_number, account_type, balance
       FROM accounts WHERE user_id = :uid ORDER BY id'
);
$stmt->execute([':uid' => (int) $user['id']]);
$accounts = $stmt->fetchAll();

// Recent transactions touching any of this user's accounts (last 10).
$transactions = [];
$accountIds = array_column($accounts, 'id');
if ($accountIds !== []) {
    $in = implode(',', array_fill(0, count($accountIds), '?'));
    $txStmt = $pdo->prepare(
        "SELECT amount, mode, status, remarks, created_at, from_account_id, to_account_id
           FROM transactions
          WHERE from_account_id IN ($in) OR to_account_id IN ($in)
          ORDER BY created_at DESC LIMIT 10"
    );
    $txStmt->execute(array_merge($accountIds, $accountIds));
    $transactions = $txStmt->fetchAll();
}
$idSet = array_flip($accountIds);

// Profile is read-only — no XSS injection possible. Always escape.
$name = htmlspecialchars((string) $user['name']);
$cid  = htmlspecialchars((string) $user['customer_id']);
$role = htmlspecialchars((string) $user['role']);
$url  = htmlspecialchars(APP_URL);

$total = 0.0;
foreach ($accounts as $a) {
    $total += (float) $a['balance'];
}
$totalFmt = htmlspecialchars(number_format($total, 2));

// Account cards.
$acctCards = '';
foreach ($accounts as $a) {
    $no   = htmlspecialchars((string) $a['account_number']);
    $type = htmlspecialchars(ucfirst((string) $a['account_type']));
    $bal  = htmlspecialchars(number_format((float) $a['balance'], 2));
    $acctCards .= <<<HTML
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
            <div class="text-xs text-slate-500 uppercase tracking-wide">{$type}</div>
            <div class="font-mono text-sm text-slate-300 mt-1">{$no}</div>
            <div class="text-2xl font-bold text-white mt-2">&#8377;{$bal}</div>
        </div>
HTML;
}
if ($acctCards === '') {
    $acctCards = '<div class="text-slate-500 text-sm">No accounts on file.</div>';
}

// Recent transactions table.
$txRows = '';
foreach ($transactions as $t) {
    $isCredit = isset($t['to_account_id'], $idSet[$t['to_account_id']]);
    $sign  = $isCredit ? '+' : '-';
    $color = $isCredit ? 'text-emerald-400' : 'text-red-400';
    $amt   = htmlspecialchars(number_format((float) $t['amount'], 2));
    $mode  = htmlspecialchars((string) $t['mode']);
    $stat  = htmlspecialchars((string) $t['status']);
    $rem   = htmlspecialchars((string) ($t['remarks'] ?? ''));
    $date  = htmlspecialchars(substr((string) $t['created_at'], 0, 10));
    $txRows .= <<<HTML
        <tr class="border-t border-slate-800">
            <td class="py-2 text-slate-400 text-sm">{$date}</td>
            <td class="py-2 text-slate-200 text-sm">{$rem}<div class="text-xs text-slate-500">{$mode} &middot; {$stat}</div></td>
            <td class="py-2 text-right font-semibold {$color}">{$sign}&#8377;{$amt}</td>
        </tr>
HTML;
}
if ($txRows === '') {
    $txRows = '<tr><td colspan="3" class="py-3 text-slate-500 text-sm">No transactions yet.</td></tr>';
}

$content = <<<HTML
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-white">Welcome back, {$name}</h2>
        <p class="text-slate-400 text-sm mt-1">{$cid} &middot; {$role}</p>
    </div>

    <div class="rounded-2xl bg-gradient-to-br from-indigo-600/30 to-sky-500/20 ring-1 ring-indigo-500/30 p-6 mb-6">
        <div class="text-sm text-indigo-200">Total balance</div>
        <div class="text-4xl font-black text-white mt-1">&#8377;{$totalFmt}</div>
    </div>

    <!-- Quick actions -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <a href="{$url}/transfer" class="rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 ring-1 ring-indigo-500/30 p-4 text-center transition">
            <div class="text-indigo-200 font-semibold">Transfer</div>
            <div class="text-xs text-slate-400 mt-1">Send money</div>
        </a>
        <a href="{$url}/payments" class="rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 ring-1 ring-emerald-500/30 p-4 text-center transition">
            <div class="text-emerald-200 font-semibold">Pay Bills</div>
            <div class="text-xs text-slate-400 mt-1">Utilities &amp; more</div>
        </a>
        <a href="{$url}/statements" class="rounded-xl bg-sky-500/15 hover:bg-sky-500/25 ring-1 ring-sky-500/30 p-4 text-center transition">
            <div class="text-sky-200 font-semibold">View Statement</div>
            <div class="text-xs text-slate-400 mt-1">Transactions</div>
        </a>
    </div>

    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Accounts</h3>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        {$acctCards}
    </div>

    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">Recent activity</h3>
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
        <table class="w-full"><tbody>{$txRows}</tbody></table>
    </div>
HTML;

// JWT hint: the access_token cookie is issued after login — this is where
// participants notice it and can explore the JWT alg:none vulnerability.
$content = ctf_hint_banner(['auth.l12']) . $content;

// CTF (auth.l25 L0): if this session reached the dashboard via the forgot-password
// auth-key overload, deliver the flag here — the page that proves you are the
// victim. One-shot: cleared once shown.
if (!empty($_SESSION['l25_takeover_flag'])) {
    $f = (string) $_SESSION['l25_takeover_flag'];
    unset($_SESSION['l25_takeover_flag']);   // one-shot: always consume, even if flags are off
    if (flags_delivered()) {
        header('X-CTF-Flag: ' . $f);
        $fj = json_encode($f);
        $content .= <<<JS
        <script>
        (function(){
            function s(){ if (typeof displayFlag === 'function') displayFlag({$fj}, 'auth.l25'); }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', s);
            else setTimeout(s, 150);
        })();
        </script>
JS;
    }
}

render_layout('Dashboard', $content, ['active' => 'dashboard']);
