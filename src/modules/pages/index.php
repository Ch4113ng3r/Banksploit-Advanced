<?php

declare(strict_types=1);

/**
 * Static "help" page viewer.
 *
 * ============================================================================
 *  VULNERABILITY — templates.g11 Local File Inclusion (CWE-98)
 * ----------------------------------------------------------------------------
 *  At vuln levels the ?page parameter is concatenated into an include() with no
 *  validation:
 *
 *      include __DIR__ . '/content/' . $_GET['page'] . '.php';
 *
 *  Because allow_url_include is On (php.ini) and there is no path
 *  sanitisation, this allows:
 *    - Path traversal:  ?page=../../../../etc/passwd%00      (or ../../config/database)
 *    - PHP filter leak: ?page=php://filter/convert.base64-encode/resource=config/app
 *    - RCE via wrapper: ?page=data://text/plain;base64,<b64 php> (allow_url_include)
 *
 *  Level 1: a naive str_replace('../','') blacklist — bypassable with '....//'.
 *  Level 2: realpath() check but still allows reading any *.php under src/.
 *  Level 3: strict whitelist of known page keys, NO dynamic include.
 * ============================================================================
 */

auth()->requireAuth();

$engine = engine();
$level  = $engine->getVulnLevel('templates', 'g11');
$page   = (string) ($_GET['page'] ?? 'welcome');
$dir    = __DIR__ . '/content/';

ob_start();

if ($level >= 3) {
    // SECURE: whitelist. No user-controlled path ever reaches include().
    $allowed = ['welcome', 'faq', 'security', 'fees'];
    $key = in_array($page, $allowed, true) ? $page : 'welcome';
    include $dir . $key . '.php';
} else {
    $p = $page;
    if ($level === 1) {
        // Naive blacklist (bypass: '....//' collapses to '../' after removal).
        $p = str_replace('../', '', $p);
    } elseif ($level === 2) {
        // realpath() confinement to src/ — still lets you read any .php there
        // (e.g. config/database) which is itself a disclosure.
        $candidate = realpath($dir . $p . '.php');
        $root = realpath(__DIR__ . '/../../');
        if ($candidate !== false && str_starts_with($candidate, (string) $root)) {
            include $candidate;
        } else {
            echo 'Page not found.';
        }
        $body = ob_get_clean();
        render_page_shell($body, $level);
        return;
    }
    // @vuln-start: local file inclusion — user path in include() (templates.g11)
    // Levels 0 & 1: a bare page key (welcome/faq/...) loads from content/, but
    // ANY path-like value is included RAW — the classic LFI. The content/ prefix
    // and .php suffix are NOT applied to path-like input, so wrappers, absolute
    // paths and traversal all resolve:
    //   ?page=/etc/passwd
    //   ?page=../../../../etc/passwd
    //   ?page=php://filter/convert.base64-encode/resource=/var/www/html/config/database.php
    if (preg_match('/^[A-Za-z0-9_-]+$/', $p)) {
        include $dir . $p . '.php';      // normal help page
    } else {
        @include $p;                     // LFI sink: raw user path / wrapper
    }
    // @vuln-end
}

$body = ob_get_clean();
render_page_shell($body, $level);

/**
 * Wrap the included content in the app layout with a page picker + lab banner.
 */
function render_page_shell(string $body, int $level): void
{
    $url = htmlspecialchars(APP_URL);
    $links = '';
    foreach (['welcome' => 'Welcome', 'faq' => 'FAQ', 'security' => 'Security', 'fees' => 'Fees'] as $k => $label) {
        $links .= '<a href="' . $url . '/pages?page=' . $k . '" class="text-indigo-300 hover:text-indigo-200 mr-3 text-sm">' . $label . '</a>';
    }
    $content = '<h2 class="text-2xl font-bold text-white mb-4">Help &amp; Information</h2>'
        . '<div class="mb-4">' . $links . '</div>'
        . '<div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5 text-slate-200 text-sm whitespace-pre-wrap break-all">'
        . $body . '</div>'
        . lab_hint_banner(['templates.g11']);
    render_layout('Help', $content, ['active' => '']);
}
