<?php

declare(strict_types=1);

/**
 * VULNERABLE login credential check (levels 0-2).
 *
 * Returns a structured result; the OTP second factor and session handling are
 * done by the caller (login.php / api.php) so HTML and API share this logic.
 *
 * @return array{ok:bool,user:?array<string,mixed>,error:?string}
 *
 * ============================================================================
 *  VULNERABILITIES DEMONSTRATED (for the code-viewer feature)
 * ----------------------------------------------------------------------------
 *  auth.g1  SQL injection  (CWE-89)  — concatenated query, graduated L0-L2:
 *           L0 error-based (raw) · L1 + blacklist (comments/UNION) · L2 bracketed
 *           WHERE and the DB error hidden (blind). L3 is the secure rewrite.
 *  auth.g1  Weak hashing   (CWE-327) — MD5 password comparison (levels 0-2)
 *  auth.l16 Account enum    (CWE-203) — distinct "not found" vs "wrong password"
 *  (OTP-in-response auth.l11 and no-rate-limit auth.l10 are added by the caller
 *   via _shared.php once credentials pass.)
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('auth_login_vuln')) {

    function auth_login_vuln(PDO $pdo, TrainingEngine $engine, string $customerId, string $password): array
    {
        $level = $engine->getVulnLevel('auth', 'g1');

        $cid = $customerId;

        // --------------------------------------------------------------------
        // LEVEL 1: naive blacklist. Blocks comment tokens and UNION so the easy
        // "-- " comment bypass no longer works. OR is NOT blocked, so a balanced
        // payload such as  BS100234' OR '1'='1  still authenticates.
        // --------------------------------------------------------------------
        if ($level === 1) {
            // @vuln-start: [L1] naive blacklist — blocks comments + UNION; bypass with OR (auth.g1)
            $lower = strtolower($cid);
            foreach (['--', '#', '/*', 'union'] as $bad) {
                if (str_contains($lower, $bad)) {
                    // Rejected: a blacklisted token was found in the input.
                    return ['ok' => false, 'user' => null, 'error' => 'Illegal characters in Customer ID.'];
                }
            }
            // @vuln-end
        }

        // --------------------------------------------------------------------
        // LEVELS 0-2 all concatenate customer_id into the query (SQL injection);
        // the password is checked against the weak MD5 column throughout. The
        // secure rewrite is level 3 (auth_login_secure): parameterized query +
        // password_verify(). Differences between the vulnerable levels:
        //   L0  raw query, DB error returned verbatim (error-based).
        //   L1  same query + blacklist above; DB error still returned.
        //   L2  customer_id wrapped in parentheses with the password check
        //       OUTSIDE them, and the DB error hidden (blind). Comment tokens
        //       are also blacklisted (generic rejection), so BS100234') --  is
        //       refused and the attacker cannot comment the tail away. The
        //       bracket must be broken WITHOUT a comment, e.g.
        //       BS100234') OR ('1'='1  — proving comment-blacklisting is not a
        //       SQLi defence.
        // --------------------------------------------------------------------

        // --------------------------------------------------------------------
        // LEVEL 2: comment-token blacklist. Blocks --, # and /* so the
        // "close the bracket and comment out the rest" payload (BS100234') -- )
        // no longer works. Unlike L1, the rejection is a GENERIC
        // "Invalid credentials." — consistent with L2 being blind, it does not
        // reveal that a filter tripped. This still does NOT stop SQLi: a
        // comment-free bracket break such as  BS100234') OR ('1'='1
        // authenticates — which is the point L2 teaches.
        // --------------------------------------------------------------------
        if ($level >= 2) {
            // @vuln-start: [L2] comment-token blacklist, generic blind rejection — bypass with comment-free OR (auth.g1)
            $lower = strtolower($cid);
            foreach (['--', '#', '/*'] as $bad) {
                if (str_contains($lower, $bad)) {
                    // Rejected, but the message is indistinguishable from a
                    // normal failed login — the filter stays invisible (blind).
                    return ['ok' => false, 'user' => null, 'error' => 'Invalid credentials.'];
                }
            }
            // @vuln-end
        }

        if ($level >= 2) {
            // @vuln-start: [L2] bracketed concat, password check outside parens; error hidden (blind SQLi) (auth.g1)
            $sql = "SELECT id, customer_id, name, role FROM users "
                 . "WHERE (customer_id = '" . $cid . "') "
                 . "AND password_md5 = '" . md5($password) . "' LIMIT 1";
            // @vuln-end
        } else {
            // @vuln-start: [L0-1] raw concatenation; SQL error returned verbatim (error-based SQLi) (auth.g1)
            $sql = "SELECT id, customer_id, name, role FROM users "
                 . "WHERE customer_id = '" . $cid . "' "
                 . "AND password_md5 = '" . md5($password) . "' LIMIT 1";
            // @vuln-end
        }

        try {
            $result = $pdo->query($sql);
            $user = $result ? $result->fetch() : false;
        } catch (PDOException $e) {
            // Error-based (L0/L1): expose the raw DB error so a lone quote
            // reveals the injection. L2 hides it (blind) — generic message only.
            $msg = $level >= 2 ? 'Invalid credentials.' : 'SQL error: ' . $e->getMessage();
            return ['ok' => false, 'user' => null, 'error' => $msg];
        }

        // Anti-tautology guard: the returned row's customer_id must appear in
        // the attacker's input, so they must inject a REAL customer id (their
        // own) — a blind  ' OR '1'='1  with no id returns no usable row.
        if ($user && !str_contains($cid, (string) $user['customer_id'])) {
            $user = false;
        }

        if ($user) {
            return ['ok' => true, 'user' => $user, 'error' => null];
        }

        // --------------------------------------------------------------------
        // auth.l16 ACCOUNT ENUMERATION: at vulnerable levels the message tells
        // the attacker WHICH half was wrong, turning login into a user oracle.
        // --------------------------------------------------------------------
        if ($engine->isVulnerable('auth', 'l16')) {
            // @vuln-start: account enumeration — distinct messages leak which accounts exist (auth.l16)
            $exists = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid LIMIT 1');
            $exists->execute([':cid' => $customerId]);
            $existsRow = $exists->fetch();
            if ($existsRow) {
                $error = 'Incorrect password.';
                // auth.l16 removed from CTF — no flag awarded (enumeration behavior kept for training).
            } else {
                $error = 'Customer ID not found.';
            }
            // @vuln-end
        } else {
            // @fix-start: account enumeration — single generic message (auth.l16)
            $error = 'Invalid credentials.';
            // @fix-end
        }

        return ['ok' => false, 'user' => null, 'error' => $error];
    }
}
