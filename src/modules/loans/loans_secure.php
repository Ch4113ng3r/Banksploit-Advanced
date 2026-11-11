<?php

declare(strict_types=1);

/**
 * SECURE loan operations (level 3).
 *
 * ============================================================================
 *  SECURE CONTROLS (contrast with loans_vuln.php)
 * ----------------------------------------------------------------------------
 *  loans.l22: interest_rate and tenure_months are derived SERVER-SIDE from a
 *      fixed product table; client-supplied values are ignored.
 *  loans.g7:  upload validated by extension allow-list + MIME sniff + random
 *      name, stored OUTSIDE the web root.
 *  loans.g10: XML parsed with external entities DISABLED (LIBXML_NONET and no
 *      LIBXML_NOENT/DTDLOAD) — XXE neutralised.
 *  loans.g6:  document URL fetch restricted to an https host allow-list with
 *      link-local / private ranges blocked.
 * ============================================================================
 */

require_once __DIR__ . '/loans_vuln.php';   // reuse loans_calc_emi + loans_list/get

if (!function_exists('loans_product_terms')) {

    /**
     * Server-decided loan terms per product. The client never sets these.
     *
     * @return array{rate:float,max_tenure:int,max_amount:float}
     */
    function loans_product_terms(string $type): array
    {
        return match ($type) {
            'home'  => ['rate' => 8.75, 'max_tenure' => 240, 'max_amount' => 10000000.0],
            default => ['rate' => 11.50, 'max_tenure' => 60,  'max_amount' => 2000000.0], // personal
        };
    }

    /**
     * Apply for a loan with SERVER-side terms (fixes loans.l22).
     *
     * @param array<string,mixed> $in
     * @return array{ok:bool,error:?string,loan_id:?int,emi:?float}
     */
    function loans_apply_secure(PDO $pdo, TrainingEngine $engine, int $userId, array $in): array
    {
        $type   = in_array($in['loan_type'] ?? '', ['personal', 'home'], true) ? $in['loan_type'] : 'personal';
        $amount = (float) ($in['amount'] ?? 0);
        $tenure = (int) ($in['tenure_months'] ?? 12);

        // @fix-start: loan parameter tampering (loans.l22)
        $terms = loans_product_terms($type);

        if ($amount <= 0 || $amount > $terms['max_amount']) {
            return ['ok' => false, 'error' => 'Amount outside the allowed range.', 'loan_id' => null, 'emi' => null];
        }
        if ($tenure <= 0 || $tenure > $terms['max_tenure']) {
            return ['ok' => false, 'error' => 'Tenure outside the allowed range.', 'loan_id' => null, 'emi' => null];
        }

        // Rate is ALWAYS the product rate — client value ignored entirely.
        $rate = $terms['rate'];
        // @fix-end

        $pdo->prepare(
            'INSERT INTO loans (user_id, loan_type, amount, tenure_months, interest_rate, status, documents)
             VALUES (:u, :t, :amt, :ten, :rate, "applied", :doc)'
        )->execute([
            ':u' => $userId, ':t' => $type, ':amt' => $amount,
            ':ten' => $tenure, ':rate' => $rate, ':doc' => '',
        ]);

        return [
            'ok'      => true,
            'error'   => null,
            'loan_id' => (int) $pdo->lastInsertId(),
            'emi'     => loans_calc_emi($amount, $rate, $tenure),
        ];
    }

    /**
     * Secure upload: extension allow-list + MIME sniff + random name, stored
     * outside the web root (fixes loans.g7).
     *
     * @param array<string,mixed> $file
     * @return array{ok:bool,message:string,path:?string}
     */
    function loans_upload_secure(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'No file uploaded.', 'path' => null];
        }
        $name = (string) ($file['name'] ?? '');
        $tmp  = (string) ($file['tmp_name'] ?? '');
        // @fix-start: upload — ext allow-list + MIME sniff + random name outside webroot (loans.g7)
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            return ['ok' => false, 'message' => 'Extension not allowed.', 'path' => null];
        }
        $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmp) : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            return ['ok' => false, 'message' => 'File content does not match an allowed type.', 'path' => null];
        }
        $safeDir = '/var/loan_docs';            // outside /var/www/html
        if (!is_dir($safeDir)) {
            @mkdir($safeDir, 0700, true);
        }
        $dest = $safeDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        @move_uploaded_file($tmp, $dest);
        // @fix-end
        return ['ok' => true, 'message' => 'Document stored securely.', 'path' => $dest];
    }

    /**
     * Secure XML parse: external entities disabled (fixes loans.g10 XXE).
     *
     * @return array{ok:bool,parsed:?string,error:?string}
     */
    function loans_parse_xml_secure(string $xml): array
    {
        if ($xml === '') {
            return ['ok' => false, 'parsed' => null, 'error' => 'Empty XML.'];
        }
        libxml_use_internal_errors(true);
        // @fix-start: XXE — no NOENT/DTDLOAD, LIBXML_NONET, DOCTYPE rejected (loans.g10)
        // No LIBXML_NOENT, no DTDLOAD, and LIBXML_NONET blocks network fetches.
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        // @fix-end
        if ($doc === false) {
            return ['ok' => false, 'parsed' => null, 'error' => 'Invalid XML.'];
        }
        // Reject any DOCTYPE outright as defence in depth.
        if (stripos($xml, '<!DOCTYPE') !== false) {
            return ['ok' => false, 'parsed' => null, 'error' => 'DOCTYPE not allowed.'];
        }
        return ['ok' => true, 'parsed' => trim((string) $doc), 'error' => null];
    }

    /**
     * Secure URL fetch: https-only, host allow-list, block private/link-local
     * (fixes loans.g6 SSRF).
     *
     * @return array{ok:bool,body:?string,error:?string}
     */
    function loans_fetch_doc_secure(string $rawUrl): array
    {
        // @fix-start: SSRF — https-only + host allow-list + block private/link-local (loans.g6)
        $parts = parse_url($rawUrl);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https') {
            return ['ok' => false, 'body' => null, 'error' => 'Only https URLs are allowed.'];
        }
        $host = strtolower($parts['host'] ?? '');
        $allow = ['docs.banksploit.local', 'cdn.example.in'];
        if (!in_array($host, $allow, true)) {
            return ['ok' => false, 'body' => null, 'error' => 'Host not on the allow-list.'];
        }
        // Block resolved private / link-local addresses.
        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['ok' => false, 'body' => null, 'error' => 'Refusing to fetch a private address.'];
        }
        // @fix-end
        $ctx = stream_context_create(['http' => ['timeout' => 4]]);
        $body = @file_get_contents($rawUrl, false, $ctx);
        return $body === false
            ? ['ok' => false, 'body' => null, 'error' => 'Fetch failed.']
            : ['ok' => true, 'body' => substr($body, 0, 4096), 'error' => null];
    }
}
