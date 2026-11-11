<?php

declare(strict_types=1);

$pdo = db();

// Config keys and their descriptions
$configKeys = [
    'otp_expiry_seconds'       => ['label' => 'OTP Expiry (seconds)', 'default' => '86400'],
    'session_timeout_minutes'  => ['label' => 'Session Timeout (minutes)', 'default' => '30'],
    'daily_transfer_limit'     => ['label' => 'Daily Transfer Limit (₹)', 'default' => '500000'],
    'max_failed_logins'        => ['label' => 'Max Failed Logins', 'default' => '5'],
    'beneficiary_cooling_hours'=> ['label' => 'Beneficiary Cooling Period (hours)', 'default' => '24'],
];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = $_POST;
    if (!csrf_verify($input['csrf_token'] ?? null)) {
        $error = 'CSRF token invalid.';
    } else {
        foreach ($configKeys as $key => $meta) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '') {
                $stmt = $pdo->prepare(
                    'INSERT INTO app_config (config_key, config_value) VALUES (:k, :v)
                     ON DUPLICATE KEY UPDATE config_value = :v2'
                );
                $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
            }
        }
        $success = 'Configuration saved.';
    }
}

// Ensure app_config table exists
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS app_config (
        config_key VARCHAR(100) PRIMARY KEY,
        config_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (PDOException $e) {
    // Ignore if it already exists
}

// Load current values
$current = [];
try {
    $stmt = $pdo->query('SELECT config_key, config_value FROM app_config');
    if ($stmt) {
        foreach ($stmt->fetchAll() as $row) {
            $current[$row['config_key']] = $row['config_value'];
        }
    }
} catch (PDOException $e) {
    $current = [];
}

$csrf = csrf_token();
$fields = '';
foreach ($configKeys as $key => $meta) {
    $value = htmlspecialchars($current[$key] ?? $meta['default']);
    $label = htmlspecialchars($meta['label']);
    $fields .= <<<HTML
        <div>
            <label class="block text-xs text-gray-600 dark:text-slate-400 mb-1 font-medium">{$label}</label>
            <input type="text" name="{$key}" value="{$value}"
                   class="w-full rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-2 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none">
        </div>
HTML;
}

$errorHtml = !empty($error)
    ? '<div class="mb-4 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 px-3 py-2 text-sm text-red-700 dark:text-red-300">' . htmlspecialchars($error) . '</div>'
    : '';
$successHtml = !empty($success)
    ? '<div class="mb-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-300">' . htmlspecialchars($success) . '</div>'
    : '';

$content = <<<HTML
    <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">System configuration for the CTF app. Changes affect the running instance immediately.</p>
    {$errorHtml}{$successHtml}
    <form method="POST" action="/system-config" class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-6 space-y-4 max-w-lg">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        {$fields}
        <button type="submit" class="rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-semibold px-4 py-2 text-sm shadow-sm">Save Configuration</button>
    </form>
HTML;

sa_render('System Config', $content, 'system-config');
