<?php

declare(strict_types=1);

/**
 * Sandbox — a portable emulated "honeypot" shell for the command-injection lab
 * (statements.g9, CWE-78).
 *
 * The injected command string is never handed to a real OS shell. This class
 * parses it and answers from an in-process **virtual filesystem (VFS)** plus a
 * single real directory (the sandbox tmp, presented as /tmp). Nothing is ever
 * executed, no socket is ever opened, and the real host filesystem is never
 * read outside the sandbox tmp.
 *
 * Design (inspired by SSH honeypots like Cowrie, re-implemented natively):
 *   - Broad, believable command set — recon, filesystem, network — so an RCE
 *     foothold *feels* real. The command list is a REALISM control, not a
 *     security boundary: confinement is enforced by the VFS + no-sockets +
 *     no-execution architecture, so adding commands never widens risk.
 *   - A fabricated Linux box (same persona on Windows/macOS/Linux). The VFS
 *     holds only invented, non-sensitive content (fake /etc/passwd, a fake
 *     .env with fake DB creds). Real secrets live outside tmp and are never
 *     touched.
 *   - Network commands (curl/wget/ping/nc/ssh/dig) are emulated with NO real
 *     socket: internal/metadata targets return canned fake data; external
 *     hosts fail realistically ("Could not resolve host") — no egress is
 *     possible.
 *   - The CTF exploit still works end to end: the per-user flag lives in the
 *     real /tmp as flag_g9_<uid>.txt, and `;cat /tmp/flag_g9_<uid>.txt;`
 *     returns it. Other users' flag files stay invisible / access-denied.
 *
 * Current working directory is caller-persisted: construct with the last cwd,
 * read getCwd() afterwards and store it (e.g. in the session) so navigation
 * feels continuous across requests.
 */
final class Sandbox
{
    /** @var string The real confined directory (absolute, no trailing slash), presented as /tmp. */
    private string $root;

    /** @var int The user ID — used for id/whoami AND to scope flag-file visibility. */
    private int $uid;

    /** @var string Current working directory, as a virtual absolute path. */
    private string $cwd;

    /** @var string www-data's home, used for ~ and `cd` with no argument. */
    private const HOME = '/var/www';

    /** @var string Default landing directory (the web root). */
    private const DEFAULT_CWD = '/var/www/html';

    /**
     * Per-user flag files. Files matching this with a different user ID are
     * invisible (ls) / access-denied (cat, head, tail, wc).
     */
    private const FLAG_FILE_PATTERN = '/^flag_g9_(\d+)\.txt$/';

    /** @var array<string,array<string,mixed>> Flat VFS: absolute vpath => node. */
    private array $vfs;

    public function __construct(?string $root = null, int $uid = 33, ?string $cwd = null)
    {
        $this->root = $root ?? self::defaultRoot();
        $this->uid  = $uid;
        $this->cwd  = $this->sanitizeCwd($cwd);
        $this->vfs  = $this->buildVfs();
    }

    /** The current working directory after exec() — persist this for the next request. */
    public function getCwd(): string
    {
        return $this->cwd;
    }

    private function sanitizeCwd(?string $cwd): string
    {
        if ($cwd === null || $cwd === '') {
            return self::DEFAULT_CWD;
        }
        $v = $this->virtualNormalize($cwd);
        return $v === '' ? self::DEFAULT_CWD : $v;
    }

    // -------------------------------------------------------------------------
    // Command-line execution
    // -------------------------------------------------------------------------

    /**
     * Execute a full command string (may contain shell metacharacters).
     *
     * Splits on ; && || | and newlines and runs each segment, mirroring a real
     * shell closely enough that an injected `;cmd;` chain behaves as expected.
     */
    public function exec(string $cmdLine): string
    {
        // Drop a trailing 2>&1 (output is already combined).
        $cmdLine = preg_replace('/\s*2>&1\s*$/', '', (string) $cmdLine);

        $units = $this->parseUnits((string) $cmdLine);
        $output = '';
        $stdin = null; // carries a pipeline's left-hand output into the next command
        foreach ($units as $u) {
            $cmd = trim($u['cmd']);
            if ($cmd === '') {
                if ($u['sep'] !== '|') { $stdin = null; }
                continue;
            }
            $res = $this->execSingle($cmd, $stdin);
            if ($u['sep'] === '|') {
                $stdin = $res;        // pipe onward, do not print
            } else {
                $output .= $res;      // end of pipeline → emit
                $stdin = null;
            }
        }
        return $output;
    }

    /**
     * Parse a command line into units with the separator that FOLLOWS each one.
     * Splits on ; && || | and newlines, but NOT inside single/double quotes (so
     * a webshell payload like `echo "<?php ...;" > f` stays one unit). The '|'
     * separator marks a pipe (left output becomes the next command's stdin).
     *
     * @return list<array{cmd:string,sep:string}>
     */
    private function parseUnits(string $cmdLine): array
    {
        $units = [];
        $cur = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($cmdLine);
        for ($i = 0; $i < $len; $i++) {
            $ch = $cmdLine[$i];
            if ($ch === "'" && !$inDouble) { $inSingle = !$inSingle; $cur .= $ch; continue; }
            if ($ch === '"' && !$inSingle) { $inDouble = !$inDouble; $cur .= $ch; continue; }
            if (!$inSingle && !$inDouble) {
                $two = substr($cmdLine, $i, 2);
                if ($two === '&&' || $two === '||') { $units[] = ['cmd' => $cur, 'sep' => $two]; $cur = ''; $i++; continue; }
                if ($ch === ';' || $ch === "\n" || $ch === "\r") { $units[] = ['cmd' => $cur, 'sep' => ';']; $cur = ''; continue; }
                if ($ch === '|') { $units[] = ['cmd' => $cur, 'sep' => '|']; $cur = ''; continue; }
            }
            $cur .= $ch;
        }
        $units[] = ['cmd' => $cur, 'sep' => ''];
        return $units;
    }

    /**
     * Minimal brace expansion: `{a,b,c}` -> `a b c`, honouring a word prefix/suffix
     * (`x{a,b}y` -> `xay xby`). One comma-list level, applied left-to-right. This
     * is the space-less bypass `{cat,/tmp/flag}` -> `cat /tmp/flag`.
     */
    private function expandBraces(string $s): string
    {
        $guard = 0;
        while ($guard++ < 16 && preg_match('/(\S*)\{([^{}]*,[^{}]*)\}(\S*)/', $s)) {
            $s = preg_replace_callback('/(\S*)\{([^{}]*,[^{}]*)\}(\S*)/', function (array $m): string {
                $parts = array_map(fn(string $it): string => $m[1] . $it . $m[3], explode(',', $m[2]));
                return implode(' ', $parts);
            }, $s, 1);
        }
        return $s;
    }

    /** Execute a single command (no metacharacters), honouring simple > / >> redirection and piped stdin. */
    private function execSingle(string $cmd, ?string $stdin = null): string
    {
        // Shell word-splitting tricks used to smuggle spaces past a filter that
        // strips whitespace — all real command-injection bypasses:
        //   $IFS / ${IFS}            -> a space (Internal Field Separator)
        //   $IFS$9, $@, $*, $1..$9   -> empty positional params (anchor so IFS
        //                               doesn't glom onto the following chars)
        //   {cat,/tmp/flag}          -> brace expansion to `cat /tmp/flag`
        $cmd = str_replace(['${IFS}', '$IFS'], ' ', $cmd);
        $cmd = preg_replace('/\$\{?[0-9@*]\}?/', '', $cmd);
        $cmd = $this->expandBraces($cmd);

        $parts = self::tokenize($cmd);
        if (empty($parts)) {
            return '';
        }

        // Pull out a trailing redirection: `... > file` / `... >> file`.
        $redirTarget = null;
        $append = false;
        foreach ($parts as $i => $tok) {
            if ($tok === '>' || $tok === '>>') {
                $append = ($tok === '>>');
                $redirTarget = $parts[$i + 1] ?? null;
                $parts = array_slice($parts, 0, $i);
                break;
            }
            if (str_starts_with($tok, '>>')) {
                $append = true;
                $redirTarget = substr($tok, 2);
                $parts = array_slice($parts, 0, $i);
                break;
            }
            if (str_starts_with($tok, '>')) {
                $redirTarget = substr($tok, 1);
                $parts = array_slice($parts, 0, $i);
                break;
            }
        }
        if (empty($parts)) {
            return '';
        }

        $binary = strtolower(basename($parts[0]));
        $args   = array_slice($parts, 1);
        $out    = $this->dispatch($binary, $args, $stdin);

        if ($redirTarget !== null && $redirTarget !== '') {
            return $this->redirect($redirTarget, $out, $append);
        }
        return $out;
    }

    private function dispatch(string $binary, array $args, ?string $stdin = null): string
    {
        return match ($binary) {
            // filesystem reads
            'cat'      => $this->cmdCat($args, $stdin),
            'head'     => $this->cmdHeadTail($args, true, $stdin),
            'tail'     => $this->cmdHeadTail($args, false, $stdin),
            'wc'       => $this->cmdWc($args, $stdin),
            'ls', 'dir' => $this->cmdLs($args),
            'stat'     => $this->cmdStat($args),
            'file'     => $this->cmdFile($args),
            'find'     => $this->cmdFind($args),
            'grep'     => $this->cmdGrep($args, $stdin),
            'readlink' => $this->cmdReadlink($args),
            'basename' => (($args[0] ?? '') === '' ? "\n" : basename($args[0]) . "\n"),
            'dirname'  => (($args[0] ?? '') === '' ? ".\n" : $this->virtualDirname($args[0]) . "\n"),
            'du'       => $this->cmdDu($args),
            'df'       => $this->cmdDf(),

            // navigation / state
            'cd'       => $this->cmdCd($args),
            'pwd'      => $this->cwd . "\n",

            // identity / recon
            'id'       => $this->cmdId(),
            'whoami'   => "www-data\n",
            'groups'   => "www-data\n",
            'uname'    => $this->cmdUname($args),
            'hostname' => "banksploit-prod-01\n",
            'uptime', 'w' => $this->cmdUptime($binary),
            'lsb_release' => $this->cmdLsbRelease($args),
            'ps'       => $this->cmdPs($args),
            'netstat', 'ss' => $this->cmdNetstat(),
            'ifconfig', 'ip' => $this->cmdIfconfig($args),
            'mount'    => $this->cmdMount(),
            'getcap'   => $this->cmdGetcap(),
            'crontab'  => $this->cmdCrontab($args),
            'sudo'     => $this->cmdSudo($args),
            'env', 'printenv', 'set' => $this->cmdEnv(),

            // network — emulated, NO real socket (no egress possible)
            'curl'     => $this->cmdCurl($args),
            'wget'     => $this->cmdWget($args),
            'ping'     => $this->cmdPing($args),
            'nc', 'netcat', 'ncat' => $this->cmdNc($args),
            'ssh'      => $this->cmdSsh($args),
            'dig', 'nslookup', 'host' => $this->cmdDig($args),

            // writes (real tmp only)
            'touch'    => $this->cmdTouch($args),
            'mkdir'    => $this->cmdMkdir($args),
            'rm'       => $this->cmdRm($args),

            // misc / cosmetic
            'echo'     => $this->cmdEcho($args),
            'which', 'type', 'command' => $this->cmdWhich($args),
            'date'     => gmdate('D M j H:i:s') . " UTC " . gmdate('Y') . "\n",
            'clear', 'history', 'export', 'alias', 'unset', 'true', ':' => '',
            'sleep', 'exit', 'logout' => '',
            'wkhtmltopdf' => $this->cmdWkhtmltopdf($args),

            default => "-bash: {$binary}: command not found\n",
        };
    }

    // -------------------------------------------------------------------------
    // Path resolution: virtual tree + the one real directory (/tmp)
    // -------------------------------------------------------------------------

    /** Resolve an argument to an absolute virtual path (relative to cwd, ~ = home). */
    private function resolveVirtual(string $arg): string
    {
        if ($arg === '~' || str_starts_with($arg, '~/')) {
            $arg = self::HOME . substr($arg, 1);
        }
        if (!str_starts_with($arg, '/')) {
            $arg = $this->cwd . '/' . $arg;
        }
        return $this->virtualNormalize($arg);
    }

    /** True when a (normalized) virtual path lives in the real sandbox tmp. */
    private function isRealTmp(string $vpath): bool
    {
        return $vpath === '/tmp' || str_starts_with($vpath, '/tmp/');
    }

    /** Map a /tmp virtual path to a confined real path, or null if it escapes. */
    private function realPathFor(string $vpath): ?string
    {
        $rest = ltrim(substr($vpath, 4), '/'); // strip "/tmp"
        $candidate = $rest === ''
            ? $this->root
            : $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rest);
        $real = $this->normalize($candidate);
        if (!str_starts_with($real, $this->root)) {
            return null; // traversal escape — refuse
        }
        return $real;
    }

    /**
     * Read a file by virtual path. Returns [ok, content|errorReason].
     * reason: 'denied' | 'missing' | 'isdir'.
     *
     * @return array{0:bool,1:string}
     */
    private function readFile(string $arg): array
    {
        $vpath = $this->resolveVirtual($arg);

        if ($this->isRealTmp($vpath)) {
            $real = $this->realPathFor($vpath);
            if ($real === null) {
                return [false, 'denied'];
            }
            if ($this->isOtherUserFlagFile(basename($real))) {
                return [false, 'denied'];
            }
            if (is_dir($real)) {
                return [false, 'isdir'];
            }
            if (!is_file($real)) {
                return [false, 'missing'];
            }
            return [true, (string) @file_get_contents($real)];
        }

        $node = $this->vfs[$vpath] ?? null;
        if ($node === null) {
            return [false, 'missing'];
        }
        if (($node['type'] ?? '') === 'dir') {
            return [false, 'isdir'];
        }
        if (!empty($node['secret'])) {
            return [false, 'denied'];
        }
        return [true, (string) ($node['content'] ?? '')];
    }

    // -------------------------------------------------------------------------
    // Filesystem commands
    // -------------------------------------------------------------------------

    private function cmdCat(array $args, ?string $stdin = null): string
    {
        $paths = array_values(array_filter($args, fn($a) => !str_starts_with($a, '-')));
        if (empty($paths)) {
            return $stdin ?? ''; // `cat` with no file acts as a stdin passthrough
        }
        $out = '';
        foreach ($paths as $p) {
            [$ok, $data] = $this->readFile($p);
            if ($ok) {
                $out .= $data;
                if ($data !== '' && !str_ends_with($data, "\n")) {
                    $out .= "\n";
                }
            } else {
                $out .= "cat: {$p}: " . $this->errText($data) . "\n";
            }
        }
        return $out;
    }

    private function cmdHeadTail(array $args, bool $head, ?string $stdin = null): string
    {
        $n = 10;
        $files = [];
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '-n' && isset($args[$i + 1])) {
                $n = max(1, (int) $args[++$i]);
            } elseif (preg_match('/^-(\d+)$/', $args[$i], $m)) {
                $n = max(1, (int) $m[1]);
            } elseif (!str_starts_with($args[$i], '-')) {
                $files[] = $args[$i];
            }
        }
        $name = $head ? 'head' : 'tail';
        if (empty($files)) {
            if ($stdin === null) {
                return "{$name}: missing operand\n";
            }
            $lines = explode("\n", rtrim($stdin, "\n"));
            $slice = $head ? array_slice($lines, 0, $n) : array_slice($lines, -$n);
            return implode("\n", $slice) . "\n";
        }
        $out = '';
        foreach ($files as $p) {
            [$ok, $data] = $this->readFile($p);
            if (!$ok) {
                $out .= "{$name}: {$p}: " . $this->errText($data) . "\n";
                continue;
            }
            $lines = explode("\n", rtrim($data, "\n"));
            $slice = $head ? array_slice($lines, 0, $n) : array_slice($lines, -$n);
            $out .= implode("\n", $slice) . "\n";
        }
        return $out;
    }

    private function cmdWc(array $args, ?string $stdin = null): string
    {
        $files = array_values(array_filter($args, fn($a) => !str_starts_with($a, '-')));
        if (empty($files)) {
            if ($stdin === null) {
                return "wc: missing operand\n";
            }
            return sprintf("%7d %7d %7d\n", substr_count($stdin, "\n"), str_word_count($stdin), strlen($stdin));
        }
        $out = '';
        foreach ($files as $p) {
            [$ok, $data] = $this->readFile($p);
            if (!$ok) {
                $out .= "wc: {$p}: " . $this->errText($data) . "\n";
                continue;
            }
            $lc = substr_count($data, "\n");
            $wc = str_word_count($data);
            $bc = strlen($data);
            $out .= sprintf("%7d %7d %7d %s\n", $lc, $wc, $bc, $p);
        }
        return $out;
    }

    private function cmdLs(array $args): string
    {
        $showAll = false;
        $long = false;
        $targets = [];
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) {
                if (str_contains($a, 'a')) $showAll = true;
                if (str_contains($a, 'l')) $long = true;
            } else {
                $targets[] = $a;
            }
        }
        if (empty($targets)) {
            $targets[] = '.';
        }

        $out = '';
        foreach ($targets as $t) {
            $vpath = $this->resolveVirtual($t);
            if (!$this->isRealTmp($vpath)) {
                $node = $this->vfs[$vpath] ?? null;
                if ($node !== null && ($node['type'] ?? '') === 'dir' && !empty($node['secret'])) {
                    $out .= "ls: cannot open directory '{$t}': Permission denied\n";
                    continue;
                }
            }
            $entries = $this->listDir($vpath, $showAll);
            if ($entries === null) {
                // Not a directory: if it's a readable file, echo its name (real ls behaviour).
                [$ok] = $this->readFile($t);
                if ($ok) {
                    $out .= ($long ? $this->lsLongLine($vpath, basename($vpath)) : basename($vpath)) . "\n";
                } else {
                    $out .= "ls: cannot access '{$t}': " . ($this->pathDenied($vpath) ? "Permission denied" : "No such file or directory") . "\n";
                }
                continue;
            }
            if (count($targets) > 1) {
                $out .= "{$t}:\n";
            }
            foreach ($entries as $name) {
                $out .= ($long ? $this->lsLongLine($this->virtualNormalize($vpath . '/' . $name), $name) : $name) . "\n";
            }
        }
        return $out;
    }

    /**
     * List a directory's entry names, or null if it is not a directory.
     * @return list<string>|null
     */
    private function listDir(string $vpath, bool $showAll): ?array
    {
        $names = [];

        if ($this->isRealTmp($vpath)) {
            $real = $this->realPathFor($vpath);
            if ($real === null || !is_dir($real)) {
                return null;
            }
            foreach ((@scandir($real) ?: []) as $e) {
                if ($e === '.' || $e === '..') continue;
                if (!$showAll && str_starts_with($e, '.')) continue;
                if ($this->isOtherUserFlagFile($e)) continue;
                $names[] = $e;
            }
            sort($names);
            return $names;
        }

        $node = $this->vfs[$vpath] ?? null;
        if ($node === null || ($node['type'] ?? '') !== 'dir') {
            return null;
        }
        $prefix = $vpath === '/' ? '/' : $vpath . '/';
        foreach (array_keys($this->vfs) as $key) {
            if ($key === $vpath) continue;
            if (!str_starts_with($key, $prefix)) continue;
            $rest = substr($key, strlen($prefix));
            if ($rest === '' || str_contains($rest, '/')) continue; // direct children only
            if (!$showAll && str_starts_with($rest, '.')) continue;
            $names[] = $rest;
        }
        // /tmp is a real directory grafted into the virtual tree at the root.
        if ($vpath === '/' && !in_array('tmp', $names, true)) {
            $names[] = 'tmp';
        }
        sort($names);
        return $names;
    }

    private function lsLongLine(string $vpath, string $name): string
    {
        if ($this->isRealTmp($vpath)) {
            $real = $this->realPathFor($vpath);
            $isDir = $real !== null && is_dir($real);
            $size  = ($real !== null && is_file($real)) ? (int) @filesize($real) : 4096;
            $mtime = ($real !== null && @file_exists($real)) ? (int) @filemtime($real) : time();
            $mode  = $isDir ? 'drwxrwxrwt' : '-rw-r--r--';
            $owner = 'www-data'; $group = 'www-data';
            $date  = gmdate('M j H:i', $mtime);
            return sprintf('%s 1 %s %s %6d %s %s', $mode, $owner, $group, $size, $date, $name);
        }
        $node  = $this->vfs[$vpath] ?? ['type' => 'file'];
        $isDir = ($node['type'] ?? '') === 'dir';
        $mode  = $node['mode'] ?? ($isDir ? 'drwxr-xr-x' : '-rw-r--r--');
        $owner = $node['owner'] ?? 'root';
        $group = $node['group'] ?? 'root';
        $size  = $isDir ? 4096 : (int) ($node['size'] ?? strlen((string) ($node['content'] ?? '')));
        $date  = $node['mtime'] ?? 'Oct 14 09:12';
        return sprintf('%s 1 %-8s %-8s %6d %s %s', $mode, $owner, $group, $size, $date, $name);
    }

    private function cmdStat(array $args): string
    {
        $files = array_values(array_filter($args, fn($a) => !str_starts_with($a, '-')));
        if (empty($files)) {
            return "stat: missing operand\n";
        }
        $out = '';
        foreach ($files as $f) {
            $vpath = $this->resolveVirtual($f);
            $exists = $this->isRealTmp($vpath)
                ? ($this->realPathFor($vpath) !== null && @file_exists($this->realPathFor($vpath)))
                : isset($this->vfs[$vpath]);
            if (!$exists) {
                $out .= "stat: cannot statx '{$f}': No such file or directory\n";
                continue;
            }
            $out .= "  File: {$vpath}\n";
        }
        return $out;
    }

    private function cmdFile(array $args): string
    {
        $files = array_values(array_filter($args, fn($a) => !str_starts_with($a, '-')));
        $out = '';
        foreach ($files as $f) {
            $vpath = $this->resolveVirtual($f);
            $isDir = ($this->vfs[$vpath]['type'] ?? '') === 'dir'
                || ($this->isRealTmp($vpath) && is_dir((string) $this->realPathFor($vpath)));
            $out .= "{$f}: " . ($isDir ? "directory" : "ASCII text") . "\n";
        }
        return $out ?: "file: missing operand\n";
    }

    private function cmdFind(array $args): string
    {
        $start = '.';
        $namePat = null;
        for ($i = 0; $i < count($args); $i++) {
            if ($args[$i] === '-name' && isset($args[$i + 1])) {
                $namePat = $args[++$i];
            } elseif (!str_starts_with($args[$i], '-') && $start === '.') {
                $start = $args[$i];
            }
        }
        $base = $this->resolveVirtual($start);
        if (!$this->isRealTmp($base) && !empty($this->vfs[$base]['secret'])) {
            return "find: '{$base}': Permission denied\n";
        }
        $hits = [];

        // Virtual subtree.
        $prefix = $base === '/' ? '/' : $base . '/';
        foreach (array_keys($this->vfs) as $key) {
            if ($key === $base || str_starts_with($key, $prefix)) {
                $hits[] = $key;
            }
        }
        // Real tmp subtree.
        if ($this->isRealTmp($base) || $base === '/') {
            $realBase = $this->isRealTmp($base) ? $this->realPathFor($base) : $this->root;
            if ($realBase !== null && is_dir($realBase)) {
                foreach ((@scandir($realBase) ?: []) as $e) {
                    if ($e === '.' || $e === '..' || $this->isOtherUserFlagFile($e)) continue;
                    $hits[] = ($base === '/' ? '/tmp/' : rtrim($base, '/') . '/') . $e;
                }
            }
        }
        sort($hits);
        $out = '';
        foreach ($hits as $h) {
            if ($namePat !== null && !fnmatch($namePat, basename($h))) continue;
            $out .= $h . "\n";
        }
        return $out;
    }

    private function cmdGrep(array $args, ?string $stdin = null): string
    {
        $pattern = null;
        $files = [];
        $ignoreCase = false;
        foreach ($args as $a) {
            if ($a === '-i') { $ignoreCase = true; continue; }
            if (str_starts_with($a, '-')) continue;
            if ($pattern === null) { $pattern = $a; continue; }
            $files[] = $a;
        }
        if ($pattern === null) {
            return "usage: grep PATTERN [FILE]\n";
        }
        if (empty($files)) {
            if ($stdin === null) {
                return "usage: grep PATTERN [FILE]\n";
            }
            $out = '';
            foreach (explode("\n", rtrim($stdin, "\n")) as $line) {
                $hit = $ignoreCase ? stripos($line, $pattern) !== false : str_contains($line, $pattern);
                if ($hit) { $out .= $line . "\n"; }
            }
            return $out;
        }
        $out = '';
        $multi = count($files) > 1;
        foreach ($files as $f) {
            [$ok, $data] = $this->readFile($f);
            if (!$ok) {
                $out .= "grep: {$f}: " . $this->errText($data) . "\n";
                continue;
            }
            foreach (explode("\n", $data) as $line) {
                $hit = $ignoreCase ? stripos($line, $pattern) !== false : str_contains($line, $pattern);
                if ($hit) {
                    $out .= ($multi ? "{$f}:" : '') . $line . "\n";
                }
            }
        }
        return $out;
    }

    private function cmdReadlink(array $args): string
    {
        $f = $args[0] ?? '';
        return $f === '' ? "\n" : $this->resolveVirtual($f) . "\n";
    }

    private function cmdDu(array $args): string
    {
        $t = '';
        foreach ($args as $a) { if (!str_starts_with($a, '-')) { $t = $a; break; } }
        return "48\t" . ($t !== '' ? $this->resolveVirtual($t) : $this->cwd) . "\n";
    }

    private function cmdDf(): string
    {
        return "Filesystem     1K-blocks     Used Available Use% Mounted on\n"
             . "overlay         41251136 18233404  20897132  47% /\n"
             . "tmpfs             102400     1240    101160   2% /tmp\n";
    }

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------

    private function cmdCd(array $args): string
    {
        $target = $args[0] ?? '~';
        if (str_starts_with($target, '-')) {
            $target = '~';
        }
        $vpath = $this->resolveVirtual($target);

        // Real tmp?
        if ($this->isRealTmp($vpath)) {
            $real = $this->realPathFor($vpath);
            if ($real !== null && is_dir($real)) {
                $this->cwd = $vpath;
                return '';
            }
            return "-bash: cd: {$target}: No such file or directory\n";
        }

        $node = $this->vfs[$vpath] ?? null;
        if ($node === null) {
            return "-bash: cd: {$target}: No such file or directory\n";
        }
        if (($node['type'] ?? '') !== 'dir') {
            return "-bash: cd: {$target}: Not a directory\n";
        }
        if (!empty($node['secret'])) {
            return "-bash: cd: {$target}: Permission denied\n";
        }
        $this->cwd = $vpath;
        return '';
    }

    // -------------------------------------------------------------------------
    // Identity / recon
    // -------------------------------------------------------------------------

    private function cmdId(): string
    {
        return "uid=33(www-data) gid=33(www-data) groups=33(www-data)\n";
    }

    private function cmdUname(array $args): string
    {
        $all = in_array('-a', $args, true);
        $r = in_array('-r', $args, true);
        if ($r) return "5.15.0-1042-aws\n";
        return $all
            ? "Linux banksploit-prod-01 5.15.0-1042-aws #47-Ubuntu SMP x86_64 x86_64 x86_64 GNU/Linux\n"
            : "Linux\n";
    }

    private function cmdUptime(string $binary): string
    {
        if ($binary === 'w') {
            return " 09:41:12 up 37 days,  4:18,  0 users,  load average: 0.08, 0.03, 0.01\n"
                 . "USER     TTY      FROM             LOGIN@   IDLE   JCPU   PCPU WHAT\n";
        }
        return " 09:41:12 up 37 days,  4:18,  0 users,  load average: 0.08, 0.03, 0.01\n";
    }

    private function cmdLsbRelease(array $args): string
    {
        return "Distributor ID:\tUbuntu\n"
             . "Description:\tUbuntu 22.04.3 LTS\n"
             . "Release:\t22.04\n"
             . "Codename:\tjammy\n";
    }

    private function cmdPs(array $args): string
    {
        return "USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND\n"
             . "root         1  0.0  0.1 168940 11460 ?        Ss   Oct08   0:12 /sbin/init\n"
             . "root       412  0.0  0.3 1240460 25600 ?       Ssl  Oct08   1:40 /usr/sbin/nginx -g daemon off;\n"
             . "www-data   528  0.0  0.6 1310204 52180 ?       S    Oct08   3:21 php-fpm: pool www\n"
             . "www-data   529  0.1  0.6 1310204 51920 ?       S    Oct08   3:18 php-fpm: pool www\n"
             . "mysql      690  0.2  4.1 1820640 332180 ?      Ssl  Oct08  22:07 /usr/sbin/mysqld\n"
             . "root       712  0.0  0.1  15852  7120 ?        Ss   Oct08   0:03 sshd: /usr/sbin/sshd -D\n"
             . "www-data  2231  0.0  0.1   7372  3380 ?        R    09:41   0:00 ps aux\n";
    }

    private function cmdNetstat(): string
    {
        return "Active Internet connections (only servers)\n"
             . "Proto Recv-Q Send-Q Local Address           Foreign Address         State\n"
             . "tcp        0      0 0.0.0.0:80              0.0.0.0:*               LISTEN\n"
             . "tcp        0      0 0.0.0.0:443             0.0.0.0:*               LISTEN\n"
             . "tcp        0      0 127.0.0.1:3306          0.0.0.0:*               LISTEN\n"
             . "tcp        0      0 127.0.0.1:6379          0.0.0.0:*               LISTEN\n"
             . "tcp        0      0 0.0.0.0:22              0.0.0.0:*               LISTEN\n";
    }

    private function cmdIfconfig(array $args): string
    {
        return "eth0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500\n"
             . "        inet 10.0.12.34  netmask 255.255.255.0  broadcast 10.0.12.255\n"
             . "        ether 0a:58:0a:00:0c:22  txqueuelen 0  (Ethernet)\n"
             . "lo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536\n"
             . "        inet 127.0.0.1  netmask 255.0.0.0\n";
    }

    private function cmdMount(): string
    {
        return "overlay on / type overlay (rw,relatime)\n"
             . "tmpfs on /tmp type tmpfs (rw,nosuid,nodev)\n"
             . "proc on /proc type proc (rw,nosuid,nodev,noexec)\n";
    }

    private function cmdGetcap(): string
    {
        return "/usr/bin/ping cap_net_raw=ep\n";
    }

    private function cmdCrontab(array $args): string
    {
        if (in_array('-l', $args, true)) {
            return "# m h  dom mon dow   command\n"
                 . "*/10 * * * * /usr/local/bin/backup.sh >/var/log/backup.log 2>&1\n";
        }
        return "no crontab for www-data\n";
    }

    private function cmdSudo(array $args): string
    {
        if (($args[0] ?? '') === '-l') {
            return "Matching Defaults entries for www-data on banksploit-prod-01:\n"
                 . "    env_reset, mail_badpass\n\n"
                 . "User www-data may run the following commands on banksploit-prod-01:\n"
                 . "    (ALL) NOPASSWD: /usr/bin/pip\n";
        }
        return "[sudo] password for www-data: \nsudo: a terminal is required to read the password\n";
    }

    private function cmdEnv(): string
    {
        return "HOSTNAME=banksploit-prod-01\n"
             . "PWD={$this->cwd}\n"
             . "HOME=" . self::HOME . "\n"
             . "USER=www-data\n"
             . "SHLVL=1\n"
             . "APP_ENV=production\n"
             . "TERM=dumb\n"
             . "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n";
    }

    // -------------------------------------------------------------------------
    // Network — emulated only. No sockets are ever opened: egress is impossible.
    // -------------------------------------------------------------------------

    /** Pull the first non-flag token that looks like a URL/host. */
    private function firstUrl(array $args): string
    {
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) continue;
            return $a;
        }
        return '';
    }

    private function hostOf(string $url): string
    {
        $h = preg_replace('#^[a-zA-Z]+://#', '', $url);
        $h = explode('/', $h)[0];
        return explode(':', $h)[0];
    }

    private function isInternalHost(string $host): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || str_starts_with($host, '10.0.12.')
            || $host === '169.254.169.254';
    }

    private function cmdCurl(array $args): string
    {
        $url  = $this->firstUrl($args);
        if ($url === '') {
            return "curl: try 'curl --help' for more information\n";
        }
        $host = $this->hostOf($url);

        if ($host === '169.254.169.254') {
            return $this->fakeMetadata($url);
        }
        if ($this->isInternalHost($host)) {
            return $this->fakeInternalService($url);
        }
        // Everything else: no egress. Fail the way a boxed host would.
        return "curl: (6) Could not resolve host: {$host}\n";
    }

    private function cmdWget(array $args): string
    {
        $url  = $this->firstUrl($args);
        if ($url === '') {
            return "wget: missing URL\n";
        }
        $host = $this->hostOf($url);
        $ts   = gmdate('Y-m-d H:i:s');
        if ($this->isInternalHost($host) || $host === '169.254.169.254') {
            $body = $host === '169.254.169.254' ? $this->fakeMetadata($url) : $this->fakeInternalService($url);
            // "Download" never leaves memory; only persists if the cwd is the real tmp.
            $name = basename(parse_url($url, PHP_URL_PATH) ?: 'index.html') ?: 'index.html';
            $saved = $this->maybeWriteCwd($name, $body);
            return "--{$ts}--  {$url}\n"
                 . "Resolving {$host}... {$host}\n"
                 . "Connecting to {$host}:80... connected.\n"
                 . "HTTP request sent, awaiting response... 200 OK\n"
                 . "Length: " . strlen($body) . " [text/plain]\n"
                 . "Saving to: '{$name}'\n\n"
                 . ($saved ? "'{$name}' saved [" . strlen($body) . "]\n" : "{$name}: Read-only file system\n");
        }
        return "--{$ts}--  {$url}\n"
             . "Resolving {$host}... failed: Name or service not known.\n"
             . "wget: unable to resolve host address '{$host}'\n";
    }

    private function cmdPing(array $args): string
    {
        $host = $this->firstUrl($args);
        if ($host === '') return "ping: usage error: Destination address required\n";
        if ($this->isInternalHost($host)) {
            return "PING {$host} ({$host}) 56(84) bytes of data.\n"
                 . "64 bytes from {$host}: icmp_seq=1 ttl=64 time=0.034 ms\n"
                 . "64 bytes from {$host}: icmp_seq=2 ttl=64 time=0.041 ms\n"
                 . "--- {$host} ping statistics ---\n"
                 . "2 packets transmitted, 2 received, 0% packet loss, time 1001ms\n";
        }
        return "ping: {$host}: Name or service not known\n";
    }

    private function cmdNc(array $args): string
    {
        $host = ''; $port = '';
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) continue;
            if ($host === '') { $host = $a; } else { $port = $a; }
        }
        if ($host === '') return "usage: nc [-options] hostname port\n";
        if ($this->isInternalHost($host)) {
            return "Connection to {$host} {$port} port [tcp/*] succeeded!\n";
        }
        return "nc: getaddrinfo for host \"{$host}\" port {$port}: Name or service not known\n";
    }

    private function cmdSsh(array $args): string
    {
        $host = $this->firstUrl($args);
        $host = str_contains($host, '@') ? explode('@', $host)[1] : $host;
        if ($host === '') return "usage: ssh [user@]hostname\n";
        if ($this->isInternalHost($host)) {
            return "ssh: connect to host {$host} port 22: Connection refused\n";
        }
        return "ssh: Could not resolve hostname {$host}: Name or service not known\n";
    }

    private function cmdDig(array $args): string
    {
        $host = $this->firstUrl($args);
        if ($host === '') return ";; connection timed out; no servers could be reached\n";
        if ($this->isInternalHost($host)) {
            return "{$host}.\t\t60\tIN\tA\t" . ($host === 'localhost' ? '127.0.0.1' : $host) . "\n";
        }
        return ";; ->>HEADER<<- opcode: QUERY, status: NXDOMAIN\n";
    }

    private function fakeMetadata(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        if (str_contains($path, 'iam/security-credentials/')) {
            if (str_ends_with(rtrim($path, '/'), 'security-credentials')) {
                return "banksploit-app-role\n";
            }
            return "{\n  \"Code\" : \"Success\",\n  \"Type\" : \"AWS-HMAC\",\n"
                 . "  \"AccessKeyId\" : \"ASIAZZEXAMPLE7KQF3\",\n"
                 . "  \"SecretAccessKey\" : \"wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY\",\n"
                 . "  \"Token\" : \"IQoJb3JpZ2luX2VjED...FAKE-SESSION-TOKEN...==\",\n"
                 . "  \"Expiration\" : \"2026-11-16T15:04:00Z\"\n}\n";
        }
        if (rtrim($path, '/') === '' || str_ends_with(rtrim($path, '/'), 'meta-data')) {
            return "ami-id\ninstance-id\ninstance-type\niam/\nlocal-ipv4\nhostname\n";
        }
        if (str_contains($path, 'instance-id')) return "i-0a1b2c3d4e5f6a7b8\n";
        if (str_contains($path, 'local-ipv4'))  return "10.0.12.34\n";
        return "ami-id\ninstance-id\niam/\nlocal-ipv4\n";
    }

    private function fakeInternalService(string $url): string
    {
        $host = $this->hostOf($url);
        $port = '';
        if (preg_match('#://[^/]*:(\d+)#', $url, $m)) $port = $m[1];
        elseif (preg_match('#^[^/:]+:(\d+)#', $url, $m)) $port = $m[1];

        if ($port === '6379') {
            return "-ERR unknown command, with args beginning with:\n";
        }
        if ($port === '3306') {
            return "J\x00\x00\x00\n5.7.42-log mysql_native_password\n";
        }
        // Default: a fake internal admin API.
        return "{\"service\":\"internal-admin\",\"host\":\"{$host}\",\"status\":\"ok\","
             . "\"note\":\"authenticated via loopback — no token required\"}\n";
    }

    // -------------------------------------------------------------------------
    // Writes (real tmp only)
    // -------------------------------------------------------------------------

    /** Write output to a redirection target; only the real tmp is writable. */
    private function redirect(string $target, string $data, bool $append): string
    {
        $vpath = $this->resolveVirtual($target);
        if (!$this->isRealTmp($vpath)) {
            return "-bash: {$target}: Read-only file system\n";
        }
        $real = $this->realPathFor($vpath);
        if ($real === null || $this->isOtherUserFlagFile(basename($real))) {
            return "-bash: {$target}: Permission denied\n";
        }
        @file_put_contents($real, $data, $append ? FILE_APPEND : 0);
        return '';
    }

    /** Write a "downloaded" body to the cwd — only if the cwd is the real tmp. */
    private function maybeWriteCwd(string $name, string $data): bool
    {
        $vpath = $this->virtualNormalize($this->cwd . '/' . $name);
        if (!$this->isRealTmp($vpath)) {
            return false;
        }
        $real = $this->realPathFor($vpath);
        if ($real === null) {
            return false;
        }
        @file_put_contents($real, $data);
        return true;
    }

    private function cmdTouch(array $args): string
    {
        $out = '';
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) continue;
            $vpath = $this->resolveVirtual($a);
            if (!$this->isRealTmp($vpath)) {
                $out .= "touch: cannot touch '{$a}': Read-only file system\n";
                continue;
            }
            $real = $this->realPathFor($vpath);
            if ($real === null || $this->isOtherUserFlagFile(basename($real))) {
                $out .= "touch: cannot touch '{$a}': Permission denied\n";
                continue;
            }
            @touch($real);
        }
        return $out;
    }

    private function cmdMkdir(array $args): string
    {
        $out = '';
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) continue;
            $vpath = $this->resolveVirtual($a);
            if (!$this->isRealTmp($vpath)) {
                $out .= "mkdir: cannot create directory '{$a}': Permission denied\n";
                continue;
            }
            $real = $this->realPathFor($vpath);
            if ($real !== null) {
                @mkdir($real, 0777, true);
            }
        }
        return $out;
    }

    private function cmdRm(array $args): string
    {
        $out = '';
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) continue;
            $vpath = $this->resolveVirtual($a);
            if (!$this->isRealTmp($vpath)) {
                $out .= "rm: cannot remove '{$a}': Permission denied\n";
                continue;
            }
            $real = $this->realPathFor($vpath);
            if ($real === null || $this->isOtherUserFlagFile(basename($real))) {
                $out .= "rm: cannot remove '{$a}': Permission denied\n";
                continue;
            }
            if (is_file($real)) {
                @unlink($real);
            }
        }
        return $out;
    }

    // -------------------------------------------------------------------------
    // Misc / cosmetic
    // -------------------------------------------------------------------------

    private function cmdEcho(array $args): string
    {
        $line = implode(' ', $args);
        $line = str_replace(['$HOME', '${HOME}'], self::HOME, $line);
        $line = str_replace(['$USER', '${USER}'], 'www-data', $line);
        $line = str_replace(['$PWD', '${PWD}'], $this->cwd, $line);
        $line = str_replace(['$HOSTNAME', '${HOSTNAME}'], 'banksploit-prod-01', $line);
        // Any remaining $VARs expand to empty, like an unset variable.
        $line = preg_replace('/\$\{?[A-Za-z_][A-Za-z0-9_]*\}?/', '', $line);
        return $line . "\n";
    }

    private function cmdWhich(array $args): string
    {
        $known = ['sh','bash','cat','ls','id','curl','wget','php','python3','perl','nc','find','grep'];
        $out = '';
        foreach ($args as $a) {
            if (str_starts_with($a, '-')) continue;
            $out .= in_array($a, $known, true) ? "/usr/bin/{$a}\n" : "{$a} not found\n";
        }
        return $out;
    }

    private function cmdWkhtmltopdf(array $args): string
    {
        $outFile = end($args) ?: 'statement.pdf';
        $vpath = $this->resolveVirtual((string) $outFile);
        if ($this->isRealTmp($vpath)) {
            $real = $this->realPathFor($vpath);
            if ($real !== null && !$this->isOtherUserFlagFile(basename($real))) {
                @file_put_contents($real, '');
            }
        }
        return "Generated PDF: {$outFile}\n";
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function errText(string $reason): string
    {
        return match ($reason) {
            'denied'  => 'Permission denied',
            'isdir'   => 'Is a directory',
            default   => 'No such file or directory',
        };
    }

    /** Would a non-existent/secret virtual path report "Permission denied" to ls? */
    private function pathDenied(string $vpath): bool
    {
        $node = $this->vfs[$vpath] ?? null;
        return $node !== null && !empty($node['secret']);
    }

    private function isOtherUserFlagFile(string $basename): bool
    {
        if (preg_match(self::FLAG_FILE_PATTERN, $basename, $m)) {
            return (int) $m[1] !== $this->uid;
        }
        return false;
    }

    private function virtualDirname(string $path): string
    {
        $v = $this->resolveVirtual($path);
        $d = substr($v, 0, (int) strrpos($v, '/'));
        return $d === '' ? '/' : $d;
    }

    /** Normalize a virtual path — always '/'-based, resolve . and .., never escapes to a host path. */
    private function virtualNormalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = explode('/', $path);
        $out = [];
        foreach ($parts as $p) {
            if ($p === '' || $p === '.') continue;
            if ($p === '..') { array_pop($out); continue; }
            $out[] = $p;
        }
        return '/' . implode('/', $out);
    }

    // -------------------------------------------------------------------------
    // Real-path helpers (confinement for the one real directory)
    // -------------------------------------------------------------------------

    private function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $drive = '';
        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]):/', $path, $m)) {
            $drive = strtoupper($m[1]) . ':';
            $path = substr($path, 2);
        }
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $result = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') { array_pop($result); continue; }
            $result[] = $part;
        }
        if ($drive !== '') {
            return $drive . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $result);
        }
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $result);
    }

    // -------------------------------------------------------------------------
    // Tokenizer
    // -------------------------------------------------------------------------

    /** @return list<string> */
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
            } elseif (($ch === ' ' || $ch === "\t") && !$inSingle && !$inDouble) {
                if ($current !== '') { $tokens[] = $current; $current = ''; }
            } else {
                $current .= $ch;
            }
        }
        if ($current !== '') { $tokens[] = $current; }
        return $tokens;
    }

    private static function defaultRoot(): string
    {
        return self::sandboxRoot();
    }

    /**
     * The dedicated sandbox tmp directory, presented as /tmp. A subdirectory of
     * the system temp so the honeypot's /tmp shows ONLY lab-controlled files
     * (the flag + participant-created files) — never the host's real temp junk.
     * Callers that seed the flag file must use this same path.
     */
    public static function sandboxRoot(): string
    {
        $dir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'banksploit_g9';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    // -------------------------------------------------------------------------
    // Virtual filesystem (fabricated, non-sensitive)
    // -------------------------------------------------------------------------

    /** @return array<string,array<string,mixed>> */
    private function buildVfs(): array
    {
        $dir = fn(string $mode = 'drwxr-xr-x', string $owner = 'root', string $group = 'root')
            => ['type' => 'dir', 'mode' => $mode, 'owner' => $owner, 'group' => $group];
        $file = fn(string $content, string $mode = '-rw-r--r--', string $owner = 'root', string $group = 'root', string $mtime = 'Oct 14 09:12')
            => ['type' => 'file', 'content' => $content, 'mode' => $mode, 'owner' => $owner, 'group' => $group, 'mtime' => $mtime];

        $passwd = "root:x:0:0:root:/root:/bin/bash\n"
                . "daemon:x:1:1:daemon:/usr/sbin:/usr/sbin/nologin\n"
                . "bin:x:2:2:bin:/bin:/usr/sbin/nologin\n"
                . "www-data:x:33:33:www-data:/var/www:/usr/sbin/nologin\n"
                . "mysql:x:106:112:MySQL Server,,,:/nonexistent:/bin/false\n"
                . "sshd:x:110:65534::/run/sshd:/usr/sbin/nologin\n"
                . "bankadmin:x:1000:1000:Bank Admin:/home/bankadmin:/bin/bash\n";

        $env = "APP_NAME=BankSploit\n"
             . "APP_ENV=production\n"
             . "APP_KEY=base64:kJ3fQ2m9Xp0bV7yR1sN4wL8cZ6dH5gT2aE0uI9oP3kM=\n"
             . "APP_DEBUG=false\n"
             . "DB_CONNECTION=mysql\n"
             . "DB_HOST=10.0.12.40\n"
             . "DB_PORT=3306\n"
             . "DB_DATABASE=banksploit\n"
             . "DB_USERNAME=bank_app\n"
             . "DB_PASSWORD=Pr0d_B4nk\$pl0it_2024!\n"
             . "JWT_SECRET=5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8\n"
             . "REDIS_HOST=10.0.12.41\n"
             . "AWS_DEFAULT_REGION=us-east-1\n";

        $config = "<?php\n"
                . "// Database configuration (production)\n"
                . "return [\n"
                . "    'host' => '10.0.12.40',\n"
                . "    'user' => 'bank_app',\n"
                . "    'pass' => 'Pr0d_B4nk\$pl0it_2024!',\n"
                . "    'name' => 'banksploit',\n"
                . "];\n";

        $backup = "#!/bin/bash\n"
                . "# Nightly DB backup — runs from root crontab\n"
                . "mysqldump -u root -p'R00t_Db_Backup_9931' banksploit | gzip > /var/backups/db_\$(date +%F).sql.gz\n";

        $vfs = [
            '/'            => $dir(),
            '/bin'         => $dir(),
            '/sbin'        => $dir(),
            '/boot'        => $dir(),
            '/dev'         => $dir('drwxr-xr-x'),
            '/etc'         => $dir(),
            '/home'        => $dir(),
            '/lib'         => $dir(),
            '/opt'         => $dir(),
            '/proc'        => $dir('dr-xr-xr-x'),
            '/root'        => $dir('drwx------', 'root', 'root') + ['secret' => true],
            '/run'         => $dir(),
            '/srv'         => $dir(),
            '/usr'         => $dir(),
            '/var'         => $dir(),

            '/etc/passwd'      => $file($passwd),
            '/etc/shadow'      => $file("root:!:19600:0:99999:7:::\n", '-rw-r-----', 'root', 'shadow') + ['secret' => true],
            '/etc/group'       => $file("root:x:0:\nwww-data:x:33:\nmysql:x:112:\n"),
            '/etc/hostname'    => $file("banksploit-prod-01\n"),
            '/etc/hosts'       => $file("127.0.0.1 localhost\n127.0.1.1 banksploit-prod-01\n10.0.12.40 db-primary\n"),
            '/etc/os-release'  => $file("PRETTY_NAME=\"Ubuntu 22.04.3 LTS\"\nVERSION_ID=\"22.04\"\nID=ubuntu\n"),
            '/etc/resolv.conf' => $file("nameserver 10.0.0.2\nsearch ec2.internal\n"),
            '/etc/crontab'     => $file("*/10 * * * * root /usr/local/bin/backup.sh\n"),
            '/etc/mysql'       => $dir(),
            '/etc/nginx'       => $dir(),
            '/etc/nginx/nginx.conf' => $file("user www-data;\nworker_processes auto;\nhttp { include mime.types; }\n"),
            '/etc/ssl'         => $dir(),
            '/etc/ssl/private' => $dir('drwx------', 'root', 'root') + ['secret' => true],

            '/home/bankadmin'  => $dir('drwxr-xr-x', 'bankadmin', 'bankadmin'),
            '/home/bankadmin/.bash_history' => $file("ls -la\nsudo systemctl restart nginx\nmysql -u root -p\nexit\n", '-rw-------', 'bankadmin', 'bankadmin') + ['secret' => true],

            '/root/.ssh'       => $dir('drwx------', 'root', 'root') + ['secret' => true],

            '/usr/bin'         => $dir(),
            '/usr/local'       => $dir(),
            '/usr/local/bin'   => $dir(),
            '/usr/local/bin/backup.sh' => $file($backup, '-rwxr-xr-x', 'root', 'root'),

            '/proc/version'    => $file("Linux version 5.15.0-1042-aws (buildd@lcy02) (gcc 11.4.0) #47-Ubuntu SMP\n"),

            '/var/backups'     => $dir(),
            '/var/log'         => $dir(),
            '/var/log/nginx'   => $dir(),
            '/var/log/nginx/access.log' => $file("10.0.12.9 - - [16/Nov/2026:09:40:01 +0000] \"GET /dashboard HTTP/1.1\" 200 4113\n", '-rw-r-----', 'www-data', 'adm'),
            '/var/log/auth.log' => $file("Nov 16 09:12:04 banksploit-prod-01 sshd[712]: Accepted publickey for bankadmin\n", '-rw-r-----', 'syslog', 'adm') + ['secret' => true],
            '/var/www'         => $dir('drwxr-xr-x', 'www-data', 'www-data'),
            '/var/www/html'    => $dir('drwxr-xr-x', 'www-data', 'www-data'),
            '/var/www/html/index.php'   => $file("<?php require __DIR__.'/includes/bootstrap.php';\n", '-rw-r--r--', 'www-data', 'www-data'),
            '/var/www/html/config.php'  => $file($config, '-rw-r--r--', 'www-data', 'www-data'),
            '/var/www/html/.env'        => $file($env, '-rw-r--r--', 'www-data', 'www-data', 'Nov 02 03:40'),
            '/var/www/html/includes'    => $dir('drwxr-xr-x', 'www-data', 'www-data'),
            '/var/www/html/includes/bootstrap.php' => $file("<?php \$cfg = require __DIR__.'/../config.php';\n", '-rw-r--r--', 'www-data', 'www-data'),
            '/var/www/html/uploads'     => $dir('drwxrwxr-x', 'www-data', 'www-data'),

            // /tmp is grafted in as a real directory (see listDir / readFile).
            '/tmp'             => $dir('drwxrwxrwt', 'root', 'root'),
        ];

        return $vfs;
    }
}
