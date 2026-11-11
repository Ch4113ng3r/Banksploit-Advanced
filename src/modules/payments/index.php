<?php

declare(strict_types=1);

/**
 * Bill payments page (HTML): pay-a-bill form + payment history.
 *
 * beneficiary.g3 Stored XSS: the biller name / remarks are stored raw and the
 * history below renders them via payments_render_remark() (-> vuln_out), so a
 * <script> biller name fires here at vuln levels and is escaped at level 3.
 */

require_once __DIR__ . '/payments_vuln.php';
require_once __DIR__ . '/payments_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];

$notice = null;
$error  = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    auth()->requireSession(); // JWT is read-only
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        $res = payments_pay($pdo, $engine, $uid, request_input());
        if ($res['ok']) {
            $notice = 'Payment successful · ' . $res['txn_ref'];
        } else {
            $error = $res['error'];
        }
    }
}

$accounts = $pdo->prepare('SELECT id, account_number, account_type, balance FROM accounts WHERE user_id = :u ORDER BY id');
$accounts->execute([':u' => $uid]);
$accounts = $accounts->fetchAll();

$history = payments_history($pdo, $uid);

$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();

$alert = '';
if ($error !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">' . htmlspecialchars($error) . '</div>';
} elseif ($notice !== null) {
    $alert = '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">' . htmlspecialchars($notice) . '</div>';
}

$accOpts = '';
foreach ($accounts as $a) {
    $accOpts .= '<option value="' . (int) $a['id'] . '">'
        . htmlspecialchars(ucfirst((string) $a['account_type']) . ' · ' . $a['account_number']
          . ' (₹' . number_format((float) $a['balance'], 2) . ')') . '</option>';
}

$billerOpts = '';
foreach (['electricity', 'water', 'gas', 'mobile', 'DTH'] as $b) {
    $billerOpts .= '<option value="' . $b . '">' . ucfirst($b) . '</option>';
}

// History rows. Remark rendered via payments_render_remark() -> stored XSS sink.
$histRows = '';
foreach ($history as $h) {
    // biller_name is the stored-XSS sink: rendered RAW at vuln levels via
    // payments_render_remark() (-> vuln_out), escaped at level 3.
    $biller = payments_render_remark((string) ($h['biller_name'] ?? ''));
    $type   = htmlspecialchars(ucfirst((string) ($h['biller_type'] ?? '')));
    $note   = payments_render_remark((string) ($h['remarks'] ?? ''));
    $amt    = htmlspecialchars(number_format((float) $h['amount'], 2));
    $date   = htmlspecialchars(substr((string) $h['created_at'], 0, 10));
    $ref    = htmlspecialchars((string) $h['txn_ref']);
    $noteHtml = $note !== '' ? "<div class=\"text-xs text-slate-500\">{$note}</div>" : '';
    $histRows .= <<<HTML
        <tr class="border-t border-slate-800">
            <td class="py-2 text-slate-400 text-sm">{$date}</td>
            <td class="py-2 text-slate-500 text-xs">{$type}</td>
            <td class="py-2 text-slate-200 text-sm">{$biller}{$noteHtml}</td>
            <td class="py-2 text-slate-500 text-xs font-mono">{$ref}</td>
            <td class="py-2 text-right text-red-400 font-semibold">-&#8377;{$amt}</td>
        </tr>
HTML;
}
if ($histRows === '') {
    $histRows = '<tr><td colspan="5" class="py-3 text-slate-500 text-sm">No payments yet.</td></tr>';
}

$content = <<<HTML
    <h2 class="text-2xl font-bold text-white mb-6">Bill Payments</h2>
    {$alert}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-4">Pay a bill</h3>
            <form method="post" action="{$url}/payments">
                {$csrf}
                <label class="block text-xs text-slate-400 mb-1">From account</label>
                <select name="from_account_id" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3">{$accOpts}</select>
                <label class="block text-xs text-slate-400 mb-1">Biller type</label>
                <select name="biller_type" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3">{$billerOpts}</select>
                <label class="block text-xs text-slate-400 mb-1">Biller name / ID</label>
                <input name="biller_name" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3" placeholder="BESCOM - 12345">
                <label class="block text-xs text-slate-400 mb-1">Amount (₹)</label>
                <input name="amount" type="number" step="0.01" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3" placeholder="1000.00">
                <label class="block text-xs text-slate-400 mb-1">Remarks</label>
                <input name="remarks" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100" placeholder="optional">
                <button class="mt-4 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Pay bill</button>
            </form>
        </div>
        <div class="lg:col-span-2">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-4">Payment history</h3>
            <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4">
                <table class="w-full">
                    <thead><tr class="text-slate-500 text-xs uppercase text-left">
                        <th class="pb-2">Date</th><th class="pb-2">Type</th><th class="pb-2">Biller</th><th class="pb-2">Ref</th><th class="pb-2 text-right">Amount</th>
                    </tr></thead>
                    <tbody>{$histRows}</tbody>
                </table>
            </div>
        </div>
    </div>
HTML;

$content .= lab_hint_banner(['beneficiary.g3']);

render_layout('Payments', $content, ['active' => 'payments']);
