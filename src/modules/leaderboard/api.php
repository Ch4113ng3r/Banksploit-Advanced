<?php

declare(strict_types=1);

/**
 * Leaderboard API — returns paginated JSON leaderboard data
 *
 * Requires authentication. GET /leaderboard/api with optional pagination & search.
 * Query params: page (1-based, default 1), limit (default 25, max 100), search (name filter)
 * Response format: {"success": true, "data": [...], "meta": {page, limit, total, total_pages}}
 */

auth()->requireAuth();

header('Content-Type: application/json; charset=utf-8');

$user = auth()->getCurrentUser();
$pdo  = db();

// Parse and validate query params
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

// --- Read hidden users from JSON file (no DB column needed) ---
$hiddenFile = dirname(__DIR__, 3) . '/data/ctf-hidden-users.json';
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

// Build query with optional search filter
// Always exclude hidden users from the public leaderboard
$whereClauses = [];
$params = [];

if (!empty($hiddenIds)) {
    $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
    $whereClauses[] = "u.id NOT IN ($placeholders)";
    $params = $hiddenIds;
}

if ($search !== '') {
    $whereClauses[] = 'u.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$whereClause = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count (before pagination)
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    LEFT JOIN ctf_submissions cs ON u.id = cs.user_id
    $whereClause
");
$countStmt->execute($params);
$totalResult = $countStmt->fetch();
$total = (int) $totalResult['total'];
$totalPages = max(1, ceil($total / $limit));

// Get paginated leaderboard
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.customer_id,
        COALESCE(SUM(cs.points), 0) as total_points,
        COUNT(DISTINCT cs.vuln_key) as flags_captured,
        MAX(cs.submitted_at) as last_capture
    FROM users u
    LEFT JOIN ctf_submissions cs ON u.id = cs.user_id
    $whereClause
    GROUP BY u.id, u.name, u.customer_id
    ORDER BY total_points DESC, last_capture DESC, u.name ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$rawLeaderboard = $stmt->fetchAll();

// Calculate rank for paginated results
$data = [];
$baseRank = $offset + 1;
foreach ($rawLeaderboard as $idx => $entry) {
    $rank = $baseRank + $idx;
    $data[] = [
        'rank' => $rank,
        'id' => (int) $entry['id'],
        'name' => (string) $entry['name'],
        'customer_id' => (string) $entry['customer_id'],
        'points' => (int) $entry['total_points'],
        'flags' => (int) $entry['flags_captured'],
        'last_capture' => $entry['last_capture'] ? substr((string) $entry['last_capture'], 0, 10) : null,
        'is_current_user' => (int) $entry['id'] === (int) $user['id']
    ];
}

echo json_encode(
    [
        'success' => true,
        'data' => $data,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
