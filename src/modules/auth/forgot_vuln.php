<?php

declare(strict_types=1);

/**
 * VULNERABLE forgot-password (levels 0-2).
 *
 * @param array<string,mixed> $in
 * @return array{ok:bool,message:string,token:?string,enumerated:bool}
 *
 * ============================================================================
 *  VULNERABILITIES
 * ----------------------------------------------------------------------------
 *  auth.l13 Predictable reset tokens (CWE-340):
 *           token = base64(user_id:timestamp) at level 0 — guessable/forgeable.
 *           Issued by auth_make_reset_token() in _shared.php.
 *  auth.l16 Account enumeration (CWE-203):
 *           response differs for known vs unknown email, and (at vuln levels)
 *           the token is even returned in the response for "dev convenience".
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('auth_forgot_vuln')) {

    function auth_forgot_vuln(PDO $pdo, TrainingEngine $engine, array $in): array
    {
        $email = trim((string) ($in['email'] ?? ''));

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $userId = $stmt->fetchColumn();

        // auth.l16: leak whether the email exists.
        if ($userId === false) {
            return [
                'ok'         => false,
                'message'    => 'No account with that email.',
                'token'      => null,
                'enumerated' => true,
            ];
        }

        // auth.l13: predictable token (base64 of user_id:timestamp at level 0).
        $token = auth_make_reset_token($pdo, $engine, (int) $userId);

        return [
            'ok'         => true,
            // Token echoed back ("for dev") — combines with predictability.
            'message'    => 'Reset link generated.',
            'token'      => $token,
            'enumerated' => true,
        ];
    }
}
