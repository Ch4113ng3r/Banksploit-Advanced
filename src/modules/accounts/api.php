<?php

declare(strict_types=1);

/**
 * Accounts JSON API.
 *
 *   GET /api/v1/accounts                      list accounts
 *   GET /api/v1/accounts/{id}                 account detail
 *   GET /api/v1/accounts/{id}/statement       transactions (date filter + search)
 *   GET /api/v1/accounts/{id}/export          CSV download
 *
 * One handler switches on REQUEST_URI. All four reuse statements/_shared.php so
 * the same level dial governs the HTML statements page and this API.
 *
 * Vulns surfaced: transfer.l1 (IDOR), accounts.l19 (hidden fields),
 * statements.g2 (SQLi date filter), statements.g8 (path traversal export).
 */

require_once __DIR__ . '/../statements/_shared.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$in     = request_input();

// Ensure ctf_flags are seeded for g2 SQLi exploitation
ensure_ctf_flags_seeded($pdo);

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');

// ---- Collection: GET /api/v1/accounts -------------------------------------
if ($path === '/api/v1/accounts') {
    $accounts = stmt_list_accounts($pdo, $engine, $uid);
    json_response(['ok' => true, 'count' => count($accounts), 'accounts' => $accounts]);
}

// ---- Sub-resources under /api/v1/accounts/{id}/... ------------------------
$id = (int) (route_param('id') ?? 0);

// .../export-pdf  (statements.g9 command injection via ?filename=)
if (str_ends_with($path, '/export-pdf')) {
    $account = stmt_get_account($pdo, $engine, $uid, $id);
    if ($account === null) {
        json_response(['ok' => false, 'error' => 'Account not found.'], 404);
    }
    $filename = (string) ($in['filename'] ?? 'statement.pdf');
    $html = '<h1>Statement for ' . htmlspecialchars((string) $account['account_number']) . '</h1>';
    $res = stmt_export_pdf($engine, $filename, $html);

    if (!$engine->isVulnerable('statements', 'g9')) {
        // SECURE (level 3): the escapeshellarg()'d command above ran harmlessly,
        // so deliver an ACTUAL PDF download (there is no injected output to show).
        $acctPdf = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) $account['account_number']);
        $stream  = "BT /F1 18 Tf 72 780 Td (BankSploit - Account Statement) Tj "
                 . "/F1 12 Tf 0 -30 Td (Account: {$acctPdf}) Tj ET";
        $objs = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
               . '/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $n => $body) {
            $offsets[$n] = strlen($pdf);
            $pdf .= $n . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($objs as $n => $body) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\n"
              . "startxref\n" . $xref . "\n%%EOF";

        // Safe, sanitised download name derived from the requested file name.
        $dl = preg_replace('/[^A-Za-z0-9._-]/', '', basename($filename)) ?: 'statement.pdf';
        if (!preg_match('/\.pdf$/i', $dl)) {
            $dl .= '.pdf';
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $dl . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    // VULN levels (0-2): return the shelled-out command + its combined output so
    // an injected command's result (e.g. `;id;`) is visible to the learner.
    json_response(['ok' => true, 'command' => $res['cmd'], 'output' => $res['output']]);
}

// .../export
if (str_ends_with($path, '/export')) {
    $account = stmt_get_account($pdo, $engine, $uid, $id);
    if ($account === null) {
        json_response(['ok' => false, 'error' => 'Account not found.'], 404);
    }
    $target = stmt_export_target($engine, (string) ($in['file'] ?? ''));

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $target['name'] . '"');

    if ($target['mode'] === 'file' && $target['path'] !== null) {
        // statements.g8: at vuln levels this streams an arbitrary path.
        if (is_file($target['path'])) {
            echo file_get_contents($target['path']);
        } else {
            echo "File not found: " . $target['path'];
        }
        exit;
    }
    // Generate from this account's transactions.
    $res = stmt_transactions($pdo, $engine, $id, '', '', '');
    echo stmt_rows_to_csv($res['rows']);
    exit;
}

// .../statement
if (str_ends_with($path, '/statement')) {
    $account = stmt_get_account($pdo, $engine, $uid, $id);
    if ($account === null) {
        json_response(['ok' => false, 'error' => 'Account not found or access denied.'], 404);
    }
    $res = stmt_transactions(
        $pdo, $engine, $id,
        (string) ($in['from_date'] ?? ''),
        (string) ($in['to_date'] ?? ''),
        (string) ($in['q'] ?? '')
    );
    $out = ['ok' => $res['error'] === null, 'account' => $account, 'transactions' => $res['rows']];
    if ($res['error'] !== null) {
        $out['error'] = $res['error'];   // verbose at core.l18
    }
    json_response($out);
}

// ---- Detail: GET /api/v1/accounts/{id} ------------------------------------
$account = stmt_get_account($pdo, $engine, $uid, $id);
if ($account === null) {
    json_response(['ok' => false, 'error' => 'Account not found or access denied.'], 404);
}
json_response(['ok' => true, 'account' => $account]);
