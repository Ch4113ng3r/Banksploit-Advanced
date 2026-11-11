<?php

declare(strict_types=1);

/**
 * VULNERABLE loan operations (levels 0-2).
 *
 * ============================================================================
 *  VULNERABILITIES (for the code-viewer feature)
 * ----------------------------------------------------------------------------
 *  loans.l22 Parameter tampering (CWE-472):
 *      interest_rate and tenure_months are taken from the CLIENT request and
 *      trusted. An attacker lowers the rate / extends the tenure to get a
 *      smaller EMI (or a bigger sanctioned amount). The server should derive
 *      these from the chosen product, not accept them from the user.
 *
 *  loans.g7 Unrestricted file upload / webshell (CWE-434):
 *      Document upload performs no real server-side type check at level 0
 *      (level 1 only checks the spoofable Content-Type header), so a .php
 *      webshell can be uploaded into the web-served uploads/ directory.
 *
 *  loans.g10 XXE (CWE-611):
 *      The XML document importer parses with external entities ENABLED, so a
 *      DOCTYPE with an external entity can read local files / trigger SSRF.
 *
 *  loans.g6 SSRF (CWE-918):
 *      A "document URL" field is fetched server-side with no host allow-list,
 *      so it can reach internal services or cloud metadata (169.254.169.254).
 * ============================================================================
 */

if (!function_exists('loans_list')) {

    /**
     * @return list<array<string,mixed>>
     */
    function loans_list(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare('SELECT * FROM loans WHERE user_id = :u ORDER BY id DESC');
        $s->execute([':u' => $userId]);
        return $s->fetchAll();
    }

    /**
     * @return array<string,mixed>|null
     */
    function loans_get(PDO $pdo, int $userId, int $id): ?array
    {
        $s = $pdo->prepare('SELECT * FROM loans WHERE id = :id AND user_id = :u');
        $s->execute([':id' => $id, ':u' => $userId]);
        $r = $s->fetch();
        return $r ?: null;
    }
}

if (!function_exists('loans_apply_vuln')) {

    /**
     * Apply for a loan.
     *
     * loans.l22: interest_rate and tenure_months come straight from $in and are
     * stored verbatim — the client controls its own loan terms.
     *
     * @param array<string,mixed> $in
     * @return array{ok:bool,error:?string,loan_id:?int,emi:?float}
     */
    function loans_apply_vuln(PDO $pdo, TrainingEngine $engine, int $userId, array $in): array
    {
        $type   = in_array($in['loan_type'] ?? '', ['personal', 'home'], true) ? $in['loan_type'] : 'personal';
        $amount = (float) ($in['amount'] ?? 0);

        // *** loans.l22 *** — TRUST the client-supplied rate (and tenure) whenever
        // present. The vulnerability is identical across levels 0-2; only how the
        // page EXPOSES the parameter hardens (readonly -> hidden -> source-comment,
        // see index.php). When the form omits it (L2's comment-only exposure) fall
        // back to the product rate so an untampered loan is still sane — but any
        // value the client DOES send is honoured, so tampering always works at 0-2.
        // @vuln-start: loan parameter tampering (loans.l22)
        $rate   = isset($in['interest_rate'])
            ? (float) $in['interest_rate']
            : (($type === 'home') ? 8.75 : 11.5);
        $tenure = (int) ($in['tenure_months'] ?? 12);
        // @vuln-end

        $pdo->prepare(
            'INSERT INTO loans (user_id, loan_type, amount, tenure_months, interest_rate, status, documents)
             VALUES (:u, :t, :amt, :ten, :rate, "applied", :doc)'
        )->execute([
            ':u' => $userId, ':t' => $type, ':amt' => $amount,
            ':ten' => $tenure, ':rate' => $rate,
            ':doc' => (string) ($in['documents'] ?? ''),
        ]);

        return [
            'ok'      => true,
            'error'   => null,
            'loan_id' => (int) $pdo->lastInsertId(),
            'emi'     => loans_calc_emi($amount, $rate, $tenure),
        ];
    }

    /**
     * Standard reducing-balance EMI. Shared by vuln + secure + calculator.
     */
    function loans_calc_emi(float $principal, float $annualRate, int $months): float
    {
        if ($months <= 0) {
            return 0.0;
        }
        $r = $annualRate / 12 / 100;
        if ($r <= 0) {
            return round($principal / $months, 2);
        }
        $emi = $principal * $r * pow(1 + $r, $months) / (pow(1 + $r, $months) - 1);
        return round($emi, 2);
    }

    // --------------------------------------------------- file upload (g7) --

    /**
     * Handle a loan document upload.
     *
     * loans.g7:
     *   level 0 -> NO check; any extension saved to the web-served uploads/.
     *   level 1 -> only the (spoofable) Content-Type header is checked.
     *   level 2 -> server-side extension allow-list.
     *
     * @param array<string,mixed> $file  a $_FILES entry
     * @return array{ok:bool,message:string,path:?string}
     */
    function loans_upload_vuln(TrainingEngine $engine, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'No file uploaded.', 'path' => null];
        }
        $level = $engine->getVulnLevel('loans', 'g7');
        $name  = basename((string) ($file['name'] ?? 'doc'));
        $tmp   = (string) ($file['tmp_name'] ?? '');
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $webDir = __DIR__ . '/../../uploads';
        if (!is_dir($webDir)) {
            @mkdir($webDir, 0777, true);
        }

        if ($level === 1) {
            // Only trusts the client-sent Content-Type — trivially spoofed.
            $ct = (string) ($file['type'] ?? '');
            $okCt = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($ct, $okCt, true)) {
                return ['ok' => false, 'message' => 'Content-Type not allowed: ' . $ct, 'path' => null];
            }
        } elseif ($level === 2) {
            // Server-side extension allow-list (better, but no MIME sniff).
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                return ['ok' => false, 'message' => 'Extension not allowed: .' . $ext, 'path' => null];
            }
        }
        // level 0: no checks at all.

        // @vuln-start: unrestricted upload — saves any file to web root (loans.g7)
        $dest = $webDir . '/' . $name;
        @move_uploaded_file($tmp, $dest);
        $note = $level === 0 ? ' (no checks — .php webshell accepted)' : '';
        return ['ok' => true, 'message' => 'Uploaded ' . $name . $note, 'path' => 'uploads/' . $name];
        // @vuln-end
    }

    // --------------------------------------------------- XML import (g10) --

    /**
     * Parse an uploaded/pasted XML loan document.
     *
     * loans.g10 XXE: at vuln levels external entity loading is ENABLED, so a
     *   <!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]> ... &xxe;
     * payload is expanded and reflected back.
     *
     * @return array{ok:bool,parsed:?string,error:?string}
     */
    function loans_parse_xml_vuln(TrainingEngine $engine, string $xml): array
    {
        if ($xml === '') {
            return ['ok' => false, 'parsed' => null, 'error' => 'Empty XML.'];
        }
        libxml_use_internal_errors(true);

        // *** loans.g10 *** — LIBXML_NOENT expands external entities.
        // (PHP < 8 also needed libxml_disable_entity_loader(false); on 8.x the
        // loader is controllable via the LIBXML_NOENT flag below.)
        // @vuln-start: XXE — external entities enabled in XML parser (loans.g10)
        $flags = LIBXML_NOENT | LIBXML_DTDLOAD;
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', $flags);
        // @vuln-end
        if ($doc === false) {
            return ['ok' => false, 'parsed' => null, 'error' => 'Invalid XML.'];
        }
        return ['ok' => true, 'parsed' => trim((string) $doc), 'error' => null];
    }

    // --------------------------------------------------- doc URL (g6 SSRF) -

    /**
     * Fetch a "document URL" server-side.
     *
     * loans.g6 SSRF: at vuln levels ANY URL is fetched — internal hosts and
     * cloud metadata (http://169.254.169.254/...) included.
     *
     * @return array{ok:bool,body:?string,error:?string}
     */
    function loans_fetch_doc_vuln(string $rawUrl): array
    {
        if ($rawUrl === '') {
            return ['ok' => false, 'body' => null, 'error' => 'No URL.'];
        }
        // @vuln-start: SSRF — fetches any client URL, no allow-list (loans.g6)
        $ctx = stream_context_create(['http' => ['timeout' => 4]]);
        $body = @file_get_contents($rawUrl, false, $ctx);
        // @vuln-end
        if ($body === false) {
            return ['ok' => false, 'body' => null, 'error' => 'Fetch failed for ' . $rawUrl];
        }
        return ['ok' => true, 'body' => substr($body, 0, 4096), 'error' => null];
    }
}
