<?php

declare(strict_types=1);

/**
 * CTF Flag Generator — per-user, HMAC-based deterministic flags.
 *
 * Each (user_id, vuln_key) pair yields a unique flag that:
 *   - Is deterministic (same user submitting the same flag always validates).
 *   - Cannot be forged without the server-side secret.
 *   - Cannot be shared between users (different user → different flag).
 *   - Is never stored in the database (computed on-the-fly).
 *
 * Flag format: BANKSPLOIT{<12-char-hex-uppercase>}
 *
 * Usage:
 *   $flag = Flags::generate('auth.g1', $userId);
 *   // → "BANKSPLOIT{A8F3C21D9E0B}"
 *
 *   $match = Flags::validate($submittedFlag, $userId);
 *   // → ['valid' => true, 'vuln_key' => 'auth.g1'] or ['valid' => false]
 */
final class Flags
{
    private static ?string $secret = null;

    /**
     * The HMAC secret, read from ~/.banksploit/flag_secret.
     *
     * It is deliberately not an environment variable, because the core.l18
     * phpinfo() lab prints the whole environment. The file sits outside the
     * project, but the app's open file-read and code-execution labs (path
     * traversal, SSRF, XXE, file inclusion, upload, deserialization) can still
     * reach it, so reading the secret remains possible through those.
     *
     * No fallback: if the file is missing, the app fails loudly. The startup
     * scripts create it.
     */
    private static function secret(): string
    {
        if (self::$secret !== null) {
            return self::$secret;
        }

        $home = getenv('HOME') ?: getenv('USERPROFILE');
        $file = ($home ?: '') . DIRECTORY_SEPARATOR . '.banksploit' . DIRECTORY_SEPARATOR . 'flag_secret';
        $value = is_file($file) ? trim((string) file_get_contents($file)) : '';

        if ($value === '') {
            throw new \RuntimeException(
                'The flag secret file is missing or empty. '
                . 'Run ./start-app.sh start (or .\\start-app.ps1 start on Windows) to create it.'
            );
        }

        self::$secret = $value;
        return $value;
    }

    /**
     * Generate a per-user flag for a vulnerability.
     *
     * @param string $vulnKey  e.g. "auth.g1", "transfer.l1"
     * @param int    $userId   The logged-in user's id
     */
    public static function generate(string $vulnKey, int $userId): string
    {
        $hash = hash_hmac('sha256', "{$userId}:{$vulnKey}", self::secret());
        $short = strtoupper(substr($hash, 0, 12));
        return "BANKSPLOIT{{$short}}";
    }

    /**
     * Validate a submitted flag against ALL known vuln keys for a user.
     *
     * Loops through every vulnerability in the config and recomputes the
     * expected flag. Returns the matching vuln_key on success.
     *
     * @return array{valid:bool,vuln_key:?string}
     */
    public static function validate(string $submittedFlag, int $userId): array
    {
        $submittedFlag = trim($submittedFlag);

        // Quick format check
        if (!preg_match('/^BANKSPLOIT\{[A-F0-9]{12}\}$/', $submittedFlag)) {
            return ['valid' => false, 'vuln_key' => null];
        }

        // Check against every configured vuln
        $allVulns = engine()->getAllVulns();
        foreach ($allVulns['modules'] as $mod => $vulns) {
            foreach ($vulns as $vid => $v) {
                $key = $mod . '.' . strtolower($vid);
                $expected = self::generate($key, $userId);
                if (hash_equals($expected, $submittedFlag)) {
                    return ['valid' => true, 'vuln_key' => $key];
                }
            }
        }

        return ['valid' => false, 'vuln_key' => null];
    }

    /**
     * Points for capturing a vulnerability's flag.
     *
     * Each vuln has a `points` block in vuln_config.json keyed by level:
     * complexity sets the level-0 starting score (easy 100, medium 200,
     * hard 300) and each harder level adds more, because more mitigation has
     * to be bypassed. Level 3 is secure — no flag, 0 points.
     *
     * @param string   $vulnKey e.g. "auth.g1"
     * @param int|null $level   the level to price; null = the vuln's current
     *                          configured level (what a capture is worth now).
     */
    public static function points(string $vulnKey, ?int $level = null): int
    {
        [$mod, $vid] = array_pad(explode('.', $vulnKey, 2), 2, '');
        $entry = engine()->getAllVulns()['modules'][$mod][strtolower($vid)] ?? null;

        if (is_array($entry) && isset($entry['points']) && is_array($entry['points'])) {
            $lvl = $level ?? (int) ($entry['level'] ?? 0);
            if ($lvl >= 3) {
                return 0; // secure — nothing to capture
            }
            $p = $entry['points'];
            return (int) ($p[(string) $lvl] ?? $p[$lvl] ?? reset($p) ?? 0);
        }

        // Legacy fallback for a vuln with no points block.
        return match ($vulnKey) {
            'auth.l11'       => 100,
            'auth.g1', 'auth.l10', 'statements.l1' => 200,
            'transfer.l1', 'auth.l12', 'statements.g2', 'statements.g9' => 300,
            default          => 150,
        };
    }
}
