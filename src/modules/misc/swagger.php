<?php

declare(strict_types=1);

/**
 * Swagger UI host (loaded from CDN) + the raw OpenAPI spec.
 *
 * Routes:
 *   GET /swagger-ui        -> this Swagger UI page
 *   GET /api/swagger.json  -> the OpenAPI 3.0 spec (served raw)
 *
 * No auth: the API docs are public so they can be browsed before signing in.
 */

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');

// Serve the raw spec.
if ($path === '/api/swagger.json') {
    header('Content-Type: application/json; charset=utf-8');
    readfile(__DIR__ . '/../../api/swagger.json');
    exit;
}

// Swagger UI shell (assets from the official CDN).
$specUrl = htmlspecialchars(APP_URL . '/api/swagger.json');
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BankSploit API &middot; Swagger UI</title>
    <link rel="stylesheet" href="/public/vendor/swagger/swagger-ui.css">
    <style>body{margin:0;background:#0b1020}.topbar{display:none}</style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="/public/vendor/swagger/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: "<?= $specUrl ?>",
                dom_id: "#swagger-ui",
                deepLinking: true,
                withCredentials: true,
                presets: [SwaggerUIBundle.presets.apis]
            });
        };
    </script>
</body>
</html>
