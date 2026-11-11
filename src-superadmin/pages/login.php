<?php

declare(strict_types=1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (SuperAdminAuth::isLockedOut()) {
        $error = 'Too many failed attempts. Locked out for 15 minutes.';
    } elseif (SuperAdminAuth::login($username, $password)) {
        header('Location: /dashboard');
        exit;
    } else {
        $remaining = 5 - SuperAdminAuth::getFailureCount();
        $error = "Invalid credentials. {$remaining} attempts remaining.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — CTF Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
        if (localStorage.getItem('cp-theme') === 'dark' ||
            (!localStorage.getItem('cp-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-slate-950 min-h-screen flex items-center justify-center transition-colors">
    <div class="w-full max-w-sm">
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-lg dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-8">
            <div class="text-center mb-6">
                <span class="text-3xl">🔒</span>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mt-2">CTF Control Panel</h1>
                <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">BankSploit — Management Access</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-3 py-2 text-sm text-red-700 dark:text-red-300">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login" class="space-y-4">
                <div>
                    <label class="block text-xs text-gray-600 dark:text-slate-400 mb-1 font-medium">Username</label>
                    <input type="text" name="username" required autofocus
                           class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-2 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none"
                           value="<?= htmlspecialchars($username ?? '') ?>">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 dark:text-slate-400 mb-1 font-medium">Password</label>
                    <input type="password" name="password" required
                           class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-2 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none">
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-semibold px-4 py-2.5 text-sm transition shadow-sm">
                    Sign In
                </button>
            </form>
        </div>
        <p class="text-center text-xs text-gray-400 dark:text-slate-600 mt-4">Management access only.<br>Participants use port 9443.</p>
    </div>
</body>
</html>
