<?php

declare(strict_types=1);

$pdo = db();
$engine = engine();

// Handle POST — reseed flags
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF invalid.']);
        exit;
    }

    // Reseed all flags
    $users = $pdo->query('SELECT id, customer_id FROM users')->fetchAll();
    $allVulns = $engine->getAllVulns();
    $vulnKeys = [];
    foreach ($allVulns['modules'] as $mod => $vulns) {
        foreach ($vulns as $vid => $v) {
            $vulnKeys[] = $mod . '.' . strtolower($vid);
        }
    }

    $stmt = $pdo->prepare('REPLACE INTO ctf_flags (user_id, vuln_key, flag, description) VALUES (:uid, :vk, :flag, :desc)');
    $count = 0;
    foreach ($users as $user) {
        foreach ($vulnKeys as $vk) {
            $flag = Flags::generate($vk, (int) $user['id']);
            $stmt->execute([
                ':uid'  => (int) $user['id'],
                ':vk'   => $vk,
                ':flag' => $flag,
                ':desc' => "Flag for {$vk} (user {$user['customer_id']})",
            ]);
            $count++;
        }
    }

    // Also reseed accounts.ctf_flag
    $acctStmt = $pdo->prepare('UPDATE accounts SET ctf_flag = :flag WHERE user_id = :uid');
    foreach ($users as $user) {
        $flag = Flags::generate('transfer.l1', (int) $user['id']);
        $acctStmt->execute([':flag' => $flag, ':uid' => (int) $user['id']]);
    }

    echo json_encode(['ok' => true, 'message' => "Reseeded {$count} flags."]);
    exit;
}

// GET — get total users and vuln keys
$totalUsers = $pdo->query('SELECT COUNT(*) as total FROM users')->fetch()['total'];
$allVulns = $engine->getAllVulns();
$vulnKeys = [];
foreach ($allVulns['modules'] as $mod => $vulns) {
    foreach ($vulns as $vid => $v) {
        $vulnKeys[] = ['key' => $mod . '.' . strtolower($vid), 'title' => $v['title'] ?? $vid];
    }
}
$vulnKeysJson = json_encode($vulnKeys);

$csrf = csrf_token();

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-sm text-gray-600 dark:text-slate-400">Per-user HMAC flags. These are computed from the server secret — never stored in config files.</p>
        </div>
        <button onclick="reseedFlags()" class="rounded-lg bg-amber-50 dark:bg-amber-600/30 hover:bg-amber-100 dark:hover:bg-amber-600/50 ring-1 ring-amber-200 dark:ring-0 px-3 py-2 text-xs text-amber-800 dark:text-amber-200 font-medium">Reseed All Flags</button>
    </div>

    <!-- Search input -->
    <div class="mb-4">
        <input type="text" id="flags-search" placeholder="Search by name or customer ID..."
               class="w-full px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
    </div>

    <!-- Flags container -->
    <div id="flags-container">
        <div class="text-center text-gray-600 dark:text-slate-400">Loading...</div>
    </div>

    <!-- Pagination controls -->
    <div class="mt-6 flex items-center justify-between">
        <div id="flags-pagination" class="flex gap-2">
            <!-- Rendered by JS -->
        </div>
        <div class="text-sm text-gray-600 dark:text-slate-400">
            Page <span id="flags-page">1</span> of <span id="flags-total-pages">1</span> (Total: {$totalUsers})
        </div>
    </div>

    <div id="flag-status" class="mt-3 text-sm text-gray-600 dark:text-slate-400"></div>

    <script>
    const CSRF = "{$csrf}";
    const VULN_KEYS = {$vulnKeysJson};
    let flagsCurrentPage = 1;
    let flagsCurrentSearch = '';
    let flagsTotalPages = 1;
    const flagsPageSize = 25;

    // Debounce utility
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    // Render flag accordions
    function renderFlagAccordions(users) {
        const container = document.getElementById('flags-container');
        if (users.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-600 dark:text-slate-400 py-8">No users found.</div>';
            return;
        }

        const html = users.map(user => {
            const cid = user.customer_id;
            const name = user.name;
            const uid = user.id;

            let flagRows = '';
            for (const vk of VULN_KEYS) {
                const flag = generateFlag(vk.key, uid);
                flagRows += `
                    <tr class="border-t border-gray-100 dark:border-slate-800 text-xs">
                        <td class="py-1 text-gray-500 dark:text-slate-400">\${vk.key}</td>
                        <td class="py-1 text-gray-700 dark:text-slate-300">\${vk.title}</td>
                        <td class="py-1 font-mono text-amber-700 dark:text-amber-300 font-medium">\${flag}</td>
                    </tr>
                `;
            }

            return `
                <details class="mb-4 rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800">
                    <summary class="px-4 py-3 cursor-pointer text-sm text-gray-700 dark:text-slate-200 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-slate-800/50 rounded-xl">
                        <span class="font-mono text-amber-700 dark:text-amber-300 font-medium">\${cid}</span> — \${name}
                    </summary>
                    <div class="px-4 pb-3">
                        <table class="w-full">
                            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase">
                                <th class="pb-1 text-left">Vuln</th><th class="pb-1 text-left">Title</th><th class="pb-1 text-left">Flag</th>
                            </tr></thead>
                            <tbody>\${flagRows}</tbody>
                        </table>
                    </div>
                </details>
            `;
        }).join('');

        container.innerHTML = html;
    }

    // Simplified flag generation (frontend only - doesn't need to match backend exactly)
    function generateFlag(vulnKey, userId) {
        // This is just a placeholder - in reality flags are computed on the backend
        return 'FLAG{...}';
    }

    // Render pagination
    function renderFlagsPagination() {
        const paginationDiv = document.getElementById('flags-pagination');
        const pageSpan = document.getElementById('flags-page');
        const totalPagesSpan = document.getElementById('flags-total-pages');

        pageSpan.textContent = flagsCurrentPage;
        totalPagesSpan.textContent = flagsTotalPages;

        let html = '';

        // Previous button
        if (flagsCurrentPage > 1) {
            html += `<button onclick="loadFlagsPage(\${flagsCurrentPage - 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">← Prev</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">← Prev</button>`;
        }

        // Page info
        html += `<span class="px-4 py-2 text-gray-800 dark:text-slate-300 font-medium">Page \${flagsCurrentPage} of \${flagsTotalPages}</span>`;

        // Next button
        if (flagsCurrentPage < flagsTotalPages) {
            html += `<button onclick="loadFlagsPage(\${flagsCurrentPage + 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">Next →</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">Next →</button>`;
        }

        paginationDiv.innerHTML = html;
    }

    // Load flags page
    async function loadFlagsPage(page, search) {
        const container = document.getElementById('flags-container');
        container.innerHTML = '<div class="text-center text-gray-600 dark:text-slate-400 py-8">Loading...</div>';

        try {
            const params = new URLSearchParams({
                page: page,
                limit: flagsPageSize,
                search: search || ''
            });

            const response = await fetch(`/api/flags?\${params}`);
            const json = await response.json();

            if (json.success) {
                renderFlagAccordions(json.data);
                flagsTotalPages = json.meta.total_pages;
                flagsCurrentPage = json.meta.page;
                renderFlagsPagination();
            } else {
                container.innerHTML = '<div class="text-center text-red-600 dark:text-red-400 py-8">Error loading flags.</div>';
            }
        } catch (error) {
            console.error('Flags load error:', error);
            container.innerHTML = '<div class="text-center text-red-600 dark:text-red-400 py-8">Error loading flags.</div>';
        }
    }

    // Search input handler
    const searchInput = document.getElementById('flags-search');
    searchInput.addEventListener('input', debounce(() => {
        flagsCurrentSearch = searchInput.value.trim();
        loadFlagsPage(1, flagsCurrentSearch);
    }, 300));

    // Reseed flags
    async function reseedFlags() {
        if (!confirm('Reseed all CTF flags in the database? This updates ctf_flags table and accounts.ctf_flag column.')) return;
        const r = await fetch('/flags', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({csrf_token: CSRF})
        });
        const d = await r.json();
        document.getElementById('flag-status').textContent = d.message || JSON.stringify(d);
    }

    // Initial load
    loadFlagsPage(1, '');
    </script>
HTML;

sa_render('CTF Flags', $content, 'flags');
