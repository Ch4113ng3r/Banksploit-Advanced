<?php

declare(strict_types=1);

$pdo = db();
$engine = engine();

// --- Master kill switch -----------------------------------------------------
// Closes the entire lab app (port 9443). The instructor panel stays reachable.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode((string) file_get_contents('php://input'), true) ?? $_POST;
    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }
    if ((string) ($input['action'] ?? '') === 'lab-switch') {
        $open = (bool) ($input['open'] ?? false);
        $pdo->prepare(
            'INSERT INTO feature_toggles (feature_key, is_enabled, description)
             VALUES (:k, :v, :d)
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)'
        )->execute([
            ':k' => 'lab_open',
            ':v' => $open ? 1 : 0,
            ':d' => 'Master switch for the whole lab environment',
        ]);
        echo json_encode(['ok' => true, 'open' => $open]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

$labOpenRow = $pdo->query("SELECT is_enabled FROM feature_toggles WHERE feature_key = 'lab_open'")->fetchColumn();
$labOpen    = $labOpenRow === false ? true : (bool) $labOpenRow;
$saCsrf     = csrf_token();

// --- Stats cards data ---
$activeUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended = 0")->fetchColumn();
$totalUsers  = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTxns   = (int) $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$flagsCaptured = (int) $pdo->query("SELECT COUNT(*) FROM ctf_submissions")->fetchColumn();

// Count vulns at each level
$allVulns = $engine->getAllVulns();
$levels = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
foreach ($allVulns['modules'] as $mod => $vulns) {
    foreach ($vulns as $v) {
        $l = (int) ($v['level'] ?? 0);
        $levels[$l] = ($levels[$l] ?? 0) + 1;
    }
}

// --- Build CTF data for JS ---

// Vuln list (fixed order for the dot matrix)
$ctfVulns = [];
$vulnOrder = [
    'auth.l11', 'auth.g1', 'auth.l10', 'statements.l1',
    'transfer.l1', 'statements.g4', 'auth.l12', 'statements.g2', 'statements.g9',
];
foreach ($vulnOrder as $vk) {
    [$mod, $vid] = explode('.', $vk, 2);
    $info = $allVulns['modules'][$mod][$vid] ?? $allVulns['modules'][$mod][strtoupper($vid)] ?? null;
    $title = $info['title'] ?? strtoupper($vid);
    $id = $info['id'] ?? strtoupper($vid);
    $ctfVulns[] = [
        'key' => $vk,
        'id'  => $id,
        'title' => $title,
        'pts' => Flags::points($vk),
    ];
}

// All participants (users with role='user') + their aggregated scores
$participants = $pdo->query(
    "SELECT u.id AS uid, u.customer_id AS cid, u.name, u.email,
            COALESCE(s.flags_captured, 0) AS flags,
            COALESCE(s.total_points, 0) AS pts,
            s.last_capture AS last
       FROM users u
       LEFT JOIN (
           SELECT user_id,
                  COUNT(*) AS flags_captured,
                  SUM(points) AS total_points,
                  MAX(submitted_at) AS last_capture
             FROM ctf_submissions
            GROUP BY user_id
       ) s ON s.user_id = u.id
      WHERE u.role = 'user'
      ORDER BY pts DESC, last ASC, u.name ASC"
)->fetchAll();

// Read hidden users from file
$hiddenFile = dirname(__DIR__, 2) . '/data/ctf-hidden-users.json';
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

// Per-user captured vulns: { user_id => { vuln_key => submitted_at } }
$allSubs = $pdo->query('SELECT user_id, vuln_key, submitted_at FROM ctf_submissions ORDER BY submitted_at ASC')->fetchAll();
$capturedMap = [];
foreach ($allSubs as $sub) {
    $capturedMap[(int) $sub['user_id']][$sub['vuln_key']] = substr($sub['submitted_at'], 0, 16);
}

// Per-vuln completion counts
$vulnCounts = [];
$countRows = $pdo->query('SELECT vuln_key, COUNT(*) AS c FROM ctf_submissions GROUP BY vuln_key')->fetchAll();
foreach ($countRows as $r) {
    $vulnCounts[$r['vuln_key']] = (int) $r['c'];
}

// Build JS-ready participant objects
$jsParticipants = [];
foreach ($participants as $p) {
    $uid = (int) $p['uid'];
    $jsParticipants[] = [
        'uid'    => $uid,
        'name'   => $p['name'],
        'cid'    => $p['cid'],
        'email'  => $p['email'],
        'flags'  => (int) $p['flags'],
        'pts'    => (int) $p['pts'],
        'last'   => $p['last'] ? substr($p['last'], 0, 16) : null,
        'hidden' => in_array($uid, $hiddenIds, true),
        'captured' => $capturedMap[$uid] ?? (object) [],
    ];
}

// Add completion counts to vulns
foreach ($ctfVulns as &$cv) {
    $cv['completions'] = $vulnCounts[$cv['key']] ?? 0;
}
unset($cv);

$totalParticipants = count($jsParticipants);
$maxPoints = array_sum(array_column($ctfVulns, 'pts'));

$ctfDataJson = json_encode([
    'vulns' => $ctfVulns,
    'participants' => $jsParticipants,
    'totalParticipants' => $totalParticipants,
    'maxPoints' => $maxPoints,
], JSON_UNESCAPED_UNICODE);

$content = <<<'HTML'
    <div id="labSwitchCard" class="rounded-xl ring-1 px-5 py-4 mb-6 flex flex-wrap items-center justify-between gap-4 LAB_CARD_CLS">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <span id="labDot" class="inline-block w-2.5 h-2.5 rounded-full LAB_DOT_CLS"></span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">Lab environment is <span id="labStateText">LAB_STATE_TEXT</span></span>
            </div>
            <p class="text-xs text-gray-600 dark:text-slate-400 mt-1 max-w-2xl">LAB_HELP_TEXT</p>
        </div>
        <button id="labSwitchBtn" onclick="toggleLab()" class="rounded-lg px-4 py-2 text-xs font-bold whitespace-nowrap text-white LAB_BTN_CLS">LAB_BTN_TEXT</button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Active Users</div>
            <div class="text-3xl font-black text-gray-900 dark:text-white mt-1">ACTIVE_USERS<span class="text-base text-gray-400 dark:text-slate-500">/TOTAL_USERS</span></div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Transactions</div>
            <div class="text-3xl font-black text-gray-900 dark:text-white mt-1">TOTAL_TXNS</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Flags Captured</div>
            <div class="text-3xl font-black text-amber-600 dark:text-amber-300 mt-1">FLAGS_CAPTURED</div>
        </div>
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
            <div class="text-xs text-gray-500 dark:text-slate-500 uppercase font-medium">Vuln Levels</div>
            <div class="text-sm mt-2 space-y-1">
                <div class="flex justify-between"><span class="text-red-600 dark:text-red-300">L0 (Vulnerable)</span><span class="font-mono text-gray-900 dark:text-white">LVL0</span></div>
                <div class="flex justify-between"><span class="text-amber-600 dark:text-amber-300">L1 (Weak)</span><span class="font-mono text-gray-900 dark:text-white">LVL1</span></div>
                <div class="flex justify-between"><span class="text-sky-600 dark:text-sky-300">L2 (Partial)</span><span class="font-mono text-gray-900 dark:text-white">LVL2</span></div>
                <div class="flex justify-between"><span class="text-emerald-600 dark:text-emerald-300">L3 (Secure)</span><span class="font-mono text-gray-900 dark:text-white">LVL3</span></div>
            </div>
        </div>
    </div>

    <!-- Per-vuln completion chips -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-semibold text-gray-500 dark:text-slate-500 uppercase tracking-wide">Vulnerability Completion</h3>
            <button onclick="clearFilter()" id="clear-filter-btn" class="hidden text-xs text-amber-600 dark:text-amber-400 hover:text-amber-700 dark:hover:text-amber-300 font-medium">Clear filter ✕</button>
        </div>
        <div class="flex flex-wrap gap-2" id="vuln-chips"></div>
    </div>

    <!-- Interactive Participant Table -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase tracking-wide">🏆 CTF Leaderboard</h3>
            <span class="text-xs text-gray-500 dark:text-slate-500" id="table-count"></span>
        </div>

        <!-- Controls -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="flex-1 relative">
                <input type="text" id="search-input" placeholder="Search by name, customer ID, or email..."
                       class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-2 pl-9 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none"
                       oninput="onSearch()">
                <span class="absolute left-3 top-2.5 text-gray-400 dark:text-slate-500 text-sm">🔍</span>
            </div>
            <select id="vuln-filter" onchange="onVulnFilter()"
                    class="rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-2 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none min-w-[200px]">
                <option value="">All participants</option>
                <option value="__none">No flags captured</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                        <th class="pb-2 w-12 text-center cursor-pointer hover:text-amber-600" onclick="sortBy('rank')">#</th>
                        <th class="pb-2 cursor-pointer hover:text-amber-600" onclick="sortBy('name')">Participant <span id="sort-name" class="text-amber-600"></span></th>
                        <th class="pb-2 text-center cursor-pointer hover:text-amber-600" onclick="sortBy('flags')">Flags <span id="sort-flags"></span></th>
                        <th class="pb-2 cursor-pointer hover:text-amber-600" onclick="sortBy('pts')">Points <span id="sort-pts" class="text-amber-600">▼</span></th>
                        <th class="pb-2 hidden lg:table-cell">Vulnerabilities</th>
                        <th class="pb-2 cursor-pointer hover:text-amber-600" onclick="sortBy('last')">Last Capture <span id="sort-last"></span></th>
                    </tr>
                </thead>
                <tbody id="participant-rows"></tbody>
            </table>
        </div>

        <!-- Pagination controls -->
        <div class="mt-6 flex items-center justify-between">
            <div id="pagination-controls" class="flex gap-2">
                <!-- Rendered by JS -->
            </div>
            <div class="text-sm text-gray-600 dark:text-slate-400">
                Page <span id="current-page">1</span> of <span id="total-pages">1</span>
            </div>
        </div>

        <!-- Empty state -->
        <div id="empty-state" class="hidden py-8 text-center text-gray-400 dark:text-slate-500 text-sm">
            No participants match your search/filter.
        </div>
    </div>

    <!-- Scoring link -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 mb-8 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase tracking-wide">🏅 Scoring</h3>
            <p class="text-xs text-gray-500 dark:text-slate-500 mt-0.5">100 (Easy) · 200 (Medium) · 300 (Hard) · 400 (Expert) — Max MAX_PTS pts</p>
        </div>
        <a href="/scoring" class="rounded-lg bg-amber-50 dark:bg-amber-600/30 hover:bg-amber-100 dark:hover:bg-amber-600/50 ring-1 ring-amber-200 dark:ring-0 px-3 py-2 text-xs text-amber-800 dark:text-amber-200 font-medium">View Full Breakdown →</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase mb-3">Quick Actions</h3>
            <div class="space-y-2">
                <a href="/vuln-config" class="block rounded-lg bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-4 py-3 text-sm text-gray-700 dark:text-slate-200">
                    🎯 Manage Vulnerability Levels
                </a>
                <a href="/users" class="block rounded-lg bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-4 py-3 text-sm text-gray-700 dark:text-slate-200">
                    👥 User Management
                </a>
                <a href="/progress" class="block rounded-lg bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-4 py-3 text-sm text-gray-700 dark:text-slate-200">
                    📈 View CTF Progress
                </a>
                <a href="/flags" class="block rounded-lg bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-4 py-3 text-sm text-gray-700 dark:text-slate-200">
                    🚩 View CTF Flags
                </a>
            </div>
        </div>

        <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-400 uppercase mb-3">Portal Info</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500 dark:text-slate-400">CTF App</span><span class="text-gray-900 dark:text-slate-200">http://localhost:9443</span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-slate-400">Original App</span><span class="text-gray-900 dark:text-slate-200">http://localhost:8443</span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-slate-400">Control Panel</span><span class="text-amber-700 dark:text-amber-300 font-medium">http://127.0.0.1:8888</span></div>
                <div class="flex justify-between"><span class="text-gray-500 dark:text-slate-400">Database</span><span class="text-gray-900 dark:text-slate-200">banksploit @ localhost:3306</span></div>
            </div>
            <div class="mt-4 p-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                <p class="text-xs text-amber-800 dark:text-amber-200">⚠️ This panel binds to 127.0.0.1 only — not accessible from the network. Participants cannot reach it.</p>
            </div>
        </div>
    </div>

    <script>
    const CTF_DATA = CTF_DATA_JSON;

    // --- State ---
    let currentSort = { key: 'pts', dir: 'desc' };
    let searchQuery = '';
    let vulnFilter = '';
    let expandedUid = null;
    let currentPage = 1;
    const pageSize = 25;
    let totalPages = 1;

    // --- Detect dark mode for JS-rendered content ---
    const isDark = () => document.documentElement.classList.contains('dark');

    // --- Tier colors (adapts to theme) ---
    const tierColor = (pts) => {
        if (isDark()) {
            if (pts <= 100) return { bg: 'rgba(16,185,129,0.15)', border: 'rgba(16,185,129,0.3)', text: '#6ee7b7' };
            if (pts <= 200) return { bg: 'rgba(14,165,233,0.15)', border: 'rgba(14,165,233,0.3)', text: '#7dd3fc' };
            if (pts <= 300) return { bg: 'rgba(245,158,11,0.15)', border: 'rgba(245,158,11,0.3)', text: '#fcd34d' };
            return { bg: 'rgba(239,68,68,0.15)', border: 'rgba(239,68,68,0.3)', text: '#fca5a5' };
        }
        if (pts <= 100) return { bg: '#ecfdf5', border: '#a7f3d0', text: '#065f46' };
        if (pts <= 200) return { bg: '#eff6ff', border: '#bfdbfe', text: '#1e40af' };
        if (pts <= 300) return { bg: '#fffbeb', border: '#fde68a', text: '#92400e' };
        return { bg: '#fef2f2', border: '#fecaca', text: '#991b1b' };
    };

    // --- Init ---
    function init() {
        renderChips();
        populateVulnDropdown();
        renderTable();
    }

    // --- Vuln completion chips ---
    function renderChips() {
        const container = document.getElementById('vuln-chips');
        container.innerHTML = CTF_DATA.vulns.map(v => {
            const c = tierColor(v.pts);
            const isActive = vulnFilter === v.key;
            const ring = isActive ? 'ring-2 ring-amber-500' : '';
            return `<button onclick="filterByVuln('${v.key}')" title="${v.title} (${v.pts} pts)"
                class="px-2.5 py-1.5 rounded-lg text-xs font-medium transition ${ring}"
                style="background:${c.bg};border:1px solid ${c.border};color:${c.text}">
                ${v.id} <span class="opacity-70 ml-1">${v.completions}/${CTF_DATA.totalParticipants}</span>
            </button>`;
        }).join('');
    }

    // --- Dropdown options ---
    function populateVulnDropdown() {
        const sel = document.getElementById('vuln-filter');
        CTF_DATA.vulns.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.key;
            opt.textContent = `${v.id} — ${v.title} (${v.completions} captured)`;
            sel.appendChild(opt);
        });
    }

    // --- Filter handlers ---
    function onSearch() {
        searchQuery = document.getElementById('search-input').value.toLowerCase().trim();
        renderTable();
    }

    function onVulnFilter() {
        vulnFilter = document.getElementById('vuln-filter').value;
        document.getElementById('clear-filter-btn').classList.toggle('hidden', !vulnFilter);
        renderChips();
        renderTable();
    }

    function filterByVuln(key) {
        if (vulnFilter === key) {
            vulnFilter = '';
            document.getElementById('vuln-filter').value = '';
        } else {
            vulnFilter = key;
            document.getElementById('vuln-filter').value = key;
        }
        document.getElementById('clear-filter-btn').classList.toggle('hidden', !vulnFilter);
        renderChips();
        renderTable();
    }

    function clearFilter() {
        vulnFilter = '';
        searchQuery = '';
        document.getElementById('vuln-filter').value = '';
        document.getElementById('search-input').value = '';
        document.getElementById('clear-filter-btn').classList.add('hidden');
        currentPage = 1;
        renderChips();
        renderTable();
    }

    // --- Pagination ---
    function renderPagination() {
        const controlsDiv = document.getElementById('pagination-controls');
        const pageSpan = document.getElementById('current-page');
        const totalPagesSpan = document.getElementById('total-pages');

        pageSpan.textContent = currentPage;
        totalPagesSpan.textContent = totalPages;

        let html = '';

        // Previous button
        if (currentPage > 1) {
            html += `<button onclick="goToPage(${currentPage - 1})" class="px-4 py-2 bg-amber-600 dark:bg-amber-600 hover:bg-amber-700 dark:hover:bg-amber-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-amber-700 dark:border-amber-800">← Prev</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">← Prev</button>`;
        }

        // Page info
        html += `<span class="px-4 py-2 text-gray-800 dark:text-slate-300 font-medium">Page ${currentPage} of ${totalPages}</span>`;

        // Next button
        if (currentPage < totalPages) {
            html += `<button onclick="goToPage(${currentPage + 1})" class="px-4 py-2 bg-amber-600 dark:bg-amber-600 hover:bg-amber-700 dark:hover:bg-amber-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-amber-700 dark:border-amber-800">Next →</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">Next →</button>`;
        }

        controlsDiv.innerHTML = html;
    }

    function goToPage(page) {
        currentPage = Math.max(1, Math.min(page, totalPages));
        renderTable();
    }

    // --- Sorting ---
    function sortBy(key) {
        if (currentSort.key === key) {
            currentSort.dir = currentSort.dir === 'desc' ? 'asc' : 'desc';
        } else {
            currentSort = { key, dir: key === 'name' ? 'asc' : 'desc' };
        }
        // Update sort indicators
        ['name','flags','pts','last'].forEach(k => {
            const el = document.getElementById('sort-' + k);
            if (el) el.textContent = currentSort.key === k ? (currentSort.dir === 'desc' ? '▼' : '▲') : '';
        });
        renderTable();
    }

    // --- Main render ---
    function renderTable() {
        let data = [...CTF_DATA.participants];

        // Apply search filter
        if (searchQuery) {
            data = data.filter(p =>
                p.name.toLowerCase().includes(searchQuery) ||
                p.cid.toLowerCase().includes(searchQuery) ||
                p.email.toLowerCase().includes(searchQuery)
            );
        }

        // Apply vuln filter
        if (vulnFilter === '__none') {
            data = data.filter(p => p.flags === 0);
        } else if (vulnFilter) {
            data = data.filter(p => p.captured && p.captured[vulnFilter]);
        }

        // Sort
        data.sort((a, b) => {
            let va, vb;
            switch (currentSort.key) {
                case 'name': va = a.name.toLowerCase(); vb = b.name.toLowerCase(); break;
                case 'flags': va = a.flags; vb = b.flags; break;
                case 'pts': va = a.pts; vb = b.pts; break;
                case 'last': va = a.last || ''; vb = b.last || ''; break;
                default: va = a.pts; vb = b.pts;
            }
            if (va < vb) return currentSort.dir === 'asc' ? -1 : 1;
            if (va > vb) return currentSort.dir === 'asc' ? 1 : -1;
            return 0;
        });

        // Calculate pagination
        totalPages = Math.max(1, Math.ceil(data.length / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;

        // Update count
        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = Math.min(startIdx + pageSize, data.length);
        document.getElementById('table-count').textContent =
            `Showing ${startIdx + 1}–${endIdx} of ${data.length} participants`;

        // Empty state
        const emptyEl = document.getElementById('empty-state');
        const tbody = document.getElementById('participant-rows');

        if (data.length === 0) {
            tbody.innerHTML = '';
            emptyEl.classList.remove('hidden');
            renderPagination();
            return;
        }
        emptyEl.classList.add('hidden');

        // Apply pagination to data
        const pageData = data.slice(startIdx, endIdx);

        // Render rows
        const dark = isDark();
        let html = '';
        pageData.forEach((p, idx) => {
            const rank = startIdx + idx + 1;
            const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : `<span class="${dark ? 'text-slate-500' : 'text-gray-400'} font-mono">${rank}</span>`;
            const pct = CTF_DATA.maxPoints > 0 ? Math.min(100, Math.round(p.pts / CTF_DATA.maxPoints * 100)) : 0;
            const barColor = rank === 1 ? 'bg-amber-500' : rank <= 3 ? 'bg-amber-400' : (dark ? 'bg-slate-600' : 'bg-gray-300');
            const isExpanded = expandedUid === p.uid;
            const hiddenClass = p.hidden ? 'opacity-40' : '';
            const hiddenBadge = p.hidden ? `<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium ${dark ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-700'}">HIDDEN</span>` : '';

            // Vuln dots
            const dots = CTF_DATA.vulns.map(v => {
                const captured = p.captured && p.captured[v.key];
                const color = captured ? 'bg-emerald-500' : (dark ? 'bg-slate-700' : 'bg-gray-200');
                const ring = (vulnFilter === v.key && captured) ? 'ring-1 ring-amber-500' : '';
                return `<span title="${v.id}: ${captured ? '✓ Captured' : 'Not yet'}" class="inline-block w-2.5 h-2.5 rounded-full ${color} ${ring}"></span>`;
            }).join('');

            html += `<tr class="border-t ${dark ? 'border-slate-800 hover:bg-slate-800/30' : 'border-gray-100 hover:bg-amber-50/40'} cursor-pointer transition ${hiddenClass}" onclick="toggleExpand(${p.uid})">
                <td class="py-2.5 text-center text-sm">${medal}</td>
                <td class="py-2.5">
                    <div class="text-sm font-medium ${dark ? 'text-white' : 'text-gray-900'}">${esc(p.name)}${hiddenBadge}</div>
                    <div class="text-xs ${dark ? 'text-slate-500' : 'text-gray-500'}">${esc(p.cid)} · ${esc(p.email)}</div>
                </td>
                <td class="py-2.5 text-center font-mono text-sm ${dark ? 'text-white' : 'text-gray-900'}">${p.flags}</td>
                <td class="py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-sm font-bold ${dark ? 'text-amber-300' : 'text-amber-700'} w-12">${p.pts}</span>
                        <div class="flex-1 h-1.5 rounded-full ${dark ? 'bg-slate-800' : 'bg-gray-100'} overflow-hidden hidden sm:block">
                            <div class="h-full ${barColor} rounded-full" style="width:${pct}%"></div>
                        </div>
                    </div>
                </td>
                <td class="py-2.5 hidden lg:table-cell"><div class="flex gap-0.5 flex-wrap">${dots}</div></td>
                <td class="py-2.5 text-xs ${dark ? 'text-slate-500' : 'text-gray-500'}">${p.last || '—'}</td>
            </tr>`;

            // Expanded detail row
            if (isExpanded) {
                html += renderExpandedRow(p);
            }
        });

        tbody.innerHTML = html;
        renderPagination();
    }

    // --- Expanded detail row ---
    function renderExpandedRow(p) {
        const dark = isDark();
        const capturedVulns = CTF_DATA.vulns.filter(v => p.captured && p.captured[v.key]);
        const uncaptured = CTF_DATA.vulns.filter(v => !(p.captured && p.captured[v.key]));

        let detail = '<div class="flex flex-wrap gap-2 mb-2">';
        if (capturedVulns.length === 0) {
            detail += `<span class="${dark ? 'text-slate-500' : 'text-gray-400'} text-xs">No flags captured yet.</span>`;
        } else {
            capturedVulns.forEach(v => {
                const c = tierColor(v.pts);
                detail += `<span class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs" style="background:${c.bg};border:1px solid ${c.border};color:${c.text}">
                    ✓ ${v.id} <span class="opacity-60">+${v.pts}</span>
                    <span class="${dark ? 'text-slate-500' : 'text-gray-500'} ml-1">${p.captured[v.key]}</span>
                </span>`;
            });
        }
        detail += '</div>';

        if (uncaptured.length > 0) {
            detail += `<div class="text-xs ${dark ? 'text-slate-600' : 'text-gray-400'} mt-1">Remaining: ` +
                uncaptured.map(v => v.id).join(', ') + '</div>';
        }

        return `<tr class="${dark ? 'bg-slate-800/20' : 'bg-amber-50/30'}"><td></td><td colspan="5" class="pb-3 pt-1 px-2">${detail}</td></tr>`;
    }

    function toggleExpand(uid) {
        expandedUid = expandedUid === uid ? null : uid;
        renderTable();
    }

    // --- Utility ---
    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // Boot
    init();
    </script>
HTML;

// Inject dynamic PHP values into the HTML template
$content .= <<<'HTML'
<script>
const LAB_CSRF = "SA_CSRF_TOKEN";
async function toggleLab(){
    const open = document.getElementById('labStateText').textContent.trim().toLowerCase() === 'open';
    const next = !open;
    const msg = next
        ? 'Reopen the lab? Participants will be able to reach the application again.'
        : 'Close the ENTIRE lab environment?\n\nEvery participant page stops working immediately and shows a "lab closed" notice. This panel stays available, and nothing is deleted \u2014 you can reopen at any time.';
    if (!confirm(msg)) return;
    const btn = document.getElementById('labSwitchBtn');
    btn.disabled = true; btn.style.opacity = '.6';
    const r = await fetch('/dashboard', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'lab-switch', open: next, csrf_token: LAB_CSRF})
    });
    const d = await r.json();
    if (d.ok) { location.reload(); }
    else { alert(d.error || 'Failed'); btn.disabled = false; btn.style.opacity = '1'; }
}
</script>
HTML;

$content = str_replace('SA_CSRF_TOKEN', $saCsrf, $content);

$content = str_replace(
    ['LAB_CARD_CLS', 'LAB_DOT_CLS', 'LAB_STATE_TEXT', 'LAB_HELP_TEXT', 'LAB_BTN_CLS', 'LAB_BTN_TEXT'],
    $labOpen
        ? ['bg-emerald-50 dark:bg-emerald-500/10 ring-emerald-200 dark:ring-emerald-500/30',
           'bg-emerald-500',
           'open',
           'Participants can reach the application normally. Use this to shut everything down at the end of the session \u2014 nothing is deleted and you can reopen instantly.',
           'bg-red-600 hover:bg-red-500',
           'Close Lab']
        : ['bg-red-50 dark:bg-red-500/10 ring-red-200 dark:ring-red-500/40',
           'bg-red-500 animate-pulse',
           'CLOSED',
           'Every participant request is being refused with a "lab closed" notice. This panel is unaffected. Reopen whenever you are ready.',
           'bg-emerald-600 hover:bg-emerald-500',
           'Reopen Lab'],
    $content
);

$content = str_replace(
    ['ACTIVE_USERS', 'TOTAL_USERS', 'TOTAL_TXNS', 'FLAGS_CAPTURED', 'LVL0', 'LVL1', 'LVL2', 'LVL3', 'MAX_PTS', 'CTF_DATA_JSON'],
    [(string)$activeUsers, (string)$totalUsers, (string)$totalTxns, (string)$flagsCaptured,
     (string)$levels[0], (string)$levels[1], (string)$levels[2], (string)$levels[3],
     (string)$maxPoints, $ctfDataJson],
    $content
);

sa_render('Dashboard', $content, 'dashboard');
