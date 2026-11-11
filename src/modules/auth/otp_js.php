<?php

declare(strict_types=1);

/**
 * Dynamic handler for /public/js/otp-validation.js
 *
 * Serves the OTP validation script with the current session's OTP injected into
 * the SESSION_DATA placeholder. This is the auth.l11 challenge: the OTP leaks
 * inside a JavaScript file that participants must inspect in DevTools (Sources
 * or Network tab) — it is NOT visible in the HTML page source.
 *
 * At L0: OTP in plaintext in the `_otp` field (obvious).
 * At L1: OTP omitted; the `_seed` it was derived from is leaked — recompute it
 *        as uint32(sha256(seed)) % 10000.
 * At L2: OTP omitted; `_otp_check` = sha256(otp) is shipped for a bogus
 *        "client-side pre-check" — brute-force the 10^4 space to recover it.
 * At L3: nothing OTP-related is injected.
 */

require_once __DIR__ . '/_shared.php';

$engine = engine();

// Read the static JS template
$jsPath = __DIR__ . '/../../public/js/otp-validation.js';
$js = file_get_contents($jsPath);

// Build the session payload. The OTP is only injected when:
// 1. The user is in the OTP phase (pending_login exists in session)
// 2. The auth.l11 vuln is active (level < 2)
$sessionPayload = [
    '_t'   => 'otp',
    '_v'   => '2.1.4',
    '_otp' => null,
    '_sid' => session_id() ?: null,
    '_exp' => time() + 600,
];

if (Session::has('pending_login')) {
    $l11Level = $engine->getVulnLevel('auth', 'l11');
    $pending  = Session::get('pending_login');
    $userId   = (int) ($pending['id'] ?? 0);

    if ($l11Level < 3 && $userId > 0) {
        // Fetch the latest unused login OTP for this user
        $pdo  = db();
        $stmt = $pdo->prepare(
            'SELECT otp_code FROM otp_tokens
              WHERE user_id = :uid AND purpose = :p AND is_used = 0 AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':p' => 'login']);
        $otpCode = $stmt->fetchColumn();

        if ($otpCode !== false) {
            // Same graduated leak as the API surface (plaintext / seed / hash),
            // plus the OTP-sealed flag (_flag_enc) — recover the OTP to unseal it.
            $sessionPayload = array_merge(
                $sessionPayload,
                auth_otp_leak_payload($engine, (string) $otpCode, $userId)
            );
            // L2 delivers the OTP hash + sealed flag in RESPONSE HEADERS instead of
            // the body (the body only carries breadcrumbs pointing here).
            foreach (auth_otp_leak_headers($engine, (string) $otpCode, $userId) as $hk => $hv) {
                header($hk . ': ' . $hv);
            }
        }
    }
}

// Replace the placeholder in the JS source
$jsonPayload = json_encode($sessionPayload, JSON_UNESCAPED_SLASHES);
$js = preg_replace(
    '/\/\*SESSION_INJECT_START\*\/.*?\/\*SESSION_INJECT_END\*\//',
    "/*SESSION_INJECT_START*/ {$jsonPayload} /*SESSION_INJECT_END*/",
    $js
);

// Serve as JavaScript with no-cache (OTP is session-specific)
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// CTF (auth.l11): the flag is now embedded INSIDE the SESSION_DATA object in
// the JS file body — participants find OTP + flag together in the same place.
// No X-CTF-Flag header needed; the flag is in _flag (L0) or _meta_flag (L1).

echo $js;
