<?php

declare(strict_types=1);

/**
 * Shared training-platform logic: progress tracking, progressive hints, CTF
 * flag checking, skill-radar scoring, and the code-marker parser used by the
 * side-by-side code viewer.
 *
 * Progress lives in the `training_progress` table, one row per vuln, keyed by
 * vuln_id = "<module>.<lowercase id>" (e.g. "transfer.l1"). session_name is
 * 'default' for the single-user local deployment.
 */

if (!function_exists('tr_session_name')) {

    function tr_session_name(): string
    {
        return 'default';
    }

    /**
     * Flatten the vuln config into a list of vuln descriptors.
     *
     * @return list<array<string,mixed>>  each: key (module.lid), module, id,
     *         title, type, level, owasp_category, cwe_id, category, status...
     */
    function tr_all_vulns(): array
    {
        $all = engine()->getAllVulns();
        $out = [];
        foreach ($all['modules'] as $mod => $vulns) {
            foreach ($vulns as $vid => $v) {
                $out[] = [
                    'key'      => $mod . '.' . strtolower($vid),
                    'module'   => $mod,
                    'vid'      => strtolower($vid),
                    'id'       => $v['id'] ?? strtoupper($vid),
                    'title'    => $v['title'] ?? $vid,
                    'type'     => $v['type'] ?? 'logical',
                    'level'    => (int) ($v['level'] ?? 0),
                    'owasp'    => $v['owasp_category'] ?? '',
                    'cwe'      => $v['cwe_id'] ?? '',
                    'category' => $v['category'] ?? 'Logic',
                    'description'    => $v['description'] ?? '',
                    'attack_scenario'=> $v['attack_scenario'] ?? '',
                    'hints'    => $v['hints'] ?? [],
                    'flag'     => $v['flag'] ?? '',
                ];
            }
        }
        return $out;
    }

    /**
     * Find one vuln descriptor by its key (module.lid) or by bare id (g1/l1).
     *
     * @return array<string,mixed>|null
     */
    function tr_find_vuln(string $needle): ?array
    {
        $needle = strtolower(trim($needle));
        foreach (tr_all_vulns() as $v) {
            if ($v['key'] === $needle || $v['vid'] === $needle || strtolower($v['id']) === $needle) {
                return $v;
            }
        }
        return null;
    }

    // -------------------------------------------------- progress -----------

    /**
     * Current progress rows keyed by vuln_id.
     *
     * @return array<string,array{status:string,hints_used:int,found_at:?string}>
     */
    function tr_progress_map(PDO $pdo): array
    {
        $s = $pdo->prepare('SELECT vuln_id, status, hints_used, found_at FROM training_progress WHERE session_name = :s');
        $s->execute([':s' => tr_session_name()]);
        $out = [];
        foreach ($s->fetchAll() as $r) {
            $out[$r['vuln_id']] = [
                'status'     => $r['status'],
                'hints_used' => (int) $r['hints_used'],
                'found_at'   => $r['found_at'],
            ];
        }
        return $out;
    }

    /**
     * Set a vuln's status (and stamp found_at when it leaves 'hunting').
     * Status order: hunting < found < exploited < reviewed.
     */
    function tr_set_status(PDO $pdo, string $vulnKey, string $status): bool
    {
        if (!in_array($status, ['hunting', 'found', 'exploited', 'reviewed'], true)) {
            return false;
        }
        $stamp = $status === 'hunting' ? 'NULL' : 'COALESCE(found_at, NOW())';
        $sql = "UPDATE training_progress
                   SET status = :st, found_at = $stamp
                 WHERE session_name = :s AND vuln_id = :v";
        $st = $pdo->prepare($sql);
        $st->execute([':st' => $status, ':s' => tr_session_name(), ':v' => $vulnKey]);
        return $st->rowCount() >= 0;
    }

    function tr_reset(PDO $pdo): void
    {
        $pdo->prepare("UPDATE training_progress SET status='hunting', hints_used=0, found_at=NULL WHERE session_name = :s")
            ->execute([':s' => tr_session_name()]);
    }

    /**
     * Aggregate counts + score across all tracked vulns.
     *
     * @return array{total:int,found:int,exploited:int,reviewed:int,score:int}
     */
    function tr_stats(PDO $pdo): array
    {
        $map = tr_progress_map($pdo);
        $total = count($map);
        $found = $exploited = $reviewed = 0;
        foreach ($map as $p) {
            // statuses are cumulative tiers
            if (in_array($p['status'], ['found', 'exploited', 'reviewed'], true)) $found++;
            if (in_array($p['status'], ['exploited', 'reviewed'], true)) $exploited++;
            if ($p['status'] === 'reviewed') $reviewed++;
        }
        $score = $total > 0 ? (int) round($exploited / $total * 100) : 0;
        return compact('total', 'found', 'exploited', 'reviewed') + ['score' => $score];
    }

    /**
     * Per-category skill radar: % exploited within each skill category.
     *
     * @return array<string,array{total:int,exploited:int,pct:int}>
     */
    function tr_skill_radar(PDO $pdo): array
    {
        $map = tr_progress_map($pdo);
        $radar = [];
        foreach (tr_all_vulns() as $v) {
            $cat = $v['category'];
            $radar[$cat] ??= ['total' => 0, 'exploited' => 0, 'pct' => 0];
            $radar[$cat]['total']++;
            $st = $map[$v['key']]['status'] ?? 'hunting';
            if (in_array($st, ['exploited', 'reviewed'], true)) {
                $radar[$cat]['exploited']++;
            }
        }
        foreach ($radar as &$r) {
            $r['pct'] = $r['total'] > 0 ? (int) round($r['exploited'] / $r['total'] * 100) : 0;
        }
        return $radar;
    }

    // -------------------------------------------------- hints --------------

    /**
     * Reveal the next hint for a vuln (progressive: 1 -> 2 -> 3). Increments
     * hints_used. Returns the hint text + how many are now used, or an error
     * if the requested index skips ahead.
     *
     * @return array{ok:bool,error:?string,hint:?string,index:int,used:int,total:int}
     */
    function tr_get_hint(PDO $pdo, string $vulnKey, int $wantIndex): array
    {
        $v = tr_find_vuln($vulnKey);
        if ($v === null) {
            return ['ok' => false, 'error' => 'Unknown vulnerability.', 'hint' => null, 'index' => 0, 'used' => 0, 'total' => 0];
        }
        $hints = $v['hints'];
        $total = count($hints);

        $row = $pdo->prepare('SELECT hints_used FROM training_progress WHERE session_name = :s AND vuln_id = :v');
        $row->execute([':s' => tr_session_name(), ':v' => $v['key']]);
        $used = (int) ($row->fetchColumn() ?: 0);

        // Progressive disclosure: you may only request the next unseen hint
        // (index == used+... ) — i.e. hint N requires hints 1..N-1 first.
        $next = $used + 1;                       // 1-based index of the next hint
        if ($wantIndex > $next) {
            return ['ok' => false, 'error' => "Request hint {$next} first.", 'hint' => null, 'index' => $next, 'used' => $used, 'total' => $total];
        }
        if ($next > $total) {
            return ['ok' => false, 'error' => 'No more hints.', 'hint' => null, 'index' => $total, 'used' => $used, 'total' => $total];
        }

        $hint = (string) $hints[$next - 1];
        // Only charge a new hint the first time it's revealed.
        if ($wantIndex === $next) {
            $pdo->prepare('UPDATE training_progress SET hints_used = :h WHERE session_name = :s AND vuln_id = :v')
                ->execute([':h' => $next, ':s' => tr_session_name(), ':v' => $v['key']]);
            $used = $next;
        }
        return ['ok' => true, 'error' => null, 'hint' => $hint, 'index' => $next, 'used' => $used, 'total' => $total];
    }

    // -------------------------------------------------- flags --------------

    /**
     * Submit a CTF flag. Per-user HMAC flags — computed on the fly, never stored.
     * On a correct match, marks the vuln 'exploited'.
     *
     * For statements.g2 (SQLi) no extra proof gate is needed: the flag is a
     * per-user HMAC that lives only in ctf_flags and is reachable solely via the
     * injection, so a correct submission already proves the extraction.
     *
     * @return array{ok:bool,correct:bool,message:string,vuln_id:?string}
     */
    function tr_submit_flag(PDO $pdo, string $flag): array
    {
        $flag = trim($flag);
        $user = auth()->getCurrentUser();
        if (!$user) {
            return ['ok' => true, 'correct' => false, 'message' => 'You must be logged in.', 'vuln_id' => null];
        }

        $userId = (int) $user['id'];
        $result = Flags::validate($flag, $userId);

        if ($result['valid']) {
            // Enforcement: a flag only scores if its vulnerability is part of the
            // current CTF (ctf_active). This is the single authoritative gate — it
            // applies no matter how the flag was delivered (header, JS, DB, file),
            // so an unselected vuln can be played with but never counts.
            [$aMod, $aVid] = array_pad(explode('.', $result['vuln_key'], 2), 2, '');
            if (!engine()->isCtfActive($aMod, $aVid)) {
                return ['ok' => true, 'correct' => false,
                        'message' => 'This vulnerability is not part of the current CTF event.',
                        'vuln_id' => null];
            }

            // statements.g2 needs no extra "proof of exploitation" check: the flag is a
            // per-user HMAC stored only in ctf_flags and obtainable solely through the
            // SQLi (in-band UNION, error-based, or boolean-blind — which never lands the
            // flag in a result row), so Flags::validate() passing IS the proof.

            $v = tr_find_vuln($result['vuln_key']);
            $title = $v ? "{$v['id']} — {$v['title']}" : $result['vuln_key'];
            // Award the points for the level that is live right now, so a harder
            // (more-mitigated) configuration is worth more at capture time.
            [$pMod, $pVid] = array_pad(explode('.', $result['vuln_key'], 2), 2, '');
            $curLevel = engine()->getVulnLevel($pMod, $pVid);
            $points = Flags::points($result['vuln_key'], $curLevel);
            tr_set_status($pdo, $result['vuln_key'], 'exploited');

            // Log to ctf_submissions for leaderboard (INSERT IGNORE = no dupes)
            $pdo->prepare(
                'INSERT IGNORE INTO ctf_submissions (user_id, vuln_key, points)
                 VALUES (:uid, :vk, :pts)'
            )->execute([':uid' => $userId, ':vk' => $result['vuln_key'], ':pts' => $points]);

            return ['ok' => true, 'correct' => true,
                    'message' => "🚩 Correct! {$title} — +{$points} pts",
                    'vuln_id' => $result['vuln_key'],
                    'points'  => $points];
        }

        return ['ok' => true, 'correct' => false, 'message' => 'Incorrect flag.', 'vuln_id' => null];
    }

    // -------------------------------------------------- code markers -------

    /**
     * Parse a PHP source file for the code-viewer markers and return its lines
     * annotated with which ones fall inside a @vuln or @fix block.
     *
     *   // @vuln-start: <label>   ... // @vuln-end
     *   // @fix-start: <label>    ... // @fix-end
     *
     * Marker labels may embed the governing vuln id in parentheses, e.g.
     *   // @vuln-start: IDOR on from_account (transfer.l1)
     * tr_marker_id() extracts "transfer.l1" from such a label.
     *
     * @param ?string $onlyKey  when given (e.g. "transfer.l1"), only blocks whose
     *        label resolves to that key are highlighted (others render plain),
     *        so a secondary vuln shows just its own lines.
     *
     * @return array{lines:list<array{n:int,text:string,hl:bool,label:string}>,labels:list<string>}
     */
    function tr_parse_markers(string $file, string $kind, ?string $onlyKey = null, ?int $level = null): array
    {
        $startTag = $kind === 'fix' ? '@fix-start' : '@vuln-start';
        $endTag   = $kind === 'fix' ? '@fix-end'   : '@vuln-end';

        $lines = [];
        $labels = [];
        if (!is_file($file)) {
            return ['lines' => [], 'labels' => []];
        }
        $raw = explode("\n", (string) file_get_contents($file));
        $inBlock = false;
        $curLabel = '';
        $blockMatches = true;
        $n = 0;
        foreach ($raw as $text) {
            $n++;
            if (str_contains($text, $startTag)) {
                $inBlock = true;
                $curLabel = trim((string) preg_replace('/.*' . preg_quote($startTag, '/') . '\s*:?\s*/', '', $text));
                // Does this block belong to the requested vuln (if filtering) AND
                // to the currently-selected level (if filtering)? A marker tagged
                // e.g. "[L2]" or "[L0-1]" is shown only when the active level is in
                // that range; a block with no level tag applies to all levels.
                $blockMatches = ($onlyKey === null || tr_marker_id($curLabel) === strtolower($onlyKey))
                    && ($level === null || tr_marker_level_matches($curLabel, $level));
                if ($curLabel !== '' && $blockMatches && !in_array($curLabel, $labels, true)) {
                    $labels[] = $curLabel;
                }
                continue; // don't render the marker line itself
            }
            if (str_contains($text, $endTag)) {
                $inBlock = false;
                $curLabel = '';
                $blockMatches = true;
                continue;
            }
            // Skip the OTHER kind's marker lines too, so a block that is wrapped in
            // BOTH @vuln and @fix (shared code shown on both panes) doesn't leak the
            // opposite kind's start/end comment into this pane.
            if (str_contains($text, '@vuln-start') || str_contains($text, '@vuln-end')
                || str_contains($text, '@fix-start') || str_contains($text, '@fix-end')) {
                continue;
            }
            $lines[] = ['n' => $n, 'text' => $text, 'hl' => $inBlock && $blockMatches, 'label' => $inBlock ? $curLabel : ''];
        }
        return ['lines' => $lines, 'labels' => $labels];
    }

    /**
     * Does a marker label apply to the given level? A label may start with a
     * level tag like "[L2]" (one level) or "[L0-1]" (a range). When present, the
     * block is shown only if $level falls inside it. With no tag the block has no
     * level restriction and applies to every level.
     */
    function tr_marker_level_matches(string $label, int $level): bool
    {
        if (preg_match('/\[L\s*(\d)\s*(?:-\s*(\d))?\s*\]/i', $label, $m)) {
            $lo = (int) $m[1];
            $hi = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $lo;
            return $level >= $lo && $level <= $hi;
        }
        return true;
    }

    /**
     * Pull the "module.lid" key out of a marker label's trailing "(...)".
     * Returns '' if none present.
     */
    function tr_marker_id(string $label): string
    {
        if (preg_match('/\(([a-z]+\.[a-z0-9]+)\)\s*$/i', $label, $m)) {
            return strtolower($m[1]);
        }
        return '';
    }

    /**
     * Resolve the vuln/secure source files for a module. Most modules use a
     * single pair `<x>_vuln.php` / `<x>_secure.php`; this maps a module to its
     * primary pair for the viewer.
     *
     * @return array{vuln:?string,secure:?string,label:string}
     */
    function tr_module_sources(string $module): array
    {
        $base = __DIR__ . '/../modules/' . $module . '/';
        // Known primary pairs per module.
        $pairs = [
            'auth'        => ['login_vuln.php', 'login_secure.php'],
            'transfer'    => ['transfer_vuln.php', 'transfer_secure.php'],
            'dashboard'   => ['profile_vuln.php', 'profile_secure.php'],
            'statements'  => ['_shared.php', '_shared.php'],
            'accounts'    => ['_shared.php', '_shared.php'],
            'cards'       => ['cards_vuln.php', 'cards_secure.php'],
            'loans'       => ['loans_vuln.php', 'loans_secure.php'],
            'payments'    => ['payments_vuln.php', 'payments_secure.php'],
            'beneficiary' => ['../transfer/beneficiary.php', '../transfer/beneficiary.php'],
            'profile'     => ['profile_vuln.php', 'profile_secure.php'],
            'admin'       => ['_shared.php', '_shared.php'],
        ];
        $p = $pairs[$module] ?? null;
        if ($p === null) {
            return ['vuln' => null, 'secure' => null, 'label' => $module];
        }
        // dashboard/profile actually live under modules/dashboard
        $vuln   = realpath($base . $p[0]) ?: ($base . $p[0]);
        $secure = realpath($base . $p[1]) ?: ($base . $p[1]);
        return ['vuln' => $vuln, 'secure' => $secure, 'label' => $module];
    }

    /**
     * Resolve the source file pair that actually CONTAINS the markers for a
     * specific vuln. Many secondary vulns live in a different file than the
     * module's primary pair (e.g. transfer.l2/l9 are in transfer/_shared.php,
     * auth.l10/l11/l13 in auth/_shared.php). Returns absolute paths plus the
     * key to filter markers by.
     *
     * @return array{vuln:?string,secure:?string,key:string}
     */
    function tr_vuln_sources(string $module, string $vid): array
    {
        $m = __DIR__ . '/../modules/';
        $key = strtolower($module . '.' . $vid);

        // Per-vuln file overrides (where a vuln's marker lives, if not the
        // module's primary pair). vuln + secure may be the SAME file when both
        // marker kinds are in one place.
        $map = [
            // auth secondary vulns live in auth/_shared.php
            'auth.l10' => ['auth/_shared.php', 'auth/_shared.php'],
            'auth.l11' => ['auth/_shared.php', 'auth/_shared.php'],
            'auth.l13' => ['auth/_shared.php', 'auth/_shared.php'],
            'auth.l12' => ['../core/Auth.php', '../core/Auth.php'],
            'auth.l17' => ['../core/Auth.php', '../core/Auth.php'],
            'auth.l16' => ['auth/login_vuln.php', 'auth/login_secure.php'],
            'auth.l25' => ['auth/forgot.php', 'auth/forgot.php'],
            'auth.l14' => ['../core/Session.php', '../core/Session.php'],
            'auth.l15' => ['../core/Session.php', '../core/Session.php'],
            // transfer secondary vulns
            'transfer.l2' => ['transfer/_shared.php', 'transfer/_shared.php'],
            'transfer.l6' => ['transfer/transfer_vuln.php', 'transfer/transfer_secure.php'],
            'transfer.l7' => ['transfer/transfer_vuln.php', 'transfer/transfer_secure.php'],
            'transfer.l9' => ['transfer/api.php', 'transfer/api.php'],
            'transfer.l20'=> ['transfer/transfer_vuln.php', 'transfer/transfer_secure.php'],
            'transfer.l21'=> ['transfer/transfer_vuln.php', 'transfer/transfer_secure.php'],
            'transfer.l23'=> ['transfer/transfer_vuln.php', 'transfer/transfer_secure.php'],
            'transfer.g5' => ['../core/CSRF.php', '../core/CSRF.php'],
            // statements / accounts
            'statements.g2' => ['statements/_shared.php', 'statements/_shared.php'],
            'statements.g4' => ['statements/index.php', 'statements/index.php'],
            'statements.g8' => ['statements/_shared.php', 'statements/_shared.php'],
            'statements.g9' => ['statements/_shared.php', 'statements/_shared.php'],
            'accounts.l19'  => ['statements/_shared.php', 'statements/_shared.php'],
            'transfer.l1'   => ['transfer/transfer_vuln.php', 'transfer/transfer_secure.php'],
            // beneficiary (all in one file)
            'beneficiary.l3' => ['transfer/beneficiary.php', 'transfer/beneficiary.php'],
            'beneficiary.l8' => ['transfer/beneficiary.php', 'transfer/beneficiary.php'],
            'beneficiary.g3' => ['transfer/beneficiary.php', 'transfer/beneficiary.php'],
            // cards
            'cards.l24' => ['cards/cards_vuln.php', 'cards/cards_secure.php'],
            // loans
            'loans.l22' => ['loans/loans_vuln.php', 'loans/loans_secure.php'],
            'loans.g6'  => ['loans/loans_vuln.php', 'loans/loans_secure.php'],
            'loans.g7'  => ['loans/loans_vuln.php', 'loans/loans_secure.php'],
            'loans.g10' => ['loans/loans_vuln.php', 'loans/loans_secure.php'],
            // payments / profile / templates / sessions / admin / core
            'beneficiary.g3'=> ['transfer/beneficiary.php', 'transfer/beneficiary.php'],
            'profile.l5'    => ['dashboard/profile_vuln.php', 'dashboard/profile_secure.php'],
            'accounts.l19b' => ['statements/_shared.php', 'statements/_shared.php'],
            'templates.g11' => ['pages/index.php', 'pages/index.php'],
            'sessions.g12'  => ['prefs/index.php', 'prefs/index.php'],
            'admin.l4'      => ['admin/_shared.php', 'admin/_shared.php'],
            'core.l18'      => ['../core/helpers.php', '../core/helpers.php'],
        ];
        // Optional ADDITIONAL files whose marked blocks also appear on a pane — for a
        // vuln whose story spans more than one file. e.g. auth.l25 shows the login
        // session-set next to the forgot-password one on BOTH panes, so you can see
        // both journeys write the same $_SESSION['user'] (and, in the fix, that only
        // the login does). Keyed per pane: 'vuln' and 'secure'.
        $extraMap = [
            'auth.l25' => ['vuln' => ['auth/login.php'], 'secure' => ['auth/login.php']],
            // g2's per-level filter lives in g2_term() (core/helpers.php); show it on the
            // vuln pane next to the concatenated sink. The fix is "use a bound parameter",
            // shown by the @fix block in the sink file, so there is no secure-pane extra.
            'statements.g2' => ['vuln' => ['../core/helpers.php'], 'secure' => []],
        ];
        $resolve = static function (array $files) use ($m): array {
            $out = [];
            foreach ($files as $f) { $out[] = realpath($m . $f) ?: ($m . $f); }
            return $out;
        };
        $vExtra = $resolve($extraMap[$key]['vuln'] ?? []);
        $sExtra = $resolve($extraMap[$key]['secure'] ?? []);

        $pair = $map[$key] ?? null;
        if ($pair === null) {
            // Fall back to the module's primary pair.
            $src = tr_module_sources($module);
            return ['vuln' => $src['vuln'], 'secure' => $src['secure'], 'key' => $key,
                    'vuln_extra' => $vExtra, 'secure_extra' => $sExtra];
        }
        $v = realpath($m . $pair[0]) ?: ($m . $pair[0]);
        $s = realpath($m . $pair[1]) ?: ($m . $pair[1]);
        return ['vuln' => $v, 'secure' => $s, 'key' => $key,
                'vuln_extra' => $vExtra, 'secure_extra' => $sExtra];
    }
}
