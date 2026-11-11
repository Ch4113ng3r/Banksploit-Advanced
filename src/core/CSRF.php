<?php

declare(strict_types=1);

require_once __DIR__ . '/Session.php';

/**
 * CSRF — DISABLED for CTF.
 *
 * CSRF protection is not part of the CTF challenge set and was adding friction
 * to exploitation. All methods are now no-ops so existing call-sites don't
 * break but no token is ever generated or checked.
 */
final class CSRF
{
    private const SESSION_KEY = '_csrf_token';

    public static function generateToken(): string
    {
        return '';
    }

    public static function validateToken(?string $token): bool
    {
        return true;
    }

    public static function getHiddenField(): string
    {
        return '';
    }
}
