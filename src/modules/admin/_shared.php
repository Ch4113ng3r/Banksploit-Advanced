<?php

declare(strict_types=1);

/**
 * Shared admin logic — the L4 authorization guard plus user / audit / config /
 * progress data access, used by every admin page and the admin JSON API.
 *
 * ============================================================================
 *  CENTRAL VULNERABILITY
 * ----------------------------------------------------------------------------
 *  admin.l4 Broken function-level authorization (CWE-285) — GRADUATED:
 *
 *    Level 0  No role check at all. The caller only needs to be logged in.
 *             The sole "protection" is cosmetic (the sidebar Admin link is
 *             hidden from non-admins), so any authenticated user can hit
 *             /admin or POST /api/v1/admin/users/{id}/unlock directly.
 *
 *    Level 1  Naive role check against a CLIENT-CONTROLLED signal instead of
 *             the server-side session role — an `X-Admin: true` header, an
 *             `is_admin` cookie, or an `is_admin` request param. Trivially
 *             bypassed by just sending it (curl -H 'X-Admin: true', etc.).
 *
 *    Level 2  Real server-side role check on the HTML PAGES, but the JSON API
 *             endpoints still skip it. This models the very common real-world
 *             gap: the UI is hidden + the page is guarded, but the underlying
 *             API was forgotten — so /admin redirects a non-admin yet
 *             /api/v1/admin/users still returns data.
 *
 *    Level 3  Secure. Genuine server-side role check on EVERY surface
 *             (Auth::requireAdmin()), pages and API alike.
 * ============================================================================
 */

if (!function_exists('admin_guard')) {

    /**
     * Whether the caller presented a client-controlled "admin" signal. This is
     * NOT trustworthy — it's attacker-supplied — which is exactly the point of
     * the level-1 weakness.
     */
    function admin_client_claims_admin(): bool
    {
        // Spoofable header (X-Admin: true / 1).
        $hdr = $_SERVER['HTTP_X_ADMIN'] ?? '';
        if ($hdr !== '' && in_array(strtolower((string) $hdr), ['1', 'true', 'yes'], true)) {
            return true;
        }
        // Spoofable cookie / request param.
        $cookie = $_COOKIE['is_admin'] ?? '';
        if (in_array(strtolower((string) $cookie), ['1', 'true', 'yes'], true)) {
            return true;
        }
        $param = $_REQUEST['is_admin'] ?? '';
        return in_array(strtolower((string) $param), ['1', 'true', 'yes'], true);
    }

    /**
     * Authorization gate for admin surfaces.
     *
     *   level 0: require login only — NO role check (fully broken).
     *   level 1: trust a client-controlled admin flag (header/cookie/param).
     *   level 2: real role check on PAGES only; API ($json) still unguarded.
     *   level 3: real server-side role check everywhere (secure).
     *
     * @param bool $json  true for API endpoints (emit JSON 401/403 instead of
     *                    redirecting). Also selects the level-2 surface split.
     */
    function admin_guard(bool $json = false): void
    {
        $auth   = auth();
        $engine = engine();
        $level  = $engine->getVulnLevel('admin', 'l4');

        // Must at least be authenticated in every case.
        if (!$auth->isAuthenticated()) {
            if ($json) {
                json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
            }
            redirect('/login');
        }

        // Decide whether to ENFORCE a genuine server-side role check on this
        // particular surface, given the level.
        // @vuln-start: broken function-level authz — login-only at vuln levels (admin.l4)
        // @fix-start: function-level authz — real role check on every surface (admin.l4)
        $enforce = match (true) {
            $level >= 3 => true,                 // secure: always enforce
            $level === 2 => !$json,              // pages enforced, API skipped
            default     => false,                // level 0/1: never (see below)
        };
        // @vuln-end
        // @fix-end

        // Level 1: a check that LOOKS real but trusts a CLIENT-CONTROLLED
        // signal. A genuine admin passes; so does ANYONE who simply sends the
        // spoofable flag (X-Admin: true / is_admin cookie or param). A normal
        // user who sends nothing IS blocked — which makes the gate look
        // effective until the attacker discovers the flag and trivially
        // forges it. (Contrast L0, where there is no gate at all.)
        if ($level === 1) {
            if ($auth->isAdmin() || admin_client_claims_admin()) {
                return; // "authorized" — incl. anyone who forged the flag
            }
            if ($json) {
                json_response(['ok' => false, 'error' => 'Admin role required.'], 403);
            }
            redirect('/dashboard');
        }

        if ($enforce && !$auth->isAdmin()) {
            if ($json) {
                json_response(['ok' => false, 'error' => 'Admin role required.'], 403);
            }
            // Non-admins get bounced to their dashboard.
            redirect('/dashboard');
        }
        // Otherwise: allowed (level 0 always; level 2 API surface).
    }

    /**
     * Is the current user actually an admin? (For showing/hiding UI only — must
     * NOT be relied on for access control; that's what admin_guard is for.)
     */
    function admin_is_real_admin(): bool
    {
        return auth()->isAdmin();
    }

    // -------------------------------------------------------- audit log ----

    /**
     * Append an audit entry. request_body is stored verbatim — at core.l18
     * (verbose errors / info disclosure) the audit viewer renders these full
     * bodies including any sensitive fields that were posted.
     *
     * @param array<string,mixed>|string|null $body
     */
    function admin_audit(PDO $pdo, ?int $userId, string $action, string $resource, $body = null, int $code = 200): void
    {
        $bodyStr = is_string($body) ? $body : ($body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES));
        try {
            $pdo->prepare(
                'INSERT INTO audit_log (user_id, action, resource, ip_address, request_body, response_code)
                 VALUES (:u, :a, :r, :ip, :b, :c)'
            )->execute([
                ':u' => $userId, ':a' => $action, ':r' => $resource,
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ':b' => $bodyStr, ':c' => $code,
            ]);
        } catch (Throwable $e) {
            // never break the request on audit failure
        }
    }

    /**
     * Search audit entries.
     *
     * statements.g2 SQL injection (CWE-89): at vuln levels the `action` /
     * `user` search terms are concatenated straight into the WHERE clause, so
     *   ?action=x' UNION SELECT id,password_md5,role,... FROM users --
     * extracts arbitrary data. Parameterized at the secure level.
     *
     * @return array{rows:list<array<string,mixed>>,error:?string}
     */
    function admin_audit_search(PDO $pdo, TrainingEngine $engine, array $f): array
    {
        $action = (string) ($f['action'] ?? '');
        $user   = (string) ($f['user'] ?? '');
        $from   = (string) ($f['from_date'] ?? '');
        $to     = (string) ($f['to_date'] ?? '');

        if (!g2_is_secure()) {
            // VULNERABLE (levels 0-2): concatenated filters via graduated
            // g2_term() (raw / blacklist / quote-escape per level).
            $a = g2_term($action);
            $u = g2_term($user);
            $df = g2_term($from);
            $dt = g2_term($to);
            $sql = 'SELECT id, user_id, action, resource, ip_address, request_body, response_code, created_at
                      FROM audit_log WHERE 1=1';
            if ($action !== '') {
                $sql .= " AND action LIKE '%$a%'";
            }
            if ($user !== '') {
                $sql .= " AND user_id = '$u'";
            }
            if ($from !== '') {
                $sql .= " AND created_at >= '$df'";
            }
            if ($to !== '') {
                $sql .= " AND created_at <= '$dt'";
            }
            $sql .= ' ORDER BY created_at DESC';
            try {
                return ['rows' => $pdo->query($sql)->fetchAll(), 'error' => null];
            } catch (PDOException $e) {
                return ['rows' => [], 'error' => l18_error($e, 'Query failed.')];
            }
        }

        // SECURE: parameterized.
        $sql = 'SELECT id, user_id, action, resource, ip_address, request_body, response_code, created_at
                  FROM audit_log WHERE 1=1';
        $bind = [];
        if ($action !== '') { $sql .= ' AND action LIKE :a'; $bind[':a'] = '%' . $action . '%'; }
        if ($user !== '')   { $sql .= ' AND user_id = :u';   $bind[':u'] = (int) $user; }
        if ($from !== '')   { $sql .= ' AND created_at >= :f'; $bind[':f'] = $from; }
        if ($to !== '')     { $sql .= ' AND created_at <= :t'; $bind[':t'] = $to; }
        $sql .= ' ORDER BY created_at DESC';
        $st = $pdo->prepare($sql);
        $st->execute($bind);
        return ['rows' => $st->fetchAll(), 'error' => null];
    }

    // ------------------------------------------------------- users ---------

    /**
     * @return list<array<string,mixed>>
     */
    function admin_users(PDO $pdo): array
    {
        return $pdo->query(
            'SELECT id, customer_id, name, email, phone, role, is_locked, is_suspended,
                    failed_attempts, created_at
               FROM users ORDER BY id'
        )->fetchAll();
    }

    function admin_user_suspend(PDO $pdo, int $id, bool $suspended): void
    {
        $pdo->prepare('UPDATE users SET is_suspended = :s WHERE id = :id')
            ->execute([':s' => $suspended ? 1 : 0, ':id' => $id]);
    }

    function admin_user_unlock(PDO $pdo, int $id): void
    {
        $pdo->prepare('UPDATE users SET is_locked = 0, failed_attempts = 0, locked_until = NULL WHERE id = :id')
            ->execute([':id' => $id]);
    }

    /**
     * Reset a user's password to a temporary one (returned to the caller).
     */
    function admin_user_reset_password(PDO $pdo, int $id): string
    {
        $temp = 'Temp@' . random_int(1000, 9999);
        $pdo->prepare('UPDATE users SET password_hash = :h, password_md5 = :m WHERE id = :id')
            ->execute([':h' => password_hash($temp, PASSWORD_BCRYPT), ':m' => md5($temp), ':id' => $id]);
        return $temp;
    }

    // ------------------------------------------------------- config --------

    /**
     * @return array<string,string>
     */
    function admin_config_get(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT config_key, config_value FROM system_config')->fetchAll() as $r) {
            $out[$r['config_key']] = $r['config_value'];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $values
     */
    function admin_config_set(PDO $pdo, array $values): void
    {
        $allowed = ['otp_expiry_seconds', 'session_timeout_minutes', 'daily_transfer_limit',
                    'max_failed_logins', 'beneficiary_cooling_hours'];
        $st = $pdo->prepare(
            'INSERT INTO system_config (config_key, config_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE config_value = :v2'
        );
        foreach ($values as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $st->execute([':k' => $k, ':v' => (string) $v, ':v2' => (string) $v]);
            }
        }
    }

    // ------------------------------------------------------- progress ------

    /**
     * @return array{rows:list<array<string,mixed>>,total:int,exploited:int,score:int}
     */
    function admin_progress(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT vuln_id, status, hints_used, found_at FROM training_progress ORDER BY vuln_id'
        )->fetchAll();
        $total = count($rows);
        $exploited = 0;
        foreach ($rows as $r) {
            if (in_array($r['status'], ['exploited', 'reviewed'], true)) {
                $exploited++;
            }
        }
        $score = $total > 0 ? (int) round($exploited / $total * 100) : 0;
        return ['rows' => $rows, 'total' => $total, 'exploited' => $exploited, 'score' => $score];
    }

    function admin_progress_reset(PDO $pdo): void
    {
        $pdo->exec("UPDATE training_progress SET status = 'hunting', hints_used = 0, found_at = NULL");
    }

    function admin_progress_set(PDO $pdo, string $vulnId, string $status): void
    {
        $found = in_array($status, ['found', 'exploited', 'reviewed'], true) ? 'NOW()' : 'NULL';
        $st = $pdo->prepare(
            "UPDATE training_progress SET status = :s, found_at = " . ($found === 'NOW()' ? 'NOW()' : 'NULL') . "
              WHERE vuln_id = :v"
        );
        $st->execute([':s' => $status, ':v' => $vulnId]);
    }
}
