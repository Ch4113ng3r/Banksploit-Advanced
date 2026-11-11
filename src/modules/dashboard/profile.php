<?php

declare(strict_types=1);

/**
 * Profile page (HTML) — READ-ONLY display.
 *
 * All fields are greyed out and non-editable. No form submission, no
 * vulnerability surface. This page simply shows the user's information
 * fetched securely (always the session user, no IDOR).
 */

auth()->requireAuth();

$pdo = db();
$me  = auth()->getCurrentUser();
$uid = (int) $me['id'];

// ------------------------------------------------- self-service actions -----
// Two POST actions, both scoped to the session user only (no id in the request):
//   security_answers — set/modify the answers to the 5 security questions
//   change_password  — change password after proving the current one
$notice = null;
$perror = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!CSRF::validateToken(input('csrf_token'))) {
        $perror = 'Invalid or missing CSRF token.';
    } else {
        $action = (string) input('action', '');
        if ($action === 'security_answers') {
            for ($i = 1; $i <= 5; $i++) {
                $ans = trim((string) input('ans_' . $i, ''));
                if ($ans !== '') {
                    $pdo->prepare('UPDATE security_questions SET answer = :a WHERE user_id = :u AND position = :p')
                        ->execute([':a' => $ans, ':u' => $uid, ':p' => $i]);
                }
            }
            $notice = 'Security answers updated.';
        } elseif ($action === 'change_password') {
            $old = (string) input('old_password', '');
            $new = (string) input('new_password', '');
            $row = $pdo->prepare('SELECT password_hash, password_md5 FROM users WHERE id = :id LIMIT 1');
            $row->execute([':id' => $uid]);
            $pw = $row->fetch();
            $okOld = $pw && (
                md5($old) === (string) $pw['password_md5']
                || (is_string($pw['password_hash']) && $pw['password_hash'] !== '' && password_verify($old, $pw['password_hash']))
            );
            if (!$okOld) {
                $perror = 'Current password is incorrect.';
            } elseif (strlen($new) < 6) {
                $perror = 'New password must be at least 6 characters.';
            } else {
                $pdo->prepare('UPDATE users SET password_hash = :ph, password_md5 = :pmd5 WHERE id = :id')
                    ->execute([
                        ':ph'   => password_hash($new, PASSWORD_BCRYPT),
                        ':pmd5' => md5($new),
                        ':id'   => $uid,
                    ]);
                $notice = 'Password changed successfully.';
            }
        }
    }
}

// Current security questions + answers (answers shown in plaintext, by design).
$sq = $pdo->prepare('SELECT position, question, answer FROM security_questions WHERE user_id = :u ORDER BY position');
$sq->execute([':u' => $uid]);
$secQuestions = $sq->fetchAll();

// Always read the session user's own profile — no IDOR, no ?user_id.
$stmt = $pdo->prepare(
    'SELECT id, customer_id, name, email, phone, address, aadhaar_masked,
            pan, role, is_locked
       FROM users WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $uid]);
$profile = $stmt->fetch();

if (!$profile) {
    $profile = ['name' => '', 'email' => '', 'phone' => '', 'address' => '',
                'customer_id' => '', 'pan' => '', 'aadhaar_masked' => '', 'role' => 'user'];
}

// Daily limit (read-only display).
$lstmt = $pdo->prepare('SELECT daily_limit FROM accounts WHERE user_id = :id ORDER BY id LIMIT 1');
$lstmt->execute([':id' => $uid]);
$curLimit = $lstmt->fetchColumn();

// ---------------------------------------------------------------- render ----

$url = htmlspecialchars(APP_URL);

// All values escaped for display.
$vName  = htmlspecialchars((string) $profile['name'], ENT_QUOTES);
$vEmail = htmlspecialchars((string) $profile['email'], ENT_QUOTES);
$vPhone = htmlspecialchars((string) $profile['phone'], ENT_QUOTES);
$vAddr  = htmlspecialchars((string) ($profile['address'] ?? ''), ENT_QUOTES);
$vRole  = htmlspecialchars((string) $profile['role'], ENT_QUOTES);
$vLimit = $curLimit !== false ? htmlspecialchars((string) (float) $curLimit, ENT_QUOTES) : '—';
$cid    = htmlspecialchars((string) $profile['customer_id']);
$pan    = htmlspecialchars((string) ($profile['pan'] ?? ''));
$aadhaar = htmlspecialchars((string) ($profile['aadhaar_masked'] ?? ''));
$roleBadge = ((string) $profile['role'] === 'admin')
    ? '<span class="px-2 py-0.5 rounded text-xs bg-amber-400/20 text-amber-300 ring-1 ring-amber-400/30">admin</span>'
    : '<span class="px-2 py-0.5 rounded text-xs bg-slate-700 text-slate-300">user</span>';

$csrf = CSRF::getHiddenField();

// Feedback banner for the self-service actions.
$pAlert = '';
if ($perror !== null) {
    $pAlert = '<div class="mb-6 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 px-3 py-2 text-sm">'
        . htmlspecialchars($perror) . '</div>';
} elseif ($notice !== null) {
    $pAlert = '<div class="mb-6 rounded-lg border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-2 text-sm">'
        . htmlspecialchars($notice) . '</div>';
}

// Security questions & answers editor (answers prefilled in plaintext).
$sqInputs = '';
foreach ($secQuestions as $q) {
    $pos = (int) $q['position'];
    $qt  = htmlspecialchars((string) $q['question'], ENT_QUOTES);
    $av  = htmlspecialchars((string) $q['answer'], ENT_QUOTES);
    $sqInputs .= <<<ROW
            <div>
                <label class="block text-xs text-slate-500 mb-1">{$qt}</label>
                <input name="ans_{$pos}" value="{$av}" autocomplete="off"
                       class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100">
            </div>
ROW;
}
$secSection = <<<HTML
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5 mt-6">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-1">Security Questions &amp; Answers</h3>
        <p class="text-xs text-slate-500 mb-5">Your current answers are shown below. Update any of them and save — these are used to verify your identity during password recovery.</p>
        <form method="post" action="{$url}/profile">
            {$csrf}
            <input type="hidden" name="action" value="security_answers">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {$sqInputs}
            </div>
            <button class="mt-5 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold px-4 py-2 text-sm transition">Save answers</button>
        </form>
    </div>
HTML;

$pwSection = <<<HTML
    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5 mt-6">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-1">Change Password</h3>
        <p class="text-xs text-slate-500 mb-5">Enter your current password to set a new one.</p>
        <form method="post" action="{$url}/profile">
            {$csrf}
            <input type="hidden" name="action" value="change_password">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-500 mb-1">Current password</label>
                    <input name="old_password" type="password" autocomplete="off"
                           class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="••••••••">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1">New password</label>
                    <input name="new_password" type="password" autocomplete="off"
                           class="w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 focus:ring-indigo-500 px-3 py-2 text-slate-100" placeholder="At least 6 characters">
                </div>
            </div>
            <button class="mt-5 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-slate-950 font-semibold px-4 py-2 text-sm transition">Change password</button>
        </form>
    </div>
HTML;

$content = <<<HTML
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-white">My Profile</h2>
        <div class="flex items-center gap-2 text-sm text-slate-400">{$cid} {$roleBadge}</div>
    </div>
    {$pAlert}

    <div class="rounded-xl bg-slate-900/60 ring-1 ring-slate-800 p-5">
        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-4">Account Information</h3>
        <p class="text-xs text-slate-500 mb-5">Profile details are managed by the bank and cannot be edited online.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-slate-500 mb-1">Full Name</label>
                <input value="{$vName}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Email</label>
                <input value="{$vEmail}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Phone</label>
                <input value="{$vPhone}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Address</label>
                <input value="{$vAddr}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">PAN</label>
                <input value="{$pan}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 font-mono cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Aadhaar</label>
                <input value="{$aadhaar}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 font-mono cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Role</label>
                <input value="{$vRole}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 cursor-not-allowed opacity-70">
            </div>
            <div>
                <label class="block text-xs text-slate-500 mb-1">Daily Transfer Limit (&#8377;)</label>
                <input value="{$vLimit}" disabled readonly
                       class="w-full rounded-lg bg-slate-800/60 ring-1 ring-slate-800 px-3 py-2 text-slate-400 cursor-not-allowed opacity-70">
            </div>
        </div>
    </div>
    {$secSection}
    {$pwSection}
HTML;

render_layout('Profile', $content, ['active' => 'profile']);
