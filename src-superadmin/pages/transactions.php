<?php

declare(strict_types=1);

$pdo = db();

// Get all users for filter dropdown
$users = $pdo->query('SELECT id, customer_id, name FROM users ORDER BY id')->fetchAll();
$filterUser = (int) ($_GET['user_id'] ?? 0);

// Query transactions
$sql = 'SELECT t.*, a1.account_number AS from_acct, a2.account_number AS to_acct,
               u.customer_id, u.name AS user_name
          FROM transactions t
          LEFT JOIN accounts a1 ON a1.id = t.from_account_id
          LEFT JOIN accounts a2 ON a2.id = t.to_account_id
          LEFT JOIN accounts a ON a.id = t.from_account_id
          LEFT JOIN users u ON u.id = a.user_id';
$bind = [];
if ($filterUser > 0) {
    $sql .= ' WHERE a.user_id = :uid';
    $bind[':uid'] = $filterUser;
}
$sql .= ' ORDER BY t.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($bind);
$txns = $stmt->fetchAll();

// Build filter dropdown
$userOpts = '<option value="0">All Users</option>';
foreach ($users as $u) {
    $sel = $filterUser === (int) $u['id'] ? ' selected' : '';
    $userOpts .= '<option value="' . (int) $u['id'] . '"' . $sel . '>' . htmlspecialchars($u['customer_id'] . ' — ' . $u['name']) . '</option>';
}

// Build rows
$rows = '';
foreach ($txns as $t) {
    $date   = htmlspecialchars(substr((string) ($t['created_at'] ?? ''), 0, 16));
    $from   = htmlspecialchars((string) ($t['from_acct'] ?? '—'));
    $to     = htmlspecialchars((string) ($t['to_acct'] ?? '—'));
    $amount = number_format((float) ($t['amount'] ?? 0), 2);
    $mode   = htmlspecialchars((string) ($t['mode'] ?? ''));
    $status = htmlspecialchars((string) ($t['status'] ?? ''));
    $user   = htmlspecialchars((string) ($t['customer_id'] ?? ''));
    $ref    = htmlspecialchars((string) ($t['txn_ref'] ?? ''));
    $remarks = htmlspecialchars(mb_substr((string) ($t['remarks'] ?? ''), 0, 40));
    $statusCls = $status === 'completed' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300';

    $rows .= <<<HTML
        <tr class="border-t border-gray-100 dark:border-slate-800 text-xs">
            <td class="py-1.5 text-gray-500 dark:text-slate-400">{$date}</td>
            <td class="py-1.5 text-gray-700 dark:text-slate-300 font-mono">{$user}</td>
            <td class="py-1.5 text-gray-700 dark:text-slate-300 font-mono">{$from}</td>
            <td class="py-1.5 text-gray-700 dark:text-slate-300 font-mono">{$to}</td>
            <td class="py-1.5 text-gray-900 dark:text-white text-right font-medium">₹{$amount}</td>
            <td class="py-1.5 text-gray-500 dark:text-slate-400">{$mode}</td>
            <td class="py-1.5 {$statusCls} font-medium">{$status}</td>
            <td class="py-1.5 text-gray-400 dark:text-slate-500">{$remarks}</td>
        </tr>
HTML;
}

$count = count($txns);

$content = <<<HTML
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-gray-600 dark:text-slate-400">View transactions across all user accounts. Showing latest {$count} entries.</p>
        <form method="GET" class="flex gap-2 items-center">
            <select name="user_id" onchange="this.form.submit()" class="rounded-lg bg-gray-50 dark:bg-slate-950 ring-1 ring-gray-300 dark:ring-slate-700 px-3 py-1.5 text-gray-900 dark:text-slate-100 text-sm focus:ring-amber-500 focus:outline-none">
                {$userOpts}
            </select>
        </form>
    </div>

    <div class="rounded-xl bg-white dark:bg-slate-900/60 shadow-sm dark:shadow-none ring-1 ring-gray-200 dark:ring-slate-800 p-4 overflow-x-auto">
        <table class="w-full">
            <thead><tr class="text-gray-500 dark:text-slate-500 text-xs uppercase text-left">
                <th class="pb-2">Date</th><th class="pb-2">User</th><th class="pb-2">From</th><th class="pb-2">To</th><th class="pb-2 text-right">Amount</th><th class="pb-2">Mode</th><th class="pb-2">Status</th><th class="pb-2">Remarks</th>
            </tr></thead>
            <tbody>{$rows}</tbody>
        </table>
    </div>
HTML;

sa_render('Transactions', $content, 'transactions');
