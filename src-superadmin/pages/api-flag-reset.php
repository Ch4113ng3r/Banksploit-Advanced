<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

// Parse and validate query params
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $limit;

// Build query with optional search filter
$whereClauses = [];
$params = [];
if ($search !== '') {
    $whereClauses[] = '(u.name LIKE ? OR u.customer_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count (unique users)
$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    $whereClause
");
$countStmt->execute($params);
$totalResult = $countStmt->fetch();
$total = (int) $totalResult['total'];
$totalPages = max(1, ceil($total / $limit));

// Get paginated users with flag stats
$stmt = $pdo->prepare("
    SELECT u.id, u.customer_id, u.name,
           COUNT(s.id) AS flags_captured,
           SUM(COALESCE(s.points, 0)) AS total_points,
           MAX(s.submitted_at) AS last_capture
    FROM users u
    LEFT JOIN ctf_submissions s ON s.user_id = u.id
    $whereClause
    GROUP BY u.id, u.customer_id, u.name
    ORDER BY total_points DESC, u.customer_id ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$rawUsers = $stmt->fetchAll();

// Transform data
$data = [];
foreach ($rawUsers as $u) {
    $data[] = [
        'id' => (int) $u['id'],
        'customer_id' => (string) $u['customer_id'],
        'name' => (string) $u['name'],
        'flags_captured' => (int) ($u['flags_captured'] ?? 0),
        'total_points' => (int) ($u['total_points'] ?? 0),
        'last_capture' => (string) ($u['last_capture'] ?? '')
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
