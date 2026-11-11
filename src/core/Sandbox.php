<?php

declare(strict_types=1);

/**
 * Sandbox — A portable fake-shell interpreter for the command injection lab.
 *
 * Instead of passing user-controlled input to a real OS shell, this class
 * parses the command string and executes a limited set of whitelisted
 * commands internally using PHP functions. The "filesystem" is confined
 * to a single sandbox directory (default: /tmp on Unix, sys_get_temp_dir()
 * on Windows).
 *
 * Why?
 *   - No real shell = no shell escape techniques can bypass the sandbox.
 *   - 100% portable (macOS, Linux, Windows) — pure PHP, no OS-specific APIs.
 *   - The CTF exploit still works: `; cat /tmp/flag_g9.txt` returns the flag.
 *   - But `; env`, `; cat /path/to/Flags.php` are blocked — no flag leakage.
 *
 * Supported commands: cat, ls, pwd, id, whoami, echo, head, tail, wc, uname, hostname
 * Everything else returns: "sh: <cmd>: command not found"
 */
final class Sandbox
{
    /** @var string The confined directory (absolute path, no trailing slash) */
    private string $root;

    /** @var int The user ID — used for id/whoami AND to scope flag file visibility */
    private int $uid;

    /**
     * Pattern for per-user flag files. Files matching this with a different
     * user ID are invisible (ls) / access-denied (cat, head, tail, wc).
     */
    private const FLAG_FILE_PATTERN = '/^flag_g9_(\d+)\.txt$/';

    public function __construct(?string $root = null, int $uid = 33)
    {
        $this->root = $root ?? self::defaultRoot();
        $this->uid  = $uid;
    }

    /**
     * Check if a filename is a flag file belonging to another user.
     * Returns true if the file should be HIDDEN/BLOCKED from the current user.
     */
    private function isOtherUserFlagFile(string $basename): bool
    {
        if (preg_match(self::FLAG_FILE_PATTERN, $basename, $m)) {
            return (int) $m[1] !== $this->uid;
        }
        return false;
    }

    /**
     * Execute a full command string (may contain shell metacharacters).
     *
     * Splits on ; && || | and executes each sub-command independently.
     * Returns combined output, similar to shell_exec() behaviour.
     */
    public function exec(string $cmdLine): string
    {
        // Strip trailing 2>&1 (we combine output anyway)
        $cmdLine = preg_replace('/\s*2>&1\s*$/', '', $cmdLine);

        // Split on shell metacharacters: ; && || |
        // We use a simple split — this is a simulated shell, not a parser.
        $segments = preg_split('/\s*(?:;|&&|\|\||\|)\s*/', $cmdLine);
        $output = '';

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') continue;
            $output .= $this->execSingle($segment);
        }

        return $output;
    }

    /**
     * Execute a single command (no metacharacters).
     */
    private function execSingle(string $cmd): string
    {
        // Parse: command name + arguments
        // Handle basic quoting (single/double)
        $parts = self::tokenize($cmd);
        if (empty($parts)) return '';

        $binary = strtolower(basename($parts[0]));
        $args   = array_slice($parts, 1);

        return match ($binary) {
            'cat'      => $this->cmdCat($args),
            'ls'       => $this->cmdLs($args),
            'dir'      => $this->cmdLs($args),  // Windows compat
            'pwd'      => $this->cmdPwd(),
            'cd'       => $this->cmdPwd(),      // cd in a subshell has no effect
            'id'       => $this->cmdId(),
            'whoami'   => $this->cmdWhoami(),
            'echo'     => $this->cmdEcho($args),
            'head'     => $this->cmdHead($args),
            'tail'     => $this->cmdTail($args),
            'wc'       => $this->cmdWc($args),
            'uname'    => $this->cmdUname($args),
            'hostname' => $this->cmdHostname(),
            'env', 'printenv', 'set' => $this->cmdEnv(),
            'wkhtmltopdf' => $this->cmdWkhtmltopdf($args),
            default    => "sh: {$binary}: command not found\n",
        };
    }

    // -------------------------------------------------------------------------
    // Command implementations
    // -------------------------------------------------------------------------

    private function cmdCat(array $args): string
    {
        if (empty($args)) return "cat: missing operand\n";

        $output = '';
        foreach ($args as $path) {
            if (str_starts_with($path, '-')) continue; // skip flags like -n

            $resolved = $this->resolvePath($path);
            if ($resolved === null) {
                $output .= "cat: {$path}: Permission denied\n";
                continue;
            }
            if ($this->isOtherUserFlagFile(basename($resolved))) {
                $output .= "cat: {$path}: Permission denied\n";
                continue;
            }
            if (!is_file($resolved)) {
                $output .= "cat: {$path}: No such file or directory\n";
                continue;
            }
            $output .= (string) @file_get_contents($resolved);
        }
        return $output;
    }

    private function cmdLs(array $args): string
    {
        // Determine target directory
        $target = null;
        $showAll = false;
        $longFormat = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '-')) {
                if (str_contains($arg, 'a')) $showAll = true;
                if (str_contains($arg, 'l')) $longFormat = true;
            } else {
                $target = $arg;
            }
        }

        $dir = $target !== null ? $this->resolvePath($target) : $this->root;
        if ($dir === null || !is_dir($dir)) {
            $display = $target ?? $this->root;
            return "ls: cannot access '{$display}': Permission denied\n";
        }

        $entries = @scandir($dir) ?: [];
        $lines = [];
        foreach ($entries as $entry) {
            if (!$showAll && str_starts_with($entry, '.')) continue;
            if ($this->isOtherUserFlagFile($entry)) continue;
            if ($longFormat) {
                $full = $dir . DIRECTORY_SEPARATOR . $entry;
                $size = is_file($full) ? filesize($full) : 0;
                $type = is_dir($full) ? 'd' : '-';
                $lines[] = "{$type}rw-r--r-- 1 www-data www-data " . str_pad((string) $size, 6, ' ', STR_PAD_LEFT) . " {$entry}";
            } else {
                $lines[] = $entry;
            }
        }
        return implode("\n", $lines) . "\n";
    }

    private function cmdPwd(): string
    {
        return $this->root . "\n";
    }

    private function cmdId(): string
    {
        return "uid={$this->uid}(www-data) gid={$this->uid}(www-data) groups={$this->uid}(www-data)\n";
    }

    private function cmdWhoami(): string
    {
        return "www-data\n";
    }

    private function cmdEcho(array $args): string
    {
        // Simulate echo — expand $VAR references to empty (env is sanitized)
        $line = implode(' ', $args);
        // Replace $ANYTHING or ${ANYTHING} with empty string
        $line = preg_replace('/\$\{?[A-Za-z_][A-Za-z0-9_]*\}?/', '', $line);
        return $line . "\n";
    }

    private function cmdHead(array $args): string
    {
        $lines = 10;
        $files = [];
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '-n' && isset($args[$i + 1])) {
                $lines = max(1, (int) $args[++$i]);
            } elseif (preg_match('/^-(\d+)$/', $args[$i], $m)) {
                $lines = max(1, (int) $m[1]);
            } elseif (!str_starts_with($args[$i], '-')) {
                $files[] = $args[$i];
            }
        }

        if (empty($files)) return "head: missing operand\n";

        $output = '';
        foreach ($files as $path) {
            $resolved = $this->resolvePath($path);
            if ($resolved === null) {
                $output .= "head: {$path}: Permission denied\n";
                continue;
            }
            if ($this->isOtherUserFlagFile(basename($resolved))) {
                $output .= "head: {$path}: Permission denied\n";
                continue;
            }
            if (!is_file($resolved)) {
                $output .= "head: {$path}: No such file or directory\n";
                continue;
            }
            $content = @file($resolved, FILE_IGNORE_NEW_LINES) ?: [];
            $output .= implode("\n", array_slice($content, 0, $lines)) . "\n";
        }
        return $output;
    }

    private function cmdTail(array $args): string
    {
        $lines = 10;
        $files = [];
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '-n' && isset($args[$i + 1])) {
                $lines = max(1, (int) $args[++$i]);
            } elseif (preg_match('/^-(\d+)$/', $args[$i], $m)) {
                $lines = max(1, (int) $m[1]);
            } elseif (!str_starts_with($args[$i], '-')) {
                $files[] = $args[$i];
            }
        }

        if (empty($files)) return "tail: missing operand\n";

        $output = '';
        foreach ($files as $path) {
            $resolved = $this->resolvePath($path);
            if ($resolved === null) {
                $output .= "tail: {$path}: Permission denied\n";
                continue;
            }
            if ($this->isOtherUserFlagFile(basename($resolved))) {
                $output .= "tail: {$path}: Permission denied\n";
                continue;
            }
            if (!is_file($resolved)) {
                $output .= "tail: {$path}: No such file or directory\n";
                continue;
            }
            $content = @file($resolved, FILE_IGNORE_NEW_LINES) ?: [];
            $output .= implode("\n", array_slice($content, -$lines)) . "\n";
        }
        return $output;
    }

    private function cmdWc(array $args): string
    {
        $files = array_filter($args, fn($a) => !str_starts_with($a, '-'));
        if (empty($files)) return "wc: missing operand\n";

        $output = '';
        foreach ($files as $path) {
            $resolved = $this->resolvePath($path);
            if ($resolved === null) {
                $output .= "wc: {$path}: Permission denied\n";
                continue;
            }
            if ($this->isOtherUserFlagFile(basename($resolved))) {
                $output .= "wc: {$path}: Permission denied\n";
                continue;
            }
            if (!is_file($resolved)) {
                $output .= "wc: {$path}: No such file or directory\n";
                continue;
            }
            $content = (string) @file_get_contents($resolved);
            $lineCount = substr_count($content, "\n");
            $wordCount = str_word_count($content);
            $byteCount = strlen($content);
            $output .= "  {$lineCount}  {$wordCount} {$byteCount} {$path}\n";
        }
        return $output;
    }

    private function cmdUname(array $args): string
    {
        $all = in_array('-a', $args, true);
        if (PHP_OS_FAMILY === 'Windows') {
            return $all
                ? "Windows banksploit 10.0 x86_64 Windows_NT\n"
                : "Windows\n";
        }
        return $all
            ? "Linux banksploit 5.15.0-generic #1 SMP x86_64 GNU/Linux\n"
            : "Linux\n";
    }

    private function cmdHostname(): string
    {
        return "banksploit\n";
    }

    /**
     * env / printenv — returns empty (secrets are not exposed).
     */
    private function cmdEnv(): string
    {
        // Only expose safe, non-secret variables
        if (PHP_OS_FAMILY === 'Windows') {
            return "PWD={$this->root}\nHOMEDRIVE=C:\\\nHOMEPATH=\\Users\\www\nTERM=dumb\nPATH=C:\\Windows\\System32;C:\\Windows\n";
        }
        return "PWD={$this->root}\nHOME=/tmp\nTERM=dumb\nPATH=/usr/local/bin:/usr/bin:/bin\n";
    }

    /**
     * wkhtmltopdf stub — simulates the PDF converter.
     */
    private function cmdWkhtmltopdf(array $args): string
    {
        $outFile = end($args) ?: 'statement.pdf';
        // Create an empty file if the path is within sandbox
        $resolved = $this->resolvePath($outFile);
        if ($resolved !== null) {
            @file_put_contents($resolved, '');
        }
        return "Generated PDF: {$outFile}\n";
    }

    // -------------------------------------------------------------------------
    // Path resolution & confinement
    // -------------------------------------------------------------------------

    /**
     * Resolve a path relative to the sandbox root.
     * Returns null if the resolved path escapes the sandbox.
     */
    private function resolvePath(string $path): ?string
    {
        // On Windows: map Unix /tmp paths to the Windows temp directory
        if (PHP_OS_FAMILY === 'Windows') {
            if ($path === '/tmp') {
                $path = $this->root;
            } elseif (str_starts_with($path, '/tmp/') || str_starts_with($path, '/tmp\\')) {
                $path = $this->root . DIRECTORY_SEPARATOR . substr($path, 5);
            }
        }

        // Absolute path: must be within the sandbox root
        if ($this->isAbsolute($path)) {
            $real = $this->normalize($path);
        } else {
            // Relative to sandbox root
            $real = $this->normalize($this->root . DIRECTORY_SEPARATOR . $path);
        }

        // Confinement check: must start with the sandbox root
        if (!str_starts_with($real, $this->root)) {
            return null;
        }

        return $real;
    }

    /**
     * Normalize a path — resolve . and .. without requiring the file to exist.
     * (realpath() returns false for non-existent files.)
     */
    private function normalize(string $path): string
    {
        // Normalize separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // On Windows, extract the drive letter first if present
        $drive = '';
        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]):/', $path, $m)) {
            $drive = strtoupper($m[1]) . ':';
            $path = substr($path, 2);  // Remove drive from path
        }

        $parts  = explode(DIRECTORY_SEPARATOR, $path);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($result);
            } else {
                $result[] = $part;
            }
        }

        // Reconstruct path
        if ($drive !== '') {
            // Windows with drive letter: C:\Users\...
            $normalized = $drive . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $result);
        } else {
            // Unix: /Users/...
            $normalized = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $result);
        }

        return $normalized;
    }

    private function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) return true;
        // Windows: C:\ or C:/
        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) return true;
        return false;
    }

    // -------------------------------------------------------------------------
    // Tokenizer
    // -------------------------------------------------------------------------

    /**
     * Split a command string into tokens, respecting single/double quotes.
     *
     * @return list<string>
     */
    private static function tokenize(string $cmd): array
    {
        $tokens = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($cmd);

        for ($i = 0; $i < $len; $i++) {
            $ch = $cmd[$i];

            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
            } elseif ($ch === ' ' && !$inSingle && !$inDouble) {
                if ($current !== '') {
                    $tokens[] = $current;
                    $current = '';
                }
            } else {
                $current .= $ch;
            }
        }
        if ($current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    private static function defaultRoot(): string
    {
        // Use sys_get_temp_dir() on all platforms so the sandbox root matches
        // where flag files are written (on macOS sys_get_temp_dir() returns
        // /var/folders/.../T/ which differs from /tmp).
        return rtrim(sys_get_temp_dir(), '\\/');
    }
}
