<?php

declare(strict_types=1);

/**
 * Statements page (HTML).
 *
 * Date-range filter + account selector + transaction search. Shares
 * statements/_shared.php with the accounts API, so the same level dials apply.
 *
 * Vulns visible here:
 *   statements.g2 SQLi in the date filter (and search) at vuln levels.
 *   transfer.l1   IDOR: the account selector / ?account_id lets you view any
 *                 account's statement at vuln levels.
 *   statements.g4 Reflected XSS: the search term is echoed via vuln_out().
 *   statements.g8 Path traversal: the CSV export link (?file=) at vuln levels.
 *   statements.g9 Command injection: the "Download PDF" control's ?filename=
 *                 is shelled out by the export-pdf endpoint at vuln levels.
 */

require_once __DIR__ . '/_shared.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$in     = request_input();

// Ensure ctf_flags are seeded for g2 SQLi exploitation
ensure_ctf_flags_seeded($pdo);

// Account selector. Only the user's own accounts appear in the dropdown.
// IDOR (statements.l1): the ?account_id= parameter accepts ANY account id at
// vuln levels — the server resolves it without ownership check. Participants
// discover other account IDs from transfers and tamper the URL.
$accounts = stmt_list_accounts($pdo, $engine, $uid);

// Support both account_id (numeric) and account (account_number) for backward compatibility
$requestedAccountId = (int) ($in['account_id'] ?? 0);
$requestedAccount = (string) ($in['account'] ?? '');
$from = (string) ($in['from_date'] ?? '');
$to   = (string) ($in['to_date'] ?? '');
$q    = (string) ($in['q'] ?? '');

// IDOR vulnerability (statements.l1): at vuln levels, access account by ID without ownership check.
// Users discover other account IDs from transfers and tamper the URL.
$stmtIdorVuln = $engine->isVulnerable('statements', 'l1');

if ($requestedAccountId > 0) {
    // IDOR vector: access by account_id
    if ($stmtIdorVuln) {
        // @vuln-start: IDOR — account resolved by id without ownership check (statements.l1)
        $stmt = $pdo->prepare("SELECT a.id, a.user_id, a.account_number, a.account_type, a.balance, a.daily_limit, a.is_active, a.created_at, u.role, u.email, u.pan FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = :id");
        $stmt->execute([':id' => $requestedAccountId]);
        // @vuln-end
    } else {
        // @fix-start: account resolved by id WITH ownership check (statements.l1)
        $stmt = $pdo->prepare("SELECT a.id, a.user_id, a.account_number, a.account_type, a.balance, a.daily_limit, a.is_active, a.created_at, u.role, u.email, u.pan FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = :id AND a.user_id = :uid");
        $stmt->execute([':id' => $requestedAccountId, ':uid' => $uid]);
        // @fix-end
    }
    $account = $stmt->fetch();
} else if ($requestedAccount !== '') {
    // Alternative: access by account_number
    $account = stmt_get_account_by_number($pdo, $engine, $uid, $requestedAccount);
} else {
    // Default to user's first account
    $firstAcct = $accounts[0] ?? null;
    if ($firstAcct !== null) {
        if ($stmtIdorVuln) {
            $stmt = $pdo->prepare("SELECT a.id, a.user_id, a.account_number, a.account_type, a.balance, a.daily_limit, a.is_active, a.created_at, u.role, u.email, u.pan FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = :id");
            $stmt->execute([':id' => (int) $firstAcct['id']]);
        } else {
            $stmt = $pdo->prepare("SELECT a.id, a.user_id, a.account_number, a.account_type, a.balance, a.daily_limit, a.is_active, a.created_at, u.role, u.email, u.pan FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = :id AND a.user_id = :uid");
            $stmt->execute([':id' => (int) $firstAcct['id'], ':uid' => $uid]);
        }
        $account = $stmt->fetch();
    } else {
        $account = null;
    }
}

// CTF (statements.l1): if the resolved account belongs to a DIFFERENT user,
// that's confirmed IDOR exploitation — deliver the flag in a response header + modal.
$ctfFlag = null;
$ctfVuln = null;
if ($account !== null && isset($account['user_id']) && (int) $account['user_id'] !== $uid) {
    // Deliver the flag only when statements.l1 is actually selected for the CTF
    // (isCtfActive = ctf_eligible AND ctf_active). The IDOR still works as a lab
    // exercise at vuln levels, but viewing another account hands out a flag only
    // when this specific vuln is the active CTF challenge.
    if ($engine->isCtfActive('statements', 'l1')) {
        $ctfFlag = Flags::generate('statements.l1', $uid);
        $ctfVuln = 'statements.l1';
        if (flags_delivered()) { header('X-CTF-Flag: ' . $ctfFlag); }
    }
}

$accountId = $account ? (int) $account['id'] : 0;
$txError = null;
$rows = [];
if ($account !== null) {
    $res = stmt_transactions($pdo, $engine, $accountId, $from, $to, $q);
    $rows = $res['rows'];
    $txError = $res['error'];
}

// ---------------------------------------------------------------- render ----

$url = htmlspecialchars(APP_URL);

// Account selector options (uses account_id as the value for IDOR parameter).
// Only shows the current user's accounts, but the IDOR vulnerability allows
// tampering with the URL to access other account IDs.
$options = '';
foreach ($accounts as $a) {
    $sel = ($requestedAccountId === (int) $a['id']) ? ' selected' : '';
    $label = htmlspecialchars(ucfirst((string) $a['account_type']) . ' · ' . $a['account_number']);
    $options .= '<option value="' . (int) $a['id'] . '"' . $sel . '>' . $label . '</option>';
}

// statements.g4 reflected XSS: the search term is reflected into TWO contexts,
// each with its own level-governed encoding (see g4_text()/g4_attr()):
//   - TEXT sink  ($qEcho): the "Results for: …" line — L0 raw, L1 single-pass
//     <script> strip, L2/L3 encoded.
//   - ATTR sink  ($qAttr): the search box value="…" — encoded except at L2,
//     where it is raw so a quote breaks out (the L2 vector).
// @vuln-start: [L0-2] reflected XSS — term reflected into text + attribute sinks (statements.g4)
// @fix-start: [L3] both sinks HTML-encoded — the reflected value can no longer break out (statements.g4)
$qEcho = $q !== '' ? '<p class="text-sm text-slate-400 mb-3">Results for: ' . g4_text($q) . '</p>' : '';
$qAttr = g4_attr($q);
// @vuln-end
// @fix-end
// Note: a strict CSP (script-src 'self') is the recommended extra hardening, but
// this training app renders legitimate inline scripts, so enforcing it here would
// break the page. L3's real fix is the context-aware encoding above.

$errBox = $txError !== null
    ? '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm font-mono">' . htmlspecialchars($txError) . '</div>'
    : '';

// Transaction rows.
$txRows = '';
foreach ($rows as $t) {
    $isCredit = ((int) ($t['to_account_id'] ?? 0) === $accountId);
    $sign  = $isCredit ? '+' : '-';
    $color = $isCredit ? 'text-emerald-400' : 'text-red-400';
    $amt   = htmlspecialchars(number_format((float) $t['amount'], 2));
    $date  = htmlspecialchars(substr((string) $t['created_at'], 0, 10));
    $rem   = htmlspecialchars((string) ($t['remarks'] ?? ''));
    $mode  = htmlspecialchars((string) $t['mode']);
    $stat  = htmlspecialchars((string) $t['status']);
    $txRows .= <<<HTML
        <tr class="border-t border-slate-800">
            <td class="py-2 text-slate-400 text-sm">{$date}</td>
            <td class="py-2 text-slate-200 text-sm">{$rem}</td>
            <td class="py-2 text-slate-500 text-xs">{$mode}</td>
            <td class="py-2 text-slate-500 text-xs">{$stat}</td>
            <td class="py-2 text-right font-semibold {$color}">{$sign}&#8377;{$amt}</td>
        </tr>
HTML;
}
if ($txRows === '') {
    $txRows = '<tr><td colspan="5" class="py-4 text-slate-500 text-sm">No transactions in range.</td></tr>';
}

$exportUrl = $url . '/api/v1/accounts/' . $accountId . '/export';
$pdfAction = $url . '/api/v1/accounts/' . $accountId . '/export-pdf';

$content = <<<HTML
    <div class="flex items-end justify-between mb-6 gap-3 flex-wrap">
        <h2 class="text-2xl font-bold text-white">Statements</h2>
        <div class="flex items-end gap-2">
            <a href="{$exportUrl}" class="text-sm rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-slate-200">Export CSV</a>
            <form method="get" action="{$pdfAction}" class="flex items-end gap-1">
                <div>
                    <label class="block text-[10px] text-slate-500 mb-0.5">PDF file name</label>
                    <input name="filename" value="statement.pdf" class="rounded-lg bg-slate-950 ring-1 ring-slate-700 px-2 py-1.5 text-slate-100 text-sm font-mono w-44">
                </div>
                <button class="text-sm rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-slate-200">Download PDF</button>
            </form>
        </div>
    </div>

    <form method="get" action="{$url}/statements" class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4 mb-6 grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
        <div class="sm:col-span-2">
            <label class="block text-xs text-slate-400 mb-1">Account</label>
            <select name="account_id" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">{$options}</select>
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">From</label>
            <input type="date" name="from_date" value="{$from}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">To</label>
            <input type="date" name="to_date" value="{$to}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
        </div>
        <div>
            <label class="block text-xs text-slate-400 mb-1">Search</label>
            <input name="q" value="{$qAttr}" placeholder="remarks…" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
        </div>
        <div class="sm:col-span-5">
            <button class="rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold px-4 py-2 transition">Apply filters</button>
        </div>
    </form>

    {$errBox}
    {$qEcho}

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
        <table class="w-full">
            <thead><tr class="text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">Date</th><th class="pb-2">Description</th><th class="pb-2">Mode</th><th class="pb-2">Status</th><th class="pb-2 text-right">Amount</th>
            </tr></thead>
            <tbody>{$txRows}</tbody>
        </table>
    </div>
HTML;

$content = ctf_hint_banner(['statements.l1', 'statements.g2', 'statements.g9', 'auth.l12']) . $content;
$content .= lab_hint_banner(['statements.l1', 'statements.g2', 'statements.g8', 'statements.g9']);

// If flag was captured (IDOR detected), add script to display modal on page load.
// Gated by flag delivery so Pentest mode doesn't embed the flag string in the page.
if ($ctfFlag !== null && flags_delivered()) {
    $ctfFlagEscaped = htmlspecialchars($ctfFlag);
    $ctfVulnEscaped = $ctfVuln ? '\'' . htmlspecialchars($ctfVuln) . '\'' : 'null';
    $content .= <<<HTML
        <script>
        console.log('CTF Flag detected: $ctfFlagEscaped');
        (function() {
            const flag = '$ctfFlagEscaped';
            const vulnId = $ctfVulnEscaped;

            function showFlag() {
                console.log('Attempting to show flag:', flag, vulnId);
                if (typeof displayFlag === 'function') {
                    displayFlag(flag, vulnId);
                } else {
                    console.warn('displayFlag function not available');
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', showFlag);
            } else {
                setTimeout(showFlag, 100);
            }
        })();
        </script>
HTML;
}

render_layout('Statements', $content, ['active' => 'statements']);
