<?php

declare(strict_types=1);

/**
 * BankSploit: CTF Leaderboard
 *
 * Shows all logged-in users ranked by their CTF submission points.
 * Authentication required - only visible to logged-in users.
 */

auth()->requireAuth();

$user = auth()->getCurrentUser();
$pdo  = db();

// --- Read hidden users from JSON file ---
$hiddenFile = dirname(__DIR__, 3) . '/data/ctf-hidden-users.json';
$hiddenIds = [];
if (is_file($hiddenFile)) {
    $raw = @file_get_contents($hiddenFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $hiddenIds = array_map('intval', $decoded);
        }
    }
}

// Get current user's stats — exclude hidden users from ranking
$hiddenClause = '';
$hiddenParams = [];
if (!empty($hiddenIds)) {
    $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
    $hiddenClause = "WHERE u.id NOT IN ($placeholders)";
    $hiddenParams = $hiddenIds;
}

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.customer_id,
        COALESCE(SUM(cs.points), 0) as total_points,
        COUNT(DISTINCT cs.vuln_key) as flags_captured
    FROM users u
    LEFT JOIN ctf_submissions cs ON u.id = cs.user_id
    $hiddenClause
    GROUP BY u.id, u.name, u.customer_id
    ORDER BY total_points DESC, u.name ASC
");
$stmt->execute($hiddenParams);
$allRanked = $stmt->fetchAll();

// Find current user's rank and stats
$currentUserRank = 1;
$yourPoints = 0;
$yourFlags = 0;
foreach ($allRanked as $idx => $row) {
    if ((int) $row['id'] === (int) $user['id']) {
        $currentUserRank = $idx + 1;
        $yourPoints = (int) $row['total_points'];
        $yourFlags = (int) $row['flags_captured'];
        break;
    }
}

$totalParticipants = count($allRanked);
$currentUserId = (int) $user['id'];
$currentUserCid = htmlspecialchars((string) $user['customer_id']);
$url = htmlspecialchars(APP_URL);

$content = <<<HTML
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-3xl font-bold text-white">🏆 CTF Leaderboard</h2>
            <p class="text-slate-400 text-sm mt-1">Live ranking of all participants by points earned</p>
        </div>
        <div class="text-right">
            <div class="text-4xl font-black text-indigo-300">{$totalParticipants}</div>
            <div class="text-xs text-slate-400 uppercase tracking-wide">Participants</div>
        </div>
    </div>

    <!-- Current user stats card -->
    <div class="rounded-xl bg-gradient-to-br from-indigo-600/25 to-purple-600/15 ring-1 ring-indigo-500/30 p-5 mb-6">
        <div class="grid grid-cols-3 gap-6">
            <div>
                <div class="text-xs text-indigo-200 uppercase tracking-wide font-semibold mb-1">Your Rank</div>
                <div class="text-3xl font-bold text-indigo-300">#{$currentUserRank}</div>
            </div>
            <div>
                <div class="text-xs text-emerald-200 uppercase tracking-wide font-semibold mb-1">Your Points</div>
                <div class="text-3xl font-bold text-emerald-400">{$yourPoints}</div>
            </div>
            <div>
                <div class="text-xs text-sky-200 uppercase tracking-wide font-semibold mb-1">Flags Captured</div>
                <div class="text-3xl font-bold text-sky-400">{$yourFlags}</div>
            </div>
        </div>
    </div>

    <!-- Search input -->
    <div class="mb-4">
        <input type="text" id="lb-search" placeholder="Search participant by name..."
               class="w-full px-4 py-2 bg-slate-800 border border-slate-700 rounded-lg text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
    </div>

    <!-- Leaderboard table -->
    <div class="rounded-xl bg-slate-900/40 ring-1 ring-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full" id="lb-table" data-cid="{$currentUserCid}">
                <thead>
                    <tr class="border-b border-slate-700 bg-slate-800/30">
                        <th class="py-3 px-4 text-center text-xs font-semibold text-slate-400 uppercase tracking-wide w-12">#</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-slate-400 uppercase tracking-wide">Participant</th>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-slate-400 uppercase tracking-wide">Flags</th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Points</th>
                        <th class="py-3 px-4 text-right text-xs font-semibold text-slate-400 uppercase tracking-wide">Last Capture</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800" id="lb-body">
                    <tr><td colspan="5" class="py-4 text-center text-slate-500">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination controls -->
    <div class="mt-6 flex items-center justify-between">
        <div id="lb-pagination" class="flex gap-2">
            <!-- Rendered by JS -->
        </div>
        <div class="text-sm text-slate-400">
            Page <span id="lb-page">1</span> of <span id="lb-total-pages">1</span>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-6 text-center text-sm text-slate-500">
        <p>Ranked by total points · Ties broken by earliest last capture</p>
        <p class="mt-2">🏆 Rankings update in real-time as flags are submitted</p>
    </div>

    <script>
    const CURRENT_USER_ID = {$currentUserId};
    const CURRENT_USER_CID = '{$currentUserCid}';
    let currentPage = 1;
    let currentSearch = '';
    let totalPages = 1;
    const pageSize = 25;

    // Debounce utility
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    // Get medal emoji for rank
    function getMedalEmoji(rank) {
        switch(rank) {
            case 1: return '🥇';
            case 2: return '🥈';
            case 3: return '🥉';
            default: return null;
        }
    }

    // Format date
    function formatDate(dateStr) {
        return dateStr ? dateStr : '—';
    }

    // Render leaderboard rows
    function renderRows(data) {
        const tbody = document.getElementById('lb-body');

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-500">No participants found.</td></tr>';
            return;
        }

        tbody.innerHTML = data.map(entry => {
            const isCurrentUser = entry.id === CURRENT_USER_ID;
            const rowClass = isCurrentUser ? 'bg-indigo-500/20 hover:bg-indigo-500/25' : 'hover:bg-slate-800/50';

            const medal = getMedalEmoji(entry.rank);
            const rankDisplay = medal
                ? `<span class="text-xl">\${medal}</span>`
                : `<span class="font-bold text-slate-400">\${entry.rank}</span>`;

            const youBadge = isCurrentUser
                ? '<span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/30 text-indigo-200">YOU</span>'
                : '';

            return `
                <tr class="\${rowClass} transition">
                    <td class="py-3 px-4 text-center">\${rankDisplay}</td>
                    <td class="py-3 px-4 text-left">
                        <div class="text-slate-200 font-medium">\${entry.name}\${youBadge}</div>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-slate-700/40 text-slate-200 text-sm font-semibold">
                            \${entry.flags}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right">
                        <div class="text-xl font-bold text-emerald-400">\${entry.points}</div>
                    </td>
                    <td class="py-3 px-4 text-right text-sm text-slate-400">\${formatDate(entry.last_capture)}</td>
                </tr>
            `;
        }).join('');
    }

    // Render pagination controls
    function renderPagination() {
        const paginationDiv = document.getElementById('lb-pagination');
        const pageSpan = document.getElementById('lb-page');
        const totalPagesSpan = document.getElementById('lb-total-pages');

        pageSpan.textContent = currentPage;
        totalPagesSpan.textContent = totalPages;

        let html = '';

        // Previous button
        if (currentPage > 1) {
            html += `<button onclick="loadPage(\${currentPage - 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">← Prev</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">← Prev</button>`;
        }

        // Page info
        html += `<span class="px-4 py-2 text-gray-800 dark:text-slate-300 font-medium">Page \${currentPage} of \${totalPages}</span>`;

        // Next button
        if (currentPage < totalPages) {
            html += `<button onclick="loadPage(\${currentPage + 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">Next →</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">Next →</button>`;
        }

        paginationDiv.innerHTML = html;
    }

    // Load page from API
    async function loadPage(page, search) {
        const tbody = document.getElementById('lb-body');
        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-500">Loading...</td></tr>';

        try {
            const params = new URLSearchParams({
                page: page,
                limit: pageSize,
                search: search || ''
            });

            const response = await fetch(`/api/v1/leaderboard?\${params}`);
            const json = await response.json();

            if (json.success) {
                renderRows(json.data);
                totalPages = json.meta.total_pages;
                currentPage = json.meta.page;
                renderPagination();
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-500">Error loading leaderboard.</td></tr>';
            }
        } catch (error) {
            console.error('Leaderboard load error:', error);
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-500">Error loading leaderboard.</td></tr>';
        }
    }

    // Search input handler with debounce
    const searchInput = document.getElementById('lb-search');
    searchInput.addEventListener('input', debounce(() => {
        currentSearch = searchInput.value.trim();
        loadPage(1, currentSearch);
    }, 300));

    // Initial load
    loadPage(1, '');
    </script>
HTML;

render_layout('CTF Leaderboard', $content, ['active' => 'leaderboard']);
