<?php

declare(strict_types=1);

/**
 * CTF Control Panel — Bulk User Import
 *
 * Allows instructors to create multiple user accounts from CSV/Excel files.
 * Required columns: Full Name, Customer ID
 * Auto-generates password: {customerid}B4rc@123
 */

$pdo = db();
$message = null;
$errors  = [];
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'CSRF token invalid.';
    } elseif (empty($_FILES['import_file']['tmp_name'])) {
        $errors[] = 'Please select a file to import.';
    } else {
        $file = $_FILES['import_file'];
        $results = process_bulk_import($pdo, $file);

        if ($results['ok']) {
            $message = 'Import completed! ' . $results['summary'];
            if (!empty($results['errors'])) {
                $message .= ' Some rows had errors (see below).';
            }
        } else {
            $errors[] = $results['error'] ?? 'Import failed.';
        }
    }
}

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

// Render results table
$resultsHtml = '';
if ($results !== null && isset($results['rows'])) {
    $successCount = count(array_filter($results['rows'], fn($r) => $r['success']));
    $failCount = count(array_filter($results['rows'], fn($r) => !$r['success']));

    $resultsHtml = '<div class="mt-8 bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">'
                 . '<h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Import Results</h3>'
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
                      . '<th class="text-left py-2 px-2 font-semibold">Full Name</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Customer ID</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Status</th>'
                      . '<th class="text-left py-2 px-2 font-semibold">Message</th>'
                      . '</tr></thead><tbody>';

        foreach ($results['rows'] as $idx => $row) {
            $status = $row['success']
                ? '<span class="text-green-600 dark:text-green-400 font-semibold">✓</span>'
                : '<span class="text-red-600 dark:text-red-400 font-semibold">✗</span>';
            $msg = $row['message'] ? htmlspecialchars($row['message']) : '';
            $resultsHtml .= '<tr class="border-b border-gray-200 dark:border-slate-800 text-gray-700 dark:text-slate-300">'
                          . '<td class="py-2 px-2">' . ($idx + 2) . '</td>'
                          . '<td class="py-2 px-2">' . htmlspecialchars($row['name'] ?? '') . '</td>'
                          . '<td class="py-2 px-2 font-mono">' . htmlspecialchars($row['customer_id'] ?? '') . '</td>'
                          . '<td class="py-2 px-2">' . $status . '</td>'
                          . '<td class="py-2 px-2 text-xs">' . $msg . '</td>'
                          . '</tr>';
        }
        $resultsHtml .= '</tbody></table></div>';
    }
    $resultsHtml .= '</div>';
}

$content = <<<'HTML'
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Bulk User Import</h2>
    </div>

    {ALERTS}

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Instructions -->
        <div class="bg-white dark:bg-slate-900 rounded-lg border border-gray-200 dark:border-slate-800 p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Import Instructions</h3>
            <div class="space-y-4 text-sm text-gray-700 dark:text-slate-300">
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">📄 File Format</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">CSV (`.csv`) or Excel (`.xlsx`)</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">📋 Required Columns</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">
                        <div>• Full Name</div>
                        <div>• Customer ID</div>
                    </div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">🔑 Password Format</div>
                    <div class="text-xs font-mono bg-gray-100 dark:bg-slate-800 p-2 rounded text-gray-900 dark:text-slate-200 mb-1">{customerid}B4rc@123</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">Example: BS100234B4rc@123</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">👤 User Role</div>
                    <div class="text-xs text-gray-600 dark:text-slate-400">All users created with 'user' role</div>
                </div>
                <div>
                    <div class="font-semibold text-gray-900 dark:text-white mb-1">✅ CSV Example</div>
                    <div class="text-xs font-mono bg-gray-100 dark:bg-slate-800 p-2 rounded text-gray-900 dark:text-slate-200">
                        Full Name,Customer ID<br>
                        John Doe,BS100001<br>
                        Jane Smith,BS100002
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
                           class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-100 file:mr-3 file:bg-amber-500 file:text-white file:rounded file:border-0 file:px-3 file:py-1 file:font-semibold file:cursor-pointer hover:file:bg-amber-600 dark:file:bg-amber-600 dark:hover:file:bg-amber-700">
                </div>

                <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-600 dark:bg-amber-600 dark:hover:bg-amber-700 text-white font-semibold transition">
                    Import Users
                </button>
            </form>
        </div>
    </div>

    {RESULTS}
</div>
HTML;

$content = str_replace(['{ALERTS}', '{CSRF}', '{RESULTS}'], [$alertHtml, $csrf, $resultsHtml], $content);

sa_render('Bulk User Import', $content, 'bulk-import');

// ============================================================================

if (!function_exists('process_bulk_import')) {
    function process_bulk_import(PDO $pdo, array $file): array
    {
        $filename = $file['tmp_name'] ?? '';
        if (!file_exists($filename)) {
            return ['ok' => false, 'error' => 'File upload failed.'];
        }

        // Parse file based on extension
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

        // Find columns
        $header = $data[0];
        $nameIdx = -1;
        $custIdIdx = -1;

        foreach ($header as $idx => $col) {
            $col = strtolower(trim($col ?? ''));
            if (in_array($col, ['full name', 'name'], true)) {
                $nameIdx = $idx;
            } elseif (in_array($col, ['customer id', 'customerid', 'cust id'], true)) {
                $custIdIdx = $idx;
            }
        }

        if ($nameIdx === -1 || $custIdIdx === -1) {
            return ['ok' => false, 'error' => 'CSV must have "Full Name" and "Customer ID" columns.'];
        }

        // Process rows
        $rows = [];
        $successCount = 0;
        $failCount = 0;

        for ($i = 1; $i < count($data); $i++) {
            $row = $data[$i];
            $name = trim($row[$nameIdx] ?? '');
            $customerId = trim($row[$custIdIdx] ?? '');

            $result = [
                'name' => $name,
                'customer_id' => $customerId,
                'success' => false,
                'message' => '',
            ];

            if ($name === '') {
                $result['message'] = 'Full Name is required.';
                $failCount++;
                $rows[] = $result;
                continue;
            }
            if ($customerId === '') {
                $result['message'] = 'Customer ID is required.';
                $failCount++;
                $rows[] = $result;
                continue;
            }

            $password = $customerId . 'B4rc@123';
            $email = strtolower(str_replace(' ', '.', $name)) . '.' . strtolower($customerId) . '@banksploit.local';

            try {
                // Check for duplicate customer ID
                $check = $pdo->prepare('SELECT id FROM users WHERE customer_id = :cid LIMIT 1');
                $check->execute([':cid' => $customerId]);
                if ($check->fetch()) {
                    $result['message'] = 'Customer ID already exists.';
                    $failCount++;
                    $rows[] = $result;
                    continue;
                }

                // Check for duplicate email
                $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $check->execute([':email' => $email]);
                if ($check->fetch()) {
                    $email = strtolower($customerId) . '+' . time() . '@banksploit.local';
                }

                // Create user
                $stmt = $pdo->prepare(
                    'INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, role)
                     VALUES (:cid, :name, :email, :phone, :ph, :pmd5, :role)'
                );
                $stmt->execute([
                    ':cid'   => $customerId,
                    ':name'  => $name,
                    ':email' => $email,
                    ':phone' => '9999999999',
                    ':ph'    => password_hash($password, PASSWORD_BCRYPT),
                    ':pmd5'  => md5($password),
                    ':role'  => 'user',
                ]);

                $result['success'] = true;
                $result['message'] = 'Created successfully.';
                $successCount++;
            } catch (Exception $e) {
                $result['message'] = 'Database error.';
                $failCount++;
            }

            $rows[] = $result;
        }

        return [
            'ok' => true,
            'summary' => "$successCount users created, $failCount failed.",
            'rows' => $rows,
            'errors' => $failCount > 0,
        ];
    }

    function parse_csv(string $filename): array
    {
        $data = [];
        $handle = fopen($filename, 'r');
        if (!$handle) return [];
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
        return $data;
    }

    function parse_excel(string $filename): array
    {
        if (!class_exists('ZipArchive')) return [];

        try {
            $zip = new ZipArchive();
            if (!$zip->open($filename)) return [];

            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            if (!$xml) return [];

            $data = [];
            $dom = new DOMDocument();
            if (!@$dom->loadXML($xml)) return [];

            $xpath = new DOMXPath($dom);
            $rows = $xpath->query('//row');

            foreach ($rows as $row) {
                $rowData = [];
                $cells = $xpath->query('.//c', $row);
                foreach ($cells as $cell) {
                    $value = '';
                    $vNode = $xpath->query('.//v', $cell);
                    if ($vNode->length > 0) {
                        $value = $vNode->item(0)->nodeValue;
                    }
                    $rowData[] = $value;
                }
                if (!empty($rowData)) {
                    $data[] = $rowData;
                }
            }

            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
}
