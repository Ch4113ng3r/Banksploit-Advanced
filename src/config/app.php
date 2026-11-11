<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Host and port are read from environment variables so they are never
 * hardcoded. APP_URL is built dynamically from them.
 */

define('APP_NAME', 'BankSploit');
define('APP_VERSION', '0.1.0');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

define('APP_SCHEME', getenv('APP_SCHEME') ?: 'https');

// Determine APP_HOST: prefer environment variable, fallback to HTTP_HOST from request
$appHost = getenv('APP_HOST') ?: 'localhost';

// If we're running on 0.0.0.0 or the host looks like an IP, extract the actual hostname/IP from the request
if ($appHost === '0.0.0.0' || $_SERVER['HTTP_HOST'] ?? false) {
    $requestHost = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
    $appHost = ($requestHost !== '0.0.0.0' && $requestHost !== '') ? $requestHost : $appHost;
}

define('APP_HOST', $appHost);
define('APP_PORT', getenv('APP_PORT') ?: '443');

// Built dynamically from scheme/host/port. The port is omitted when it's the
// default for the scheme (80 for http, 443 for https) so links read cleanly as
// https://banksploit.com. Keeping these absolute and on the same host the
// browser uses matters when proxying through Burp (which bypasses 'localhost').
define('APP_URL', APP_SCHEME . '://' . APP_HOST
    . (((string) APP_PORT === (APP_SCHEME === 'https' ? '443' : '80')) ? '' : ':' . APP_PORT));
