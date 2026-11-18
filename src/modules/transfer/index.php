<?php

declare(strict_types=1);

/**
 * Fund transfer wizard (HTML): Details -> Confirm -> OTP -> Receipt.
 *
 * A single stateful controller. The current stage lives in the session
 * ($_SESSION['transfer_flow']); each POST carries an `action` that advances it.
 * Uses the SAME xfer_* functions as the JSON API, so vuln/secure behaviour and
 * the transfer.l9 state machine are identical across surfaces.
 */

require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/transfer_vuln.php';
require_once __DIR__ . '/transfer_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$enforced = xfer_state_enforced($engine);

$error = null;
$stage = 'details';          // details | confirm | otp | receipt
$receipt = null;

$accounts      = xfer_user_accounts($pdo, $uid);
$beneficiaries = xfer_user_beneficiaries($pdo, $uid);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // JWT tokens are read-only — block transfer execution via forged JWT
    auth()->requireSession();
    if (!CSRF::validateToken(input('csrf_token'))) {
        $error = 'Invalid or missing CSRF token.';
    } else {
        $action = (string) input('action', '');

        if ($action === 'initiate') {
            // Decode the per-level "from account" reference to an account number
            // (transfer.l1). Ownership is NOT checked here — only L3's execute
            // path enforces it — so L0/L1/L2 stay exploitable with a crafted ref.
            $fromAccNum = xfer_resolve_from_ref($pdo, $engine, (string) input('from_account_ref', ''), (string) input('from_account_sig', ''));
            // Resolve the per-level "to account" reference. At L1 the client sends
            // the plain number plus its HMAC signature, verified in O(1) — the same
            // signing scheme as the from field, which is what leaks valid pairs.
            $toAccNum = xfer_resolve_to_ref($engine, (string) input('to_account_number', ''), (string) input('to_account_sig', ''));

            if ($fromAccNum === '' || $toAccNum === '') {
                $error = 'Please select both accounts.';
                $stage = 'details';
            } else {
                xfer_flow_set([
                    'stage'                => 'initiated',
                    'from_account_number'  => $fromAccNum,
                    'to_account_number'    => $toAccNum,
                    'amount'               => (float) input('amount', 0),
                    'mode'                 => (string) input('mode', 'IMPS'),
                    'remarks'              => (string) input('remarks', ''),
                ]);
                $stage = 'confirm';
            }

        } elseif ($action === 'confirm') {
            $flow = xfer_flow_get();
            if ($enforced && ($flow['stage'] ?? '') !== 'initiated') {
                $error = 'Transfer not initiated.';
                $stage = 'details';
            } else {
                // Resolve account numbers to IDs (allows IDOR: no ownership check)
                $fromAccNum = (string) ($flow['from_account_number'] ?? '');
                $toAccNum = (string) ($flow['to_account_number'] ?? '');

                // Get from_account_id by account number (no ownership check = IDOR at vuln level)
                $stmt = $pdo->prepare('SELECT id FROM accounts WHERE account_number = :num LIMIT 1');
                $stmt->execute([':num' => $fromAccNum]);
                $fromRow = $stmt->fetch();
                $fromAccId = $fromRow ? (int) $fromRow['id'] : 0;

                // Get to_account_id by account number (resolve beneficiary)
                $stmt = $pdo->prepare('SELECT id FROM beneficiaries WHERE account_number = :num LIMIT 1');
                $stmt->execute([':num' => $toAccNum]);
                $toRow = $stmt->fetch();
                $toAccId = $toRow ? (int) $toRow['id'] : 0;

                if ($fromAccId === 0 || $toAccId === 0) {
                    $error = 'Invalid account number. Please try again.';
                    $stage = 'details';
                } else {
                    $flow['stage'] = 'confirmed';
                    $flow['from_account_id'] = $fromAccId;
                    $flow['beneficiary_id'] = $toAccId;
                    xfer_flow_set($flow);
                    // Issue the transfer OTP to the registered device. The code is
                    // NOT revealed on screen / in the response (no dev leak here).
                    auth_generate_otp($pdo, $uid, 'transfer');
                    $stage = 'otp';
                }
            }

        } elseif ($action === 'verify') {
            $flow = xfer_flow_get();
            $in   = request_input();
            // transfer.l9 — MISSING-PARAMETER OTP BYPASS. At vuln levels, if the
            // `otp` field is omitted from the POST entirely, the server has
            // "nothing to verify" and waves the 2FA step through — deleting the
            // otp=... name/value pair (e.g. in Burp) completes the transfer with
            // no code. A present-but-blank or wrong otp is still rejected.
            // @vuln-start: absent 'otp' field skips OTP verification (transfer.l9)
            // @fix-start: OTP is mandatory — a missing/blank field is rejected (transfer.l9)
            $otpPresent = array_key_exists('otp', $in);
            $otpOk = $enforced
                ? ($otpPresent && auth_verify_otp($pdo, $engine, $uid, (string) ($in['otp'] ?? ''), 'transfer'))
                : (!$otpPresent || auth_verify_otp($pdo, $engine, $uid, (string) ($in['otp'] ?? ''), 'transfer'));
            // @vuln-end
            // @fix-end
            if ($enforced && ($flow['stage'] ?? '') !== 'confirmed') {
                $error = 'Transfer not confirmed.';
                $stage = 'details';
            } elseif (!$otpOk) {
                $error = 'Invalid or expired OTP.';
                $stage = 'otp';
            } else {
                $flow['stage'] = 'otp_verified';
                xfer_flow_set($flow);
                // Execute.
                $useVuln = $engine->isVulnerable('transfer', 'l1')
                        || $engine->isVulnerable('transfer', 'l6')
                        || $engine->isVulnerable('transfer', 'l7');
                $res = $useVuln
                    ? xfer_execute_vuln($pdo, $engine, $uid, $flow)
                    : xfer_execute_secure($pdo, $engine, $uid, $flow);
                if ($res['ok']) {
                    // CTF (transfer.l1): deliver IDOR flag via response header on HTML path
                    if (!empty($res['ctf_flag'])) {
                        if (flags_delivered()) { header('X-CTF-Flag: ' . $res['ctf_flag']); }
                        $_SESSION['transfer_ctf_flag'] = $res['ctf_flag'];
                        $_SESSION['transfer_ctf_vuln'] = 'transfer.l1';
                    }
                    xfer_flow_clear();
                    // Confirmation for the transfer we just executed this request:
                    // fetch it directly by the id the server just created, not via
                    // the ownership-scoped receipt lookup (that scoping is for the
                    // receipt-viewing API, transfer.l2). Otherwise a transfer.l1
                    // IDOR — whose source/destination are not the caller's own
                    // accounts — would find no receipt and render a blank page.
                    $rq = $pdo->prepare('SELECT * FROM transactions WHERE id = :id');
                    $rq->execute([':id' => (int) $res['txn_id']]);
                    $receipt = $rq->fetch() ?: null;
                    $stage = 'receipt';
                } else {
                    $error = $res['error'];
                    $stage = 'otp';
                }
            }
        }
    }
}

// ---------------------------------------------------------------- render ----

$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();
$flow = xfer_flow_get();

// Progress indicator.
$steps = ['details' => 'Details', 'confirm' => 'Confirm', 'otp' => 'OTP', 'receipt' => 'Receipt'];
$order = array_keys($steps);
$curIdx = array_search($stage, $order, true);
$progress = '<div class="flex items-center gap-2 mb-8">';
foreach ($order as $i => $key) {
    $done = $i <= $curIdx;
    $cls = $done ? 'bg-indigo-500 text-slate-950' : 'bg-slate-800 text-slate-500';
    $progress .= '<div class="flex items-center gap-2">'
        . '<span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold ' . $cls . '">' . ($i + 1) . '</span>'
        . '<span class="text-sm ' . ($done ? 'text-slate-200' : 'text-slate-500') . '">' . $steps[$key] . '</span></div>';
    if ($i < count($order) - 1) {
        $progress .= '<div class="flex-1 h-px bg-slate-800 mx-1"></div>';
    }
}
$progress .= '</div>';

$alert = $error !== null
    ? '<div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">' . htmlspecialchars($error) . '</div>'
    : '';

$body = '';

if ($stage === 'details') {
    $l1Level   = $engine->getVulnLevel('transfer', 'l1');
    $fromField = xfer_from_field_html($accounts, $l1Level);
    $toField   = xfer_to_field_html($beneficiaries, $l1Level);
    $body = <<<HTML
        <form method="post" action="{$url}/transfer">
            {$csrf}
            <input type="hidden" name="action" value="initiate">
            {$fromField}
            {$toField}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Amount (₹)</label>
                    <input name="amount" type="number" step="0.01" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100" placeholder="1000.00">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Mode</label>
                    <select name="mode" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
                        <option>IMPS</option><option>NEFT</option><option>NEFT</option><option>RTGS</option>
                    </select>
                </div>
            </div>
            <label class="block text-xs text-slate-400 mb-1 mt-3">Remarks</label>
            <input name="remarks" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100" placeholder="optional">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Continue</button>
        </form>
HTML;

} elseif ($stage === 'confirm') {
    // Get From account details from account number stored in flow
    $fromName = htmlspecialchars((string) ($flow['from_account_number'] ?? ''));

    // Get To account details from account number stored in flow
    $toName = htmlspecialchars((string) ($flow['to_account_number'] ?? ''));

    $amt = htmlspecialchars(number_format((float) ($flow['amount'] ?? 0), 2));
    $mode = htmlspecialchars((string) ($flow['mode'] ?? ''));
    $rem  = htmlspecialchars((string) ($flow['remarks'] ?? ''));
    $body = <<<HTML
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5 mb-5 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">From account</span><span class="text-slate-200 font-mono">{$fromName}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">To account</span><span class="text-slate-200 font-mono">{$toName}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Amount</span><span class="text-white font-bold">₹{$amt}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Mode</span><span class="text-slate-200">{$mode}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Remarks</span><span class="text-slate-200">{$rem}</span></div>
        </div>
        <form method="post" action="{$url}/transfer">
            {$csrf}
            <input type="hidden" name="action" value="confirm">
            <button class="w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Confirm &amp; send OTP</button>
        </form>
HTML;

} elseif ($stage === 'otp') {
    $body = <<<HTML
        <p class="text-sm text-slate-400 mb-4">Enter the 4-digit OTP sent to your registered mobile to authorise this transfer.</p>
        <form method="post" action="{$url}/transfer">
            {$csrf}
            <input type="hidden" name="action" value="verify">
            <input name="otp" inputmode="numeric" autofocus maxlength="4" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 tracking-[0.4em] font-mono text-center" placeholder="0000">
            <button class="mt-5 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Authorise transfer</button>
        </form>
        <div class="mt-4 flex justify-center">
            <button type="button" id="transfer-otp-btn" onclick="showTransferOTP()" class="text-xs font-semibold rounded-lg bg-emerald-500/15 ring-1 ring-emerald-500/30 hover:bg-emerald-500/25 px-3 py-1.5 text-emerald-300 transition">📱 View OTP Code</button>
        </div>
        <script>
        function showTransferOTP() {
            // Fetch the pending transfer OTP from server
            fetch('/get-transfer-otp', { method: 'GET' })
                .then(r => r.json())
                .then(data => {
                    if (data.otp) {
                        // Show in a modal or alert
                        const otpCode = data.otp;
                        const msg = '📱 Your Transfer OTP Code:\\n\\n' + otpCode + '\\n\\nEnter this 4-digit code above and click "Authorise transfer"';
                        alert(msg);
                    } else {
                        alert('No pending OTP found. Please try again.');
                    }
                })
                .catch(err => {
                    alert('Could not retrieve OTP. Error: ' + err.message);
                });
        }
        </script>
HTML;

} elseif ($stage === 'receipt' && $receipt !== null) {
    $ref  = htmlspecialchars((string) $receipt['txn_ref']);
    $amt  = htmlspecialchars(number_format((float) $receipt['amount'], 2));
    $mode = htmlspecialchars((string) $receipt['mode']);
    $stat = htmlspecialchars((string) $receipt['status']);
    $date = htmlspecialchars((string) $receipt['created_at']);
    $rem  = htmlspecialchars((string) ($receipt['remarks'] ?? ''));
    $rid  = (int) $receipt['id'];

    // Check if there's a CTF flag from IDOR transfer
    $ctfFlag = $_SESSION['transfer_ctf_flag'] ?? null;
    $ctfVuln = $_SESSION['transfer_ctf_vuln'] ?? null;
    if ($ctfFlag !== null) {
        if (flags_delivered()) { header('X-CTF-Flag: ' . $ctfFlag); }
        unset($_SESSION['transfer_ctf_flag']);
        unset($_SESSION['transfer_ctf_vuln']);
    }

    $body = <<<HTML
        <div class="rounded-xl bg-emerald-500/10 ring-1 ring-emerald-500/30 p-5 mb-5 text-center">
            <div class="text-emerald-300 text-lg font-bold">✓ Transfer successful</div>
            <div class="text-3xl font-black text-white mt-2">₹{$amt}</div>
        </div>
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Reference</span><span class="text-slate-200 font-mono">{$ref}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Mode</span><span class="text-slate-200">{$mode}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Status</span><span class="text-emerald-300">{$stat}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Date</span><span class="text-slate-200">{$date}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Remarks</span><span class="text-slate-200">{$rem}</span></div>
        </div>
        <div class="flex gap-3 mt-5">
            <a href="{$url}/api/v1/transfer/{$rid}/receipt" class="flex-1 text-center rounded-lg bg-slate-800 hover:bg-slate-700 px-4 py-2 text-slate-200 text-sm">Download receipt</a>
            <a href="{$url}/transfer" class="flex-1 text-center rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold px-4 py-2 text-sm">New transfer</a>
        </div>
HTML;

    // If IDOR flag exists, display popup on page load (gated by flag delivery
    // so Pentest mode doesn't embed the flag string in the page).
    if ($ctfFlag !== null && flags_delivered()) {
        $ctfFlagEscaped = htmlspecialchars($ctfFlag);
        $ctfVulnEscaped = $ctfVuln ? '\'' . htmlspecialchars($ctfVuln) . '\'' : 'null';
        $body .= <<<HTML
        <script>
        console.log('CTF Flag detected: $ctfFlagEscaped');
        (function() {
            const flag = '$ctfFlagEscaped';
            const vulnId = $ctfVulnEscaped;

            function showFlag() {
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
}

$content = '<h2 class="text-2xl font-bold text-white mb-6">Fund Transfer</h2>'
    . ctf_hint_banner(['transfer.l1', 'auth.l12'])
    . $progress . $alert . $body
    . lab_hint_banner(['transfer.l1', 'transfer.l6', 'transfer.l7', 'transfer.l9', 'transfer.l2', 'transfer.g5', 'statements.g2']);

render_layout('Transfer', $content, ['active' => 'transfer']);
