<?php

declare(strict_types=1);

/**
 * SECURE login credential check (level 3).
 *
 * @return array{ok:bool,user:?array<string,mixed>,error:?string,locked:bool}
 *
 * ============================================================================
 *  SECURE CONTROLS (contrast with login_vuln.php)
 * ----------------------------------------------------------------------------
 *  - Parameterized PDO query (no SQL injection).
 *  - password_verify() against the bcrypt password_hash (no MD5).
 *  - Rate limiting: lock the account for 15 minutes after 5 failures.
 *  - Generic error "Invalid credentials" (no account enumeration).
 *  - OTP is sent (simulated) but NEVER returned in the response (caller).
 *  - Session is regenerated on success (handled by Session::onLogin()).
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('auth_login_secure')) {

    function auth_login_secure(PDO $pdo, TrainingEngine $engine, string $customerId, string $password): array
    {
        // Look the user up first (parameterized) so we can rate-limit per user.
        // @fix-start: SQL injection in login (auth.g1)
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, name, role, password_hash, is_locked, locked_until
               FROM users WHERE customer_id = :cid LIMIT 1'
        );
        $stmt->execute([':cid' => $customerId]);
        $row = $stmt->fetch();
        // @fix-end

        // Rate-limit lockout check (only meaningful for a real account, but we
        // return the same generic message either way to avoid enumeration).
        if ($row && (int) $row['is_locked'] === 1) {
            $until = $row['locked_until'];
            if ($until === null || strtotime((string) $until) > time()) {
                return [
                    'ok'     => false,
                    'user'   => null,
                    'error'  => 'Account temporarily locked. Try again later.',
                    'locked' => true,
                ];
            }
        }

        // @fix-start: bcrypt password verification — weak-hash half (auth.g1)
        if ($row && password_verify($password, (string) $row['password_hash'])) {
            auth_reset_failures($pdo, (int) $row['id']);
            unset($row['password_hash'], $row['is_locked'], $row['locked_until']);
            return ['ok' => true, 'user' => $row, 'error' => null, 'locked' => false];
        }
        // @fix-end

        // Failed: count it against the account (if it exists) and lock at 5.
        if ($row) {
            auth_register_failure($pdo, (int) $row['id']);
        }

        // Generic message regardless of which factor failed (no enumeration).
        return [
            'ok'     => false,
            'user'   => null,
            'error'  => 'Invalid credentials.',
            'locked' => false,
        ];
    }
}
