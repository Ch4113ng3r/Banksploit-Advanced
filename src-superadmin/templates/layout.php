<?php
/**
 * CTF Control Panel layout — supports light/dark mode toggle.
 * Theme is persisted in localStorage and applied via `class="dark"` on <html>.
 * Variables available: $title, $content, $active
 */
$user = SuperAdminAuth::getCurrentUser();
$userName = htmlspecialchars($user['name'] ?? 'Admin');
$navItems = [
    ['path' => '/dashboard',          'label' => 'Dashboard',          'icon' => '📊', 'key' => 'dashboard'],
    ['path' => '/modes',              'label' => 'Modes',               'icon' => '🎚️', 'key' => 'modes'],
    ['path' => '/vuln-config',        'label' => 'Vuln Config',        'icon' => '🎯', 'key' => 'vuln-config'],
    ['path' => '/ctf-vulns',          'label' => 'CTF Vulnerabilities', 'icon' => '🧩', 'key' => 'ctf-vulns'],
    ['path' => '/verify',             'label' => 'Verification',       'icon' => '✅', 'key' => 'verify'],
    ['path' => '/hints',              'label' => 'Hints',              'icon' => '💡', 'key' => 'hints'],
    ['path' => '/system-config',      'label' => 'System Config',      'icon' => '⚙️', 'key' => 'system-config'],
    ['path' => '/users',              'label' => 'Users',              'icon' => '👥', 'key' => 'users'],
    ['path' => '/bulk-import',        'label' => 'Bulk Import',        'icon' => '📥', 'key' => 'bulk-import'],
    ['path' => '/bulk-replace-users', 'label' => 'Replace Users',      'icon' => '🔄', 'key' => 'bulk-replace-users'],
    ['path' => '/transactions',       'label' => 'Transactions',       'icon' => '💳', 'key' => 'transactions'],
    ['path' => '/progress',           'label' => 'Progress',           'icon' => '📈', 'key' => 'progress'],
    ['path' => '/flags',              'label' => 'CTF Flags',          'icon' => '🚩', 'key' => 'flags'],
    ['path' => '/flag-reset',         'label' => 'Flag Reset',         'icon' => '🔄', 'key' => 'flag-reset'],
    ['path' => '/scoring',            'label' => 'Scoring',            'icon' => '🏅', 'key' => 'scoring'],
    ['path' => '/leaderboard',        'label' => 'Leaderboard',         'icon' => '🏆', 'key' => 'leaderboard'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> — CTF Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        // Apply saved theme before render to prevent flash
        if (localStorage.getItem('cp-theme') === 'dark' ||
            (!localStorage.getItem('cp-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; }
        .sidebar-link { transition: all 0.15s ease; }
        .sidebar-link:hover { background: rgba(245, 158, 11, 0.08); }
        .dark .sidebar-link:hover { background: rgba(251, 191, 36, 0.1); }
        .sidebar-link.active { background: #fffbeb; border-left: 3px solid #d97706; color: #92400e; font-weight: 500; }
        .dark .sidebar-link.active { background: rgba(251, 191, 36, 0.15); border-left: 3px solid #f59e0b; color: #fcd34d; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-950 text-gray-900 dark:text-slate-100 min-h-screen flex transition-colors">
    <!-- Sidebar -->
    <aside class="w-64 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 flex flex-col min-h-screen fixed shadow-sm dark:shadow-none">
        <div class="p-4 border-b border-gray-200 dark:border-slate-800">
            <div class="flex items-center gap-2">
                <span class="text-lg">🔒</span>
                <div>
                    <div class="text-sm font-bold text-amber-700 dark:text-amber-400">BankSploit</div>
                    <div class="text-xs text-gray-500 dark:text-slate-500">CTF Control Panel</div>
                </div>
            </div>
        </div>

        <nav class="flex-1 p-3 space-y-1">
            <?php foreach ($navItems as $item): ?>
                <a href="<?= $item['path'] ?>"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm <?= ($active === $item['key']) ? 'active' : 'text-gray-600 dark:text-slate-300' ?>">
                    <span class="text-base"><?= $item['icon'] ?></span>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-gray-200 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div class="text-xs text-gray-500 dark:text-slate-500"><?= $userName ?></div>
                <a href="/logout" class="text-xs text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-medium">Logout</a>
            </div>
            <div class="mt-1 text-xs text-gray-400 dark:text-slate-600">Port <?= APP_PORT ?> · Secure</div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flex-1 ml-64">
        <!-- Top bar -->
        <header class="sticky top-0 z-10 bg-white/80 dark:bg-slate-900/80 backdrop-blur border-b border-gray-200 dark:border-slate-800 px-6 py-3">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($title) ?></h1>
                <div class="flex items-center gap-3">
                    <!-- Theme toggle -->
                    <button onclick="toggleTheme()" id="theme-toggle"
                            class="p-1.5 rounded-lg text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition"
                            title="Toggle dark/light mode">
                        <span id="theme-icon-light" class="hidden dark:inline">☀️</span>
                        <span id="theme-icon-dark" class="inline dark:hidden">🌙</span>
                    </button>
                    <span class="px-2 py-0.5 rounded text-xs bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-300 font-mono font-medium">MANAGEMENT ONLY</span>
                </div>
            </div>
        </header>

        <!-- Page content -->
        <div class="p-6 max-w-6xl">
            <?= $content ?>
        </div>
    </main>

    <script>
    function toggleTheme() {
        const html = document.documentElement;
        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            localStorage.setItem('cp-theme', 'light');
        } else {
            html.classList.add('dark');
            localStorage.setItem('cp-theme', 'dark');
        }
    }
    </script>
</body>
</html>
