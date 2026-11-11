<?php

declare(strict_types=1);

/**
 * Loans JSON API.
 *
 *   POST /api/v1/loans/apply            apply (loans.l22 parameter tampering)
 *   GET  /api/v1/loans                  list
 *   GET  /api/v1/loans/{id}             detail
 *   POST /api/v1/loans/{id}/documents   upload (g7) / XML import (g10) / URL (g6)
 *   GET  /api/v1/loans/emi-calculator   EMI calc
 *
 * Each vuln has its own dial: l22 (apply), g7 (upload), g10 (xml), g6 (url).
 */

require_once __DIR__ . '/loans_vuln.php';
require_once __DIR__ . '/loans_secure.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$in     = request_input();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');

// ---- EMI calculator (GET) ----
if ($path === '/api/v1/loans/emi-calculator') {
    $amount = (float) ($in['amount'] ?? 0);
    $rate   = (float) ($in['interest_rate'] ?? 11.5);
    $tenure = (int) ($in['tenure_months'] ?? 12);
    $emi = loans_calc_emi($amount, $rate, $tenure);
    json_response([
        'ok' => true, 'emi' => $emi,
        'total_payable' => round($emi * $tenure, 2),
        'total_interest' => round($emi * $tenure - $amount, 2),
    ]);
}

// ---- apply (POST) ----
if ($path === '/api/v1/loans/apply' && $method === 'POST') {
    auth()->requireSession(); // JWT is read-only
    $secure = !$engine->isVulnerable('loans', 'l22');
    if ($secure && !CSRF::validateToken($in['csrf_token'] ?? null)) {
        json_response(['ok' => false, 'error' => 'CSRF token required.'], 403);
    }
    $res = $secure
        ? loans_apply_secure($pdo, $engine, $uid, $in)
        : loans_apply_vuln($pdo, $engine, $uid, $in);
    if (!$res['ok']) {
        json_response(['ok' => false, 'error' => $res['error']], 400);
    }
    json_response(['ok' => true, 'loan_id' => $res['loan_id'], 'emi' => $res['emi']], 201);
}

// ---- documents (POST /{id}/documents) ----
if (str_ends_with($path, '/documents') && $method === 'POST') {
    $loanId = (int) (route_param('id') ?? 0);
    $loan = loans_get($pdo, $uid, $loanId);
    if ($loan === null) {
        json_response(['ok' => false, 'error' => 'Loan not found.'], 404);
    }
    $out = ['ok' => true, 'loan_id' => $loanId];

    // (a) file upload (g7)
    if (isset($_FILES['document'])) {
        $out['upload'] = $engine->isVulnerable('loans', 'g7')
            ? loans_upload_vuln($engine, $_FILES['document'])
            : loans_upload_secure($_FILES['document']);
    }
    // (b) XML import (g10 XXE)
    if (!empty($in['xml'])) {
        $out['xml'] = $engine->isVulnerable('loans', 'g10')
            ? loans_parse_xml_vuln($engine, (string) $in['xml'])
            : loans_parse_xml_secure((string) $in['xml']);
    }
    // (c) document URL fetch (g6 SSRF)
    if (!empty($in['document_url'])) {
        $out['url_fetch'] = $engine->isVulnerable('loans', 'g6')
            ? loans_fetch_doc_vuln((string) $in['document_url'])
            : loans_fetch_doc_secure((string) $in['document_url']);
    }
    json_response($out);
}

// ---- detail (GET /{id}) ----
if (route_param('id') !== null && $method === 'GET') {
    $loan = loans_get($pdo, $uid, (int) route_param('id'));
    if ($loan === null) {
        json_response(['ok' => false, 'error' => 'Loan not found.'], 404);
    }
    json_response(['ok' => true, 'loan' => $loan]);
}

// ---- list (GET) ----
if ($path === '/api/v1/loans' && $method === 'GET') {
    json_response(['ok' => true, 'loans' => loans_list($pdo, $uid)]);
}

json_response(['ok' => false, 'error' => 'Unsupported.'], 405);
