<?php

declare(strict_types=1);

/**
 * VULNERABLE profile read/update (levels 0-2).
 *
 * The whole profile module progresses through one dial: profile.l5. The stage
 * spec describes the levels as a module progression, so we honour that here:
 *
 *   Level 0 : IDOR (?user_id=N reads anyone) + mass assignment (role/is_locked/
 *             daily_limit accepted) + stored XSS (name/address saved raw).
 *   Level 1 : IDOR fixed (always the session user) BUT mass assignment still
 *             works; strip_tags() applied to name only, NOT address.
 *   Level 2 : mass assignment blocked by a whitelist BUT IDOR returns via a
 *             *different* parameter (?id=N) — "still works via a different
 *             endpoint" per the spec.
 *
 * ============================================================================
 *  VULNERABILITIES (for the code-viewer feature)
 * ----------------------------------------------------------------------------
 *  profile (l5) IDOR             (CWE-639) — user_id/id from the request picks
 *               the row with no ownership check.
 *  profile.l5   Mass assignment  (CWE-915) — request fields written verbatim,
 *               including role / is_locked (users) and daily_limit (accounts).
 *  (stored XSS) (CWE-79)         — name/address stored raw; rendered unescaped
 *               by the caller via vuln_out().
 * ============================================================================
 */

if (!function_exists('profile_target_user_id')) {

    /**
     * Decide WHOSE profile to read — the IDOR decision point.
     */
    function profile_target_user_id(TrainingEngine $engine, int $currentUserId, array $in): int
    {
        $level = $engine->getVulnLevel('profile', 'l5');

        // Level 0: classic IDOR via ?user_id=.
        if ($level === 0 && isset($in['user_id']) && $in['user_id'] !== '') {
            return (int) $in['user_id'];
        }
        // Level 2: IDOR resurfaces through a *different* parameter name.
        if ($level === 2 && isset($in['id']) && $in['id'] !== '') {
            return (int) $in['id'];
        }
        // Level 1 (and anything else): pinned to the logged-in user.
        return $currentUserId;
    }

    /**
     * Read a profile row (subject to the IDOR above).
     *
     * @return array<string,mixed>|null
     */
    function profile_get_vuln(PDO $pdo, TrainingEngine $engine, int $currentUserId, array $in): ?array
    {
        $targetId = profile_target_user_id($engine, $currentUserId, $in);

        // Parameterized lookup — the flaw is the MISSING ownership check, not
        // injection (SQLi is exercised in the auth/statements modules).
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, name, email, phone, address, aadhaar_masked,
                    pan, role, is_locked
               FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $targetId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Update a profile (subject to mass assignment above).
     *
     * @return array{ok:bool,error:?string,escalated:bool}
     */
    function profile_update_vuln(PDO $pdo, TrainingEngine $engine, int $currentUserId, array $in): array
    {
        $level = $engine->getVulnLevel('profile', 'l5');

        // Whose row to update — same IDOR surface as the read at level 0/2.
        $targetId = profile_target_user_id($engine, $currentUserId, $in);

        // Columns that physically exist on users and may be written.
        // @vuln-start: mass assignment — role/is_locked bindable (profile.l5)
        $userCols = ['name', 'email', 'phone', 'address', 'aadhaar_masked', 'pan', 'role', 'is_locked'];
        // @vuln-end

        if ($level >= 2) {
            // ---- Level 2: whitelist defeats mass assignment ----
            $allowed = ['name', 'email', 'phone', 'address'];
            $userCols = array_values(array_intersect($userCols, $allowed));
        }

        $set = [];
        $bind = [':id' => $targetId];
        $escalated = false;

        foreach ($userCols as $col) {
            if (!array_key_exists($col, $in)) {
                continue;
            }
            $value = $in[$col];

            // Level 1: naive XSS mitigation on name only (address left raw).
            if ($level === 1 && $col === 'name') {
                $value = strip_tags((string) $value);
            }

            if ($col === 'role' && (string) $value === 'admin') {
                $escalated = true;       // mass-assignment privilege escalation
            }

            $set[] = "$col = :$col";
            $bind[":$col"] = $value;
        }

        if ($set !== []) {
            $sql = 'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id';
            $pdo->prepare($sql)->execute($bind);
        }

        // daily_limit lives on accounts; at mass-assignment levels it is also
        // blindly applied (per spec: "role, daily_limit, is_locked").
        if ($level <= 1 && array_key_exists('daily_limit', $in)) {
            $pdo->prepare('UPDATE accounts SET daily_limit = :dl WHERE user_id = :uid')
                ->execute([':dl' => (float) $in['daily_limit'], ':uid' => $targetId]);
        }

        return ['ok' => true, 'error' => null, 'escalated' => $escalated];
    }
}
