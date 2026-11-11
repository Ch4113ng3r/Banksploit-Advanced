<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/core/Flags.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF invalid.']);
        exit;
    }

    $action = (string) ($input['action'] ?? '');

    if ($action === 'reset-user-flag') {
        $userId = (int) ($input['user_id'] ?? 0);
        $vulnKey = (string) ($input['vuln_key'] ?? '');

        if ($userId <= 0 || !$vulnKey) {
            echo json_encode(['ok' => false, 'error' => 'Invalid user ID or vulnerability key.']);
            exit;
        }

        try {
            $pdo->prepare('DELETE FROM ctf_submissions WHERE user_id = :uid AND vuln_key = :vk')
                ->execute([':uid' => $userId, ':vk' => $vulnKey]);

            if ($vulnKey === 'statements.g2') {
                $flag = Flags::generate('statements.g2', $userId);
                $user = $pdo->prepare('SELECT customer_id FROM users WHERE id = :id')->execute([':id' => $userId])->fetch();
                $username = $user ? (string) $user['customer_id'] : '';

                $pdo->prepare('REPLACE INTO ctf_flags (user_id, username, vuln_key, flag, description) VALUES (:uid, :username, :vk, :flag, :desc)')
                    ->execute([':uid' => $userId, ':username' => $username, ':vk' => 'statements.g2', ':flag' => $flag, ':desc' => "SQL Injection flag (user {$username})"]);
            }

            echo json_encode(['ok' => true, 'message' => "Flag reset for user ID {$userId} ({$vulnKey})."]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'reset-all-flags-for-user') {
        $userId = (int) ($input['user_id'] ?? 0);

        if ($userId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid user ID.']);
            exit;
        }

        try {
            $pdo->prepare('DELETE FROM ctf_submissions WHERE user_id = :uid')->execute([':uid' => $userId]);

            $flag = Flags::generate('statements.g2', $userId);
            $user = $pdo->prepare('SELECT customer_id FROM users WHERE id = :id')->execute([':id' => $userId])->fetch();
            $username = $user ? (string) $user['customer_id'] : '';

            $pdo->prepare('REPLACE INTO ctf_flags (user_id, username, vuln_key, flag, description) VALUES (:uid, :username, :vk, :flag, :desc)')
                ->execute([':uid' => $userId, ':username' => $username, ':vk' => 'statements.g2', ':flag' => $flag, ':desc' => "SQL Injection flag (user {$username})"]);

            echo json_encode(['ok' => true, 'message' => "All flags reset for user ID {$userId}."]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'reset-all-flags') {
        $confirm = (string) ($input['confirm'] ?? '');

        if ($confirm !== 'yes') {
            echo json_encode(['ok' => false, 'error' => 'Confirmation required. Send confirm=yes.']);
            exit;
        }

        try {
            $pdo->exec('TRUNCATE TABLE ctf_submissions');

            $users = $pdo->query('SELECT id, customer_id FROM users')->fetchAll(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare('REPLACE INTO ctf_flags (user_id, username, vuln_key, flag, description) VALUES (:uid, :username, :vk, :flag, :desc)');

            foreach ($users as $user) {
                $flag = Flags::generate('statements.g2', (int) $user['id']);
                $stmt->execute([':uid' => (int) $user['id'], ':username' => (string) $user['customer_id'], ':vk' => 'statements.g2', ':flag' => $flag, ':desc' => "SQL Injection flag (user {$user['customer_id']})"]);
            }

            echo json_encode(['ok' => true, 'message' => 'All flags reset for all users.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

$totalUsers = $pdo->query('SELECT COUNT(*) as total FROM users')->fetch()['total'];
$csrf = csrf_token();

$content = <<<HTML
    <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">Reset CTF flags for individual users or all participants. Students will need to re-exploit vulnerabilities to recapture flags.</p>

    <div class="mb-6 rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <div class="flex items-center justify-between gap-4">
            <div class="flex-1">
                <input type="text" id="flag-search" placeholder="Search by name or customer ID..."
                       class="w-full px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
            <button id="reset-all-btn" onclick="resetAllFlags()" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700 text-white font-semibold text-sm transition shadow-sm">Reset ALL</button>
        </div>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <table class="w-full" id="flags-table">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">User</th><th class="pb-2">Customer ID</th><th class="pb-2">Flags</th><th class="pb-2">Points</th><th class="pb-2">Last Capture</th><th class="pb-2 text-right">Action</th>
            </tr></thead>
            <tbody id="flags-tbody">
                <tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-slate-500">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex items-center justify-between">
        <div id="flags-pagination" class="flex gap-2"></div>
        <div class="text-sm text-gray-600 dark:text-slate-400">
            Page <span id="flags-page">1</span> of <span id="flags-total-pages">1</span> (Total: {$totalUsers})
        </div>
    </div>

    <pre id="action-out" class="mt-3 text-xs text-gray-600 dark:text-slate-400 whitespace-pre-wrap"></pre>

    <script>
    const CSRF = '{$csrf}';
    let flagCurrentPage = 1;
    let flagCurrentSearch = '';
    let flagTotalPages = 1;
    const flagPageSize = 25;

    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    function renderFlagRows(users) {
        const tbody = document.getElementById('flags-tbody');
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-slate-500">No users found.</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(u => {
            const lastCapture = u.last_capture ? new Date(u.last_capture).toLocaleString() : '-';
            return `
                <tr class="border-t border-gray-100 dark:border-slate-800">
                    <td class="py-2 text-gray-900 dark:text-slate-200 text-sm">\${u.name}</td>
                    <td class="py-2 text-gray-600 dark:text-slate-400 text-sm font-mono">\${u.customer_id}</td>
                    <td class="py-2 text-sm"><span class="px-2 py-1 rounded-full text-xs bg-blue-50 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300">\${u.flags_captured}</span></td>
                    <td class="py-2 text-sm font-semibold text-gray-900 dark:text-slate-200">\${u.total_points}</td>
                    <td class="py-2 text-xs text-gray-600 dark:text-slate-400">\${lastCapture}</td>
                    <td class="py-2 text-right">
                        <button onclick="resetUserAllFlags(\${u.id})" class="text-xs rounded bg-orange-100 dark:bg-orange-500/20 hover:bg-orange-200 dark:hover:bg-orange-500/30 text-orange-700 dark:text-orange-300 px-2 py-1 font-medium">Reset</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderFlagPagination() {
        const paginationDiv = document.getElementById('flags-pagination');
        const pageSpan = document.getElementById('flags-page');
        const totalPagesSpan = document.getElementById('flags-total-pages');

        pageSpan.textContent = flagCurrentPage;
        totalPagesSpan.textContent = flagTotalPages;

        let html = '';

        if (flagCurrentPage > 1) {
            html += `<button onclick="loadFlagPage(\${flagCurrentPage - 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">Prev</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">Prev</button>`;
        }

        html += `<span class="px-4 py-2 text-gray-800 dark:text-slate-300 font-medium">Page \${flagCurrentPage} of \${flagTotalPages}</span>`;

        if (flagCurrentPage < flagTotalPages) {
            html += `<button onclick="loadFlagPage(\${flagCurrentPage + 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">Next</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">Next</button>`;
        }

        paginationDiv.innerHTML = html;
    }

    async function loadFlagPage(page, search) {
        const tbody = document.getElementById('flags-tbody');
        tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-slate-500">Loading...</td></tr>';

        try {
            const params = new URLSearchParams({
                page: page,
                limit: flagPageSize,
                search: search || ''
            });

            const response = await fetch(`/api/flag-reset?\${params}`);
            const json = await response.json();

            if (json.success) {
                renderFlagRows(json.data);
                flagTotalPages = json.meta.total_pages;
                flagCurrentPage = json.meta.page;
                renderFlagPagination();
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-slate-500">Error loading users.</td></tr>';
            }
        } catch (error) {
            console.error('Flag stats load error:', error);
            tbody.innerHTML = '<tr><td colspan="6" class="py-4 text-center text-gray-500 dark:text-slate-500">Error loading users.</td></tr>';
        }
    }

    const searchInput = document.getElementById('flag-search');
    searchInput.addEventListener('input', debounce(() => {
        flagCurrentSearch = searchInput.value.trim();
        loadFlagPage(1, flagCurrentSearch);
    }, 300));

    async function resetUserAllFlags(uid) {
        const confirmed = confirm('Reset all flags for this user? They will need to re-exploit to recapture.');
        if (!confirmed) return;

        const r = await fetch('/flag-reset', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'reset-all-flags-for-user', user_id: uid, csrf_token: CSRF})
        });
        const d = await r.json();
        document.getElementById('action-out').textContent = d.message || JSON.stringify(d);
        if (d.ok) setTimeout(() => loadFlagPage(flagCurrentPage, flagCurrentSearch), 800);
    }

    async function resetAllFlags() {
        const confirmed = confirm('WARNING: This will reset flags for ALL users. Students will need to re-exploit. Proceed?');
        if (!confirmed) return;

        const r = await fetch('/flag-reset', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'reset-all-flags', confirm: 'yes', csrf_token: CSRF})
        });
        const d = await r.json();
        document.getElementById('action-out').textContent = d.message || JSON.stringify(d);
        if (d.ok) setTimeout(() => loadFlagPage(1, ''), 1500);
    }

    loadFlagPage(1, '');
    </script>
HTML;

sa_render('Flag Reset', $content, 'flag-reset');
