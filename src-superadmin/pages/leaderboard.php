<?php

declare(strict_types=1);

/**
 * CTF Control Panel — Live Leaderboard
 *
 * Auto-refreshes every 5 seconds via polling.
 * Instructor can hide/unhide individual users from CTF rankings.
 */

$csrfToken = csrf_token();

$content = <<<'HTML'
    <!-- Live status bar -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">🏆 Live CTF Leaderboard</h2>
            <p class="text-sm text-gray-500 dark:text-slate-500 mt-0.5">Auto-refreshes every 5 seconds · Rankings exclude hidden users</p>
        </div>
        <div class="flex items-center gap-3">
            <div id="live-indicator" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-200 dark:ring-emerald-500/30">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Live</span>
            </div>
            <span id="last-updated" class="text-xs text-gray-400 dark:text-slate-600">—</span>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <div class="relative">
            <input type="text" id="lb-search" placeholder="Search by name, customer ID, or email..."
                   class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-2.5 pl-9 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none"
                   oninput="onSearchInput()">
            <span class="absolute left-3 top-2.5 text-gray-400 dark:text-slate-500 text-sm">🔍</span>
        </div>
    </div>

    <!-- Active Leaderboard Table -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase tracking-wide">Active Rankings</h3>
            <span class="text-xs text-gray-500 dark:text-slate-500" id="table-count">Loading...</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left bg-gray-50 dark:bg-slate-900/40">
                        <th class="py-2.5 px-4 w-12 text-center">#</th>
                        <th class="py-2.5 px-4">Participant</th>
                        <th class="py-2.5 px-4 text-center">Flags</th>
                        <th class="py-2.5 px-4 text-right">Points</th>
                        <th class="py-2.5 px-4 text-right">Last Capture</th>
                        <th class="py-2.5 px-4 text-center w-24">Action</th>
                    </tr>
                </thead>
                <tbody id="lb-body" class="divide-y divide-gray-100 dark:divide-slate-800">
                    <tr><td colspan="6" class="py-8 text-center text-gray-400 dark:text-slate-500">Loading leaderboard...</td></tr>
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div class="px-5 py-3 border-t border-gray-100 dark:border-slate-800 flex items-center justify-between">
            <div id="lb-pagination" class="flex gap-2"></div>
            <div class="text-xs text-gray-500 dark:text-slate-500">
                Page <span id="lb-page">1</span> of <span id="lb-total-pages">1</span>
            </div>
        </div>
    </div>

    <!-- Hidden Users Section -->
    <div id="hidden-section" class="hidden">
        <div class="rounded-xl bg-gray-50 dark:bg-slate-900/30 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-500 uppercase tracking-wide">🚫 Hidden from Leaderboard</h3>
                <span class="text-xs text-gray-400 dark:text-slate-600" id="hidden-count">0 users</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 dark:text-slate-600 text-xs uppercase text-left bg-gray-100 dark:bg-slate-950/50">
                            <th class="py-2 px-4">Participant</th>
                            <th class="py-2 px-4 text-center">Flags</th>
                            <th class="py-2 px-4 text-right">Points (excluded)</th>
                            <th class="py-2 px-4 text-center w-24">Action</th>
                        </tr>
                    </thead>
                    <tbody id="hidden-body" class="divide-y divide-gray-200 dark:divide-slate-800"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    const CSRF_TOKEN = 'CSRF_TOKEN_PLACEHOLDER';
    let currentPage = 1;
    let totalPages = 1;
    let currentSearch = '';
    let lastHash = '';
    let pollInterval = null;
    let searchTimeout = null;

    // --- Debounced search ---
    function onSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentSearch = document.getElementById('lb-search').value.trim();
            currentPage = 1;
            fetchLeaderboard();
        }, 300);
    }

    // --- Fetch leaderboard data ---
    async function fetchLeaderboard() {
        try {
            const params = new URLSearchParams({
                page: currentPage,
                limit: 25,
                search: currentSearch
            });

            const res = await fetch(`/api/leaderboard?${params}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();

            if (!json.success) throw new Error('API error');

            // Only re-render if data actually changed
            const newHash = json.hash + ':' + json.meta.page + ':' + currentSearch;
            if (newHash !== lastHash) {
                lastHash = newHash;
                renderActiveTable(json.data, json.meta);
                renderHiddenTable(json.hidden);
            }

            // Update status
            updateLiveIndicator(true);
        } catch (err) {
            console.error('Leaderboard fetch error:', err);
            updateLiveIndicator(false);
        }
    }

    // --- Render active leaderboard ---
    function renderActiveTable(data, meta) {
        const tbody = document.getElementById('lb-body');
        const countEl = document.getElementById('table-count');
        totalPages = meta.total_pages;
        currentPage = meta.page;

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-gray-400 dark:text-slate-500">No participants found.</td></tr>';
            countEl.textContent = '0 participants';
            renderPagination();
            return;
        }

        const start = (meta.page - 1) * meta.limit + 1;
        const end = start + data.length - 1;
        countEl.textContent = `${start}–${end} of ${meta.total} participants`;

        const dark = document.documentElement.classList.contains('dark');

        tbody.innerHTML = data.map(entry => {
            const medal = entry.rank === 1 ? '🥇' : entry.rank === 2 ? '🥈' : entry.rank === 3 ? '🥉' : `<span class="font-mono ${dark ? 'text-slate-400' : 'text-gray-500'}">${entry.rank}</span>`;

            return `<tr class="${dark ? 'hover:bg-slate-800/30' : 'hover:bg-amber-50/40'} transition">
                <td class="py-3 px-4 text-center">${medal}</td>
                <td class="py-3 px-4">
                    <div class="font-medium ${dark ? 'text-white' : 'text-gray-900'}">${esc(entry.name)}</div>
                    <div class="text-xs ${dark ? 'text-slate-500' : 'text-gray-500'}">${esc(entry.customer_id)} · ${esc(entry.email)}</div>
                </td>
                <td class="py-3 px-4 text-center">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full ${dark ? 'bg-slate-800 text-white' : 'bg-gray-100 text-gray-900'} text-xs font-semibold">${entry.flags}</span>
                </td>
                <td class="py-3 px-4 text-right">
                    <span class="font-mono font-bold ${dark ? 'text-amber-300' : 'text-amber-700'}">${entry.points}</span>
                </td>
                <td class="py-3 px-4 text-right text-xs ${dark ? 'text-slate-500' : 'text-gray-500'}">${entry.last_capture || '—'}</td>
                <td class="py-3 px-4 text-center">
                    <button onclick="toggleHide(${entry.id}, true)" title="Hide from leaderboard"
                            class="px-2.5 py-1 rounded-md text-xs font-medium ${dark ? 'bg-red-500/10 text-red-300 hover:bg-red-500/20 ring-1 ring-red-500/30' : 'bg-red-50 text-red-700 hover:bg-red-100 ring-1 ring-red-200'}  transition">
                        Hide
                    </button>
                </td>
            </tr>`;
        }).join('');

        renderPagination();
    }

    // --- Render hidden users ---
    function renderHiddenTable(hidden) {
        const section = document.getElementById('hidden-section');
        const tbody = document.getElementById('hidden-body');
        const countEl = document.getElementById('hidden-count');

        if (!hidden || hidden.length === 0) {
            section.classList.add('hidden');
            return;
        }

        section.classList.remove('hidden');
        countEl.textContent = `${hidden.length} user${hidden.length > 1 ? 's' : ''}`;

        const dark = document.documentElement.classList.contains('dark');

        tbody.innerHTML = hidden.map(entry => {
            return `<tr class="opacity-60 ${dark ? 'hover:bg-slate-800/20' : 'hover:bg-gray-100/50'} transition">
                <td class="py-2.5 px-4">
                    <div class="font-medium ${dark ? 'text-slate-400' : 'text-gray-600'}">${esc(entry.name)}</div>
                    <div class="text-xs ${dark ? 'text-slate-600' : 'text-gray-400'}">${esc(entry.customer_id)} · ${esc(entry.email)}</div>
                </td>
                <td class="py-2.5 px-4 text-center text-xs ${dark ? 'text-slate-500' : 'text-gray-500'}">${entry.flags}</td>
                <td class="py-2.5 px-4 text-right text-xs ${dark ? 'text-slate-500' : 'text-gray-500'} line-through">${entry.points}</td>
                <td class="py-2.5 px-4 text-center">
                    <button onclick="toggleHide(${entry.id}, false)" title="Restore to leaderboard"
                            class="px-2.5 py-1 rounded-md text-xs font-medium ${dark ? 'bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20 ring-1 ring-emerald-500/30' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 ring-1 ring-emerald-200'} transition">
                        Unhide
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    // --- Pagination ---
    function renderPagination() {
        const div = document.getElementById('lb-pagination');
        const pageSpan = document.getElementById('lb-page');
        const totalSpan = document.getElementById('lb-total-pages');

        pageSpan.textContent = currentPage;
        totalSpan.textContent = totalPages;

        const dark = document.documentElement.classList.contains('dark');
        let html = '';

        if (currentPage > 1) {
            html += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1.5 ${dark ? 'bg-amber-600 hover:bg-amber-700 border-amber-800' : 'bg-amber-500 hover:bg-amber-600 border-amber-600'} text-white rounded-lg text-xs font-medium transition border">← Prev</button>`;
        } else {
            html += `<button disabled class="px-3 py-1.5 ${dark ? 'bg-slate-700 text-slate-500 border-slate-600' : 'bg-gray-200 text-gray-400 border-gray-300'} rounded-lg text-xs font-medium cursor-not-allowed border">← Prev</button>`;
        }

        if (currentPage < totalPages) {
            html += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1.5 ${dark ? 'bg-amber-600 hover:bg-amber-700 border-amber-800' : 'bg-amber-500 hover:bg-amber-600 border-amber-600'} text-white rounded-lg text-xs font-medium transition border">Next →</button>`;
        } else {
            html += `<button disabled class="px-3 py-1.5 ${dark ? 'bg-slate-700 text-slate-500 border-slate-600' : 'bg-gray-200 text-gray-400 border-gray-300'} rounded-lg text-xs font-medium cursor-not-allowed border">Next →</button>`;
        }

        div.innerHTML = html;
    }

    function goToPage(page) {
        currentPage = Math.max(1, Math.min(page, totalPages));
        fetchLeaderboard();
    }

    // --- Hide/Unhide toggle ---
    async function toggleHide(userId, hide) {
        try {
            const res = await fetch('/api/leaderboard-hide', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({ user_id: userId, hidden: hide })
            });

            const json = await res.json();
            if (!json.success) {
                alert('Error: ' + (json.error || 'Unknown error'));
                return;
            }

            // Force immediate refresh
            lastHash = '';
            await fetchLeaderboard();
        } catch (err) {
            console.error('Hide toggle error:', err);
            alert('Failed to update user. Check console for details.');
        }
    }

    // --- Live indicator ---
    function updateLiveIndicator(success) {
        const el = document.getElementById('live-indicator');
        const lastEl = document.getElementById('last-updated');

        if (success) {
            el.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-200 dark:ring-emerald-500/30';
            el.innerHTML = `<span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
            </span><span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Live</span>`;
            lastEl.textContent = 'Updated ' + new Date().toLocaleTimeString();
        } else {
            el.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-red-50 dark:bg-red-500/10 ring-1 ring-red-200 dark:ring-red-500/30';
            el.innerHTML = `<span class="relative flex h-2.5 w-2.5">
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
            </span><span class="text-xs font-medium text-red-700 dark:text-red-300">Error</span>`;
        }
    }

    // --- Utility ---
    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // --- Polling with Page Visibility API ---
    function startPolling() {
        fetchLeaderboard(); // immediate first load
        pollInterval = setInterval(fetchLeaderboard, 5000);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });

    // Boot
    startPolling();
    </script>
HTML;

// Inject CSRF token
$content = str_replace('CSRF_TOKEN_PLACEHOLDER', $csrfToken, $content);

sa_render('Live Leaderboard', $content, 'leaderboard');
