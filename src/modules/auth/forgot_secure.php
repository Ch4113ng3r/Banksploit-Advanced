<?php

declare(strict_types=1);

/**
 * SECURE forgot-password (level 3).
 *
 * @param array<string,mixed> $in
 * @return array{ok:bool,message:string,token:?string,enumerated:bool}
 *
 * ============================================================================
 *  SECURE CONTROLS
 * ----------------------------------------------------------------------------
 *  - Cryptographically random token bin2hex(random_bytes(32)) via
 *    auth_make_reset_token() (level >= 2 path).
 *  - IDENTICAL response whether or not the email exists (no enumeration).
 *  - Token is NEVER returned in the response; it would be emailed out of band.
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('auth_forgot_secure')) {

    function auth_forgot_secure(PDO $pdo, TrainingEngine $engine, array $in): array
    {
        $email = trim((string) ($in['email'] ?? ''));

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $userId = $stmt->fetchColumn();

        if ($userId !== false) {
            // Issue a random token and (in real life) email it. Never returned.
            auth_make_reset_token($pdo, $engine, (int) $userId);
        }

        // Same response either way — no oracle.
        return [
            'ok'         => true,
            'message'    => 'If that email is registered, a reset link has been sent.',
            'token'      => null,
            'enumerated' => false,
        ];
    }
}
