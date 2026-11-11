<?php

declare(strict_types=1);

/**
 * Hints Management — Instructor controls which vulnerability hints are
 * visible to CTF participants. Each hint has a title, description, and
 * enabled/disabled toggle. There's also a global master switch.
 */

$configPath = dirname(__DIR__, 2) . '/src/config/hints_config.json';

// Load current config
$config = [];
if (is_file($configPath)) {
    $raw = file_get_contents($configPath);
    $config = json_decode($raw ?: '{}', true) ?: [];
}
$globalEnabled = (bool) ($config['global_enabled'] ?? false);
$hints = $config['hints'] ?? [];

// Handle POST
$notice = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'CSRF token invalid.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'toggle_global') {
            $config['global_enabled'] = !$globalEnabled;
            $globalEnabled = $config['global_enabled'];
            $notice = $globalEnabled
                ? 'Hints globally ENABLED — participants can now see hints on vulnerability pages.'
                : 'Hints globally DISABLED — no hints shown to participants.';
        } elseif ($action === 'toggle_hint') {
            $key = $_POST['vuln_key'] ?? '';
            if (isset($hints[$key])) {
                $hints[$key]['enabled'] = !($hints[$key]['enabled'] ?? false);
                $config['hints'] = $hints;
                $notice = "Hint for {$key}: " . ($hints[$key]['enabled'] ? 'ENABLED' : 'DISABLED');
            }
        } elseif ($action === 'toggle_all_on') {
            foreach ($hints as $k => &$h) {
                $h['enabled'] = true;
            }
            unset($h);
            $config['hints'] = $hints;
            $notice = 'All individual hints enabled.';
        } elseif ($action === 'toggle_all_off') {
            foreach ($hints as $k => &$h) {
                $h['enabled'] = false;
            }
            unset($h);
            $config['hints'] = $hints;
            $notice = 'All individual hints disabled.';
        } elseif ($action === 'update_hint') {
            $key = $_POST['vuln_key'] ?? '';
            if (isset($hints[$key])) {
                $hints[$key]['title'] = trim($_POST['title'] ?? $hints[$key]['title']);
                $hints[$key]['description'] = trim($_POST['description'] ?? $hints[$key]['description']);
                $config['hints'] = $hints;
                $notice = "Hint for {$key} updated.";
            }
        }

        // Save
        $config['hints'] = $hints;
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($configPath, $json) === false) {
            $error = 'Failed to write hints config file.';
        }
    }
}

// Build page
$csrf = csrf_token();

$alertHtml = '';
if ($error) {
    $alertHtml = '<div class="mb-4 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-3 py-2 text-sm text-red-700 dark:text-red-300">' . htmlspecialchars($error) . '</div>';
} elseif ($notice) {
    $alertHtml = '<div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-300">' . htmlspecialchars($notice) . '</div>';
}

// Global toggle
$globalBadge = $globalEnabled
    ? '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-800 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-500/30">ON</span>'
    : '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-300 ring-1 ring-red-200 dark:ring-red-500/30">OFF</span>';

$globalBtnLabel = $globalEnabled ? 'Disable All Hints' : 'Enable Hints Globally';
$globalBtnCls = $globalEnabled
    ? 'bg-red-600 hover:bg-red-500 text-white'
    : 'bg-emerald-600 hover:bg-emerald-500 text-white';

// Per-hint cards
$hintCards = '';
foreach ($hints as $vulnKey => $hint) {
    $title = htmlspecialchars($hint['title'] ?? $vulnKey);
    $desc  = htmlspecialchars($hint['description'] ?? '');
    $enabled = (bool) ($hint['enabled'] ?? false);

    $statusBadge = $enabled
        ? '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">Visible</span>'
        : '<span class="px-2 py-0.5 rounded text-xs font-semibold bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400">Hidden</span>';

    $toggleBtnLabel = $enabled ? 'Hide' : 'Show';
    $toggleBtnCls = $enabled
        ? 'bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300'
        : 'bg-emerald-500 hover:bg-emerald-400 text-white';

    $ringCls = $enabled
        ? 'ring-emerald-200 dark:ring-emerald-500/30'
        : 'ring-gray-200 dark:ring-slate-800';

    $escapedKey = htmlspecialchars($vulnKey);

    $hintCards .= <<<HTML
        <div class="rounded-xl bg-white dark:bg-slate-900/60 ring-1 {$ringCls} p-5 shadow-sm dark:shadow-none">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <code class="text-xs px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono">{$escapedKey}</code>
                        {$statusBadge}
                    </div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{$title}</h3>
                </div>
                <form method="POST" action="/hints" class="shrink-0">
                    <input type="hidden" name="csrf_token" value="{$csrf}">
                    <input type="hidden" name="action" value="toggle_hint">
                    <input type="hidden" name="vuln_key" value="{$escapedKey}">
                    <button type="submit" class="text-xs font-semibold rounded-lg px-3 py-1.5 {$toggleBtnCls} transition">{$toggleBtnLabel}</button>
                </form>
            </div>
            <p class="text-sm text-gray-600 dark:text-slate-400 mb-3 leading-relaxed">{$desc}</p>
            <details class="text-xs">
                <summary class="cursor-pointer text-amber-700 dark:text-amber-400 font-medium hover:underline">Edit hint text</summary>
                <form method="POST" action="/hints" class="mt-3 space-y-2">
                    <input type="hidden" name="csrf_token" value="{$csrf}">
                    <input type="hidden" name="action" value="update_hint">
                    <input type="hidden" name="vuln_key" value="{$escapedKey}">
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-slate-500 mb-0.5">Title</label>
                        <input type="text" name="title" value="{$title}" class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-2.5 py-1.5 text-sm text-gray-900 dark:text-slate-100 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 dark:text-slate-500 mb-0.5">Description (shown to participants)</label>
                        <textarea name="description" rows="3" class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-2.5 py-1.5 text-sm text-gray-900 dark:text-slate-100 focus:ring-amber-500 focus:outline-none">{$desc}</textarea>
                    </div>
                    <button type="submit" class="rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-semibold px-3 py-1.5 text-xs shadow-sm">Save Changes</button>
                </form>
            </details>
        </div>
HTML;
}

$content = <<<HTML
    <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
        Control which vulnerability hints are visible to CTF participants. When enabled, a hint banner appears on pages where the vulnerability is present — showing the vulnerability type and a short description of the technique (without giving away the exact exploit steps).
    </p>
    {$alertHtml}

    <!-- Global master switch -->
    <div class="mb-6 rounded-xl bg-white dark:bg-slate-900/60 ring-1 ring-gray-200 dark:ring-slate-800 p-5 shadow-sm dark:shadow-none">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">💡</span>
                <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">Global Hints Master Switch</div>
                    <div class="text-xs text-gray-500 dark:text-slate-500">When OFF, no hints are shown regardless of individual settings</div>
                </div>
                {$globalBadge}
            </div>
            <form method="POST" action="/hints">
                <input type="hidden" name="csrf_token" value="{$csrf}">
                <input type="hidden" name="action" value="toggle_global">
                <button type="submit" class="text-sm font-semibold rounded-lg px-4 py-2 {$globalBtnCls} transition shadow-sm">{$globalBtnLabel}</button>
            </form>
        </div>
    </div>

    <!-- Bulk actions -->
    <div class="mb-4 flex items-center gap-2">
        <span class="text-xs text-gray-500 dark:text-slate-500 font-medium">Bulk:</span>
        <form method="POST" action="/hints" class="inline">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="action" value="toggle_all_on">
            <button type="submit" class="text-xs font-semibold rounded-lg px-2.5 py-1 bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-500/25 ring-1 ring-emerald-200 dark:ring-emerald-500/30 transition">Enable All</button>
        </form>
        <form method="POST" action="/hints" class="inline">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="action" value="toggle_all_off">
            <button type="submit" class="text-xs font-semibold rounded-lg px-2.5 py-1 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-600 ring-1 ring-slate-200 dark:ring-slate-600 transition">Disable All</button>
        </form>
    </div>

    <!-- Individual hint cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {$hintCards}
    </div>

    <div class="mt-6 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 px-4 py-3 text-xs text-amber-800 dark:text-amber-300">
        <strong>💡 How hints work for participants:</strong> When both the global switch AND an individual hint are enabled, participants see a small info banner at the top of the relevant page. The banner shows the vulnerability type name and a generic explanation of the technique — enough to point them in the right direction without revealing exact exploitation steps.
    </div>
HTML;

sa_render('Hints Management', $content, 'hints');
