<?php

declare(strict_types=1);

/**
 * Fund-transfer JSON API.
 *
 *   POST /api/v1/transfer/initiate      stage 1 — capture transfer details
 *   POST /api/v1/transfer/confirm       stage 2 — confirm the summary
 *   POST /api/v1/transfer/verify-otp    stage 3 — verify the transfer OTP
 *   POST /api/v1/transfer/execute       stage 4 — move the money
 *   GET  /api/v1/transfer/{id}/receipt  receipt (transfer.l2 IDOR)
 *   GET  /api/v1/transfer/history       history (statements.g2 SQLi filter)
 *
 * transfer.l9 (step skipping): at vuln levels /execute works WITHOUT going
 * through initiate/confirm/verify-otp because the state machine isn't enforced
 * (xfer_state_enforced() is false). At the secure level each stage records
 * progress in $_SESSION['transfer_flow'] and execute() refuses to run unless
 * the prior stages completed.
 */

require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/transfer_vuln.php';
require_once __DIR__ . '/transfer_secure.php';

auth()->requireAuth();
// JWT tokens are read-only — reject write operations via forged JWT.
// GET (history/receipt) is allowed via JWT; POST (initiate/confirm/execute) is not.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    auth()->requireSession();
}

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$in     = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$enforced = xfer_state_enforced($engine);

// transfer.g5 CSRF: enforced (real check) only once the CSRF vuln is fixed;
// CSRF::validateToken() returns true unconditionally at vuln levels.
function xfer_csrf_ok(array $in): bool
{
    return CSRF::validateToken($in['csrf_token'] ?? null);
}

// ---- history (GET) --------------------------------------------------------
if ($path === '/api/v1/transfer/history') {
    $res = xfer_history($pdo, $engine, $uid, (string) ($in['q'] ?? ''));
    $out = ['ok' => $res['error'] === null, 'transactions' => $res['rows']];
    if ($res['error'] !== null) {
        $out['error'] = $res['error'];
    }
    json_response($out);
}

// ---- receipt (GET /{id}/receipt) ------------------------------------------
if (str_ends_with($path, '/receipt')) {
    $txnId = (int) (route_param('id') ?? 0);
    $r = xfer_receipt($pdo, $engine, $uid, $txnId);
    if ($r === null) {
        json_response(['ok' => false, 'error' => 'Receipt not found or access denied.'], 404);
    }

    // Return formatted HTML receipt
    $ref  = htmlspecialchars((string) $r['txn_ref']);
    $amt  = htmlspecialchars(number_format((float) $r['amount'], 2));
    $mode = htmlspecialchars((string) $r['mode']);
    $stat = htmlspecialchars((string) $r['status']);
    $date = htmlspecialchars((string) $r['created_at']);
    $rem  = htmlspecialchars((string) ($r['remarks'] ?? ''));
    $fromId = (int) $r['from_account_id'];
    $toId = (int) $r['to_account_id'];

    // Fetch account details to display account numbers instead of IDs
    $fromStmt = $pdo->prepare('SELECT account_number, account_type FROM accounts WHERE id = :id LIMIT 1');
    $fromStmt->execute([':id' => $fromId]);
    $fromAcc = $fromStmt->fetch();

    $toStmt = $pdo->prepare('SELECT account_number, account_type FROM accounts WHERE id = :id LIMIT 1');
    $toStmt->execute([':id' => $toId]);
    $toAcc = $toStmt->fetch();

    $fromDisplay = $fromAcc ? htmlspecialchars(ucfirst((string) $fromAcc['account_type']) . ' · ' . $fromAcc['account_number']) : 'Account #' . $fromId;
    $toDisplay = $toAcc ? htmlspecialchars(ucfirst((string) $toAcc['account_type']) . ' · ' . $toAcc['account_number']) : 'Account #' . $toId;

    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Transfer Receipt</title>
        <style>
            body { font-family: system-ui; background: #0b1020; color: #e6e6e6; margin: 0; padding: 2rem; }
            .card { background: #131a30; border: 1px solid #233056; border-radius: 16px; padding: 2rem; max-width: 600px; margin: 0 auto; }
            h1 { margin: 0 0 2rem; text-align: center; font-size: 1.5rem; }
            .success { text-align: center; color: #4ade80; font-size: 3rem; margin-bottom: 1rem; }
            .details { background: #0b1020; border: 1px solid #233056; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0; }
            .detail-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #233056; }
            .detail-row:last-child { border-bottom: none; }
            .label { color: #9aa5c4; font-size: 0.9rem; }
            .value { color: #e6e6e6; font-weight: 600; }
            .amount { font-size: 1.5rem; color: #4ade80; }
            .button { display: inline-block; padding: 0.75rem 1.5rem; background: #4f46e5; color: white; text-decoration: none; border-radius: 8px; margin-top: 1.5rem; }
            .button:hover { background: #4338ca; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="success">✓</div>
            <h1>Transfer Successful</h1>

            <div class="details">
                <div class="detail-row">
                    <span class="label">From Account</span>
                    <span class="value">{$fromDisplay}</span>
                </div>
                <div class="detail-row">
                    <span class="label">To Account</span>
                    <span class="value">{$toDisplay}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Amount</span>
                    <span class="value amount">₹{$amt}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Mode</span>
                    <span class="value">{$mode}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Status</span>
                    <span class="value">{$stat}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Reference</span>
                    <span class="value">{$ref}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Date</span>
                    <span class="value">{$date}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Remarks</span>
                    <span class="value">{$rem}</span>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="/transfer" class="button">New Transfer</a>
            </div>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

// Everything below is POST.
if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
if (!xfer_csrf_ok($in)) {
    json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
}

switch ($path) {

    // ---- stage 1: initiate ----
    case '/api/v1/transfer/initiate': {
        $flow = [
            'stage'           => 'initiated',
            'from_account_id' => (int) ($in['from_account_id'] ?? 0),
            'beneficiary_id'  => (int) ($in['beneficiary_id'] ?? 0),
            'amount'          => (float) ($in['amount'] ?? 0),
            'mode'            => (string) ($in['mode'] ?? 'IMPS'),
            'remarks'         => (string) ($in['remarks'] ?? ''),
        ];
        xfer_flow_set($flow);
        json_response(['ok' => true, 'stage' => 'initiated', 'summary' => $flow]);
        break;
    }

    // ---- stage 2: confirm ----
    case '/api/v1/transfer/confirm': {
        $flow = xfer_flow_get();
        if ($enforced && ($flow['stage'] ?? '') !== 'initiated') {
            json_response(['ok' => false, 'error' => 'Transfer not initiated.'], 409);
        }
        $flow['stage'] = 'confirmed';
        xfer_flow_set($flow);
        json_response(['ok' => true, 'stage' => 'confirmed', 'summary' => $flow]);
        break;
    }

    // ---- stage 3: verify-otp ----
    case '/api/v1/transfer/verify-otp': {
        $flow = xfer_flow_get();
        if ($enforced && ($flow['stage'] ?? '') !== 'confirmed') {
            json_response(['ok' => false, 'error' => 'Transfer not confirmed.'], 409);
        }

        // transfer.l9 — MISSING-PARAMETER OTP BYPASS. If the `otp` field is
        // omitted from the request entirely, vuln levels treat it as "nothing to
        // check" and skip 2FA; deleting the otp=... pair waves the step through.
        // @vuln-start: absent 'otp' field skips OTP verification (transfer.l9)
        // @fix-start: OTP is mandatory — a missing field is rejected, never skipped (transfer.l9)
        if (!array_key_exists('otp', $in)) {
            if ($enforced) {
                json_response(['ok' => false, 'error' => 'OTP is required.'], 401);
            }
            $flow['stage'] = 'otp_verified';
            xfer_flow_set($flow);
            json_response(['ok' => true, 'stage' => 'otp_verified', 'note' => 'no otp supplied — verification skipped']);
        }
        // @vuln-end
        // @fix-end

        // Field present but blank -> (re)issue the OTP to the registered device.
        // The code is NOT returned in the response (no on-screen / dev leak).
        if ((string) ($in['otp'] ?? '') === '') {
            auth_generate_otp($pdo, $uid, 'transfer');
            json_response(['ok' => true, 'stage' => 'otp_sent']);
        }
        if (!auth_verify_otp($pdo, $engine, $uid, (string) $in['otp'], 'transfer')) {
            json_response(['ok' => false, 'error' => 'Invalid or expired OTP.'], 401);
        }
        $flow['stage'] = 'otp_verified';
        xfer_flow_set($flow);
        json_response(['ok' => true, 'stage' => 'otp_verified']);
        break;
    }

    // ---- stage 4: execute ----
    case '/api/v1/transfer/execute': {
        $flow = xfer_flow_get();

        // transfer.l9: at vuln levels we accept params straight off the request
        // and DON'T require the prior stages — /execute can be called directly.
        // @fix-start: wizard state machine enforces initiate->confirm->otp order (transfer.l9)
        if ($enforced) {
            if (($flow['stage'] ?? '') !== 'otp_verified') {
                json_response(['ok' => false, 'error' => 'Complete the previous steps first.'], 409);
            }
            $params = $flow;
        // @fix-end
        // @vuln-start: step-skipping — /execute runs with no prior-stage check (transfer.l9)
        } else {
            // Prefer the flow if present, else take everything from the request.
            $params = [
                'from_account_id' => $in['from_account_id'] ?? ($flow['from_account_id'] ?? 0),
                'beneficiary_id'  => $in['beneficiary_id']  ?? ($flow['beneficiary_id']  ?? 0),
                'amount'          => $in['amount']          ?? ($flow['amount']          ?? 0),
                'mode'            => $in['mode']            ?? ($flow['mode']            ?? 'IMPS'),
                'remarks'         => $in['remarks']         ?? ($flow['remarks']         ?? ''),
            ];
        }
        // @vuln-end

        $res = $engine->isVulnerable('transfer', 'l1') || $engine->isVulnerable('transfer', 'l6') || $engine->isVulnerable('transfer', 'l7')
            ? xfer_execute_vuln($pdo, $engine, $uid, $params)
            : xfer_execute_secure($pdo, $engine, $uid, $params);

        if (!$res['ok']) {
            json_response(['ok' => false, 'error' => $res['error']], 400);
        }
        xfer_flow_clear();
        $xferResp = [
            'ok'          => true,
            'txn_id'      => $res['txn_id'],
            'txn_ref'     => $res['txn_ref'],
            'new_balance' => $res['new_balance'],
        ];
        // CTF (transfer.l1): surface the IDOR flag if present
        if (!empty($res['ctf_flag']) && flags_delivered()) {
            $xferResp['ctf_flag'] = $res['ctf_flag'];
        }
        json_response($xferResp);
        break;
    }

    default:
        json_response(['ok' => false, 'error' => 'Unknown transfer endpoint.'], 404);
}
