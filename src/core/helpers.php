<?php

declare(strict_types=1);

/**
 * Shared view + request helpers for BankSploit modules.
 */

// Global registry for services
if (!isset($GLOBALS['__bs'])) {
    $GLOBALS['__bs'] = [];
}

if (!function_exists('bs_register')) {
    function bs_register(string $key, mixed $value): void
    {
        $GLOBALS['__bs'][$key] = $value;
    }
}

if (!function_exists('bs_lookup')) {
    function bs_lookup(string $key): mixed
    {
        return $GLOBALS['__bs'][$key] ?? null;
    }
}

if (!function_exists('render_page')) {
    /**
     * Wrap module HTML in the shared dark BankSploit layout and print it.
     *
     * @param string $title Page <title> (and visible heading).
     * @param string $body  Inner HTML for the card body (already escaped by caller).
     */
    function render_page(string $title, string $body): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $app = htmlspecialchars(APP_NAME);
        $t   = htmlspecialchars($title);
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$app} &middot; {$t}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: #0b1020; color: #e6e6e6; margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 1.5rem;
        }
        .card {
            background: #131a30; border: 1px solid #233056; border-radius: 16px;
            padding: 2.25rem 2.5rem; width: 100%; max-width: 380px;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }
        h1 { margin: 0 0 1.25rem; font-size: 1.5rem; }
        label { display: block; font-size: .8rem; color: #9aa5c4; margin: .75rem 0 .25rem; }
        input[type=text], input[type=password] {
            width: 100%; padding: .6rem .7rem; border-radius: 8px;
            border: 1px solid #233056; background: #0b1020; color: #e6e6e6; font-size: .95rem;
        }
        button {
            margin-top: 1.25rem; width: 100%; padding: .65rem; border: 0; border-radius: 8px;
            background: #7aa2f7; color: #0b1020; font-weight: 600; font-size: .95rem; cursor: pointer;
        }
        button:hover { background: #93b4f9; }
        a { color: #7aa2f7; }
        .alert { padding: .6rem .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1rem; }
        .alert-error { background: #2a1620; border: 1px solid #f7768e; color: #f7768e; }
        .alert-ok    { background: #16241c; border: 1px solid #9ece6a; color: #9ece6a; }
        .muted { color: #9aa5c4; font-size: .8rem; margin-top: 1rem; }
        .warn { color: #f7768e; font-size: .75rem; margin-top: 1.25rem;
                border-top: 1px solid #233056; padding-top: .9rem; }
        code { color: #c0caf5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{$t}</h1>
        {$body}
        <div class="warn">&#9888; Intentionally vulnerable &mdash; local training only.</div>
    </div>
</body>
</html>
HTML;
    }
}

if (!function_exists('redirect')) {
    /**
     * Send a Location redirect (absolute, built from APP_URL) and stop.
     */
    function redirect(string $path): void
    {
        $path = '/' . ltrim($path, '/');
        header('Location: ' . APP_URL . $path);
        exit;
    }
}

// --------------------------------------------------------------------------
// Service registry — index.php registers the shared singletons here so route
// handler files (included by Router) can reach them without globals juggling.
// --------------------------------------------------------------------------

if (!function_exists('engine')) {
    function engine(): TrainingEngine
    {
        return $GLOBALS['__bs']['engine'];
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return $GLOBALS['__bs']['auth'];
    }
}

if (!function_exists('db')) {
    function db(): PDO
    {
        return $GLOBALS['__bs']['db'];
    }
}

if (!function_exists('config_int')) {
    /**
     * Read a tunable integer from system_config (the instructor System Config
     * panel — e.g. otp_expiry_seconds). Falls back to $default when the key is
     * missing, non-numeric, or the lookup fails.
     */
    function config_int(string $key, int $default): int
    {
        try {
            $st = db()->prepare('SELECT config_value FROM system_config WHERE config_key = :k LIMIT 1');
            $st->execute([':k' => $key]);
            $v = $st->fetchColumn();
            return ($v !== false && is_numeric($v)) ? (int) $v : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

/**
 * Whether the request carries a spoofable, client-controlled "admin" signal
 * (an `X-Admin` header, an `is_admin` cookie, or an `is_admin` param). This
 * is NOT trustworthy — it powers the admin.l4 level-1 weakness: at that level
 * it both lets the server-side guard through AND reveals the sidebar Admin
 * link, so flipping the flag demonstrates the full privilege-escalation path.
 */
function client_claims_admin(): bool
{
    $hdr = $_SERVER['HTTP_X_ADMIN'] ?? '';
    if ($hdr !== '' && in_array(strtolower((string) $hdr), ['1', 'true', 'yes'], true)) {
        return true;
    }
    $cookie = $_COOKIE['is_admin'] ?? '';
    if (in_array(strtolower((string) $cookie), ['1', 'true', 'yes'], true)) {
        return true;
    }
    $param = $_REQUEST['is_admin'] ?? '';
    return in_array(strtolower((string) $param), ['1', 'true', 'yes'], true);
}

/**
 * A route placeholder captured by the Router (e.g. {id}).
 */
function route_param(string $key, ?string $default = null): ?string
{
    return Router::param($key, $default);
}

/**
 * Level-aware output. Returns the value RAW while the governing vuln is
 * exploitable (stored/reflected XSS sink) and HTML-escaped once it reaches
 * the secure level. Centralises the XSS branch for every page.
 */
function vuln_out(string $value, string $module, string $vuln): string
{
    return engine()->isVulnerable($module, $vuln)
        ? $value
        : htmlspecialchars($value, ENT_QUOTES);
}

/**
 * statements.g2 — GRADUATED SQL-injection filter term. Centralises how a
 * user-supplied search term is folded into a concatenated WHERE clause, so
 * every g2 call site (statements, transfer history, admin audit) shares one
 * ladder:
 *
 *   level 0: returned raw -> classic in-band UNION / error-based extraction.
 *   level 1: leaky blacklist — strips union/select and comment markers in ONE
 *            case-insensitive pass, but quotes pass through and the single pass
 *            lets split/nested keywords survive (e.g. "UNIunionON" collapses to
 *            "UNION", and inline-comment splitting also rebuilds the keyword).
 *            Raw SQL errors are still shown, so error-based extraction works too once
 *            the filter is bypassed. Demonstrates "we filtered bad words".
 *   level 2: UNION is stripped repeatedly (in-band extraction closed) and the
 *            call site hides SQL errors, but boolean logic + sub-selects still
 *            reach the query, so the flag must be read one character at a time
 *            (boolean-blind). Demonstrates "we only blocked UNION".
 *   level 3: caller MUST use a bound parameter instead — g2_is_secure()
 *            returns true and the call site takes the parameterized path.
 *
 * Returns the (possibly transformed) term to splice into the string query.
 * Only meaningful at levels 0-2; at level 3 callers branch on g2_is_secure().
 */
function g2_term(string $term): string
{
    $level = engine()->getVulnLevel('statements', 'g2');

    if ($level === 1) {
        // @vuln-start: [L1] leaky single-pass keyword blacklist (statements.g2)
        // Strips union/select and comment markers ONCE, case-insensitively, so split
        // or nested keywords survive ("UNIunionON" -> "UNION", "uni/**/on" -> "union")
        // and quotes pass through — a crafted term can still break out of the string.
        return (string) str_ireplace(
            ['union', 'select', '--', '#', '/*', '*/'],
            '',
            $term
        );
        // @vuln-end
    }
    if ($level === 2) {
        // @vuln-start: [L2] UNION stripped repeatedly — closes in-band extraction (statements.g2)
        // Looping until stable defeats nesting, so UNION cannot be rebuilt. Boolean logic
        // and sub-selects still reach the query, so the flag is read one char at a time.
        $out = $term;
        do {
            $prev = $out;
            $out = (string) str_ireplace('union', '', $out);
        } while ($out !== $prev);
        return $out;
        // @vuln-end
    }
    // @vuln-start: [L0] the term is returned exactly as typed (statements.g2)
    // It is spliced straight into the SQL, so a single quote ends the string literal
    // and everything after it runs as SQL. (level 3 never reaches here.)
    return $term;
    // @vuln-end
}

/**
 * True once statements.g2 is fully fixed (level 3) — the call site should
 * use a bound/parameterized query rather than g2_term().
 */
function g2_is_secure(): bool
{
    return !engine()->isVulnerable('statements', 'g2');
}

/**
 * statements.g4 — GRADUATED reflected-XSS reflection for the TEXT context
 * (the "Results for: …" line). The search term is folded into HTML body text:
 *
 *   level 0: raw — any tag/handler fires (<script>…, <img src=x onerror=…>).
 *   level 1: single-pass, case-insensitive strip of the <script>/</script>
 *            tag only. A naive <script>alert(1)</script> is neutralised, but a
 *            nested <scr<script>ipt> collapses back to <script> and survives,
 *            and event-handler tags (<img onerror=…>, <svg onload=…>) are not
 *            touched. Demonstrates "we blocked <script>".
 *   level 2: HTML-encoded here (this text sink is safe) — the intended L2
 *            vector is the ATTRIBUTE sink below, not this one.
 *   level 3: HTML-encoded (secure); a CSP is also sent by the page.
 *
 * Only the TEXT context. The attribute context uses g4_attr().
 */
function g4_text(string $term): string
{
    $level = engine()->getVulnLevel('statements', 'g4');

    // @vuln-start: [L0] raw reflection into HTML text — any payload fires (statements.g4)
    if ($level === 0) {
        return $term;
    }
    // @vuln-end
    // @vuln-start: [L1] single-pass <script></script> strip — nest it or use an event handler (statements.g4)
    if ($level === 1) {
        // Removes the literal <script>/</script> tags once, without re-scanning the
        // result — so a nested <scr<script>ipt> collapses back to <script> and
        // survives, and event-handler tags (<img onerror=…>, <svg onload=…>) and
        // <script src=…> are not touched at all.
        return (string) str_ireplace(['<script>', '</script>'], '', $term);
    }
    // @vuln-end
    // @fix-start: [L2-3] text context HTML-encoded (statements.g4)
    return htmlspecialchars($term, ENT_QUOTES);
    // @fix-end
}

/**
 * statements.g4 — GRADUATED reflected-XSS reflection for the ATTRIBUTE context
 * (the search box value="…"). This is the level-2 vector: at L2 the <> text
 * sink is encoded, but the attribute is reflected unescaped, so a double quote
 * breaks out of value="…" and an auto-firing element runs:
 *   "><svg/onload=…>   or   "><img src=x onerror=…>
 *
 *   level 0/1: encoded here (the text sink is the intended vector at those levels).
 *   level 2:   raw — the quote breaks out of the attribute.
 *   level 3:   encoded (secure).
 */
function g4_attr(string $term): string
{
    $level = engine()->getVulnLevel('statements', 'g4');

    // @vuln-start: [L2] attribute reflected unescaped — break out of value="…" (statements.g4)
    if ($level === 2) {
        return $term;
    }
    // @vuln-end
    // @fix-start: [L0-1,L3] attribute context HTML-encoded (statements.g4)
    return htmlspecialchars($term, ENT_QUOTES);
    // @fix-end
}

/**
 * core.l18 — GRADUATED error disclosure. Centralises how an exception is
 * surfaced to the client across every l18 call site:
 *
 *   level 0: full raw message (SQL text, schema, file paths) — verbose.
 *   level 1: only the driver's SQLSTATE class is shown (e.g. "Database
 *            error [42S22]") — leaks the error category but not the query.
 *   level 2: a generic message PLUS a correlation id; the detail is logged
 *            server-side (error_log) only — minor leak (that an error type
 *            occurred) but no schema.
 *   level 3: a single generic message, nothing logged to the client.
 *
 * @param Throwable $e        the caught exception
 * @param string    $generic  the safe message to show at higher levels
 */
function l18_error(Throwable $e, string $generic = 'An error occurred.'): string
{
    $level = engine()->getVulnLevel('core', 'l18');
    // @vuln-start: verbose errors — raw message / SQLSTATE leaked (core.l18)
    if ($level === 0) {
        return $e->getMessage();                       // fully verbose
    }
    if ($level === 1) {
        $code = $e instanceof PDOException ? (string) ($e->errorInfo[0] ?? $e->getCode()) : (string) $e->getCode();
        return $generic . ' [' . $code . ']';          // leaks SQLSTATE only
    }
    // @vuln-end
    // @fix-start: generic error to client, detail logged server-side only (core.l18)
    if ($level === 2) {
        $ref = substr(bin2hex(random_bytes(4)), 0, 8);
        error_log("[l18:$ref] " . $e->getMessage());   // detail server-side
        return $generic . ' (ref ' . $ref . ')';
    }
    return $generic;                                   // level 3: nothing leaks
    // @fix-end
}

// --------------------------------------------------------------------------
// Lab mode — when on, pages show the yellow vuln-hint banners and Lab UI.
// Toggled via ?lab=1 / ?lab=0 (persisted in the session) so a trainer can
// flip it without a separate settings screen.
// --------------------------------------------------------------------------

if (!function_exists('lab_mode')) {
    function lab_mode(): bool
    {
        // CTF mode: only admin can toggle lab mode via query param.
        if (isset($_GET['lab']) && auth()->isAdmin()) {
            $on = $_GET['lab'] === '1' || strtolower((string) $_GET['lab']) === 'on';
            if (class_exists('Session')) {
                Session::set('lab_mode', $on);
            }
            return $on;
        }
        if (class_exists('Session') && Session::has('lab_mode')) {
            return (bool) Session::get('lab_mode');
        }
        // Default: on in development so the training UI is visible out of the box.
        return APP_ENV !== 'production';
    }

    /**
     * Human label for a vuln level (0-3).
     */
    function level_label(int $level): string
    {
        return match (true) {
            $level >= 3 => 'Secure Mode',
            default     => 'Vuln Mode',
        };
    }

    /**
     * Whether the per-page vulnerability hint banner should AUTO-REVEAL its
     * contents (spoilers shown immediately) or start COLLAPSED behind a
     * "Reveal" button so the learner can try first.
     *
     * Central setting lives in the session ('lab_reveal'), toggled via
     * ?reveal=1 / ?reveal=0 (so the navbar control can flip it globally).
     * Default: collapsed (false) — give the learner a chance to try.
     *
     * Pages may override per-render via lab_hint_banner(..., $forceReveal).
     */
    function lab_reveal_mode(): bool
    {
        // CTF mode: only admin can toggle hint reveal via query param.
        if (isset($_GET['reveal']) && auth()->isAdmin()) {
            $on = $_GET['reveal'] === '1' || strtolower((string) $_GET['reveal']) === 'on';
            if (class_exists('Session')) {
                Session::set('lab_reveal', $on);
            }
            return $on;
        }
        if (class_exists('Session') && Session::has('lab_reveal')) {
            return (bool) Session::get('lab_reveal');
        }
        return false; // default: collapsed, let them try
    }
}

// --------------------------------------------------------------------------
// Request input helpers — unify JSON body, form body and query into one bag
// so handlers work identically for the HTML forms and the JSON API.
// --------------------------------------------------------------------------

if (!function_exists('request_input')) {
    /**
     * Merged input: JSON body (if any) overlaid with $_POST and $_GET.
     * Cached per request.
     *
     * @return array<string, mixed>
     */
    function request_input(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $data = [];
        $raw  = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        $cache = array_merge($_GET, $data, $_POST);
        return $cache;
    }

    function input(string $key, mixed $default = null): mixed
    {
        return request_input()[$key] ?? $default;
    }

    /**
     * True when the client wants JSON (API route or Accept/Content-Type JSON).
     */
    function wants_json(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($uri, '/api/')) {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $ctype  = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($accept, 'application/json')
            || str_contains($ctype, 'application/json');
    }
}

// --------------------------------------------------------------------------
// JSON response helper for the API routes.
// --------------------------------------------------------------------------

if (!function_exists('json_response')) {
    /**
     * Emit a JSON response and stop.
     *
     * @param array<string, mixed> $data
     */
    function json_response(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// --------------------------------------------------------------------------
// Flash messages — one-shot notices carried across a redirect.
// --------------------------------------------------------------------------

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        if (class_exists('Session')) {
            $all = Session::get('_flash', []);
            $all[] = ['type' => $type, 'message' => $message];
            Session::set('_flash', $all);
        }
    }

    /**
     * Pull and clear flashes.
     *
     * @return list<array{type:string,message:string}>
     */
    function take_flashes(): array
    {
        if (!class_exists('Session')) {
            return [];
        }
        $all = Session::get('_flash', []);
        Session::remove('_flash');
        return is_array($all) ? $all : [];
    }
}

// --------------------------------------------------------------------------
// Layout renderer — wraps page content in templates/layout.php (the Tailwind
// app shell). Falls back to the simple card layout for unauthenticated pages
// via the $shell flag.
// --------------------------------------------------------------------------

if (!function_exists('theme_assets')) {
    /**
     * Light/Dark theme support, emitted once in <head> on every page. Dark by
     * default; toggling adds `theme-light` to <html> and the CSS remaps the dark
     * slate palette to light. Choice persists in localStorage, applied pre-paint
     * to avoid a flash. The code-viewer modal stays dark on purpose.
     */
    function theme_assets(): string
    {
        return <<<'HTML'
<style id="bs-theme-light">
html.theme-light body { background: #eef2f7 !important; }
html.theme-light [class*="bg-slate-9"] { background-color: #ffffff !important; }
html.theme-light [class*="bg-slate-8"] { background-color: #e7edf3 !important; }
html.theme-light [class*="ring-slate"] { --tw-ring-color: #cbd5e1 !important; }
html.theme-light [class*="border-slate"] { border-color: #cbd5e1 !important; }
html.theme-light [class*="text-slate-1"],
html.theme-light [class*="text-slate-2"],
html.theme-light [class*="text-slate-3"] { color: #1e293b !important; }
html.theme-light [class*="text-slate-4"] { color: #475569 !important; }
html.theme-light [class*="text-slate-5"],
html.theme-light [class*="text-slate-6"] { color: #64748b !important; }
html.theme-light .text-white { color: #0f172a !important; }
html.theme-light [class*="divide-slate"] > * { border-color: #e2e8f0 !important; }
html.theme-light [class*="text-indigo-"]  { color: #4338ca !important; }
html.theme-light [class*="text-violet-"],
html.theme-light [class*="text-purple-"]  { color: #6d28d9 !important; }
html.theme-light [class*="text-fuchsia-"] { color: #a21caf !important; }
html.theme-light [class*="text-blue-"]     { color: #1d4ed8 !important; }
html.theme-light [class*="text-sky-"]      { color: #0369a1 !important; }
html.theme-light [class*="text-cyan-"]     { color: #0e7490 !important; }
html.theme-light [class*="text-teal-"]     { color: #0f766e !important; }
html.theme-light [class*="text-emerald-"]  { color: #047857 !important; }
html.theme-light [class*="text-green-"]    { color: #15803d !important; }
html.theme-light [class*="text-lime-"]     { color: #4d7c0f !important; }
html.theme-light [class*="text-yellow-"]   { color: #854d0e !important; }
html.theme-light [class*="text-amber-"]    { color: #b45309 !important; }
html.theme-light [class*="text-orange-"]   { color: #c2410c !important; }
html.theme-light [class*="text-red-"]      { color: #b91c1c !important; }
html.theme-light [class*="text-rose-"]     { color: #be123c !important; }
html.theme-light [class*="text-pink-"]     { color: #be185d !important; }
html.theme-light [class*="text-slate-950"] { color: #1e293b !important; }
html.theme-light input[class*="text-slate-100"],
html.theme-light select[class*="text-slate-100"],
html.theme-light textarea[class*="text-slate-100"] { color: #1e293b !important; background-color: #f8fafc !important; }
html.theme-light input::placeholder { color: #64748b !important; }
</style>
<script>
(function(){try{if(localStorage.getItem('bs_theme')==='light')document.documentElement.classList.add('theme-light');}catch(e){}})();
if(!window.bsToggleTheme){window.bsToggleTheme=function(){
  var on=document.documentElement.classList.toggle('theme-light');
  try{localStorage.setItem('bs_theme',on?'light':'dark');}catch(e){}
  document.querySelectorAll('[data-theme-btn]').forEach(function(b){b.textContent=on?'Light':'Dark';});
};}
document.addEventListener('DOMContentLoaded',function(){
  var on=document.documentElement.classList.contains('theme-light');
  document.querySelectorAll('[data-theme-btn]').forEach(function(b){b.textContent=on?'Light':'Dark';});
});
</script>
HTML;
    }

    /** Theme toggle button placed in every page's control area. */
    function theme_toggle_button(): string
    {
        return '<button type="button" data-theme-btn onclick="bsToggleTheme()" '
            . 'title="Switch light / dark theme" '
            . 'class="text-xs px-2.5 py-1 rounded-full ring-1 font-semibold '
            . 'bg-slate-700/40 text-slate-300 ring-slate-600/40 hover:bg-slate-600/60">Dark</button>';
    }
}

if (!function_exists('render_layout')) {
    /**
     * Render a full page using the Tailwind layout shell.
     *
     * @param string               $title   Page title.
     * @param string               $content Inner HTML for the content area.
     * @param array<string, mixed> $opts    'active' (sidebar key), 'auth_shell'
     *                                       (bool: minimal centered shell for
     *                                       login/register), 'vuln_ids' (list of
     *                                       vuln IDs for the lab hint banner).
     */
    function render_layout(string $title, string $content, array $opts = []): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $layout = __DIR__ . '/../templates/layout.php';
        // Expose to the template scope.
        $page_title   = $title;
        $page_content = $content;
        $active       = $opts['active'] ?? '';
        $auth_shell   = (bool) ($opts['auth_shell'] ?? false);
        $focused      = (bool) ($opts['focused'] ?? false);
        $vuln_ids     = $opts['vuln_ids'] ?? [];
        require $layout;
    }

    /**
     * Build the yellow lab-mode hint banner listing the vulns on this page.
     * Returns '' when lab mode is off or there are no vulns to show.
     *
     * @param list<string> $vulnIds  e.g. ['auth.g1','auth.l11']
     */
    function lab_hint_banner(array $vulnIds, ?bool $forceReveal = null): string
    {
        if ($vulnIds === []) {
            return '';
        }
        $eng  = engine();
        $all  = $eng->getAllVulns();
        $rows = '';
        $pageVulns = [];
        $levels    = [];
        foreach ($vulnIds as $ref) {
            [$mod, $vuln] = array_pad(explode('.', $ref, 2), 2, '');
            $e = $all['modules'][$mod][$vuln] ?? null;
            if (!is_array($e)) {
                continue;
            }
            $pageVulns[] = $mod . '.' . $vuln;
            $level = (int) ($e['level'] ?? 0);
            $levels[] = $level;
            $badge = $level >= 3 ? 'bg-emerald-200 text-emerald-900' : 'bg-red-200 text-red-900';
            $state = $level >= 3 ? 'patched' : ('level ' . $level);
            $rows .= '<li class="mb-1"><span class="font-mono text-xs px-1.5 py-0.5 rounded ' . $badge . '">'
                  . htmlspecialchars((string) $e['id']) . ' &middot; ' . htmlspecialchars($state) . '</span> '
                  . '<span class="font-semibold">' . htmlspecialchars((string) $e['title']) . '</span> '
                  . '<span class="text-yellow-800">&mdash; ' . htmlspecialchars((string) ($e['attack_scenario'] ?? '')) . '</span></li>';
        }
        if ($pageVulns === []) {
            return '';
        }

        // Uniform page level (to highlight the active Lx button); null if mixed.
        $uniform   = (count(array_unique($levels)) === 1) ? $levels[0] : null;
        $vulnsAttr = htmlspecialchars(implode(',', $pageVulns), ENT_QUOTES);

        // All lab/teaching features (hints, code viewer, difficulty selector) are now
        // exclusive to the instructor portal (src-superadmin on port 8888).
        // The main CTF app (port 9443) shows no learning aids to users or admins.
        return '';
    }

    /**
     * Central lab controls for the navbar / auth shell: Lab on/off toggle, a
     * Hints reveal toggle (only when Lab is on), and an "All -> L0-L3" control
     * that sets EVERY vuln to a level. The level control is ALWAYS shown
     * (only the yellow hint box is Lab-gated). The active uniform global level
     * is highlighted.
     */
    function lab_controls_html(): string
    {
        if (!class_exists('TrainingEngine')) {
            return '';
        }
        // CTF mode: only admin can see/use lab controls (levels, hints, lab toggle).
        if (!auth()->isAdmin()) {
            return '';
        }
        $eng  = engine();
        $url  = htmlspecialchars(APP_URL);
        $here = $_SERVER['REQUEST_URI'] ?? '/dashboard';
        $toggle = static function (string $key, string $val) use ($here): string {
            $b = preg_replace('/([?&])' . preg_quote($key, '/') . '=[^&]*(&|$)/', '$1', $here);
            $b = rtrim((string) $b, '?&');
            return htmlspecialchars($b . (str_contains($b, '?') ? '&' : '?') . $key . '=' . $val);
        };
        $labOn    = lab_mode();
        $revealOn = lab_reveal_mode();

        // Uniform global level across all vulns (null if mixed) for highlight.
        $levels = [];
        foreach ($eng->getAllVulns()['modules'] as $vs) {
            foreach ($vs as $v) {
                $levels[] = (int) ($v['level'] ?? 0);
            }
        }
        $uniform = (count(array_unique($levels)) === 1) ? $levels[0] : null;

        $labCls = $labOn
            ? 'bg-yellow-400/15 text-yellow-300 ring-yellow-400/30'
            : 'bg-slate-700/40 text-slate-400 ring-slate-600/40';

        $h = '<div class="flex items-center gap-2 flex-wrap">';
        $h .= '<a href="' . $toggle('lab', $labOn ? '0' : '1') . '" '
            . 'title="Toggle Lab Mode (shows/hides the yellow vulnerability hint bar)" '
            . 'class="text-xs px-2.5 py-1 rounded-full ring-1 font-semibold ' . $labCls . '">Lab: ' . ($labOn ? 'ON' : 'OFF') . '</a>';

        if ($labOn) {
            $rvCls = $revealOn
                ? 'bg-emerald-400/15 text-emerald-300 ring-emerald-400/30'
                : 'bg-slate-700/40 text-slate-400 ring-slate-600/40';
            $h .= '<a href="' . $toggle('reveal', $revealOn ? '0' : '1') . '" '
                . 'title="Toggle whether vulnerability hints auto-reveal" '
                . 'class="text-xs px-2.5 py-1 rounded-full ring-1 font-semibold ' . $rvCls . '">Hints: ' . ($revealOn ? 'shown' : 'hidden') . '</a>';
        }

        // Central level control (ALWAYS shown), active level highlighted.
        $btns = '';
        foreach ([0, 1, 2, 3] as $lv) {
            $hl = ($uniform === $lv)
                ? 'bg-indigo-500 text-slate-950 ring-1 ring-indigo-300'
                : 'bg-slate-700/50 text-slate-300 hover:bg-indigo-500 hover:text-slate-950';
            $btns .= '<button type="button" onclick="labSetAllLevels(' . $lv . ')" '
                . 'class="rounded px-1.5 py-0.5 font-semibold ' . $hl . '">L' . $lv . '</button>';
        }
        $h .= '<span class="flex items-center gap-1 text-xs text-slate-400">'
            . '<span class="hidden sm:inline">All&rarr;</span>' . $btns . '</span>';

        $h .= '<script>if(!window.labSetAllLevels){window.labSetAllLevels=function(lv){'
            . 'if(!confirm("Set ALL vulnerabilities to level "+lv+"?"))return;'
            . 'fetch("' . $url . '/api/v1/training/set-level",{method:"POST",headers:{"Content-Type":"application/json"},'
            . 'body:JSON.stringify({level:lv,all:true})}).then(function(){location.reload();});};}</script>';
        $h .= '</div>';
        return $h;
    }
}

if (!function_exists('ctf_hint_banner')) {
    /**
     * Participant-facing vulnerability hint banner.
     *
     * Unlike lab_hint_banner() (admin-only, detailed), this shows a lightweight
     * informational hint controlled by the instructor via the superadmin panel.
     * The instructor can enable/disable hints globally and per-vulnerability,
     * and edit the title + description text.
     *
     * Shown to ALL users (participants) when:
     *  1. global_enabled = true in hints_config.json
     *  2. The specific vuln's hint is enabled
     *  3. The vuln is actually active (level < 3)
     *
     * @param list<string> $vulnIds e.g. ['auth.g1','statements.l1']
     * @param string $layout 'inline' (default, above content) or 'side' (fixed
     *               side panel for narrow pages like login)
     */
    function ctf_hint_banner(array $vulnIds, string $layout = 'inline'): string
    {
        if ($vulnIds === []) {
            return '';
        }

        // Mode gate: hints are suppressed unless the 'hints' feature is on
        // (off in CTF and Pentest presets, on in Training).
        if (!is_feature_enabled('hints')) {
            return '';
        }

        // Load hints config
        static $hintsConfig = null;
        if ($hintsConfig === null) {
            $configPath = dirname(__DIR__) . '/config/hints_config.json';
            if (is_file($configPath)) {
                $hintsConfig = json_decode(file_get_contents($configPath) ?: '{}', true) ?: [];
            } else {
                $hintsConfig = ['global_enabled' => false, 'hints' => []];
            }
        }

        // Global kill switch
        if (empty($hintsConfig['global_enabled'])) {
            return '';
        }

        $eng   = engine();
        $all   = $eng->getAllVulns();
        $hints = $hintsConfig['hints'] ?? [];
        $items = [];

        foreach ($vulnIds as $ref) {
            [$mod, $vuln] = array_pad(explode('.', $ref, 2), 2, '');
            $key = $mod . '.' . $vuln;

            // Skip if vuln not active (patched at L3)
            $vulnEntry = $all['modules'][$mod][$vuln] ?? null;
            if (!is_array($vulnEntry) || ((int) ($vulnEntry['level'] ?? 0)) >= 3) {
                continue;
            }

            // Hints are a CTF aid: only show them for vulnerabilities the
            // instructor selected into the event (ctf_active). A vuln that isn't
            // part of the CTF gets no hint, even if the hints feature is on.
            if (empty($vulnEntry['ctf_active'])) {
                continue;
            }

            // Skip if hint not configured or not enabled
            $hint = $hints[$key] ?? null;
            if (!is_array($hint) || empty($hint['enabled'])) {
                continue;
            }

            // Level-aware hints: when the entry has a "levels" map, show the
            // progressive steps for the vuln's CURRENT level (concept → technique
            // → payload skeleton), so the hint matches what actually runs. The
            // final step (the payload) is tucked behind a "Reveal payload" toggle
            // so players get guidance before the answer. Entries without a
            // "levels" map fall back to the flat "description".
            $level   = (int) ($vulnEntry['level'] ?? 0);
            $title   = $hint['title'] ?? 'Vulnerability';
            $lvlHint = $hint['levels'][(string) $level] ?? null;

            if (is_array($lvlHint)) {
                $sub   = htmlspecialchars((string) ($lvlHint['subtitle'] ?? ('Level ' . $level)));
                $steps = is_array($lvlHint['steps'] ?? null) ? $lvlHint['steps'] : [];
                $n     = count($steps);
                $body  = '<div class="text-xs font-semibold text-sky-400/80 mb-1.5">' . $sub . '</div>';
                $body .= '<ol class="list-decimal list-inside space-y-1.5 text-xs text-sky-300/80 leading-relaxed">';
                foreach ($steps as $i2 => $step) {
                    // Steps may contain small bits of trusted HTML (e.g. <code>);
                    // they are authored in config, not user input.
                    if ($n > 1 && $i2 === $n - 1) {
                        $body .= '<li><details><summary class="cursor-pointer text-sky-300 hover:text-sky-200 font-medium">Reveal payload</summary>'
                            . '<div class="mt-1 pl-1">' . $step . '</div></details></li>';
                    } else {
                        $body .= '<li>' . $step . '</li>';
                    }
                }
                $body .= '</ol>';
            } else {
                $body = '<p class="text-xs text-sky-300/80 leading-relaxed">' . htmlspecialchars((string) ($hint['description'] ?? '')) . '</p>';
            }

            $items[] = ['title' => $title, 'body' => $body];
        }

        if ($items === []) {
            return '';
        }

        // Build hint items HTML
        $itemsHtml = '';
        foreach ($items as $i => $item) {
            if ($i > 0) {
                $itemsHtml .= '<div class="border-t border-sky-400/20 my-3"></div>';
            }
            $itemsHtml .= '<div class="mb-1">';
            $itemsHtml .= '<span class="text-sm font-semibold text-sky-200">' . htmlspecialchars($item['title']) . '</span>';
            $itemsHtml .= '</div>';
            $itemsHtml .= $item['body'];
        }

        $countLabel = count($items) > 1 ? 's' : '';

        // Side layout: fixed panel on the right side of the viewport (for narrow
        // pages like login/register where inline hints crowd the form).
        if ($layout === 'side') {
            return <<<HTML
<div class="fixed top-4 right-4 w-80 max-h-[90vh] overflow-y-auto z-40 rounded-xl border border-sky-400/40 bg-slate-900/95 backdrop-blur-sm p-4 shadow-lg" id="ctf-hints-side">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="text-lg">💡</span>
            <span class="text-sm font-bold text-sky-300">Hint{$countLabel}</span>
        </div>
        <button onclick="document.getElementById('ctf-hints-side').classList.toggle('hidden')" class="text-xs text-slate-500 hover:text-slate-300 px-1.5 py-0.5 rounded hover:bg-slate-800">✕</button>
    </div>
    {$itemsHtml}
</div>
HTML;
        }

        // Inline layout (default): banner above the content area.
        return '<div class="mt-4 mb-2 rounded-xl border border-sky-400/40 bg-sky-500/10 p-4 shadow-sm" id="ctf-hints-inline">'
            . '<div class="flex items-center justify-between mb-2">'
            . '<div class="flex items-center gap-2">'
            . '<span class="text-lg">💡</span>'
            . '<span class="text-sm font-bold text-sky-300">Hint' . $countLabel . '</span>'
            . '</div>'
            . '<button onclick="document.getElementById(\'ctf-hints-inline\').classList.add(\'hidden\')" class="text-xs text-slate-500 hover:text-slate-300 px-1.5 py-0.5 rounded hover:bg-slate-800">✕</button>'
            . '</div>'
            . $itemsHtml
            . '</div>';
    }
}

/**
 * Check if a feature is enabled via feature_toggles table.
 * Defaults to TRUE if the feature doesn't exist in the table.
 *
 * @param string $featureKey Feature identifier (e.g., 'training_lab', 'leaderboard')
 * @return bool TRUE if enabled, FALSE if disabled
 */
if (!function_exists('is_feature_enabled')) {
    function is_feature_enabled(string $featureKey): bool
    {
        try {
            $pdo = bs_lookup('db');
            if (!$pdo) return true; // Fallback to enabled if db not available

            $stmt = $pdo->prepare('SELECT is_enabled FROM feature_toggles WHERE feature_key = :key');
            $stmt->execute([':key' => $featureKey]);
            $result = $stmt->fetchColumn();

            // If feature doesn't exist, default to enabled
            return $result === null ? true : (bool) $result;
        } catch (Exception $e) {
            // Fallback to enabled on error
            return true;
        }
    }
}

if (!function_exists('is_debug_enabled')) {
    /**
     * Debug mode (instructor-controlled, OFF by default). Unlike the generic
     * feature helper, a missing/unknown toggle defaults to DISABLED here, so
     * debug tooling (e.g. the /debug/session viewer) is never exposed unless an
     * instructor explicitly turns it on.
     */
    function is_debug_enabled(): bool
    {
        try {
            $pdo = bs_lookup('db');
            if (!$pdo) return false;
            $stmt = $pdo->prepare('SELECT is_enabled FROM feature_toggles WHERE feature_key = :key');
            $stmt->execute([':key' => 'debug']);
            $result = $stmt->fetchColumn();
            return $result === null ? false : (bool) $result;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('is_lab_open')) {
    /**
     * Master kill switch for the whole lab environment.
     *
     * Stored as the `lab_open` row in feature_toggles. A MISSING row means the
     * lab is OPEN, so an instance that was never switched off keeps working.
     * When this returns false the front controller serves a "lab closed" page
     * for every routed request.
     */
    function is_lab_open(): bool
    {
        try {
            $pdo = bs_lookup('db');
            if (!$pdo) return true;
            $stmt = $pdo->prepare('SELECT is_enabled FROM feature_toggles WHERE feature_key = :key');
            $stmt->execute([':key' => 'lab_open']);
            $result = $stmt->fetchColumn();
            return $result === null ? true : (bool) $result;
        } catch (Exception $e) {
            return true;
        }
    }
}

/**
 * Get all feature toggles for UI display.
 *
 * @return array Array of features with keys: feature_key, is_enabled, description, updated_at
 */
if (!function_exists('get_all_features')) {
    function get_all_features(): array
    {
        try {
            $pdo = bs_lookup('db');
            if (!$pdo) return [];

            $stmt = $pdo->query('SELECT feature_key, is_enabled, description, updated_at FROM feature_toggles ORDER BY feature_key');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Enable/disable a single feature toggle (upsert).
 */
if (!function_exists('set_feature_enabled')) {
    function set_feature_enabled(string $featureKey, bool $enabled): void
    {
        $pdo = bs_lookup('db');
        if (!$pdo) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO feature_toggles (feature_key, is_enabled, description)
             VALUES (:k, :e, :desc)
             ON DUPLICATE KEY UPDATE is_enabled = :e2'
        );
        $stmt->execute([':k' => $featureKey, ':e' => $enabled ? 1 : 0, ':desc' => $featureKey, ':e2' => $enabled ? 1 : 0]);
    }
}

/**
 * ============================================================================
 *  Run modes — CTF / Training / Pentest
 * ----------------------------------------------------------------------------
 *  A "mode" is a named preset over the feature toggles. The toggles remain the
 *  real state the app enforces; a mode just sets them all at once. When the
 *  live toggles don't match any preset the mode reads as "custom". The chosen
 *  mode label is stored in system_config.app_mode for display only.
 * ============================================================================
 */
if (!function_exists('detect_mode')) {
    /** The live mode computed from the toggles: a preset key, or 'custom'. */
    function detect_mode(): string
    {
        $pdo = bs_lookup('db');
        return $pdo instanceof PDO ? Modes::detect($pdo) : 'custom';
    }
}

if (!function_exists('get_app_mode')) {
    /** The stored mode label (may lag the live toggles; detect_mode() is truth). */
    function get_app_mode(): string
    {
        $pdo = bs_lookup('db');
        return $pdo instanceof PDO ? Modes::get($pdo) : 'custom';
    }
}

if (!function_exists('flags_delivered')) {
    /** Whether CTF flags should be surfaced to participants (modal/header/JS). */
    function flags_delivered(): bool
    {
        return is_feature_enabled('flag_delivery');
    }
}
