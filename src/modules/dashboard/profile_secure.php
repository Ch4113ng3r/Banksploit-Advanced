<?php

declare(strict_types=1);

/**
 * SECURE profile read/update (level 3).
 *
 * ============================================================================
 *  SECURE CONTROLS (contrast with profile_vuln.php)
 * ----------------------------------------------------------------------------
 *  - No user_id/id parameter is honoured: always the session user (no IDOR).
 *  - Update whitelist: name, email, phone, address ONLY (no role / is_locked /
 *    daily_limit) — defeats mass assignment.
 *  - Inputs validated; output is HTML-escaped by the caller via vuln_out()
 *    (which escapes at the secure level).
 *  - CSRF is enforced by the caller before reaching here.
 * ============================================================================
 */

if (!function_exists('profile_get_secure')) {

    /**
     * @return array<string,mixed>|null
     */
    function profile_get_secure(PDO $pdo, TrainingEngine $engine, int $currentUserId, array $in): ?array
    {
        // user_id/id in the request is ignored entirely.
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, name, email, phone, address, aadhaar_masked,
                    pan, role, is_locked
               FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $currentUserId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{ok:bool,error:?string,escalated:bool}
     */
    function profile_update_secure(PDO $pdo, TrainingEngine $engine, int $currentUserId, array $in): array
    {
        // Strict allow-list. role / is_locked / daily_limit can NEVER be set.
        // @fix-start: mass assignment — strict field allow-list (profile.l5)
        $allowed = ['name', 'email', 'phone', 'address'];
        // @fix-end

        $set = [];
        $bind = [':id' => $currentUserId];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $in)) {
                continue;
            }
            $value = trim((string) $in[$col]);

            // Validate per field.
            if ($col === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'Invalid email address.', 'escalated' => false];
            }
            if ($col === 'phone' && $value !== '' && !preg_match('/^\+?[0-9]{10,15}$/', $value)) {
                return ['ok' => false, 'error' => 'Invalid phone number.', 'escalated' => false];
            }
            if (mb_strlen($value) > 255) {
                return ['ok' => false, 'error' => ucfirst($col) . ' is too long.', 'escalated' => false];
            }

            $set[] = "$col = :$col";
            $bind[":$col"] = $value;
        }

        if ($set !== []) {
            $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id')
                ->execute($bind);
        }

        return ['ok' => true, 'error' => null, 'escalated' => false];
    }
}
