<?php
/**
 * Router for PHP built-in server.
 * Replicates the .htaccess rewrite rules:
 *   - Serve existing files directly
 *   - Route everything else through index.php
 *   - Pass Authorization header through
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__ . '/src';
$path = $root . $uri;

// Participant deck (read-only, published subset). Served here so standard users
// or anonymous visitors can view it; the instructor publishes it from port 8888.
// /deck            -> view the published deck
// /deck?download=1 -> download a standalone HTML copy of the published deck
if ($uri === '/deck') {
    require __DIR__ . '/deck_config.php';
    $html = @file_get_contents(deck_html_path());
    if ($html === false) {
        http_response_code(503);
        echo 'The presentation is not available yet.';
        return true;
    }
    $cfg      = deck_config_read();
    $published = (isset($cfg['published']) && is_array($cfg['published']))
        ? deck_sanitize_ids($cfg['published']['order'] ?? [])
        : null;
    $download = isset($_GET['download']);

    $server = [
        'mode'    => 'published',
        'config'  => ['order' => $published],   // null when nothing published yet
        'offline' => $download,                 // baked-in copy makes no network calls
    ];
    $inject = '<script>window.DECK_SERVER='
        . json_encode($server, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        . ';</script>';
    $html = str_replace('</head>', $inject . "\n</head>", $html);

    header('Content-Type: text/html; charset=utf-8');
    if ($download) {
        header('Content-Disposition: attachment; filename="BankSploit_Workshop.html"');
    }
    echo $html;
    return true;
}

// Dynamic JS route: otp-validation.js is served via PHP so the OTP can be
// injected per-session (auth.l11 challenge — OTP leaked in JS file).
if ($uri === '/public/js/otp-validation.js') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
    chdir($root);
    require $root . '/index.php';
    return true;
}

// Config and source folders are never served directly. They hold attack
// scenarios and hints, and pages such as /training/code are routes rather than
// files, so they still work. realpath() and strtolower() stop "/public/../config"
// and "/CONFIG" (macOS file names ignore case) from getting around this check.
$privateDirs = ['config', 'core', 'modules', 'scripts', 'templates', 'training'];
$isPrivate = false;
$resolvedPath = realpath($path);
$resolvedRoot = realpath($root);
if ($resolvedPath !== false && $resolvedRoot !== false) {
    $relative = strtolower(ltrim(substr($resolvedPath, strlen($resolvedRoot)), DIRECTORY_SEPARATOR));
    $isPrivate = in_array(explode(DIRECTORY_SEPARATOR, $relative)[0], $privateDirs, true)
        && str_contains($relative, DIRECTORY_SEPARATOR);
}

// Serve existing files and directories (static assets, etc.)
if ($uri !== '/' && !$isPrivate && file_exists($path) && is_file($path)) {
    // Let PHP serve static files with correct MIME type
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'html' => 'text/html',
        'pdf'  => 'application/pdf',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        readfile($path);
        return true;
    }
    return false; // Let PHP's built-in server handle it
}

// Route everything else through the front controller
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
