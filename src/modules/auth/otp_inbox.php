<?php

declare(strict_types=1);

/**
 * OTP Inbox — simulates an SMS/email delivery mechanism.
 *
 * This page acts as the participant's "phone" or "email inbox" where they can
 * legitimately receive the OTP without exploiting any vulnerability. It shows
 * the OTP code ONLY — it does NOT reveal any CTF flag. The flag is obtained
 * separately by exploiting the auth.l11 vulnerability (finding the OTP in the
 * JavaScript file).
 *
 * Accessible at: /otp-inbox
 * Only shows the OTP when there's an active pending_login session (OTP phase).
 */

$engine = engine();

// This page only works during the OTP phase
if (!Session::has('pending_login')) {
    $content = <<<HTML
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-8 text-center">
            <div class="text-4xl mb-4">📱</div>
            <h2 class="text-xl font-bold text-white mb-2">SMS Inbox</h2>
            <p class="text-slate-400 text-sm">No pending OTP. Log in first to trigger an OTP delivery.</p>
            <a href="/login" class="mt-4 inline-block text-sm text-indigo-400 hover:text-indigo-300">← Back to Login</a>
        </div>
HTML;
    render_layout('SMS Inbox', $content, ['auth_shell' => true]);
    return;
}

$pending = Session::get('pending_login');
$userId  = (int) ($pending['id'] ?? 0);
$who     = htmlspecialchars((string) ($pending['customer_id'] ?? ''));

// Fetch the current login OTP from the database
$pdo  = db();
$stmt = $pdo->prepare(
    'SELECT otp_code, created_at, expires_at FROM otp_tokens
      WHERE user_id = :uid AND purpose = :p AND is_used = 0 AND expires_at > NOW()
      ORDER BY id DESC LIMIT 1'
);
$stmt->execute([':uid' => $userId, ':p' => 'login']);
$otpRow = $stmt->fetch();

if (!$otpRow) {
    $content = <<<HTML
        <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-8 text-center">
            <div class="text-4xl mb-4">📱</div>
            <h2 class="text-xl font-bold text-white mb-2">SMS Inbox</h2>
            <p class="text-slate-400 text-sm">No active OTP found. It may have expired.</p>
            <a href="/login" class="mt-4 inline-block text-sm text-indigo-400 hover:text-indigo-300">← Back to Login</a>
        </div>
HTML;
    render_layout('SMS Inbox', $content, ['auth_shell' => true]);
    return;
}

$otpCode   = htmlspecialchars((string) $otpRow['otp_code']);
$createdAt = htmlspecialchars(substr((string) $otpRow['created_at'], 11, 5)); // HH:MM
$url       = htmlspecialchars(APP_URL);

// NOTE: No flag is exposed here. This is the LEGITIMATE delivery channel.
// The CTF flag for auth.l11 is ONLY available via the otp-validation.js exploit.

$content = <<<HTML
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-6 max-w-md mx-auto">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-xl">📱</div>
            <div>
                <h2 class="text-lg font-bold text-white">SMS Inbox</h2>
                <p class="text-xs text-slate-500">Messages for {$who}</p>
            </div>
        </div>

        <!-- SMS message bubble -->
        <div class="rounded-xl bg-slate-950 ring-1 ring-slate-700 p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-emerald-400">BankSploit</span>
                <span class="text-xs text-slate-600">{$createdAt}</span>
            </div>
            <p class="text-sm text-slate-300 leading-relaxed">
                Your One-Time Password for login verification is:
            </p>
            <div class="mt-3 text-center">
                <span class="inline-block text-3xl font-black tracking-[0.3em] text-white bg-slate-800 rounded-lg px-6 py-3 font-mono">
                    {$otpCode}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-3">
                This code expires in 24 hours. Do not share it with anyone.
            </p>
        </div>

        <div class="flex items-center justify-between">
            <a href="{$url}/login" class="text-sm text-indigo-400 hover:text-indigo-300">← Back to Login</a>
            <span class="text-xs text-slate-600">Sender: BK-SPLOIT</span>
        </div>
    </div>
HTML;

render_layout('SMS Inbox', $content, ['auth_shell' => true]);
