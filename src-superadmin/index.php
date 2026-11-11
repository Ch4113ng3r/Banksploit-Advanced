<?php

declare(strict_types=1);

/**
 * BankSploit CTF Control Panel — Front Controller.
 *
 * Completely isolated from the CTF app:
 * - Separate PHP server (port 8888)
 * - Separate session namespace
 * - Config-file credentials (not in DB)
 * - No vulnerability code paths
 */

// --- Constants (NOT shared with CTF app — we define our own) -----------------
define('APP_NAME', 'BankSploit CTF Control Panel');
define('APP_VERSION', '0.1.0');
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN));
define('APP_SCHEME', 'http');

// Determine APP_HOST: extract from request if available, otherwise fallback
$superadminHost = '127.0.0.1';
if ($_SERVER['HTTP_HOST'] ?? false) {
    $requestHost = explode(':', $_SERVER['HTTP_HOST'])[0];
    if ($requestHost !== '0.0.0.0' && $requestHost !== '') {
        $superadminHost = $requestHost;
    }
}
define('APP_HOST', $superadminHost);
define('APP_PORT', getenv('SUPERADMIN_PORT') ?: '8888');
define('APP_URL', APP_SCHEME . '://' . APP_HOST . ':' . APP_PORT);
define('SUPERADMIN_MODE', true);

$projectRoot = dirname(__DIR__);

// --- Load shared core from the CTF app ---------------------------------------
require $projectRoot . '/src/core/TrainingEngine.php';
require $projectRoot . '/src/core/Flags.php';
require $projectRoot . '/src/core/Modes.php';

// Minimal helpers we need (bs_register, engine(), db())
// We can't include the full helpers.php without conflicting, so define our own minimal set.
$__registry = [];

function bs_register(string $key, mixed $value): void
{
    global $__registry;
    $__registry[$key] = $value;
}

function engine(): TrainingEngine
{
    global $__registry;
    return $__registry['engine'];
}

function db(): PDO
{
    global $__registry;
    return $__registry['db'];
}

// --- Database ----------------------------------------------------------------
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'banksploit';
$dbUser = getenv('DB_USER') ?: 'banksploit';
$dbPass = getenv('DB_PASS') ?: 'banksploit_pass';

$pdo = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

$engine = TrainingEngine::getInstance();
bs_register('db', $pdo);
bs_register('engine', $engine);

// --- Session (separate namespace) -------------------------------------------
session_name('SUPERADMIN_SESSID');
session_start();

// --- Auth --------------------------------------------------------------------
require __DIR__ . '/core/SuperAdminAuth.php';
SuperAdminAuth::init();

// --- CSRF (simple implementation) -------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    return $token !== null && hash_equals(csrf_token(), $token);
}

// --- Template helper ---------------------------------------------------------
function sa_render(string $title, string $content, string $active = ''): void
{
    require __DIR__ . '/templates/layout.php';
    exit;
}

// --- Routing -----------------------------------------------------------------
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Public routes (no auth required)
if ($path === '/login') {
    require __DIR__ . '/pages/login.php';
    exit;
}

if ($path === '/logout') {
    SuperAdminAuth::logout();
    header('Location: /login');
    exit;
}

// All other routes require auth
SuperAdminAuth::requireAuth();

// API routes (return JSON)
$apiFile = match ($path) {
    '/api/users'            => 'api-users.php',
    '/api/flags'            => 'api-flags.php',
    '/api/flag-reset'       => 'api-flag-reset.php',
    '/api/leaderboard'      => 'api-leaderboard.php',
    '/api/leaderboard-hide' => 'api-leaderboard-hide.php',
    default                 => null,
};

if ($apiFile !== null && is_file(__DIR__ . '/pages/' . $apiFile)) {
    require __DIR__ . '/pages/' . $apiFile;
    exit;
}

$pageFile = match ($path) {
    '/', '/dashboard'        => 'dashboard.php',
    '/modes'                 => 'modes.php',
    '/vuln-config'           => 'vuln_config.php',
    '/ctf-vulns'             => 'ctf_vulns.php',
    '/verify'                => 'verify.php',
    '/system-config'         => 'system_config.php',
    '/hints'                 => 'hints.php',
    '/users'                 => 'users.php',
    '/bulk-import'           => 'bulk_import.php',
    '/bulk-replace-users'    => 'bulk_replace_users.php',
    '/transactions'          => 'transactions.php',
    '/progress'              => 'progress.php',
    '/flags'                 => 'flags.php',
    '/flag-reset'            => 'flag_reset.php',
    '/scoring'               => 'scoring.php',
    '/leaderboard'           => 'leaderboard.php',
    default                  => null,
};

if ($pageFile !== null && is_file(__DIR__ . '/pages/' . $pageFile)) {
    require __DIR__ . '/pages/' . $pageFile;
    exit;
}

// 404
http_response_code(404);
sa_render('Not Found', '<p class="text-slate-400">Page not found.</p>');
