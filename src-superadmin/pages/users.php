<?php

declare(strict_types=1);

$pdo = db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF invalid.']);
        exit;
    }

    $action = (string) ($input['action'] ?? '');

    switch ($action) {
        case 'create':
            $name = (string) ($input['name'] ?? '');
            $email = (string) ($input['email'] ?? '');
            $password = (string) ($input['password'] ?? '');
            $cid = (string) ($input['customer_id'] ?? '');
            $phone = (string) ($input['phone'] ?? '');

            if (!$name || !$email || !$password || !$cid || !$phone) {
                echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
                exit;
            }

            if (!preg_match('/^[A-Z]\d{8}$/i', $cid)) {
                echo json_encode(['ok' => false, 'error' => 'Customer ID must be a letter followed by 8 digits (e.g., G01197938).']);
                exit;
            }

            try {
                $stmt = $pdo->prepare('
                    INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, role)
                    VALUES (:cid, :name, :email, :phone, :ph, :md5, :role)
                ');
                $stmt->execute([
                    ':cid'   => $cid,
                    ':name'  => $name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':ph'    => password_hash($password, PASSWORD_BCRYPT),
                    ':md5'   => md5($password),
                    ':role'  => 'user',
                ]);
                echo json_encode(['ok' => true, 'message' => "User '{$name}' created successfully."]);
            } catch (PDOException $e) {
                $error = $e->getCode() === '23000' ? 'Customer ID or email already exists.' : 'Database error: ' . $e->getMessage();
                echo json_encode(['ok' => false, 'error' => $error]);
            }
            break;

        case 'suspend':
        case 'unlock':
        case 'reset-password':
            $userId = (int) ($input['user_id'] ?? 0);
            if ($userId <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid user ID.']);
                exit;
            }

            if ($action === 'suspend') {
                $suspended = (int) ($input['suspended'] ?? 1);
                $pdo->prepare('UPDATE users SET is_suspended = :s WHERE id = :id')
                    ->execute([':s' => $suspended, ':id' => $userId]);
                echo json_encode(['ok' => true, 'message' => $suspended ? 'User suspended.' : 'User unsuspended.']);
            } elseif ($action === 'unlock') {
                $pdo->prepare('UPDATE users SET is_locked = 0, failed_attempts = 0, locked_until = NULL WHERE id = :id')
                    ->execute([':id' => $userId]);
                echo json_encode(['ok' => true, 'message' => 'User unlocked.']);
            } elseif ($action === 'reset-password') {
                // Reset to the standard generated password: {customer_id}B4rc@123
                // (same convention as bulk import / replace).
                $cidStmt = $pdo->prepare('SELECT customer_id FROM users WHERE id = :id');
                $cidStmt->execute([':id' => $userId]);
                $customerId = $cidStmt->fetchColumn();
                if ($customerId === false) {
                    echo json_encode(['ok' => false, 'error' => 'User not found.']);
                    exit;
                }
                $temp = $customerId . 'B4rc@123';
                $pdo->prepare('UPDATE users SET password_hash = :ph, password_md5 = :md5 WHERE id = :id')
                    ->execute([
                        ':ph'  => password_hash($temp, PASSWORD_BCRYPT),
                        ':md5' => md5($temp),
                        ':id'  => $userId,
                    ]);
                echo json_encode(['ok' => true, 'message' => "Password reset to: {$temp}"]);
            }
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    }
    exit;
}

// GET — get total users for initial display (will be paginated via JS)
$totalUsers = $pdo->query('SELECT COUNT(*) as total FROM users')->fetch()['total'];
$csrf = csrf_token();

// Create new user form
$createForm = <<<HTML
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Create New User</h3>
        <form id="create-user-form" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1">Full Name *</label>
                <input type="text" name="name" required class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="John Doe">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1">Customer ID * <span class="text-xs text-gray-500 dark:text-slate-400">(letter + 8 digits)</span></label>
                <input type="text" name="customer_id" required pattern="[A-Za-z]\d{8}" title="Must be a letter followed by 8 digits (e.g., G01197938 or a01197938)" class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase" placeholder="G01197938">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1">Email *</label>
                <input type="email" name="email" required class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="user@example.com">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1">Phone *</label>
                <input type="tel" name="phone" required class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="+91-9876543210">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300 mb-1">Password *</label>
                <input type="password" name="password" required class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="••••••••">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm transition">Create User</button>
            </div>
        </form>
    </div>
HTML;

$content = <<<HTML
    <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">Manage CTF participant accounts. Actions take effect immediately on the CTF app.</p>

    {$createForm}

    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Existing Users</h3>

    <!-- Search input -->
    <div class="mb-4">
        <input type="text" id="user-search" placeholder="Search by name, email, or customer ID..."
               class="w-full px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
    </div>

    <!-- Users table -->
    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4">
        <table class="w-full" id="users-table">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">User</th><th class="pb-2">Customer ID</th><th class="pb-2">Role</th><th class="pb-2">Status</th><th class="pb-2 text-right">Actions</th>
            </tr></thead>
            <tbody id="users-tbody">
                <tr><td colspan="5" class="py-4 text-center text-gray-500 dark:text-slate-500">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination controls -->
    <div class="mt-6 flex items-center justify-between">
        <div id="users-pagination" class="flex gap-2">
            <!-- Rendered by JS -->
        </div>
        <div class="text-sm text-gray-600 dark:text-slate-400">
            Page <span id="users-page">1</span> of <span id="users-total-pages">1</span> (Total: {$totalUsers})
        </div>
    </div>

    <pre id="action-out" class="mt-3 text-xs text-gray-600 dark:text-slate-400 whitespace-pre-wrap"></pre>

    <script>
    const CSRF = '{$csrf}';
    let userCurrentPage = 1;
    let userCurrentSearch = '';
    let userTotalPages = 1;
    const userPageSize = 25;

    // Debounce utility
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    // Render user rows
    function renderUserRows(users) {
        const tbody = document.getElementById('users-tbody');
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-gray-500 dark:text-slate-500">No users found.</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(u => {
            const roleBadge = u.role === 'admin'
                ? '<span class="px-2 py-0.5 rounded text-xs bg-amber-50 dark:bg-amber-400/20 text-amber-800 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-0 font-medium">admin</span>'
                : '<span class="px-2 py-0.5 rounded text-xs bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300">user</span>';

            const status = u.is_suspended
                ? '<span class="text-xs text-red-600 dark:text-red-300 font-medium">suspended</span>'
                : (u.is_locked ? '<span class="text-xs text-amber-600 dark:text-amber-300 font-medium">locked</span>' : '<span class="text-xs text-emerald-600 dark:text-emerald-300">active</span>');

            const suspendLabel = u.is_suspended ? 'Unsuspend' : 'Suspend';
            const suspendVal = u.is_suspended ? 0 : 1;

            return `
                <tr class="border-t border-gray-100 dark:border-slate-800">
                    <td class="py-2 text-gray-900 dark:text-slate-200 text-sm">\${u.name}<div class="text-xs text-gray-500 dark:text-slate-500">\${u.email}</div></td>
                    <td class="py-2 text-gray-600 dark:text-slate-400 text-sm font-mono">\${u.customer_id}</td>
                    <td class="py-2">\${roleBadge}</td>
                    <td class="py-2">\${status}</td>
                    <td class="py-2 text-right whitespace-nowrap">
                        <button onclick="userAction(\${u.id},'suspend',{\${suspendVal}})" class="text-xs rounded bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-2 py-1 text-gray-700 dark:text-slate-200">\${suspendLabel}</button>
                        <button onclick="userAction(\${u.id},'unlock',{})" class="text-xs rounded bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-2 py-1 text-gray-700 dark:text-slate-200">Unlock</button>
                        <button onclick="userAction(\${u.id},'reset-password',{},this)" class="text-xs rounded bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 ring-1 ring-gray-200 dark:ring-0 px-2 py-1 text-gray-700 dark:text-slate-200">Reset PW</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Render pagination
    function renderUserPagination() {
        const paginationDiv = document.getElementById('users-pagination');
        const pageSpan = document.getElementById('users-page');
        const totalPagesSpan = document.getElementById('users-total-pages');

        pageSpan.textContent = userCurrentPage;
        totalPagesSpan.textContent = userTotalPages;

        let html = '';

        // Previous button
        if (userCurrentPage > 1) {
            html += `<button onclick="loadUserPage(\${userCurrentPage - 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">← Prev</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">← Prev</button>`;
        }

        // Page info
        html += `<span class="px-4 py-2 text-gray-800 dark:text-slate-300 font-medium">Page \${userCurrentPage} of \${userTotalPages}</span>`;

        // Next button
        if (userCurrentPage < userTotalPages) {
            html += `<button onclick="loadUserPage(\${userCurrentPage + 1})" class="px-4 py-2 bg-indigo-600 dark:bg-indigo-600 hover:bg-indigo-700 dark:hover:bg-indigo-700 text-white rounded-lg font-medium transition shadow-sm border-2 border-indigo-700 dark:border-indigo-800">Next →</button>`;
        } else {
            html += `<button disabled class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-600 dark:text-slate-500 rounded-lg font-medium cursor-not-allowed border-2 border-gray-300 dark:border-slate-600">Next →</button>`;
        }

        paginationDiv.innerHTML = html;
    }

    // Load users page from API
    async function loadUserPage(page, search) {
        const tbody = document.getElementById('users-tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-gray-500 dark:text-slate-500">Loading...</td></tr>';

        try {
            const params = new URLSearchParams({
                page: page,
                limit: userPageSize,
                search: search || ''
            });

            const response = await fetch(`/api/users?\${params}`);
            const json = await response.json();

            if (json.success) {
                renderUserRows(json.data);
                userTotalPages = json.meta.total_pages;
                userCurrentPage = json.meta.page;
                renderUserPagination();
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-gray-500 dark:text-slate-500">Error loading users.</td></tr>';
            }
        } catch (error) {
            console.error('Users load error:', error);
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-gray-500 dark:text-slate-500">Error loading users.</td></tr>';
        }
    }

    // Search input handler with debounce
    const searchInput = document.getElementById('user-search');
    searchInput.addEventListener('input', debounce(() => {
        userCurrentSearch = searchInput.value.trim();
        loadUserPage(1, userCurrentSearch);
    }, 300));

    // Create user form submission
    document.getElementById('create-user-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        data.action = 'create';

        const r = await fetch('/users', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await r.json();
        document.getElementById('action-out').textContent = result.message || JSON.stringify(result);

        if (result.ok) {
            e.target.reset();
            setTimeout(() => loadUserPage(1, ''), 1000);
        }
    });

    // Manage user actions
    async function userAction(uid, action, extra, btn) {
        const r = await fetch('/users', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action, user_id: uid, ...extra, csrf_token: CSRF})
        });
        const d = await r.json();
        document.getElementById('action-out').textContent = d.message || JSON.stringify(d);
        if (action === 'reset-password') {
            // Inline confirmation on the user's own row.
            if (btn && d.ok) { btn.textContent = 'Done ✓'; btn.disabled = true; btn.classList.add('text-emerald-600', 'dark:text-emerald-300'); }
        } else {
            setTimeout(() => loadUserPage(userCurrentPage, userCurrentSearch), 800);
        }
    }

    // Initial load
    loadUserPage(1, '');
    </script>
HTML;

sa_render('User Management', $content, 'users');
