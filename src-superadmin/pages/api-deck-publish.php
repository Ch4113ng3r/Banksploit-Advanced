<?php

/**
 * POST /api/deck-publish (port 8888, behind SuperAdminAuth).
 * Saves the latest working arrangement (if sent) and writes the published
 * snapshot that the participant deck on 9443 reads.
 */

require_once dirname(__DIR__, 2) . '/deck_config.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    return;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    $body = [];
}

$cfg = deck_config_read();

if (array_key_exists('order', $body)) {
    $cfg['order'] = deck_sanitize_ids($body['order']);
}
if (array_key_exists('instructorHidden', $body)) {
    $cfg['instructorHidden'] = deck_sanitize_ids($body['instructorHidden']);
}
if (array_key_exists('participantHidden', $body)) {
    $cfg['participantHidden'] = deck_sanitize_ids($body['participantHidden']);
}

$publishedOrder = deck_compute_published($cfg);
$cfg['published'] = [
    'order' => $publishedOrder,
    'at'    => date('c'),
];
$cfg['updatedAt'] = date('c');

$ok = deck_config_write($cfg);
echo json_encode([
    'ok'        => $ok,
    'published' => $cfg['published'],
    'count'     => count($publishedOrder),
]);
