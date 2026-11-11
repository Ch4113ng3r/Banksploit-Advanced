<?php

declare(strict_types=1);

/**
 * Modes — CTF / Training / Pentest run modes.
 *
 * A mode is a named preset over the feature_toggles table. The toggles stay the
 * real state the app enforces; a mode sets them all at once. When the live
 * toggles don't match any preset, the mode reads as "custom". The chosen label
 * is stored in system_config.app_mode (display only; detect() is the truth).
 *
 * This class is intentionally dependency-free (just a PDO) so both the CTF app
 * (via helpers.php) and the isolated instructor portal can use it.
 */
final class Modes
{
    /** @return array<string,array<string,bool>> mode => (feature_key => enabled) */
    public static function presets(): array
    {
        return [
            // CTF event: scoring + leaderboard on, teaching aids off. Hints are
            // OFF by default (optional) — an instructor can enable them, and when
            // on they only show for the vulnerabilities selected into the event.
            'ctf' => [
                'training_lab'    => true,
                'leaderboard'     => true,
                'flag_submission' => true,
                'flag_delivery'   => true,
                'hints'           => false,
                'code_viewer'     => false,
                'code_inspector'  => true,
                'swagger_docs'    => true,
                'otp_display'     => false,
                'request_logging' => true,
                'debug'           => false,
            ],
            // Teaching: the open code viewer (vulnerable vs secure) is the aid.
            // Hints are OFF by default — they are a CTF concept, shown only for
            // the vulnerabilities selected into the event (an instructor can still
            // custom-enable hints here if they want them). No flag capture/scoring.
            'training' => [
                'training_lab'    => true,
                'leaderboard'     => false,
                'flag_submission' => false,
                'flag_delivery'   => false,
                'hints'           => false,
                'code_viewer'     => true,
                'code_inspector'  => true,
                'swagger_docs'    => true,
                'otp_display'     => true,
                'request_logging' => true,
                'debug'           => false,
            ],
            // Pentest: plain banking app — every CTF/teaching aid hidden.
            'pentest' => [
                'training_lab'    => false,
                'leaderboard'     => false,
                'flag_submission' => false,
                'flag_delivery'   => false,
                'hints'           => false,
                'code_viewer'     => false,
                'code_inspector'  => false,
                'swagger_docs'    => true,
                'otp_display'     => false,
                'request_logging' => true,
                'debug'           => false,
            ],
        ];
    }

    /** @return array<string,string> */
    public static function labels(): array
    {
        return ['ctf' => 'CTF', 'training' => 'Training', 'pentest' => 'Pentest', 'custom' => 'Custom'];
    }

    /** One-line summary of each mode, for the panel. @return array<string,string> */
    public static function descriptions(): array
    {
        return [
            'ctf'      => 'Competitive event: flags, scoring and leaderboard on. Hints optional (off by default).',
            'training' => 'Teaching: code viewer and OTP display on; flags, leaderboard and hints off.',
            'pentest'  => 'Plain banking app: API docs available, but no flags, hints, leaderboard, lab or inspector.',
        ];
    }

    public static function isEnabled(PDO $pdo, string $key): bool
    {
        $stmt = $pdo->prepare('SELECT is_enabled FROM feature_toggles WHERE feature_key = :k');
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v === false ? true : (bool) $v; // missing defaults to enabled
    }

    public static function setEnabled(PDO $pdo, string $key, bool $enabled): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO feature_toggles (feature_key, is_enabled, description)
             VALUES (:k, :e, :desc)
             ON DUPLICATE KEY UPDATE is_enabled = :e2'
        );
        $stmt->execute([':k' => $key, ':e' => $enabled ? 1 : 0, ':desc' => $key, ':e2' => $enabled ? 1 : 0]);
    }

    /** Apply a preset: write every toggle it names, then store the label. */
    public static function apply(PDO $pdo, string $mode): bool
    {
        $presets = self::presets();
        if (!isset($presets[$mode])) {
            return false;
        }
        foreach ($presets[$mode] as $key => $enabled) {
            self::setEnabled($pdo, $key, $enabled);
        }
        self::set($pdo, $mode);
        return true;
    }

    /** The live mode computed from the toggles: a preset key, or 'custom'. */
    public static function detect(PDO $pdo): string
    {
        foreach (self::presets() as $mode => $preset) {
            $match = true;
            foreach ($preset as $key => $enabled) {
                if (self::isEnabled($pdo, $key) !== $enabled) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $mode;
            }
        }
        return 'custom';
    }

    public static function get(PDO $pdo): string
    {
        $stmt = $pdo->prepare('SELECT config_value FROM system_config WHERE config_key = :k');
        $stmt->execute([':k' => 'app_mode']);
        $v = $stmt->fetchColumn();
        return is_string($v) && $v !== '' ? $v : 'custom';
    }

    public static function set(PDO $pdo, string $mode): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO system_config (config_key, config_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE config_value = :v2'
        );
        $stmt->execute([':k' => 'app_mode', ':v' => $mode, ':v2' => $mode]);
    }
}
