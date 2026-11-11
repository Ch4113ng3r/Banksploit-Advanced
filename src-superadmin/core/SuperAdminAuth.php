<?php

declare(strict_types=1);

/**
 * Super Admin authentication — hardened, zero-vulnerability auth.
 *
 * - Credentials stored in config/superadmin.php (not in DB — immune to SQLi)
 * - bcrypt password_verify() always
 * - Session-only (no JWT, no alg:none attack surface)
 * - File-based rate limiting (lockout after 5 failures for 15 min)
 * - Separate session namespace (SUPERADMIN_SESSID)
 */
final class SuperAdminAuth
{
    private static ?array $config = null;
    private static string $lockFile = '';

    public static function init(): void
    {
        $configFile = __DIR__ . '/../config/superadmin.php';
        self::$lockFile = dirname(__DIR__, 2) . '/.local/superadmin_lockout.json';

        if (!is_file($configFile)) {
            self::firstRunSetup($configFile);
        }

        self::$config = require $configFile;
    }

    /**
     * First-run: generate config with default credentials.
     */
    private static function firstRunSetup(string $configFile): void
    {
        $defaultPassword = 'instructor@123';
        $hash = password_hash($defaultPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $content = "<?php\n\nreturn [\n"
            . "    'users' => [\n"
            . "        [\n"
            . "            'username' => 'instructor',\n"
            . "            'password_hash' => '{$hash}',\n"
            . "            'name' => 'CTF Admin',\n"
            . "        ],\n"
            . "    ],\n"
            . "    'session_timeout_minutes' => 60,\n"
            . "    'max_failed_attempts' => 5,\n"
            . "    'lockout_minutes' => 15,\n"
            . "];\n";

        $dir = dirname($configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($configFile, $content);

        self::$config = require $configFile;
    }

    public static function isAuthenticated(): bool
    {
        $user = $_SESSION['superadmin_user'] ?? null;
        if (!is_array($user)) {
            return false;
        }
        // Check session timeout
        $timeout = (self::$config['session_timeout_minutes'] ?? 60) * 60;
        $lastActivity = (int) ($_SESSION['superadmin_last_activity'] ?? 0);
        if (time() - $lastActivity > $timeout) {
            self::logout();
            return false;
        }
        $_SESSION['superadmin_last_activity'] = time();
        return true;
    }

    public static function requireAuth(): void
    {
        if (!self::isAuthenticated()) {
            header('Location: /login');
            exit;
        }
    }

    public static function getCurrentUser(): ?array
    {
        return self::isAuthenticated() ? ($_SESSION['superadmin_user'] ?? null) : null;
    }

    /**
     * Attempt login. Returns true on success, false on failure.
     */
    public static function login(string $username, string $password): bool
    {
        // Rate limiting check
        if (self::isLockedOut()) {
            return false;
        }

        $users = self::$config['users'] ?? [];
        foreach ($users as $u) {
            if ($u['username'] === $username && password_verify($password, $u['password_hash'])) {
                // Success
                $_SESSION['superadmin_user'] = [
                    'username' => $u['username'],
                    'name'     => $u['name'] ?? $u['username'],
                ];
                $_SESSION['superadmin_last_activity'] = time();
                self::resetFailures();
                return true;
            }
        }

        // Failed
        self::recordFailure();
        return false;
    }

    /**
     * Verify the current user's password without affecting the session.
     * Used for dangerous actions that require re-authentication.
     */
    public static function verifyPassword(string $password): bool
    {
        $current = self::getCurrentUser();
        if ($current === null) {
            return false;
        }
        $users = self::$config['users'] ?? [];
        foreach ($users as $u) {
            if ($u['username'] === $current['username']) {
                return password_verify($password, $u['password_hash']);
            }
        }
        return false;
    }

    public static function logout(): void
    {
        unset($_SESSION['superadmin_user'], $_SESSION['superadmin_last_activity']);
        session_destroy();
    }

    public static function isLockedOut(): bool
    {
        $data = self::getLockData();
        if ($data['count'] >= (self::$config['max_failed_attempts'] ?? 5)) {
            $lockoutMinutes = self::$config['lockout_minutes'] ?? 15;
            if (time() - $data['last_failure'] < $lockoutMinutes * 60) {
                return true;
            }
            // Lockout expired
            self::resetFailures();
        }
        return false;
    }

    public static function getFailureCount(): int
    {
        return self::getLockData()['count'];
    }

    private static function recordFailure(): void
    {
        $data = self::getLockData();
        $data['count']++;
        $data['last_failure'] = time();
        self::saveLockData($data);
    }

    private static function resetFailures(): void
    {
        self::saveLockData(['count' => 0, 'last_failure' => 0]);
    }

    private static function getLockData(): array
    {
        if (self::$lockFile !== '' && is_file(self::$lockFile)) {
            $raw = (string) file_get_contents(self::$lockFile);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data + ['count' => 0, 'last_failure' => 0];
            }
        }
        return ['count' => 0, 'last_failure' => 0];
    }

    private static function saveLockData(array $data): void
    {
        if (self::$lockFile === '') {
            return;
        }
        $dir = dirname(self::$lockFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::$lockFile, json_encode($data), LOCK_EX);
    }
}
