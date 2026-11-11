<?php

declare(strict_types=1);

/**
 * Side-by-side code viewer.
 *
 * ?module=transfer&vuln=l1 — reads the module's vulnerable + secure source
 * files, parses the @vuln-start/@vuln-end and @fix-start/@fix-end markers, and
 * renders two panes with highlight.js syntax highlighting. Lines inside a
 * marked block get a red (vuln) / green (fix) background. A toggle flips
 * between side-by-side and unified views. Viewing marks the vuln 'reviewed'.
 */

require_once __DIR__ . '/_shared.php';

// The code viewer is reachable WITHOUT login when the instructor has opened it
// to participants (code_viewer) — the auth vulnerabilities (login bypass, OTP
// leak, …) are studied from the pre-login pages, so a participant on the login
// screen must be able to open the source. When the feature is off the viewer
// stays instructor-only, which still requires a logged-in admin (the gate below).
if (!is_feature_enabled('code_viewer')) {
    auth()->requireAuth();
}

// Who may see the code viewer (attack scenarios + vulnerable source):
//  - In Training the instructor opens it to participants via the 'code_viewer'
//    feature toggle, so any authenticated user may view.
//  - Otherwise it's instructors only. That check reads the role from the PHP
//    SESSION, not auth()->getCurrentUser(): a normal browser login also carries
//    an access_token JWT cookie, so getCurrentUser() reports via=jwt even for a
//    real admin. Reading the session lets the genuine admin through while a
//    forged admin JWT (the auth.l12 challenge) — which creates no session — is
//    rejected. It also sidesteps admin_guard(), deliberately bypassable at low
//    admin.l4 levels.
$sessionUser = \Session::get('user');
$isSessionAdmin = is_array($sessionUser) && ($sessionUser['role'] ?? '') === 'admin';
$openToParticipants = is_feature_enabled('code_viewer');
if (!$isSessionAdmin && !$openToParticipants) {
    http_response_code(403);
    render_layout(
        'Access denied',
        '<div class="text-center py-16"><h2 class="text-2xl font-bold text-red-400 mb-4">Access denied</h2>'
        . '<p class="text-slate-400">The code viewer is available to instructors only.</p></div>',
        ['active' => '']
    );
    exit;
}

$pdo    = db();
$url    = htmlspecialchars(APP_URL);
$module = (string) input('module', '');
$vuln   = (string) input('vuln', '');

// Scope + shell. An unauthenticated viewer is on the pre-login pages, so it is
// limited to the login-page (auth module) vulnerabilities and rendered in a
// focused shell with no banking sidebar. A logged-in participant may browse the
// whole catalogue inside the normal app shell.
$isAuthed    = auth()->getCurrentUser() !== null;
$scopeModule = $isAuthed ? null : 'auth';
$shell       = $isAuthed ? ['active' => 'code'] : ['focused' => true];
$scopeLabel  = $isAuthed ? 'the application' : 'the login page';

// Scoped catalogue used by both the index and the per-vuln selector.
$catalogue = array_values(array_filter(
    tr_all_vulns(),
    static fn(array $vv): bool => $scopeModule === null || $vv['module'] === $scopeModule
));

// An out-of-scope target (e.g. a transfer vuln requested while logged out) is
// ignored — fall back to the index for the allowed scope.
if ($scopeModule !== null && $module !== '' && $module !== $scopeModule) {
    $module = '';
    $vuln   = '';
}

// No specific vuln requested → show an index of every vulnerability in the app,
// grouped by module, each linking to its side-by-side view. This is the landing
// page reached from the "Code Viewer" nav item, so the viewer covers the whole
// application rather than only the vulns that happen to be linked elsewhere.
if ($module === '' && $vuln === '') {
    $byModule = [];
    foreach ($catalogue as $vv) {
        $byModule[$vv['module']][] = $vv;
    }
    ksort($byModule);
    $blocks = '';
    $count  = 0;
    foreach ($byModule as $mod => $vulns) {
        $modE  = htmlspecialchars((string) $mod);
        $items = '';
        foreach ($vulns as $vv) {
            $count++;
            $href   = $url . '/training/code?module=' . urlencode($vv['module']) . '&vuln=' . urlencode($vv['vid']);
            $idE    = htmlspecialchars($vv['id']);
            $titleE = htmlspecialchars($vv['title']);
            $metaE  = htmlspecialchars(trim(($vv['owasp'] ?? '') . ' · ' . ($vv['cwe'] ?? ''), ' ·'));
            $items .= <<<HTML
                <a href="{$href}" class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg ring-1 ring-slate-800 hover:ring-indigo-500/40 hover:bg-slate-800/50 transition">
                    <span class="text-sm text-slate-200">{$titleE}<span class="block text-xs text-slate-500">{$metaE}</span></span>
                    <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 shrink-0">{$idE}</span>
                </a>
HTML;
        }
        $blocks .= <<<HTML
            <div class="mb-5">
                <div class="text-xs uppercase tracking-wide text-slate-500 font-semibold mb-2">{$modE}</div>
                <div class="grid sm:grid-cols-2 gap-2">{$items}</div>
            </div>
HTML;
    }
    $indexContent = <<<HTML
        <div class="mb-4">
            <h2 class="text-xl font-bold text-white">Code Viewer</h2>
            <p class="text-sm text-slate-400 mt-1">Pick a vulnerability available on {$scopeLabel} to compare its <span class="text-red-300">vulnerable</span> and <span class="text-emerald-300">secure</span> code side by side. The code shown reflects the level the instructor has configured. <span class="text-slate-500">({$count} available)</span></p>
        </div>
        {$blocks}
HTML;
    render_layout('Code Viewer', $indexContent, $shell);
    exit;
}

$v = tr_find_vuln($module !== '' ? $module . '.' . $vuln : $vuln);
// Resolve the file pair that actually contains THIS vuln's markers (may differ
// from the module's primary pair for secondary vulns), and the key to filter
// the highlight to just this vuln's block.
$src = $v !== null
    ? tr_vuln_sources($v['module'], $v['vid'])
    : tr_module_sources($module);
$onlyKey = $v['key'] ?? null;

// Mark this vuln 'reviewed' (don't downgrade an exploited/reviewed one — handled
// by tr_set_status stamping; we only bump hunting/found up to reviewed).
if ($v !== null && auth()->getCurrentUser() !== null) {
    $cur = tr_progress_map($pdo)[$v['key']]['status'] ?? 'hunting';
    if (in_array($cur, ['hunting', 'found'], true)) {
        tr_set_status($pdo, $v['key'], 'reviewed');
    }
}

// Show ONLY the vulnerable code for the level this vuln is currently set to.
// Markers tagged "[L0-1]" / "[L2]" etc. are filtered to the active level so the
// other levels' implementations are not disclosed. At level 3 the vuln is
// patched, so there is no vulnerable code to show (the fixed code still is).
$level    = (int) (is_array($v) ? ($v['level'] ?? 0) : 0);
$showVuln = $level < 3;

$vulnParsed   = ($src['vuln'] && $showVuln) ? tr_parse_markers($src['vuln'], 'vuln', $onlyKey, $level) : ['lines' => [], 'labels' => []];
$secureParsed = $src['secure'] ? tr_parse_markers($src['secure'], 'fix', $onlyKey) : ['lines' => [], 'labels' => []];

// If the key-specific filter matched nothing (e.g. a marker without a
// "(module.key)" suffix), fall back to the marked blocks of that kind — still
// filtered by level, and still only the marked code, never the whole file.
$cc_has_hl = static fn(array $p): bool => (bool) array_filter($p['lines'], static fn($l) => !empty($l['hl']));
if ($src['vuln'] && $showVuln && !$cc_has_hl($vulnParsed)) {
    $vulnParsed = tr_parse_markers($src['vuln'], 'vuln', null, $level);
}
if ($src['secure'] && !$cc_has_hl($secureParsed)) {
    $secureParsed = tr_parse_markers($src['secure'], 'fix', null);
}

/**
 * Render ONLY the marked (vulnerable / fixed) lines of a file — not the whole
 * source — so the viewer stays compact and doesn't reveal unrelated code. A
 * break in line numbers is shown as an ellipsis so it's clear code was skipped.
 */
function cc_render(array $parsed, string $hlClass): string
{
    $out   = '';
    $prev  = null;
    $shown = 0;
    foreach ($parsed['lines'] as $ln) {
        if (empty($ln['hl'])) {
            continue;
        }
        if ($prev !== null && $ln['n'] > $prev + 1) {
            $out .= '<div class="cc-gap">&#8943;</div>';
        }
        $text = htmlspecialchars($ln['text'] === '' ? ' ' : $ln['text'], ENT_QUOTES);
        $out .= '<div class="cc-line ' . $hlClass . '"><span class="cc-num">' . $ln['n'] . '</span>' . $text . '</div>';
        $prev = $ln['n'];
        $shown++;
    }
    if ($shown === 0) {
        $out = '<div class="cc-line"><span class="cc-num">&ndash;</span>(no marked block for this vulnerability in this file)</div>';
    }
    return $out;
}

$vulnFile   = $src['vuln'] ? htmlspecialchars(str_replace(__DIR__ . '/../', '', $src['vuln'])) : '(none)';
$secureFile = $src['secure'] ? htmlspecialchars(str_replace(__DIR__ . '/../', '', $src['secure'])) : '(none)';
$levelName  = ['Fully vulnerable', 'Weak', 'Partial', 'Secure'][$level] ?? (string) $level;
$vulnBody   = $showVuln
    ? cc_render($vulnParsed, 'cc-vuln')
    : '<div class="cc-line"><span class="cc-num">&ndash;</span>Patched at level 3 (secure) &mdash; no vulnerable code at this level.</div>';
// Additional files whose marked blocks also belong on the vulnerable pane (a vuln
// whose story spans more than one file). Each is shown under its own filename.
if ($showVuln) {
    foreach (($src['vuln_extra'] ?? []) as $extraFile) {
        $ep = tr_parse_markers($extraFile, 'vuln', $onlyKey, $level);
        if (!$cc_has_hl($ep)) {
            continue;
        }
        $efName = htmlspecialchars(str_replace(__DIR__ . '/../', '', $extraFile));
        $vulnBody .= '<div class="cc-filehdr">' . $efName . '</div>' . cc_render($ep, 'cc-vuln');
    }
}
$secureBody = cc_render($secureParsed, 'cc-fix');
// Additional files whose fixed/secure blocks also belong on the secure pane
// (level-filtered, so a level-specific fix only shows at its level).
foreach (($src['secure_extra'] ?? []) as $extraFile) {
    $ep = tr_parse_markers($extraFile, 'fix', $onlyKey, $level);
    if (!$cc_has_hl($ep)) {
        continue;
    }
    $efName = htmlspecialchars(str_replace(__DIR__ . '/../', '', $extraFile));
    $secureBody .= '<div class="cc-filehdr">' . $efName . '</div>' . cc_render($ep, 'cc-fix');
}

$title = $v ? htmlspecialchars($v['id'] . ' · ' . $v['title']) : 'Code viewer';
$meta  = $v ? htmlspecialchars($v['owasp'] . ' · ' . $v['cwe'] . ' · ' . $v['category']) : '';
$backUrl = $url . '/training/code';

// Vuln selector — pick another vulnerability available in this scope.
$options = '';
foreach ($catalogue as $vv) {
    $optHref = $url . '/training/code?module=' . urlencode($vv['module']) . '&vuln=' . urlencode($vv['vid']);
    $sel     = ($v && $vv['key'] === $v['key']) ? ' selected' : '';
    $optLbl  = htmlspecialchars($vv['id'] . ' · ' . $vv['title']);
    $options .= '<option value="' . htmlspecialchars($optHref, ENT_QUOTES) . '"' . $sel . '>' . $optLbl . '</option>';
}
$selector = '<select onchange="if(this.value)location.href=this.value" class="rounded-lg bg-slate-800 ring-1 ring-slate-700 text-slate-200 text-sm px-2 py-1.5 max-w-xs">' . $options . '</select>';

$content = <<<HTML
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-3">
            <div>
                <h2 class="text-xl font-bold text-white">{$title}</h2>
                <div class="text-xs text-slate-500 mt-0.5">{$meta}</div>
            </div>
            {$selector}
        </div>
        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-xs text-slate-400">
                <input type="checkbox" id="unifiedToggle" onchange="toggleView()" class="accent-indigo-500"> Unified diff
            </label>
            <a href="{$backUrl}" class="text-sm text-slate-400 hover:text-slate-200">← All</a>
        </div>
    </div>

    <!-- note (kept deliberately brief — the exploit is intentionally NOT spelled
         out here so the viewer doesn't give away the answer) -->
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-4 mb-4">
        <p class="text-sm text-slate-400">Vulnerable code for the selected level on the left, the secure version on the right. Exploit steps are intentionally not shown here — work them out, or see the hints on the Lab.</p>
    </div>

    <!-- side-by-side -->
    <div id="sideBySide" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="cc-panel">
            <div class="cc-head cc-head-vuln">&#9760; Vulnerable · <span class="opacity-80 font-normal">L{$level} {$levelName}</span> · <span class="font-mono">{$vulnFile}</span></div>
            <pre class="cc-pre"><code class="language-php">{$vulnBody}</code></pre>
        </div>
        <div class="cc-panel">
            <div class="cc-head cc-head-fix">&#128737; Secure · <span class="font-mono">{$secureFile}</span></div>
            <pre class="cc-pre"><code class="language-php">{$secureBody}</code></pre>
        </div>
    </div>

    <!-- unified (hidden by default) -->
    <div id="unified" class="hidden">
        <div class="cc-panel">
            <div class="cc-head cc-head-vuln">&#9760; Vulnerable · <span class="opacity-80 font-normal">L{$level} {$levelName}</span> · <span class="font-mono">{$vulnFile}</span></div>
            <pre class="cc-pre"><code class="language-php">{$vulnBody}</code></pre>
        </div>
        <div class="cc-panel mt-4">
            <div class="cc-head cc-head-fix">&#128737; Secure · <span class="font-mono">{$secureFile}</span></div>
            <pre class="cc-pre"><code class="language-php">{$secureBody}</code></pre>
        </div>
    </div>

    <!-- Self-hosted highlight.js (vendored) — no external CDN. -->
    <link rel="stylesheet" href="/public/vendor/highlight/monokai.min.css">
    <script src="/public/vendor/highlight/highlight.min.js"></script>
    <script src="/public/vendor/highlight/php.min.js"></script>
    <style>
        .cc-panel { background:#272822; border:1px solid #3b3c35; border-radius:12px; overflow:hidden; }
        .cc-head { padding:.5rem .9rem; font-size:.75rem; font-weight:600; }
        .cc-head-vuln { background:#3a1d22; color:#ff8a9b; }
        .cc-head-fix  { background:#16321f; color:#8ee6a8; }
        .cc-pre { margin:0; padding:0; overflow-x:auto; font-size:12.5px; line-height:1.55; color:#f8f8f2; }
        .cc-pre code { display:block; padding:0; background:transparent !important; color:#f8f8f2; }
        .cc-line { padding:0 .9rem 0 0; white-space:pre; }
        .cc-num { display:inline-block; width:2.6rem; text-align:right; margin-right:.9rem; color:#75715e; user-select:none; }
        .cc-vuln { background:rgba(255,77,90,.16); border-left:3px solid #ff4d5a; }
        .cc-fix  { background:rgba(80,220,130,.14); border-left:3px solid #3ddc7a; }
        .cc-gap  { color:#75715e; text-align:center; font-size:11px; padding:2px 0; user-select:none; }
        .cc-filehdr { color:#c3cbe0; font-family:ui-monospace,Menlo,monospace; font-size:11px; padding:8px .9rem 3px; margin-top:8px; border-top:1px solid #2a2f3a; }
    </style>
    <script>
        // highlight.js over each code block, preserving our per-line divs.
        document.querySelectorAll('pre code').forEach(el => {
            // highlight only the text, keep line backgrounds: run hljs per line span.
            el.querySelectorAll('.cc-line').forEach(line => {
                const num = line.querySelector('.cc-num');
                const code = document.createElement('span');
                // move everything except the line-number into a highlightable span
                const text = line.textContent.replace(/^\s*\d+\s/, '');
                code.textContent = text;
                try { code.innerHTML = hljs.highlight(text, {language:'php'}).value; } catch(e){}
                line.innerHTML = '';
                line.appendChild(num);
                line.appendChild(code);
            });
        });
        function toggleView() {
            const unified = document.getElementById('unifiedToggle').checked;
            document.getElementById('sideBySide').classList.toggle('hidden', unified);
            document.getElementById('unified').classList.toggle('hidden', !unified);
        }
    </script>
HTML;

render_layout('Code Viewer', $content, $shell);
