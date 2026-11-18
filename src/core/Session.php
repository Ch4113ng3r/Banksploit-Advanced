<?php

declare(strict_types=1);

/**
 * Session — thin wrapper over PHP sessions with level-aware hardening.
 *
 * Session fixation is governed by auth.l14:
 *   level 0  -> start() does NOT regenerate the id on login (fixation possible).
 *   level 3  -> regenerate id on login + SameSite cookie.
 *
 * The session cookie's HttpOnly flag is governed SEPARATELY by the reflected-XSS
 * lab (statements.g4): off at g4 L0 so an XSS payload can steal the cookie via
 * document.cookie; on at g4 L1+ so cookie theft fails and the learner must
 * escalate (e.g. session-riding). auth.l14 does not touch HttpOnly.
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
            'secure'   => false,                       // app is plain HTTP in training
            // @vuln-start: [L0] session cookie left JS-readable so reflected XSS can steal it (statements.g4)
            // @fix-start: [L1-3] session cookie marked HttpOnly — document.cookie can't read it (statements.g4)
            // HttpOnly follows the reflected-XSS lab: g4 L0 -> readable (cookie
            // theft works); g4 L1+ -> HttpOnly (forces escalation). This is
            // independent of auth.l14, which owns id regeneration + SameSite.
            'httponly' => !self::xssCookieReadable(),
            // @vuln-end
            // @fix-end
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
     * Whether the reflected-XSS lab (statements.g4) is at its fully-vulnerable
     * level, where the session cookie is deliberately left JS-readable (no
     * HttpOnly) so an XSS payload can steal it. True only at g4 L0; at L1+ the
     * cookie is HttpOnly. Governs HttpOnly independently of auth.l14.
     */
    private static function xssCookieReadable(): bool
    {
        if (!class_exists('TrainingEngine')) {
            return false;
        }
        return TrainingEngine::getInstance()->getVulnLevel('statements', 'g4') === 0;
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
