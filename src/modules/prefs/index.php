<?php

declare(strict_types=1);

/**
 * User preferences (theme / landing page / items-per-page).
 *
 * ============================================================================
 *  VULNERABILITY — sessions.g12 Insecure Deserialization (CWE-502)
 * ----------------------------------------------------------------------------
 *  At vuln levels the preferences are round-tripped through a CLIENT cookie as
 *  a base64-wrapped PHP-serialized string, and unserialize() is called on the
 *  attacker-controlled cookie value. That allows PHP object injection: craft a
 *  serialized instance of a class with a dangerous __wakeup()/__destruct() and
 *  it executes on read.
 *
 *  Gadget shipped for the lab: PrefLogger (see _gadget.php) writes a file in
 *  its __destruct(). A payload cookie of a serialized PrefLogger therefore
 *  drops a file when the cookie is deserialized.
 *
 *  Exploit (set the `bs_prefs` cookie to the base64 of):
 *    O:10:"PrefLogger":2:{s:4:"path";s:23:"/var/www/html/pwned.php";s:7:"content";s:16:"<?php phpinfo();";}
 *
 *  Level 3: preferences are stored/parsed as JSON (json_decode), which cannot
 *  instantiate arbitrary objects.
 * ============================================================================
 */

require_once __DIR__ . '/_gadget.php';

auth()->requireAuth();

$engine = engine();
$pdo    = db();
$me     = auth()->getCurrentUser();
$uid    = (int) $me['id'];
$secure = !$engine->isVulnerable('sessions', 'g12');

$COOKIE = 'bs_prefs';
$notice = null;
$defaults = ['theme' => 'dark', 'landing' => 'dashboard', 'per_page' => '10'];

// ---- save ----
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    auth()->requireSession(); // JWT is read-only
    if (!CSRF::validateToken(input('csrf_token'))) {
        $notice = 'Invalid CSRF token.';
    } else {
        $prefs = [
            'theme'    => (string) input('theme', 'dark'),
            'landing'  => (string) input('landing', 'dashboard'),
            'per_page' => (string) input('per_page', '10'),
        ];
        if ($secure) {
            // SECURE: JSON, both in the cookie and the DB.
            $blob = json_encode($prefs);
        } else {
            // @vuln-start: insecure deserialization — PHP serialize in a cookie (sessions.g12)
            $blob = base64_encode(serialize($prefs));
            // @vuln-end
        }
        setcookie($COOKIE, $blob, ['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
        $pdo->prepare('INSERT INTO user_preferences (user_id, prefs) VALUES (:u,:p) ON DUPLICATE KEY UPDATE prefs = :p2')
            ->execute([':u' => $uid, ':p' => $blob, ':p2' => $blob]);
        $notice = 'Preferences saved.';
        $_COOKIE[$COOKIE] = $blob;
    }
}

// ---- read (the vulnerable sink) ----
$prefs = $defaults;
$raw = (string) ($_COOKIE[$COOKIE] ?? '');
if ($raw !== '') {
    if ($secure) {
        // @fix-start: deserialization — JSON only, cannot instantiate objects (sessions.g12)
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $prefs = array_merge($defaults, $decoded);
        }
        // @fix-end
    } else {
        // @vuln-start: object injection — unserialize() on a client cookie (sessions.g12)
        $decoded = @unserialize(base64_decode($raw, true) ?: $raw);
        if (is_array($decoded)) {
            $prefs = array_merge($defaults, $decoded);
        }
        // @vuln-end
    }
}

// ---------------------------------------------------------------- render ----

$url  = htmlspecialchars(APP_URL);
$csrf = CSRF::getHiddenField();
$alert = $notice !== null
    ? '<div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">' . htmlspecialchars($notice) . '</div>'
    : '';

$theme   = htmlspecialchars((string) ($prefs['theme'] ?? 'dark'));
$landing = htmlspecialchars((string) ($prefs['landing'] ?? 'dashboard'));
$perPage = htmlspecialchars((string) ($prefs['per_page'] ?? '10'));
$storage = $secure ? 'JSON' : 'PHP serialize() (base64 cookie)';

$content = <<<HTML
    <h2 class="text-2xl font-bold text-white mb-6">Preferences</h2>
    {$alert}
    <div class="max-w-lg rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
        <form method="post" action="{$url}/preferences">
            {$csrf}
            <label class="block text-xs text-slate-400 mb-1">Theme</label>
            <select name="theme" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3">
                <option value="dark"{$theme}>Dark</option><option value="light">Light</option>
            </select>
            <label class="block text-xs text-slate-400 mb-1">Landing page</label>
            <input name="landing" value="{$landing}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3">
            <label class="block text-xs text-slate-400 mb-1">Rows per page</label>
            <input name="per_page" value="{$perPage}" class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100">
            <button class="mt-4 w-full rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold py-2.5 transition">Save preferences</button>
        </form>
        <p class="mt-3 text-xs text-slate-500">Stored in the <code>bs_prefs</code> cookie as: <span class="text-slate-300">{$storage}</span></p>
    </div>
HTML;

$content .= lab_hint_banner(['sessions.g12']);

render_layout('Preferences', $content, ['active' => '']);
