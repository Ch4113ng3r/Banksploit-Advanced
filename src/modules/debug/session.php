<?php

declare(strict_types=1);

/**
 * Session viewer — UNAUTHENTICATED, on purpose.
 *
 * A teaching tool for SESSION PUZZLING (auth.l25): it prints the current
 * browser session ($_SESSION) live, so students can watch the forgot-password
 * journey overload the auth session. After entering just an email at
 * /forgot-password (vuln levels), reload this page and you'll see a fully
 * populated `user` object — the SAME object a real login creates — which is
 * what lets you open /dashboard without proving anything.
 *
 * No authentication is required to view this page (info disclosure, deliberate).
 * Session viewer (no auth): /debug/session
 */

// Debug mode (instructor-controlled, OFF by default). When debug is disabled the
// viewer does not exist — return 404 so it can't be used or discovered.
if (!function_exists('is_debug_enabled') || !is_debug_enabled()) {
    http_response_code(404);
    echo 'Not Found';
    return;
}

// The bootstrap already started the session; be defensive anyway.
if (class_exists('Session') && method_exists('Session', 'start')) {
    Session::start();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$sid     = session_id();
$session = $_SESSION ?? [];

$pretty = htmlspecialchars(
    json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
    ENT_QUOTES
);

$url = htmlspecialchars(defined('APP_URL') ? APP_URL : '');

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<!-- BankSploit lab — Session viewer (no auth): <?= $url ?>/debug/session -->
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Session Viewer — BankSploit (no auth)</title>
<style>
  body{margin:0;background:#0b1020;color:#e6edf7;font:15px/1.6 -apple-system,Segoe UI,Roboto,sans-serif}
  .wrap{max-width:880px;margin:0 auto;padding:24px 20px 60px}
  h1{font-size:22px;margin:0 0 4px}
  .sub{color:#9aa5c4;font-size:13px;margin-bottom:18px}
  .card{border:1px solid #233056;border-radius:10px;padding:12px 16px;margin:14px 0;background:#131a30;font-size:14px}
  .card.warn{border-color:#b4530977;background:#3a230c}
  .card.ok{border-color:#16653477;background:#0e2a1c}
  code{background:#0d1326;padding:1px 6px;border-radius:5px;font-family:Consolas,monospace;font-size:13px;color:#7dd3fc}
  pre{background:#0d1326;border:1px solid #233056;border-radius:10px;padding:14px 16px;overflow:auto;font-family:Consolas,monospace;font-size:13px;color:#e6edf7}
  a{color:#7aa2f7}
  .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:10px 0}
  .btn{background:#7aa2f7;color:#0b1020;border:0;border-radius:8px;padding:7px 14px;font-weight:700;cursor:pointer;text-decoration:none;font-size:13px}
  .mut{color:#9aa5c4;font-size:12px}
  .kv{font-family:Consolas,monospace;font-size:13px;color:#9aa5c4}
</style></head>
<body><div class="wrap">
  <h1>&#128270; Session Viewer <span class="mut">(unauthenticated)</span></h1>
  <div class="sub">Live dump of the current browser session.</div>

  <div class="row">
    <span class="kv">PHPSESSID = <?= htmlspecialchars($sid !== '' ? $sid : '(none)') ?></span>
    <a class="btn" href="<?= $url ?>/debug/session">&#8635; Refresh</a>
  </div>

  <h3>$_SESSION</h3>
  <pre><?= $pretty ?></pre>

  <div class="card">
    <b>What is a session?</b> HTTP has no memory. On its own, the server has no way to know
    that whoever is asking for <code>/dashboard</code> right now is the same person who typed a
    password a moment ago — each request arrives like a stranger knocking at the door. A
    <b>session</b> is how that gap is bridged: when you sign in, the server keeps a small record
    on <i>its</i> side describing who you are, and hands your browser a single random key — the
    <code>PHPSESSID</code> cookie — to present on every later request. The server reads that key,
    looks up the record, and recognises you. Everything shown above is the contents of that
    record for this browser.
  </div>
</div></body></html>
