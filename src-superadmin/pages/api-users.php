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
    $whereClauses[] = '(name LIKE ? OR email LIKE ? OR customer_id LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $whereClause");
$countStmt->execute($params);
$totalResult = $countStmt->fetch();
$total = (int) $totalResult['total'];
$totalPages = max(1, ceil($total / $limit));

// Get paginated users
$stmt = $pdo->prepare("
    SELECT id, customer_id, name, email, role, is_locked, is_suspended, failed_attempts, created_at
    FROM users
    $whereClause
    ORDER BY id
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [$limit, $offset]));
$rawUsers = $stmt->fetchAll();

// Transform data
$data = [];
foreach ($rawUsers as $u) {
    $data[] = [
        'id' => (int) $u['id'],
        'name' => (string) $u['name'],
        'email' => (string) $u['email'],
        'customer_id' => (string) $u['customer_id'],
        'role' => (string) $u['role'],
        'is_locked' => (int) $u['is_locked'] === 1,
        'is_suspended' => (int) $u['is_suspended'] === 1,
        'failed_attempts' => (int) $u['failed_attempts'],
        'created_at' => (string) $u['created_at']
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
