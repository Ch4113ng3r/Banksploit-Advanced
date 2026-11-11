<?php

declare(strict_types=1);

/**
 * Superadmin Leaderboard API — returns paginated CTF leaderboard data.
 *
 * GET /api/leaderboard?page=1&limit=25&search=...
 * Returns ranked participants (excluding hidden users from ranking) plus
 * a separate list of hidden users. Includes a `hash` for change-detection polling.
 *
 * Hidden users are stored in data/ctf-hidden-users.json (array of user IDs).
 */

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

// Parse query params
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

// --- Read hidden users from JSON file ---
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

// --- Change-detection hash (cheap query for polling) ---
$hashRow = $pdo->query("SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS total FROM ctf_submissions")->fetch();
// Include hidden list in hash so UI refreshes when hide state changes
$dataHash = md5($hashRow['max_id'] . ':' . $hashRow['total'] . ':' . implode(',', $hiddenIds));

// Build WHERE clause
$whereClauses = ["u.role = 'user'"];
$params = [];
if ($search !== '') {
    $whereClauses[] = '(u.name LIKE ? OR u.customer_id LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

// --- Exclude hidden users from ranked query ---
$activeWhereClauses = $whereClauses;
if (!empty($hiddenIds)) {
    $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
    $activeWhereClauses[] = "u.id NOT IN ($placeholders)";
}
$activeWhere = implode(' AND ', $activeWhereClauses);
$activeParams = !empty($hiddenIds) ? array_merge($params, $hiddenIds) : $params;

// Count active participants
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT u.id) as total FROM users u WHERE $activeWhere");
$countStmt->execute($activeParams);
$total = (int) $countStmt->fetch()['total'];
$totalPages = max(1, (int) ceil($total / $limit));

// Paginated ranked data
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.customer_id,
        u.email,
        COALESCE(SUM(cs.points), 0) as total_points,
        COUNT(DISTINCT cs.vuln_key) as flags_captured,
        MAX(cs.submitted_at) as last_capture
    FROM users u
    LEFT JOIN ctf_submissions cs ON u.id = cs.user_id
    WHERE $activeWhere
    GROUP BY u.id, u.name, u.customer_id, u.email
    ORDER BY total_points DESC, last_capture ASC, u.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($activeParams, [$limit, $offset]));
$rawData = $stmt->fetchAll();

$data = [];
$baseRank = $offset + 1;
foreach ($rawData as $idx => $entry) {
    $data[] = [
        'rank'         => $baseRank + $idx,
        'id'           => (int) $entry['id'],
        'name'         => (string) $entry['name'],
        'customer_id'  => (string) $entry['customer_id'],
        'email'        => (string) $entry['email'],
        'points'       => (int) $entry['total_points'],
        'flags'        => (int) $entry['flags_captured'],
        'last_capture' => $entry['last_capture'] ? substr((string) $entry['last_capture'], 0, 16) : null,
        'ctf_hidden'   => false,
    ];
}

// --- Hidden users (always returned in full, typically few) ---
$hidden = [];
if (!empty($hiddenIds)) {
    $hiddenWhereClauses = $whereClauses;
    $hiddenPlaceholders = implode(',', array_fill(0, count($hiddenIds), '?'));
    $hiddenWhereClauses[] = "u.id IN ($hiddenPlaceholders)";
    $hiddenWhere = implode(' AND ', $hiddenWhereClauses);
    $hiddenParams = array_merge($params, $hiddenIds);

    $hiddenStmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.customer_id,
            u.email,
            COALESCE(SUM(cs.points), 0) as total_points,
            COUNT(DISTINCT cs.vuln_key) as flags_captured,
            MAX(cs.submitted_at) as last_capture
        FROM users u
        LEFT JOIN ctf_submissions cs ON u.id = cs.user_id
        WHERE $hiddenWhere
        GROUP BY u.id, u.name, u.customer_id, u.email
        ORDER BY u.name ASC
    ");
    $hiddenStmt->execute($hiddenParams);
    $hiddenRaw = $hiddenStmt->fetchAll();

    foreach ($hiddenRaw as $entry) {
        $hidden[] = [
            'id'           => (int) $entry['id'],
            'name'         => (string) $entry['name'],
            'customer_id'  => (string) $entry['customer_id'],
            'email'        => (string) $entry['email'],
            'points'       => (int) $entry['total_points'],
            'flags'        => (int) $entry['flags_captured'],
            'last_capture' => $entry['last_capture'] ? substr((string) $entry['last_capture'], 0, 16) : null,
            'ctf_hidden'   => true,
        ];
    }
}

echo json_encode([
    'success' => true,
    'data'    => $data,
    'hidden'  => $hidden,
    'hash'    => $dataHash,
    'meta'    => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'total_pages' => $totalPages,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
