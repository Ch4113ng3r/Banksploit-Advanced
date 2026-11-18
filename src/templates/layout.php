<?php

declare(strict_types=1);

/**
 * Base HTML layout (Tailwind via CDN).
 *
 * Rendered through helpers.php render_layout(). Expected in-scope variables:
 *   string $page_title
 *   string $page_content   pre-rendered inner HTML for the content area
 *   string $active         sidebar key to highlight (e.g. 'dashboard')
 *   bool   $auth_shell      true -> minimal centered shell (login/register)
 *   array  $vuln_ids        vuln refs for the lab hint banner (handled by page)
 *
 * Two shells share one <head>:
 *   - auth_shell: centered card, no navbar/sidebar (pre-login pages)
 *   - app shell:  top navbar + left sidebar + content + footer (post-login)
 */

$appName = htmlspecialchars(APP_NAME);
$title   = htmlspecialchars($page_title);
$url     = htmlspecialchars(APP_URL);

// Current user + global "mode" badge (instructor/admin only).
$user      = function_exists('auth') ? auth()->getCurrentUser() : null;
$isInstructor = $user && (($user['role'] ?? '') === 'admin' || ($user['role'] ?? '') === 'instructor');
// Only show mode/lab controls if user is session-authenticated (not JWT forged)
$showLabControls = !($auth_shell ?? false) && ($user && ($user['via'] ?? '') !== 'jwt');
$labOn     = $showLabControls && $isInstructor && lab_mode();
$g1Level   = function_exists('engine') ? engine()->getVulnLevel('auth', 'g1') : 0;
$modeLabel = $showLabControls && $isInstructor ? level_label($g1Level) : '';
$modeClass = $g1Level >= 3
    ? 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30'
    : 'bg-red-500/15 text-red-300 ring-red-500/30';

// Sidebar navigation: [key, label, path, icon-svg-path-d].
$nav = [
    ['dashboard',    'Dashboard',     '/dashboard',     'M3 12l9-9 9 9M5 10v10h14V10'],
    ['transfer',     'Transfer',      '/transfer',      'M4 17h16M4 17l4-4m-4 4l4 4M20 7H4m16 0l-4-4m4 4l-4 4'],
    ['payments',     'Payments',      '/payments',      'M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z'],
    ['beneficiaries','Beneficiaries', '/beneficiaries', 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4'],
    ['cards',        'Cards',         '/cards',         'M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z'],
    ['loans',        'Loans',         '/loans',         'M12 8c-1.66 0-3 .9-3 2s1.34 2 3 2 3 .9 3 2-1.34 2-3 2m0-8V6m0 12v-2'],
    ['statements',   'Statements',    '/statements',    'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['leaderboard',  'Leaderboard',   '/leaderboard',   'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
];
$navTools = [
    ['lab',       'Flag Submission',   '/lab',     'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2z'],
    // Request Inspector disabled — logging removed from middleware
];
// Code Viewer (vulnerable vs secure source) — a Training aid, shown to
// participants only when the instructor has enabled the 'code_viewer' feature.
if (function_exists('is_feature_enabled') && is_feature_enabled('code_viewer')) {
    $navTools[] = ['code', 'Code Viewer', '/training/code', 'M10 20l4-16M6 16l-4-4 4-4m12 0l4 4-4 4'];
}

// Admin submenu items (shown when viewing admin pages)
$adminNav = [
    ['admin-dashboard', 'Dashboard',    '/admin',        'M12 1l9 4v6c0 5-3.8 9.4-9 11-5.2-1.6-9-6-9-11V5l9-4z'],
    ['admin-vault',     'CTF Vault',    '/admin/vault',  'M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['admin-audit',     'Audit Log',    '/admin/audit',  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
];

function nav_item(array $item, string $active): string
{
    [$key, $label, $path, $d] = $item;
    $isActive = $key === $active;
    $cls = $isActive
        ? 'bg-indigo-600/20 text-indigo-200 ring-1 ring-indigo-500/30'
        : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200';
    $href = htmlspecialchars(APP_URL . $path);
    $lab  = htmlspecialchars($label);
    return <<<HTML
        <a href="{$href}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {$cls}">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{$d}"/></svg>
            <span>{$lab}</span>
        </a>
HTML;
}

?><!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $appName ?> &middot; <?= $title ?></title>
    <!-- Tailwind is self-hosted (vendored) so the app renders identically in any
         browser / proxy (e.g. Burp's embedded browser) and fully offline. -->
    <script src="/public/vendor/tailwind.js"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } } };
    </script>
    <style>
        body { background: #0b1020; }
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-thumb { background: #233056; border-radius: 6px; }
    </style>
    <?= theme_assets() ?>
</head>
<body class="h-full text-slate-200 antialiased">
<?php if ($auth_shell): ?>
    <!-- ===================== AUTH SHELL (pre-login) ===================== -->
    <?php if (function_exists('is_debug_enabled') && is_debug_enabled()): ?>
    <!-- [BankSploit lab] Unauthenticated session viewer (watch session puzzling, auth.l25): <?= htmlspecialchars(APP_URL) ?>/debug/session -->
    <?php endif; ?>

    <div class="min-h-full flex flex-col items-center justify-center px-4 py-10">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-sky-400 flex items-center justify-center font-black text-slate-900">B</div>
            <span class="text-xl font-bold tracking-tight"><?= $appName ?></span>
            <?php if ($showLabControls && $modeLabel): ?>
                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ring-1 <?= $modeClass ?>"><?= htmlspecialchars($modeLabel) ?></span>
            <?php endif; ?>
        </div>
        <!-- Lab controls (instructor/admin only) -->
        <div class="mb-6 flex items-center gap-2 flex-wrap"><?= $showLabControls ? lab_controls_html() : '' ?><?php
            // Code Viewer link for participants studying the pre-login auth
            // vulnerabilities — shown only when the instructor enabled code_viewer.
            if (function_exists('is_feature_enabled') && is_feature_enabled('code_viewer')) {
                echo '<a href="' . htmlspecialchars(APP_URL) . '/training/code" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-500/15 text-indigo-300 ring-1 ring-indigo-500/30 hover:bg-indigo-500/25">&lt;/&gt; Code Viewer</a>';
            }
        ?><?= theme_toggle_button() ?></div>
        <div class="w-full max-w-md bg-slate-900/70 ring-1 ring-slate-800 rounded-2xl shadow-2xl p-8">
            <?= $page_content ?>
        </div>
        <p class="mt-6 text-xs text-slate-400">&#9888; Intentionally vulnerable application &mdash; local security training only.</p>
    </div>
<?php elseif ($focused): ?>
    <!-- ================= FOCUSED SHELL (no sidebar) ================= -->
    <!-- Used by the code viewer when opened from the pre-login pages: a plain
         top bar + full-width content, so the banking sidebar is not shown. -->
    <div class="min-h-full flex flex-col">
        <header class="h-16 shrink-0 bg-slate-900/60 ring-1 ring-slate-800 flex items-center justify-between px-6">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-sky-400 flex items-center justify-center font-black text-slate-900">B</div>
                <span class="text-lg font-bold tracking-tight"><?= $appName ?></span>
                <span class="text-sm text-slate-400 ml-1">&middot; <?= $title ?></span>
            </div>
            <div class="flex items-center gap-3">
                <?= theme_toggle_button() ?>
                <a href="<?= htmlspecialchars(APP_URL) ?>/login" class="text-sm text-slate-400 hover:text-slate-200">&larr; Back to login</a>
            </div>
        </header>
        <main class="flex-1 p-6 w-full max-w-6xl mx-auto"><?= $page_content ?></main>
    </div>
<?php else: ?>
    <!-- ===================== APP SHELL (post-login) ===================== -->
    <div class="min-h-full flex">
        <!-- Sidebar -->
        <aside class="w-60 shrink-0 bg-slate-900/60 ring-1 ring-slate-800 flex flex-col">
            <div class="h-16 flex items-center gap-2 px-5 border-b border-slate-800">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-sky-400 flex items-center justify-center font-black text-slate-900">B</div>
                <span class="text-lg font-bold tracking-tight"><?= $appName ?></span>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <?php foreach ($nav as $item) { echo nav_item($item, $active); } ?>
                <div class="my-3 border-t border-slate-800"></div>
                <?php foreach ($navTools as $item) { echo nav_item($item, $active); } ?>
                <?php
                // Admin link: shown to REAL admins (role check). Hiding it is NOT
                // access control — see admin.l4.
                //
                // admin.l4 LEVEL 1 (broken authz via a client-controlled flag):
                // the spoofable is_admin signal (is_admin cookie/param or
                // X-Admin header) ALSO reveals this link, so flipping the flag to
                // true makes the menu appear and flipping it to false hides it —
                // demonstrating the full client-side privilege-escalation path
                // (flip flag -> menu shows -> click through to the admin panel).
                $showAdmin = $user && (($user['role'] ?? '') === 'admin');
                if (!$showAdmin && $user
                        && engine()->getVulnLevel('admin', 'l4') === 1
                        && client_claims_admin()) {
                    $showAdmin = true;
                }
                if ($showAdmin) {
                    echo nav_item(['admin', 'Admin', '/admin', 'M12 1l9 4v6c0 5-3.8 9.4-9 11-5.2-1.6-9-6-9-11V5l9-4z'], $active);
                    // Show admin submenu items when on admin pages
                    $isAdminPage = strpos($active, 'admin') === 0;
                    if ($isAdminPage) {
                        echo '<div class="ml-2 space-y-1">';
                        foreach ($adminNav as $item) {
                            echo nav_item($item, $active);
                        }
                        echo '</div>';
                    }
                }
                ?>
            </nav>
        </aside>

        <!-- Main column -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Navbar -->
            <header class="h-16 shrink-0 bg-slate-900/60 ring-1 ring-slate-800 flex items-center justify-between px-6">
                <h1 class="text-base font-semibold text-slate-200"><?= $title ?></h1>
                <div class="flex items-center gap-3">
                    <?php if ($showLabControls && $modeLabel): ?>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ring-1 <?= $modeClass ?>"><?= htmlspecialchars($modeLabel) ?></span>
                    <?php endif; ?>
                    <?= $showLabControls ? lab_controls_html() : '' ?>
                    <?= theme_toggle_button() ?>
                    <?php if ($user): ?>
                        <div class="relative" id="user-dropdown">
                            <button onclick="document.getElementById('user-dropdown-menu').classList.toggle('hidden')" class="flex items-center gap-2 text-sm text-slate-300 hover:text-white">
                                <span class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-xs font-bold"><?= htmlspecialchars(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
                                <span class="hidden sm:block"><?= htmlspecialchars((string) $user['name']) ?></span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div id="user-dropdown-menu" class="absolute right-0 mt-2 w-48 bg-slate-800 ring-1 ring-slate-700 rounded-lg shadow-xl py-1 z-20 hidden">
                                <div class="px-4 py-2 text-xs text-slate-400 border-b border-slate-700"><?= htmlspecialchars((string) $user['customer_id']) ?> &middot; <?= htmlspecialchars((string) $user['role']) ?></div>
                                <a href="<?= $url ?>/profile" class="block px-4 py-2 text-sm text-slate-300 hover:bg-slate-700">Profile</a>
                                <form method="post" action="<?= $url ?>/logout" class="block" data-no-intercept="true">
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-300 hover:bg-slate-700">Sign out</button>
                                </form>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('click', function(e) {
                                var dropdown = document.getElementById('user-dropdown');
                                var menu = document.getElementById('user-dropdown-menu');
                                if (!dropdown.contains(e.target)) {
                                    menu.classList.add('hidden');
                                }
                            });
                        </script>
                    <?php else: ?>
                        <a href="<?= $url ?>/login" class="text-sm text-indigo-300 hover:text-indigo-200">Sign in</a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <div class="max-w-5xl mx-auto">
                    <?= $page_content ?>
                </div>
            </main>

            <!-- Footer -->
            <footer class="shrink-0 border-t border-slate-800 px-6 py-3 text-xs text-slate-600 flex justify-between">
                <span><?= $appName ?> v<?= htmlspecialchars(APP_VERSION) ?> &middot; <?= htmlspecialchars(APP_ENV) ?></span>
                <span>&#9888; Training sandbox &mdash; do not use real data</span>
            </footer>
        </div>
    </div>
<?php endif; ?>

<script>
// CTF Flag Modal System - Shows modal with selectable text and copy button
(function() {
    // Mode gate: when flag delivery is off (e.g. Pentest mode) the modal never
    // shows, so no header-driven popup or page-embedded flag reaches the user.
    const FLAGS_ENABLED = <?= flags_delivered() ? 'true' : 'false' ?>;
    let shownFlags = new Set();

    function showFlagModal(flag, vulnId = null) {
        if (!FLAGS_ENABLED) return;
        if (flag && !shownFlags.has(flag)) {
            shownFlags.add(flag);

            // Try to copy to clipboard immediately
            try {
                const textarea = document.createElement('textarea');
                textarea.value = flag;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                textarea.style.pointerEvents = 'none';
                document.body.appendChild(textarea);
                textarea.select();
                textarea.setSelectionRange(0, flag.length);
                document.execCommand('copy');
                document.body.removeChild(textarea);
            } catch (e) {
                // Silent fail - copy will work via button click
            }

            // Detect current theme (light/dark)
            const isLight = document.documentElement.classList.contains('theme-light');

            // Theme-aware colors
            const darkTheme = {
                overlay: 'rgba(0,0,0,0.85)',
                bg: 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)',
                border: 'rgba(59, 130, 246, 0.2)',
                title: '#f97316',
                subtitle: '#64748b',
                boxBg: 'rgba(30, 41, 59, 0.8)',
                boxBorder: 'rgba(59, 130, 246, 0.1)',
                text: '#0ea5e9',
                btnBg: 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
                btnBgHover: 'linear-gradient(135deg, #60a5fa 0%, #2563eb 100%)',
                btnShadow: '0 4px 15px rgba(59, 130, 246, 0.3)',
                btnShadowHover: '0 6px 20px rgba(59, 130, 246, 0.4)',
                btnClose: 'rgba(71, 85, 105, 0.4)',
                btnCloseBorder: 'rgba(100, 116, 139, 0.3)',
                btnCloseText: '#cbd5e1',
                btnCloseHover: 'rgba(100, 116, 139, 0.6)'
            };

            const lightTheme = {
                overlay: 'rgba(0,0,0,0.5)',
                bg: 'linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%)',
                border: 'rgba(51, 65, 85, 0.2)',
                title: '#ea580c',
                subtitle: '#475569',
                boxBg: 'rgba(226, 232, 240, 0.8)',
                boxBorder: 'rgba(15, 23, 42, 0.1)',
                text: '#0369a1',
                btnBg: 'linear-gradient(135deg, #0284c7 0%, #0c4a6e 100%)',
                btnBgHover: 'linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%)',
                btnShadow: '0 4px 15px rgba(2, 132, 199, 0.3)',
                btnShadowHover: '0 6px 20px rgba(2, 132, 199, 0.4)',
                btnClose: 'rgba(203, 213, 225, 0.4)',
                btnCloseBorder: 'rgba(100, 116, 139, 0.3)',
                btnCloseText: '#1e293b',
                btnCloseHover: 'rgba(203, 213, 225, 0.6)'
            };

            const theme = isLight ? lightTheme : darkTheme;

            // Create modal HTML
            const modal = document.createElement('div');
            modal.id = 'ctf-flag-modal';
            const vulnSubtitle = vulnId ? `<div style="font-size: 11px; color: ${theme.subtitle}; margin-bottom: 4px; font-family: 'Monaco', monospace; font-weight: 500;">${escapeHtml(vulnId)}</div>` : '';
            modal.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: ${theme.overlay}; display: flex; align-items: center; justify-content: center; z-index: 9999; animation: fadeIn 0.2s ease-in;">
                    <div style="background: ${theme.bg}; border-radius: 16px; padding: 32px; max-width: 420px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5), 0 0 60px rgba(59, 130, 246, 0.2); border: 1px solid ${theme.border}; animation: slideIn 0.3s ease-out;">
                        <div style="font-size: 28px; margin-bottom: 8px; font-weight: 700; color: ${theme.title};">🚩 FLAG CAPTURED!</div>
                        ${vulnSubtitle}
                        <div style="font-size: 12px; color: ${theme.subtitle}; margin-bottom: 16px;">Vulnerability Exploited Successfully</div>
                        <div style="background: ${theme.boxBg}; padding: 16px; border-radius: 10px; margin-bottom: 20px; font-family: 'Monaco', 'Courier New', monospace; word-break: break-all; user-select: all; color: ${theme.text}; font-size: 13px; line-height: 1.6; border: 1px solid ${theme.boxBorder}; box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);">` + escapeHtml(flag) + `</div>
                        <button id="ctf-claim-btn" style="width: 100%; padding: 13px 16px; margin-bottom: 10px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 15px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.35);">&#127937; Submit for Points</button>
                        <div id="ctf-claim-status" style="display:none; font-size: 12.5px; line-height:1.5; margin-bottom: 12px; padding: 9px 12px; border-radius: 8px;"></div>
                        <div style="display: flex; gap: 10px;">
                            <button id="ctf-copy-btn" style="flex: 1; padding: 11px 16px; background: ${theme.btnBg}; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s; box-shadow: ${theme.btnShadow};">📋 Copy Flag</button>
                            <button id="ctf-close-btn" style="flex: 1; padding: 11px 16px; background: ${theme.btnClose}; color: ${theme.btnCloseText}; border: 1px solid ${theme.btnCloseBorder}; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.2s;">Close</button>
                        </div>
                    </div>
                    <style>
                        @keyframes fadeIn {
                            from { opacity: 0; }
                            to { opacity: 1; }
                        }
                        @keyframes slideIn {
                            from { transform: scale(0.95) translateY(-20px); opacity: 0; }
                            to { transform: scale(1) translateY(0); opacity: 1; }
                        }
                        #ctf-copy-btn:hover {
                            background: ${theme.btnBgHover};
                            box-shadow: ${theme.btnShadowHover};
                            transform: translateY(-2px);
                        }
                        #ctf-close-btn:hover {
                            background: ${theme.btnCloseHover};
                            border-color: rgba(148, 163, 184, 0.5);
                        }
                    </style>
                </div>
            `;

            document.body.appendChild(modal);

            // Copy button handler - use synchronous execCommand
            document.getElementById('ctf-copy-btn').addEventListener('click', function() {
                try {
                    const textarea = document.createElement('textarea');
                    textarea.value = flag;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    textarea.style.pointerEvents = 'none';
                    document.body.appendChild(textarea);
                    textarea.select();
                    textarea.setSelectionRange(0, flag.length);
                    const success = document.execCommand('copy');
                    document.body.removeChild(textarea);

                    const btn = this;
                    if (success) {
                        btn.textContent = '✅ Copied!';
                        btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                        btn.style.boxShadow = '0 6px 20px rgba(16, 185, 129, 0.4)';
                        setTimeout(() => {
                            btn.textContent = '📋 Copy Flag';
                            btn.style.background = 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)';
                            btn.style.boxShadow = '0 4px 15px rgba(59, 130, 246, 0.3)';
                        }, 2000);
                    } else {
                        btn.textContent = '❌ Failed';
                        setTimeout(() => { btn.textContent = '📋 Copy Flag'; }, 2000);
                    }
                } catch (e) {
                    const btn = this;
                    btn.textContent = '❌ Error';
                    setTimeout(() => { btn.textContent = '📋 Copy Flag'; }, 2000);
                }
            });

            // Claim button handler — submits the flag straight to the scoreboard,
            // so a participant never has to copy/paste into the training lab.
            document.getElementById('ctf-claim-btn').addEventListener('click', async function() {
                const btn = this;
                const status = document.getElementById('ctf-claim-status');
                if (btn.disabled) return;
                btn.disabled = true;
                btn.style.opacity = '0.75';
                btn.textContent = 'Submitting\u2026';
                const show = (msg, kind) => {
                    status.style.display = 'block';
                    status.textContent = msg;
                    if (kind === 'ok') {
                        status.style.background = 'rgba(22,163,74,0.15)';
                        status.style.border = '1px solid rgba(22,163,74,0.45)';
                        status.style.color = '#4ade80';
                    } else {
                        status.style.background = 'rgba(239,68,68,0.12)';
                        status.style.border = '1px solid rgba(239,68,68,0.4)';
                        status.style.color = '#fca5a5';
                    }
                };
                try {
                    const r = await fetch('/api/v1/training/submit-flag', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({flag: flag})
                    });
                    let d = {};
                    try { d = await r.json(); } catch (e) { d = {}; }
                    if (r.status === 403) {
                        show(d.error || 'Flag submission is currently disabled by the instructor.', 'err');
                        btn.textContent = 'Submission disabled';
                        btn.style.background = 'rgba(100,116,139,0.5)';
                        return;
                    }
                    if (d && d.correct) {
                        btn.textContent = d.duplicate ? '\u2705 Already captured'
                                        : (d.points ? ('\u2705 Captured  +' + d.points + ' pts') : '\u2705 Captured');
                        btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                        btn.style.opacity = '1';
                        show(d.message || 'Flag accepted.', 'ok');
                        if (typeof window.refreshCtfScore === 'function') window.refreshCtfScore();
                    } else {
                        btn.textContent = '\u26A0 Not counted';
                        btn.style.background = 'rgba(100,116,139,0.5)';
                        btn.style.opacity = '1';
                        show((d && d.message) || 'This flag was not accepted.', 'err');
                    }
                } catch (e) {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.textContent = '\u127937 Submit for Points';
                    show('Could not reach the server. Try the training lab\u2019s submit box instead.', 'err');
                }
            });

            // Close button handler
            document.getElementById('ctf-close-btn').addEventListener('click', function() {
                modal.remove();
            });
        }
    }

    function escapeHtml(text) {
        const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Global function for page-embedded flag display (optional vulnId parameter)
    window.displayFlag = function(flag, vulnId = null) {
        showFlagModal(flag, vulnId);
    };

    // Intercept fetch responses
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            const flag = response.headers.get('X-CTF-Flag');
            if (flag) showFlagModal(flag);
            return response;
        });
    };
})();
</script>
</body>
</html>
