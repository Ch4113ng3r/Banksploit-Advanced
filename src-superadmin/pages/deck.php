<?php

/**
 * Instructor deck (port 8888, behind SuperAdminAuth).
 * Serves the full workshop deck with all controls, injecting the current
 * server-side arrangement + the endpoints the deck posts changes back to.
 */

require_once dirname(__DIR__, 2) . '/deck_config.php';

$html = @file_get_contents(deck_html_path());
if ($html === false) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Deck not built</h1><p>Run <code>python3 build_deck.py</code> at the project root first.</p>';
    return;
}

$cfg = deck_config_read();

$server = [
    'mode'       => 'instructor',
    'config'     => [
        'order'             => deck_sanitize_ids($cfg['order']),
        'instructorHidden'  => deck_sanitize_ids($cfg['instructorHidden']),
        'participantHidden' => deck_sanitize_ids($cfg['participantHidden']),
        'published'         => $cfg['published'] ?? null,
    ],
    'saveUrl'    => '/api/deck-save',
    'publishUrl' => '/api/deck-publish',
    'viewUrl'    => '/deck',
];

$inject = '<script>window.DECK_SERVER='
    . json_encode($server, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    . ';</script>';

$html = str_replace('</head>', $inject . "\n</head>", $html);

header('Content-Type: text/html; charset=utf-8');
echo $html;
