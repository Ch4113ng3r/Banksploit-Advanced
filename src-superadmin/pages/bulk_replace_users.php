<?php

declare(strict_types=1);

/**
 * CTF Control Panel — Bulk User Replacement (Dual Format)
 *
 * BULK TAB - Two CSV Formats:
 * Format A (Auto-Match): 2 columns (Customer ID, Full Name)
 *   - Auto-detects customer ID format (any letter + 8 digits)
 *   - Finds next AVAILABLE pre-created user (BS1003xx)
 *   - Marks user as 'replaced' after replacement
 *   - Error if all pre-created slots exhausted
 *
 * Format B (Manual): 3 columns (Old Customer ID, New Customer ID, New Full Name)
 *   - Allows replacing ANY customer_id (ignores replacement_status)
 *   - Useful when instructor knows which user to swap
 *   - No "slots exhausted" error
 *
 * SINGLE TAB - Two Modes:
 * Mode A (Auto-Match): Silently replace next available pre-created user
 * Mode B (Manual): Dropdown to select any user
 */

$pdo = db();
$message = null;
$errors = [];
$results = null;
$activeTab = $_GET['tab'] ?? 'bulk';
$singleMode = $_GET['mode'] ?? 'auto';

// ============================================================================
// HELPER FUNCTION: Generate Password Using Template
// ============================================================================
function generate_template_password($customerId) {
    return $customerId . 'B4rc@123';
}

function hash_password($password) {
    return [
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'password_md5' => md5($password)
    ];
}

// ============================================================================
// BULK REPLACE HANDLING (Format Auto-Detection)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab === 'bulk') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'CSRF token invalid.';
    } elseif (empty($_FILES['import_file']['tmp_name'])) {
        $errors[] = 'Please select a file to import.';
    } else {
        $file = $_FILES['import_file'];
        $results = process_bulk_replace($pdo, $file);

        if ($results['ok']) {
            $message = 'Replacement completed! ' . $results['summary'];
            if (!empty($results['errors'])) {
                $message .= ' Some rows had errors (see below).';
            }
        } else {
            $errors[] = $results['error'] ?? 'Replacement failed.';
        }
    }
}

// ============================================================================
// SINGLE USER REPLACE HANDLING (Mode Auto-Detection)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab === 'single') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if (!csrf_verify($input['csrf_token'] ?? null)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF token invalid.']);
        exit;
    }

    $action = (string) ($input['action'] ?? '');

    if ($action === 'replace-auto') {
        // Mode A: Auto-select next available pre-created user
        try {
            $availableUser = $pdo->query(
                "SELECT id, customer_id, name FROM users
                 WHERE customer_id LIKE 'BS1003%' AND replacement_status = 'available'
                 ORDER BY customer_id ASC LIMIT 1"
            )->fetch();

            if (!$availableUser) {
                echo json_encode(['ok' => false, 'error' => 'All 100 pre-created slots are used. Switch to Manual Mode to replace an existing user.']);
                exit;
            }

            $newCustomerId = (string) ($input['new_customer_id'] ?? '');
            $newName = (string) ($input['new_name'] ?? '');

            if (!$newCustomerId || !$newName) {
                echo json_encode(['ok' => false, 'error' => 'Customer ID and Full Name are required.']);
                exit;
            }

            // Check new customer ID is unique
            $checkNew = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid AND id != :uid LIMIT 1');
            $checkNew->execute([':cid' => $newCustomerId, ':uid' => $availableUser['id']]);
            if ($checkNew->fetch()) {
                echo json_encode(['ok' => false, 'error' => "Customer ID '{$newCustomerId}' already exists in database."]);
                exit;
            }

            // Generate new password using template formula
            $password = generate_template_password($newCustomerId);
            $hashes = hash_password($password);

            // Update user and mark as replaced
            $stmt = $pdo->prepare(
                'UPDATE users SET customer_id = :new_cid, name = :new_name,
                 password_hash = :pwd_hash, password_md5 = :pwd_md5,
                 replacement_status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                ':new_cid' => $newCustomerId,
                ':new_name' => $newName,
                ':pwd_hash' => $hashes['password_hash'],
                ':pwd_md5' => $hashes['password_md5'],
                ':status' => 'replaced',
                ':id' => $availableUser['id']
            ]);

            echo json_encode([
                'ok' => true,
                'message' => 'Auto-replaced ' . $availableUser['customer_id'] . ' → ' . $newCustomerId . ' (' . $newName . ')'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'replace-manual') {
        // Mode B: Manual replacement of any user
        $oldCustomerId = (string) ($input['old_customer_id'] ?? '');
        $newCustomerId = (string) ($input['new_customer_id'] ?? '');
        $newName = (string) ($input['new_name'] ?? '');

        if (!$oldCustomerId || !$newCustomerId || !$newName) {
            echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
            exit;
        }

        try {
            // Verify old customer ID exists
            $checkOld = $pdo->prepare('SELECT id, name FROM users WHERE customer_id = :cid LIMIT 1');
            $checkOld->execute([':cid' => $oldCustomerId]);
            $oldUser = $checkOld->fetch();

            if (!$oldUser) {
                echo json_encode(['ok' => false, 'error' => "Customer ID '{$oldCustomerId}' not found."]);
                exit;
            }

            // Check for duplicate new customer ID (if changing)
            if ($newCustomerId !== $oldCustomerId) {
                $checkNewCid = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid LIMIT 1');
                $checkNewCid->execute([':cid' => $newCustomerId]);
                if ($checkNewCid->fetch()) {
                    echo json_encode(['ok' => false, 'error' => "Customer ID '{$newCustomerId}' already exists in database."]);
                    exit;
                }
            }

            // Generate new password using template formula when customer ID changes
            $updateFields = ['name = :new_name'];
            $params = [':new_name' => $newName, ':id' => $oldUser['id']];

            if ($newCustomerId !== $oldCustomerId) {
                // Customer ID is changing, so regenerate password
                $password = generate_template_password($newCustomerId);
                $hashes = hash_password($password);

                $updateFields[] = 'customer_id = :new_cid';
                $updateFields[] = 'password_hash = :pwd_hash';
                $updateFields[] = 'password_md5 = :pwd_md5';

                $params[':new_cid'] = $newCustomerId;
                $params[':pwd_hash'] = $hashes['password_hash'];
                $params[':pwd_md5'] = $hashes['password_md5'];
            }

            // Update user (ignore replacement_status for Format B)
            $stmt = $pdo->prepare(
                'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = :id'
            );
            $stmt->execute($params);

            $changes = [];
            if ($newCustomerId !== $oldCustomerId) {
                $changes[] = "Customer ID: {$oldCustomerId} → {$newCustomerId}";
            }
            if ($newName !== '') {
                $changes[] = "Name: {$oldUser['name']} → {$newName}";
            }

            echo json_encode([
                'ok' => true,
                'message' => 'User updated successfully: ' . implode('; ', $changes)
            ]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ============================================================================
// RENDER PAGE
// ============================================================================

$csrf = csrf_token();

// Render alerts
$alertHtml = '';
if (!empty($errors)) {
    foreach ($errors as $err) {
        $alertHtml .= '<div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-sm">'
                    . htmlspecialchars($err) . '</div>';
    }
}
if ($message !== null) {
    $alertHtml .= '<div class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-300 text-sm">'
                . htmlspecialchars($message) . '</div>';
}

// Render results table for bulk replace
$resultsHtml = '';
if ($results !== null && isset($results['rows'])) {
    $successCount = count(array_filter($results['rows'], fn($r) => $r['success']));
    $failCount = count(array_filter($results['rows'], fn($r) => !$r['success']));

    $resultsHtml = '<div class="mt-8 bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">'
                 . '<h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Replacement Results</h3>'
                 . '<div class="grid grid-cols-2 gap-4 mb-6">'
                 . '<div class="p-4 rounded-lg bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30">'
                 . '<div class="text-xs font-semibold text-green-700 dark:text-green-400 uppercase">Successful</div>'
                 . '<div class="text-3xl font-bold text-green-600 dark:text-green-300 mt-1">' . $successCount . '</div></div>'
                 . '<div class="p-4 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30">'
                 . '<div class="text-xs font-semibold text-red-700 dark:text-red-400 uppercase">Failed</div>'
                 . '<div class="text-3xl font-bold text-red-600 dark:text-red-300 mt-1">' . $failCount . '</div></div>'
                 . '</div>';

    if (!empty($results['rows'])) {
        $resultsHtml .= '<div class="overflow-x-auto">'
                      . '<table class="w-full text-sm">'
                      . '<thead><tr class="border-b border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300">'
                      . '<th class="text-left py-2 px-2 font-semibold">Row</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Format</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Old/Pre-Created ID</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">New Customer ID</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">New Full Name</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Status</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Message</th>'
                      . '</tr></thead><tbody>';

        foreach ($results['rows'] as $idx => $row) {
            $status = $row['success']
                ? '<span class="text-green-600 dark:text-green-400 font-semibold">✓</span>'
                : '<span class="text-red-600 dark:text-red-400 font-semibold">✗</span>';
            $msg = $row['message'] ? htmlspecialchars($row['message']) : '';
            $format = $row['format'] === 'A' ? '<span class="bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 px-2 py-1 rounded text-xs font-semibold">Auto</span>'
                    : '<span class="bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 px-2 py-1 rounded text-xs font-semibold">Manual</span>';
            $resultsHtml .= '<tr class="border-b border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300">'
                          . '<td class="py-2 px-2">' . ($idx + 2) . '</td>'
                          . '<td class="py-2 px-2">' . $format . '</td>'
                          . '<td class="py-2 px-2 font-mono">' . htmlspecialchars($row['old_id'] ?? '') . '</td>'
                          . '<td class="py-2 px-2 font-mono">' . htmlspecialchars($row['new_customer_id'] ?? '') . '</td>'
                          . '<td class="py-2 px-2">' . htmlspecialchars($row['new_name'] ?? '') . '</td>'
                          . '<td class="py-2 px-2">' . $status . '</td>'
                          . '<td class="py-2 px-2 text-xs">' . $msg . '</td>'
                          . '</tr>';
        }
        $resultsHtml .= '</tbody></table></div>';
    }
    $resultsHtml .= '</div>';
}

// Fetch users for Mode B dropdown (show all users with current customer_id)
$allUsers = $pdo->query(
    "SELECT id, customer_id, name FROM users ORDER BY customer_id ASC"
)->fetchAll();

$userOptionsManual = '';
foreach ($allUsers as $user) {
    $cid = htmlspecialchars((string) $user['customer_id']);
    $name = htmlspecialchars((string) $user['name']);
    $userOptionsManual .= "<option value=\"{$cid}\">{$cid} — {$name}</option>";
}

// Count available pre-created users
$availableCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE customer_id LIKE 'BS1003%' AND replacement_status = 'available'"
)->fetchColumn();

$content = <<<HTML
<div class="space-y-6">
    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">User Replacement</h2>

    {$alertHtml}

    <!-- Tab Navigation -->
    <div class="flex gap-2 border-b border-gray-200 dark:border-slate-800" id="main-tabs">
        <a href="?tab=bulk" data-tab="bulk" class="tab-btn px-4 py-2 font-semibold text-sm transition text-gray-600 border-b-2 border-transparent hover:text-gray-900">
            📊 Bulk Replace (CSV/Excel)
        </a>
        <a href="?tab=single" data-tab="single" class="tab-btn px-4 py-2 font-semibold text-sm transition text-gray-600 border-b-2 border-transparent hover:text-gray-900">
            👤 Single User Replace
        </a>
    </div>

    <script>
    // Highlight main tabs
    function highlightMainTabs() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'bulk';

        document.querySelectorAll('#main-tabs .tab-btn').forEach(tab => {
            if (tab.getAttribute('data-tab') === activeTab) {
                tab.style.backgroundColor = '#4f46e5';
                tab.style.color = 'white';
                tab.style.borderBottom = '4px solid #4f46e5';
                tab.style.borderBottomWidth = '4px';
            } else {
                tab.style.backgroundColor = 'transparent';
                tab.style.color = '#4b5563';
                tab.style.borderBottom = '2px solid transparent';
            }
        });
    }
    highlightMainTabs();
    </script>

HTML;

// ============================================================================
// BULK REPLACE TAB
// ============================================================================

if ($activeTab === 'bulk') {
    $content .= <<<'BULKHTML'
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Instructions -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Bulk Replace Instructions</h3>
            <div class="space-y-4 text-sm text-gray-700 dark:text-slate-300">
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <div class="font-semibold text-blue-600 dark:text-blue-400 mb-2">📄 FORMAT A: Auto-Match (Recommended)</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400 space-y-1">
                        <div>• <span class="font-mono">2 columns:</span> Customer ID, Full Name</div>
                        <div>• Auto-detects Barclays format (G/B/H/E + 8 digits)</div>
                        <div>• Finds next AVAILABLE pre-created slot (BS1003xx)</div>
                        <div>• Marks user as "replaced" after replacement</div>
                        <div>✅ CSV Example:</div>
                        <div class="font-mono bg-blue-50 dark:bg-blue-900/30 p-2 rounded text-blue-900 dark:text-blue-200">
                            Customer ID,Full Name<br>
                            G01197938,Alice Johnson<br>
                            B01182746,Bob Smith
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <div class="font-semibold text-purple-600 dark:text-purple-400 mb-2">📋 FORMAT B: Manual (Advanced)</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400 space-y-1">
                        <div>• <span class="font-mono">3 columns:</span> Old Customer ID, New Customer ID, New Full Name</div>
                        <div>• Replace ANY customer_id (BS, Barclays, or other)</div>
                        <div>• Ignores "availability" status</div>
                        <div>• Use when you know which user to swap</div>
                        <div>✅ CSV Example:</div>
                        <div class="font-mono bg-purple-50 dark:bg-purple-900/30 p-2 rounded text-purple-900 dark:text-purple-200">
                            Old Customer ID,New Customer ID,New Full Name<br>
                            BS100300,STAFF001,Alice Johnson<br>
                            BS100301,STAFF002,Bob Smith
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">⚠️ Validation</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">
                        • Format A errors if no available pre-created slots<br>
                        • Format B ignores availability (always works)<br>
                        • All IDs must be unique in database<br>
                        • Clear error messages per row
                    </div>
                </div>
                <div class="border-t border-gray-200 dark:border-slate-700 pt-4">
                    <div class="font-semibold text-amber-600 dark:text-amber-400 mb-1">🔐 Password Information</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400 space-y-2">
                        <div><strong>Auto-Generated Password:</strong></div>
                        <div class="font-mono bg-amber-50 dark:bg-amber-900/30 p-2 rounded text-amber-900 dark:text-amber-200">
                            {CustomerID}B4rc@123
                        </div>
                        <div><strong>Examples:</strong></div>
                        <div class="font-mono bg-amber-50 dark:bg-amber-900/30 p-2 rounded text-amber-900 dark:text-amber-200">
                            G01197938 → G01197938B4rc@123<br>
                            STAFF001 → STAFF001B4rc@123<br>
                            B01182746 → B01182746B4rc@123
                        </div>
                        <div class="text-xs text-amber-700 dark:text-amber-300 mt-2">
                            ℹ️ Users will receive this auto-generated password after replacement and can change it at login.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload form -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Upload File</h3>
            <form method="post" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="{CSRF}">

                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Select CSV or Excel file</label>
                    <input type="file" name="import_file" accept=".csv,.xlsx" required
                           class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 file:mr-3 file:bg-blue-500 file:text-white file:rounded file:border-0 file:px-3 file:py-1 file:font-semibold file:cursor-pointer hover:file:bg-blue-600 dark:file:bg-blue-600 dark:hover:file:bg-blue-700">
                </div>

                <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-blue-500 hover:bg-blue-600 dark:bg-blue-600 dark:hover:bg-blue-700 text-white font-semibold transition">
                    Replace Users
                </button>
            </form>
        </div>
    </div>

    {RESULTS}
BULKHTML;
}

// ============================================================================
// SINGLE USER REPLACE TAB
// ============================================================================

if ($activeTab === 'single') {
    $noAvailable = $availableCount === 0;
    $disabledAutoAttr = $noAvailable ? 'disabled' : '';
    $disabledMsg = $noAvailable ? '<p class="text-xs text-amber-600 dark:text-amber-300 mb-4">⚠️ No available pre-created slots. Use Manual Mode instead.</p>' : '';

    $content .= <<<SINGLEHTML
    <!-- Mode Selection Tabs -->
    <div class="flex gap-2 border-b border-gray-200 dark:border-slate-800 mb-6" id="mode-tabs">
        <a href="?tab=single&mode=auto" data-mode="auto" class="mode-btn px-4 py-2 font-semibold text-sm transition text-gray-600 border-b-2 border-transparent hover:text-gray-900">
            🤖 Mode A: Auto-Match (Pre-Created)
        </a>
        <a href="?tab=single&mode=manual" data-mode="manual" class="mode-btn px-4 py-2 font-semibold text-sm transition text-gray-600 border-b-2 border-transparent hover:text-gray-900">
            ⚙️ Mode B: Manual (Any User)
        </a>
    </div>

    <script>
    // Highlight mode tabs
    function highlightModeTabs() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeMode = urlParams.get('mode') || 'auto';

        document.querySelectorAll('#mode-tabs .mode-btn').forEach(tab => {
            if (tab.getAttribute('data-mode') === activeMode) {
                if (activeMode === 'auto') {
                    tab.style.backgroundColor = '#2563eb';
                } else {
                    tab.style.backgroundColor = '#a855f7';
                }
                tab.style.color = 'white';
                tab.style.borderBottom = '4px solid ' + tab.style.backgroundColor;
            } else {
                tab.style.backgroundColor = 'transparent';
                tab.style.color = '#4b5563';
                tab.style.borderBottom = '2px solid transparent';
            }
        });
    }
    highlightModeTabs();
    </script>

SINGLEHTML;

    if ($singleMode === 'auto') {
        // Mode A: Auto-Match
        $content .= <<<'AUTOHTML'
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Instructions -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">🤖 Auto-Match Mode</h3>
            <div class="space-y-4 text-sm text-gray-700 dark:text-slate-300">
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">🎯 Purpose</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">Automatically replace the NEXT available pre-created slot</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">📝 How It Works</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">
                        1. System finds first available slot (BS1003xx)<br>
                        2. Enter your Barclays staff ID (e.g., G01197938)<br>
                        3. Enter your actual name<br>
                        4. Click "Replace Next Slot"<br>
                        5. System auto-selects which BS user was replaced
                    </div>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 rounded">
                    <div class="font-semibold text-blue-700 dark:text-blue-300 mb-1">📊 Slot Status</div>
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                        <span>{AVAILABLE_COUNT}</span> / 100 slots available
                    </div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">✅ Why Use This?</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">
                        • Simplest for walk-in staff<br>
                        • No need to select which user<br>
                        • System manages slots automatically<br>
                        • When all slots filled, switch to Manual Mode
                    </div>
                </div>
                <div class="border-t border-blue-200 dark:border-blue-500/30 pt-4">
                    <div class="font-semibold text-blue-600 dark:text-blue-400 mb-1">🔐 Default Password Template</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400 space-y-2">
                        <div class="font-mono bg-blue-50 dark:bg-blue-900/30 p-2 rounded text-blue-900 dark:text-blue-200">
                            {YourStaffID}B4rc@123
                        </div>
                        <div class="text-xs text-blue-700 dark:text-blue-300">
                            Example: If you enter <strong>G01197938</strong><br>
                            Password will be: <strong>G01197938B4rc@123</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Replace form -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Replace Next Slot</h3>
            {DISABLED_MSG}
            <form id="auto-replace-form" class="space-y-4">
                <input type="hidden" name="csrf_token" value="{CSRF}">

                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Your Barclays Staff ID *</label>
                    <input type="text" name="new_customer_id" required {DISABLED_AUTO}
                           class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., G01197938">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Your Full Name *</label>
                    <input type="text" name="new_name" required {DISABLED_AUTO}
                           class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., John Doe">
                </div>

                <button type="submit" {DISABLED_AUTO} class="w-full px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold transition">
                    Replace Next Slot
                </button>
            </form>

            <div id="auto-result" class="mt-4 text-xs text-gray-700 dark:text-slate-300"></div>
        </div>
    </div>

    <script>
    const CSRF = "{CSRF}";

    document.getElementById('auto-replace-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        data.action = 'replace-auto';

        const r = await fetch('/bulk-replace-users?tab=single&mode=auto', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await r.json();
        const resultDiv = document.getElementById('auto-result');

        if (result.ok) {
            resultDiv.className = 'mt-4 p-3 rounded-lg bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-300 text-xs';
            resultDiv.textContent = '✓ ' + result.message;
            e.target.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            resultDiv.className = 'mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-xs';
            resultDiv.textContent = '✗ ' + result.error;
        }
    });
    </script>
AUTOHTML;
    } else {
        // Mode B: Manual
        $noUsers = empty($allUsers);
        $disabledManualAttr = $noUsers ? 'disabled' : '';
        $emptyMsgManual = $noUsers ? '<p class="text-xs text-amber-600 dark:text-amber-300">No users found in system.</p>' : '';

        $content .= <<<MANUALHTML
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Instructions -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">⚙️ Manual Mode</h3>
            <div class="space-y-4 text-sm text-gray-700 dark:text-slate-300">
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">🎯 Purpose</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">Manually replace ANY user in the system</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">📝 How It Works</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">
                        1. Select a user from dropdown<br>
                        2. Enter new Customer ID (staff ID)<br>
                        3. Enter new Full Name (your name)<br>
                        4. Click "Replace User"<br>
                        5. Immediately replaced (ignores availability)
                    </div>
                </div>
                <div class="p-3 bg-purple-50 dark:bg-purple-500/10 border border-purple-200 dark:border-purple-500/30 rounded">
                    <div class="font-semibold text-purple-700 dark:text-purple-300 mb-1">📊 Total Users</div>
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {TOTAL_USERS} users available
                    </div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">✅ Why Use This?</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">
                        • Choose specific user to replace<br>
                        • Works even if all slots filled<br>
                        • For instructors who know assignments<br>
                        • Flexible: replace anyone in DB
                    </div>
                </div>
                <div class="border-t border-purple-200 dark:border-purple-500/30 pt-4">
                    <div class="font-semibold text-purple-600 dark:text-purple-400 mb-1">🔐 Default Password Template</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400 space-y-2">
                        <div class="font-mono bg-purple-50 dark:bg-purple-900/30 p-2 rounded text-purple-900 dark:text-purple-200">
                            {NewCustomerID}B4rc@123
                        </div>
                        <div class="text-xs text-purple-700 dark:text-purple-300">
                            Example: If you enter <strong>G01197938</strong><br>
                            Password will be: <strong>G01197938B4rc@123</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Replace form -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Replace User</h3>
            {EMPTY_MSG_MANUAL}
            <form id="manual-replace-form" class="space-y-4">
                <input type="hidden" name="csrf_token" value="{CSRF}">

                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Select User to Replace *</label>
                    <select name="old_customer_id" id="old_customer_id" required {DISABLED_MANUAL}
                           class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <option value="">Choose a user...</option>
                        {USER_OPTIONS_MANUAL}
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">New Customer ID (Staff ID) *</label>
                    <input type="text" name="new_customer_id" required {DISABLED_MANUAL}
                           class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                           placeholder="e.g., G01197938">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">New Full Name *</label>
                    <input type="text" name="new_name" required {DISABLED_MANUAL}
                           class="w-full px-3 py-2 rounded-lg bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
                           placeholder="e.g., John Doe">
                </div>

                <button type="submit" {DISABLED_MANUAL} class="w-full px-4 py-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-semibold transition">
                    Replace User
                </button>
            </form>

            <div id="manual-result" class="mt-4 text-xs text-gray-700 dark:text-slate-300"></div>
        </div>
    </div>

    <script>
    const CSRF = "{CSRF}";

    document.getElementById('manual-replace-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        data.action = 'replace-manual';

        const r = await fetch('/bulk-replace-users?tab=single&mode=manual', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const result = await r.json();
        const resultDiv = document.getElementById('manual-result');

        if (result.ok) {
            resultDiv.className = 'mt-4 p-3 rounded-lg bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-300 text-xs';
            resultDiv.textContent = '✓ ' + result.message;
            e.target.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            resultDiv.className = 'mt-4 p-3 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-xs';
            resultDiv.textContent = '✗ ' + result.error;
        }
    });
    </script>
MANUALHTML;

        $content = str_replace(
            ['{TOTAL_USERS}', '{USER_OPTIONS_MANUAL}', '{EMPTY_MSG_MANUAL}', '{DISABLED_MANUAL}'],
            [count($allUsers), $userOptionsManual, $emptyMsgManual, $disabledManualAttr],
            $content
        );
    }

    $content = str_replace(
        ['{AVAILABLE_COUNT}', '{DISABLED_MSG}', '{DISABLED_AUTO}'],
        [$availableCount, $disabledMsg, $disabledAutoAttr],
        $content
    );
}

// Replace placeholders (for both bulk and single tabs)
$content = str_replace(['{CSRF}'], [$csrf], $content);

if ($activeTab === 'bulk') {
    $content = str_replace(['{RESULTS}'], [$resultsHtml], $content);
}

$content .= '</div>';

sa_render('User Replacement', $content, 'bulk-replace-users');

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function process_bulk_replace(PDO $pdo, array $file): array
{
    $filename = $file['tmp_name'] ?? '';
    if (!file_exists($filename)) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    $data = [];
    $name = strtolower($file['name'] ?? '');
    if (str_ends_with($name, '.xlsx')) {
        $data = parse_excel($filename);
    } elseif (str_ends_with($name, '.csv')) {
        $data = parse_csv($filename);
    } else {
        return ['ok' => false, 'error' => 'Only .csv and .xlsx files are supported.'];
    }

    if (empty($data)) {
        return ['ok' => false, 'error' => 'File is empty or could not be parsed.'];
    }

    // Detect format: 2 cols = Format A (Auto), 3 cols = Format B (Manual)
    $header = $data[0];
    $numCols = count($header);

    if ($numCols === 2) {
        return process_format_a($pdo, $data);
    } elseif ($numCols === 3) {
        return process_format_b($pdo, $data);
    } else {
        return ['ok' => false, 'error' => 'CSV must have either 2 columns (Format A: auto-match) or 3 columns (Format B: manual).'];
    }
}

function process_format_a(PDO $pdo, array $data): array
{
    $header = $data[0];

    // Flexible column detection
    $cidIdx = -1;
    $nameIdx = -1;
    foreach ($header as $idx => $col) {
        $col = strtolower(trim($col ?? ''));
        if (in_array($col, ['customer id', 'customerid', 'cust id', 'barclays id'], true)) {
            $cidIdx = $idx;
        } elseif (in_array($col, ['full name', 'name'], true)) {
            $nameIdx = $idx;
        }
    }

    if ($cidIdx === -1 || $nameIdx === -1) {
        return ['ok' => false, 'error' => 'CSV must have "Customer ID" and "Full Name" columns.'];
    }

    $rows = [];
    $seenCids = [];
    $successCount = 0;
    $failCount = 0;

    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];
        $barclaysId = trim((string) ($row[$cidIdx] ?? ''));
        $name = trim((string) ($row[$nameIdx] ?? ''));

        $result = ['success' => false, 'format' => 'A', 'old_id' => '', 'new_customer_id' => $barclaysId, 'new_name' => $name, 'message' => ''];

        if (!$barclaysId || !$name) {
            $result['message'] = 'Customer ID and Full Name are required.';
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Validate customer ID format (optional but recommended check)
        if (!preg_match('/^[A-Z]\d{8}$/i', $barclaysId)) {
            $result['message'] = 'Invalid Customer ID format (expected: letter + 8 digits, e.g., G01197938).';
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Check for duplicate within file
        if (isset($seenCids[$barclaysId])) {
            $result['message'] = "Duplicate in file (first seen at row " . ($seenCids[$barclaysId] + 2) . ").";
            $rows[] = $result;
            $failCount++;
            continue;
        }
        $seenCids[$barclaysId] = $i;

        // Check ID is unique in DB
        $check = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid LIMIT 1');
        $check->execute([':cid' => $barclaysId]);
        if ($check->fetch()) {
            $result['message'] = "Customer ID '{$barclaysId}' already exists in database.";
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Find next available pre-created user
        $availableUser = $pdo->query(
            "SELECT id, customer_id FROM users
             WHERE customer_id LIKE 'BS1003%' AND replacement_status = 'available'
             ORDER BY customer_id ASC LIMIT 1"
        )->fetch();

        if (!$availableUser) {
            $result['message'] = 'No available pre-created slots. Switch to Manual Mode or create more base users.';
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Replace and mark as replaced
        try {
            // Generate new password using template formula
            $password = generate_template_password($barclaysId);
            $hashes = hash_password($password);

            $stmt = $pdo->prepare(
                'UPDATE users SET customer_id = :new_cid, name = :new_name,
                 password_hash = :pwd_hash, password_md5 = :pwd_md5,
                 replacement_status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                ':new_cid' => $barclaysId,
                ':new_name' => $name,
                ':pwd_hash' => $hashes['password_hash'],
                ':pwd_md5' => $hashes['password_md5'],
                ':status' => 'replaced',
                ':id' => $availableUser['id']
            ]);

            $result['success'] = true;
            $result['old_id'] = $availableUser['customer_id'];
            $result['message'] = 'Replaced successfully';
            $successCount++;
        } catch (PDOException $e) {
            $result['message'] = 'Database error: ' . $e->getMessage();
            $failCount++;
        }

        $rows[] = $result;
    }

    return [
        'ok' => true,
        'rows' => $rows,
        'summary' => $successCount . ' users replaced, ' . $failCount . ' failed.',
        'errors' => array_filter($rows, fn($r) => !$r['success'])
    ];
}

function process_format_b(PDO $pdo, array $data): array
{
    $header = $data[0];

    // Find columns
    $oldCidIdx = -1;
    $newCidIdx = -1;
    $newNameIdx = -1;

    foreach ($header as $idx => $col) {
        $col = strtolower(trim($col ?? ''));
        if (in_array($col, ['old customer id', 'old customerid', 'old cust id'], true)) {
            $oldCidIdx = $idx;
        } elseif (in_array($col, ['new customer id', 'new customerid', 'new cust id'], true)) {
            $newCidIdx = $idx;
        } elseif (in_array($col, ['new full name', 'new name'], true)) {
            $newNameIdx = $idx;
        }
    }

    if ($oldCidIdx === -1 || $newCidIdx === -1 || $newNameIdx === -1) {
        return ['ok' => false, 'error' => 'CSV must have "Old Customer ID", "New Customer ID", and "New Full Name" columns.'];
    }

    $rows = [];
    $seenNewCids = [];
    $successCount = 0;
    $failCount = 0;

    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];
        $oldCid = trim((string) ($row[$oldCidIdx] ?? ''));
        $newCid = trim((string) ($row[$newCidIdx] ?? ''));
        $newName = trim((string) ($row[$newNameIdx] ?? ''));

        $result = ['success' => false, 'format' => 'B', 'old_id' => $oldCid, 'new_customer_id' => $newCid, 'new_name' => $newName, 'message' => ''];

        if (!$oldCid || !$newCid || !$newName) {
            $result['message'] = 'All fields are required.';
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Check old ID exists
        $checkOld = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid LIMIT 1');
        $checkOld->execute([':cid' => $oldCid]);
        $oldUser = $checkOld->fetch();

        if (!$oldUser) {
            $result['message'] = "Old Customer ID '{$oldCid}' not found in database.";
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Check for duplicate in file
        if (isset($seenNewCids[$newCid])) {
            $result['message'] = "Duplicate in file (first seen at row " . ($seenNewCids[$newCid] + 2) . ").";
            $rows[] = $result;
            $failCount++;
            continue;
        }
        $seenNewCids[$newCid] = $i;

        // Check new ID is unique in DB
        $checkNew = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid AND customer_id != :old_cid LIMIT 1');
        $checkNew->execute([':cid' => $newCid, ':old_cid' => $oldCid]);
        if ($checkNew->fetch()) {
            $result['message'] = "New Customer ID '{$newCid}' already exists in database.";
            $rows[] = $result;
            $failCount++;
            continue;
        }

        // Replace (ignore replacement_status for Format B)
        try {
            // Generate new password using template formula
            $password = generate_template_password($newCid);
            $hashes = hash_password($password);

            $stmt = $pdo->prepare(
                'UPDATE users SET customer_id = :new_cid, name = :new_name,
                 password_hash = :pwd_hash, password_md5 = :pwd_md5
                 WHERE customer_id = :old_cid'
            );
            $stmt->execute([
                ':new_cid' => $newCid,
                ':new_name' => $newName,
                ':pwd_hash' => $hashes['password_hash'],
                ':pwd_md5' => $hashes['password_md5'],
                ':old_cid' => $oldCid
            ]);

            $result['success'] = true;
            $result['message'] = 'Replaced successfully';
            $successCount++;
        } catch (PDOException $e) {
            $result['message'] = 'Database error: ' . $e->getMessage();
            $failCount++;
        }

        $rows[] = $result;
    }

    return [
        'ok' => true,
        'rows' => $rows,
        'summary' => $successCount . ' users replaced, ' . $failCount . ' failed.',
        'errors' => array_filter($rows, fn($r) => !$r['success'])
    ];
}

function parse_csv(string $filename): array
{
    $data = [];
    $file = fopen($filename, 'r');
    if ($file) {
        while (($row = fgetcsv($file)) !== false) {
            $data[] = $row;
        }
        fclose($file);
    }
    return $data;
}

function parse_excel(string $filename): array
{
    $data = [];
    try {
        $zip = new ZipArchive();
        if ($zip->open($filename)) {
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($xml) {
                $doc = new DOMDocument();
                $doc->loadXML($xml);
                $rows = $doc->getElementsByTagName('row');
                foreach ($rows as $row) {
                    $cols = [];
                    $cells = $row->getElementsByTagName('c');
                    foreach ($cells as $cell) {
                        $v = $cell->getElementsByTagName('v')->item(0);
                        $cols[] = $v ? trim($v->nodeValue) : '';
                    }
                    $data[] = $cols;
                }
            }
            $zip->close();
        }
    } catch (Exception) {
        return [];
    }
    return $data;
}
