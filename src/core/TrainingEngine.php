<?php

declare(strict_types=1);

/**
 * TrainingEngine — central registry of vulnerability levels.
 *
 * Loads src/config/vuln_config.json once per request (singleton) and lets the
 * rest of the framework branch between insecure (level < 3) and secure
 * (level 3) behaviour. Levels: 0 fully vulnerable, 1 weak mitigation,
 * 2 partial fix, 3 secure.
 *
 * Config shape: { "modules": { "<module>": { "<vuln>": { "level": int, ... } } } }
 * Vuln keys are the spec IDs lowercased (g1..g12, l1..l24).
 */
final class TrainingEngine
{
    private static ?TrainingEngine $instance = null;

    private string $configPath;

    /** @var array<string, mixed> Decoded config. */
    private array $config;

    private function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? (__DIR__ . '/../config/vuln_config.json');
        $this->load();
    }

    /**
     * Get (or lazily create) the singleton. The path is only honoured on the
     * first call.
     */
    public static function getInstance(?string $configPath = null): TrainingEngine
    {
        if (self::$instance === null) {
            self::$instance = new self($configPath);
        }
        return self::$instance;
    }

    private function load(): void
    {
        $raw = is_file($this->configPath) ? file_get_contents($this->configPath) : false;
        if ($raw === false) {
            $this->config = ['modules' => []];
            return;
        }
        $decoded = json_decode($raw, true);
        $this->config = is_array($decoded) ? $decoded : ['modules' => []];
        if (!isset($this->config['modules']) || !is_array($this->config['modules'])) {
            $this->config['modules'] = [];
        }
    }

    /**
     * @return array<string, mixed>|null The raw vuln entry, or null if unknown.
     */
    private function entry(string $module, string $vuln): ?array
    {
        $module = strtolower($module);
        $vuln   = strtolower($vuln);
        $e = $this->config['modules'][$module][$vuln] ?? null;
        return is_array($e) ? $e : null;
    }

    /**
     * Current level for a vuln (0-3). Unknown vulns default to 0 (vulnerable)
     * so a missing config entry fails open for training, never silently
     * "secure".
     */
    public function getVulnLevel(string $module, string $vuln): int
    {
        $e = $this->entry($module, $vuln);
        return $e !== null ? (int) ($e['level'] ?? 0) : 0;
    }

    /**
     * True while the vuln is still exploitable (level < 3).
     */
    public function isVulnerable(string $module, string $vuln): bool
    {
        return $this->getVulnLevel($module, $vuln) < 3;
    }

    /**
     * Resolve the handler file for a module/action, choosing the secure variant
     * once the governing vuln reaches level 3.
     *
     * Convention (relative to the document root, as Router expects):
     *   vulnerable -> modules/<module>/<action>.php
     *   secure     -> modules/<module>/<action>_secure.php  (if it exists)
     *
     * The governing level is the vuln whose key == $action when present,
     * otherwise the lowest (most vulnerable) level among the module's vulns.
     */
    public function getHandler(string $module, string $action): string
    {
        $module = strtolower($module);
        $action = strtolower($action);
        $base   = "modules/{$module}/{$action}";

        $level = $this->resolveActionLevel($module, $action);

        if ($level >= 3) {
            $secureRel  = $base . '_secure.php';
            $secureFull = __DIR__ . '/../' . $secureRel;
            if (is_file($secureFull)) {
                return $secureRel;
            }
        }
        return $base . '.php';
    }

    private function resolveActionLevel(string $module, string $action): int
    {
        $e = $this->entry($module, $action);
        if ($e !== null) {
            return (int) ($e['level'] ?? 0);
        }
        $vulns = $this->config['modules'][$module] ?? [];
        if (!is_array($vulns) || $vulns === []) {
            return 0;
        }
        $min = 3;
        foreach ($vulns as $v) {
            if (is_array($v)) {
                $min = min($min, (int) ($v['level'] ?? 0));
            }
        }
        return $min;
    }

    /**
     * Full config (for the admin panel). Returns a copy.
     *
     * @return array<string, mixed>
     */
    public function getAllVulns(): array
    {
        return $this->config;
    }

    /**
     * Update a vuln's level (clamped 0-3) and persist to disk atomically.
     * Re-reads the file first so a concurrent admin change isn't clobbered.
     */
    public function setVulnLevel(string $module, string $vuln, int $level): void
    {
        $module = strtolower($module);
        $vuln   = strtolower($vuln);
        $level  = max(0, min(3, $level));

        // Re-load so we merge onto the freshest on-disk state.
        $this->load();

        if (!isset($this->config['modules'][$module][$vuln])
            || !is_array($this->config['modules'][$module][$vuln])) {
            // Unknown vuln: create a minimal entry rather than throwing.
            $this->config['modules'][$module][$vuln] = ['id' => strtoupper($vuln)];
        }
        $this->config['modules'][$module][$vuln]['level'] = $level;

        $json = json_encode(
            $this->config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($json === false) {
            throw new RuntimeException('Failed to encode vuln config: ' . json_last_error_msg());
        }

        // Atomic-ish write with an exclusive lock (LF line endings).
        $json = str_replace("\r\n", "\n", $json) . "\n";
        if (file_put_contents($this->configPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write vuln config to ' . $this->configPath);
        }
    }

    /**
     * Whether a vuln is flagged as part of the current CTF event. Unknown or
     * unflagged vulns default to false (not in the event).
     */
    public function isCtfActive(string $module, string $vuln): bool
    {
        $e = $this->entry($module, $vuln);
        return $e !== null && !empty($e['ctf_active']);
    }

    /**
     * Mark a vuln as active/inactive for the CTF event and persist. Mirrors
     * setVulnLevel(): re-reads first so a concurrent admin change isn't lost.
     * This only records the selection; it does not change the vuln's level.
     */
    public function setCtfActive(string $module, string $vuln, bool $active): void
    {
        $module = strtolower($module);
        $vuln   = strtolower($vuln);

        $this->load();

        if (!isset($this->config['modules'][$module][$vuln])
            || !is_array($this->config['modules'][$module][$vuln])) {
            // Only known vulns can be selected for a CTF.
            return;
        }
        $this->config['modules'][$module][$vuln]['ctf_active'] = $active;

        $json = json_encode(
            $this->config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($json === false) {
            throw new RuntimeException('Failed to encode vuln config: ' . json_last_error_msg());
        }

        $json = str_replace("\r\n", "\n", $json) . "\n";
        if (file_put_contents($this->configPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write vuln config to ' . $this->configPath);
        }
    }
}
