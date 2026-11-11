<?php

declare(strict_types=1);

/**
 * Session — thin wrapper over PHP sessions with level-aware hardening.
 *
 * Governed by auth.l14 (session fixation):
 *   level 0  -> start() does NOT regenerate the id on login; cookie has no
 *               httponly/secure flags (fixation possible).
 *   level 3  -> regenerate id on login, httponly + secure + SameSite cookie.
 *
 * All methods are static so modules can call Session::get(...) freely.
 */
final class Session
{
    /**
     * Start the session, applying cookie hardening based on the L14 level.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::isSecureLevel();

        // Cookie params must be set before session_start().
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            // At level 0 these protections are deliberately off.
            'secure'   => false,                       // app is plain HTTP in training
            'httponly' => $secure,
            // SameSite=None REQUIRES Secure, and browsers SILENTLY DROP a
            // None cookie over plain HTTP — which kills the session on
            // localhost. Vulnerable state = no SameSite attr (''); L3 = Lax.
            'samesite' => $secure ? 'Lax' : '',
        ]);

        session_start();
    }

    /**
     * Whether the session-fixation vuln (auth.l14) is fixed.
     */
    private static function isSecureLevel(): bool
    {
        if (!class_exists('TrainingEngine')) {
            return false;
        }
        return !TrainingEngine::getInstance()->isVulnerable('auth', 'l14');
    }

    /**
     * Called right after a successful login. At level 3 the id is regenerated
     * to defeat session fixation; at level 0 it is intentionally left intact.
     */
    public static function onLogin(): void
    {
        // @vuln-start: session fixation — id NOT regenerated on login at level 0 (auth.l14)
        // @fix-start: session fixation — regenerate the session id on login (auth.l14)
        if (self::isSecureLevel()) {
            self::regenerate();
        }
        // level 0: deliberately do nothing -> fixation.
        // @vuln-end
        // @fix-end
    }

    /**
     * Force a new session id, preserving data.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Release the session write-lock WITHOUT destroying the session.
     *
     * PHP holds an exclusive lock on the session file for the whole request,
     * which serialises concurrent requests that share a PHPSESSID. The race
     * condition in fund transfer (transfer.l7) needs two requests to overlap,
     * so the vulnerable path calls this to drop the lock before the
     * read-check-write — exactly the "session_write_close() too early" mistake
     * real apps make. Session data already set remains readable.
     */
    public static function unlock(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Full teardown: clear data, expire the cookie, destroy the session.
     */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $p['path'],
                $p['domain'],
                $p['secure'],
                $p['httponly']
            );
        }

        // Also clear the JWT access_token cookie (lab convenience feature)
        setcookie('access_token', '', time() - 42000, '/', '');

        session_destroy();
    }
}
