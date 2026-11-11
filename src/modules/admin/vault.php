<?php

declare(strict_types=1);

/**
 * CTF Flag Vault — Admin-only page (JWT forging proof).
 *
 * This page is only visible to users with role=admin (either real admins or
 * users who successfully forged an admin JWT via auth.l12).
 *
 * Shows the flag for auth.l12 (JWT Algorithm Negotiation vulnerability).
 * Demonstrates that forging a JWT with role=admin grants access to this
 * restricted resource.
 */

require_once __DIR__ . '/_shared.php';

admin_guard();

$pdo  = db();
$user = auth()->getCurrentUser();
$userId = (int) ($user['id'] ?? 0);

// Generate the flag for this user
$flag = Flags::generate('auth.l12', $userId);

// Prepare exploit info
$exploitMethod = ($user['via'] ?? '') === 'jwt' ? 'JWT Forging' : 'Real Admin Account';
$exploitClass = ($user['via'] ?? '') === 'jwt'
    ? 'bg-amber-500/10 text-amber-300 ring-1 ring-amber-500/30'
    : 'bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-500/30';

$customerId = htmlspecialchars((string) ($user['customer_id'] ?? 'Unknown'));
$name = htmlspecialchars((string) ($user['name'] ?? 'Unknown'));

$content = <<<HTML
<div class="space-y-6">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white">🔓 CTF Vault</h2>
            <p class="text-slate-400 text-sm mt-1">You have successfully exploited the JWT vulnerability</p>
        </div>
        <div class="text-right px-4 py-3 rounded-lg {$exploitClass}">
            <div class="text-xs uppercase tracking-wide font-semibold mb-1">Access Method</div>
            <div class="text-lg font-bold">{$exploitMethod}</div>
        </div>
    </div>

    <!-- Vulnerability Info Card -->
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Vulnerability: JWT Algorithm Negotiation</h3>
        <div class="space-y-3 text-sm text-slate-300">
            <div class="flex items-start gap-3">
                <span class="text-indigo-400 font-semibold mt-0.5">ID:</span>
                <span class="font-mono text-amber-300">auth.l12</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-indigo-400 font-semibold mt-0.5">Type:</span>
                <span>CWE-347: Improper Verification of Cryptographic Signature</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-indigo-400 font-semibold mt-0.5">Severity:</span>
                <span class="px-2.5 py-0.5 rounded-full bg-red-500/20 text-red-300 text-xs font-semibold">CRITICAL</span>
            </div>
            <div class="flex items-start gap-3">
                <span class="text-indigo-400 font-semibold mt-0.5">Description:</span>
                <span>The server accepts JWT tokens with <code class="bg-slate-800 px-2 py-1 rounded text-xs">alg: "none"</code>, allowing unsigned token forgery without signature verification.</span>
            </div>
        </div>
    </div>

    <!-- Current Access Info -->
    <div class="rounded-xl bg-indigo-900/30 ring-1 ring-indigo-500/30 p-6">
        <h3 class="text-lg font-semibold text-indigo-200 mb-4">Your Access Details</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-slate-400 text-xs uppercase tracking-wide mb-1">Customer ID</div>
                <div class="font-mono text-indigo-300 font-semibold">{$customerId}</div>
            </div>
            <div>
                <div class="text-slate-400 text-xs uppercase tracking-wide mb-1">Name</div>
                <div class="text-indigo-300 font-semibold">{$name}</div>
            </div>
            <div class="col-span-2">
                <div class="text-slate-400 text-xs uppercase tracking-wide mb-1">Current Role</div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-xs font-semibold">admin</span>
                    <span class="text-slate-500">← Claimed via forged JWT</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Flag Display -->
    <div class="rounded-xl bg-gradient-to-br from-slate-900/80 to-slate-900/40 ring-1 ring-slate-700 p-8">
        <div class="text-center">
            <div class="text-5xl mb-4">🚩</div>
            <h3 class="text-2xl font-bold text-white mb-2">Flag Captured!</h3>
            <p class="text-slate-400 text-sm mb-6">You have successfully exploited the JWT vulnerability.</p>

            <!-- Flag Box -->
            <div class="bg-slate-800/60 rounded-lg border border-amber-500/30 p-6 mb-6">
                <div class="text-xs text-slate-400 uppercase tracking-wide mb-2">Your Flag</div>
                <div class="font-mono text-2xl text-amber-300 font-bold break-all select-all">{$flag}</div>
                <div class="text-xs text-slate-500 mt-3">Click to copy or use the copy button below</div>
            </div>

            <!-- Copy Button -->
            <button onclick="copyFlag()" class="px-6 py-3 rounded-lg bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-semibold transition transform hover:scale-105 active:scale-95">
                📋 Copy Flag
            </button>
        </div>
    </div>

    <!-- Next Steps -->
    <div class="rounded-xl bg-emerald-900/20 ring-1 ring-emerald-500/30 p-6">
        <h3 class="text-lg font-semibold text-emerald-300 mb-4">📝 Next Steps</h3>
        <ol class="space-y-3 text-sm text-slate-300 list-decimal list-inside">
            <li>Copy your flag from above</li>
            <li>Go to <a href="/training" class="text-emerald-400 hover:text-emerald-300 underline">/training</a> (Flag Submission)</li>
            <li>Find "JWT alg:none (auth.l12)" vulnerability</li>
            <li>Paste your flag in the submission box</li>
            <li>Click Submit to claim your points!</li>
        </ol>
    </div>

    <!-- Technical Details -->
    <details class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-6 cursor-pointer">
        <summary class="font-semibold text-white hover:text-slate-200 transition">📚 How You Got Here (Technical Explanation)</summary>
        <div class="mt-4 space-y-3 text-sm text-slate-400">
            <p><strong class="text-slate-300">Step 1: Forged a JWT with role=admin</strong></p>
            <div class="bg-slate-800/40 p-3 rounded font-mono text-xs text-cyan-300 overflow-x-auto">
eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0.eyJzdWIiOiAiWW91clVzZXJJRCIsICJyb2xlIjogImFkbWluIn0.
            </div>

            <p><strong class="text-slate-300">Step 2: Sent token as Bearer authentication</strong></p>
            <div class="bg-slate-800/40 p-3 rounded font-mono text-xs text-green-300">
GET /admin/vault HTTP/1.1<br>
Authorization: Bearer &lt;YOUR_FORGED_TOKEN&gt;
            </div>

            <p><strong class="text-slate-300">Step 3: Server verified JWT</strong></p>
            <div class="bg-slate-800/40 p-3 rounded font-mono text-xs text-slate-400">
verifyJWT() checked:<br>
✓ alg is "none" → accepted (vulnerability!)<br>
✓ role claim is "admin" → access granted<br>
✓ Generated flag for you
            </div>

            <p><strong class="text-slate-300">Why This Is Vulnerable:</strong></p>
            <ul class="list-disc list-inside space-y-2 text-slate-400">
                <li>Server accepts <code class="bg-slate-800 px-1 rounded text-xs text-amber-300">alg: "none"</code> without verifying signature</li>
                <li>Anyone can forge a token with any role claim</li>
                <li>No secret key needed to create valid tokens</li>
                <li>Leads to complete authentication bypass and privilege escalation</li>
            </ul>
        </div>
    </details>

    <!-- Leaderboard Link -->
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-6 text-center">
        <p class="text-slate-400 text-sm mb-3">After submitting your flag:</p>
        <a href="/leaderboard" class="inline-block px-6 py-3 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold transition">
            🏆 View Leaderboard
        </a>
    </div>
</div>

<script>
function copyFlag() {
    const flagText = '{$flag}';
    const btn = event.target;

    try {
        const textarea = document.createElement('textarea');
        textarea.value = flagText;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        btn.textContent = '✅ Copied!';
        btn.style.transform = 'scale(1.05)';
        setTimeout(() => {
            btn.textContent = '📋 Copy Flag';
            btn.style.transform = 'scale(1)';
        }, 2000);
    } catch (e) {
        btn.textContent = '❌ Failed';
        setTimeout(() => { btn.textContent = '📋 Copy Flag'; }, 2000);
    }
}

// Show modal only on first visit (track with sessionStorage)
document.addEventListener('DOMContentLoaded', function() {
    const flagShownKey = 'flag_shown_auth_l12';
    const flagAlreadyShown = sessionStorage.getItem(flagShownKey) === 'true';

    if (!flagAlreadyShown && window.displayFlag) {
        window.displayFlag('{$flag}', 'auth.l12');
        sessionStorage.setItem(flagShownKey, 'true');
    }
});
</script>
HTML;

render_layout('CTF Vault — JWT Exploit', $content, ['active' => 'admin-vault']);
