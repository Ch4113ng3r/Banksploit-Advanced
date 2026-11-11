<?php

/**
 * Super Admin portal — PHP built-in server router.
 * Serves static assets directly, routes everything else to index.php.
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__;
$path = $root . $uri;

// Serve static files (CSS/JS/images) directly
if ($uri !== '/' && is_file($path)) {
    return false;
}

// Route all other requests to front controller
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
