<?php

/**
 * Shared store for the Leadership workshop deck's arrangement + published snapshot.
 *
 * Backed by a single-row MySQL table `deck_config` so the whole arrangement
 * travels with a database dump (move the code + DB to another server and nothing
 * is lost). Falls back to a local JSON file only if the database is unreachable.
 *
 * - Instructor panel (8888) reads AND writes: working arrangement (order +
 *   hidden), slides marked Non-user, and the published snapshot.
 * - Banking app (9443) reads ONLY the published snapshot for the participant deck.
 *
 * This file lives at the project root; both routers require it by absolute path.
 * It self-connects to the DB (env creds, same defaults as start-app.sh) because
 * the banking app's router requires it before the app bootstraps its own PDO.
 */

declare(strict_types=1);

function deck_root(): string
{
    return __DIR__;
}

function deck_config_path(): string
{
    return __DIR__ . '/.local/deck-config.json';   // legacy / fallback only
}

function deck_html_path(): string
{
    return __DIR__ . '/Leadership_Lab_Workshop.html';
}

/** Keep only well-formed slide ids ("s12"), de-duplicated, order preserved. */
function deck_sanitize_ids($ids): array
{
    if (!is_array($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        if (is_string($id) && preg_match('/^s\d+$/', $id) && !in_array($id, $out, true)) {
            $out[] = $id;
        }
    }
    return $out;
}

/** Lazy, self-contained PDO. Returns null (not throws) if the DB is unreachable. */
function deck_pdo(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'banksploit';
    $user = getenv('DB_USER') ?: 'banksploit';
    $pass = getenv('DB_PASS') ?: 'banksploit_pass';
    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function deck_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS deck_config ('
        . ' id TINYINT UNSIGNED NOT NULL PRIMARY KEY,'
        . ' data LONGTEXT NOT NULL,'
        . ' updated_at DATETIME NOT NULL'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function deck_defaults(): array
{
    return [
        'order'             => [],
        'instructorHidden'  => [],
        'participantHidden' => [],   // slides marked Non-user
        'published'         => null, // {order:[...], at:ISO} once published
        'updatedAt'         => null,
    ];
}

/** Legacy file read (used to migrate once, and as a fallback). null if absent. */
function deck_config_read_file(): ?array
{
    $raw = @file_get_contents(deck_config_path());
    if ($raw === false || $raw === '') {
        return null;
    }
    $cfg = json_decode($raw, true);
    return is_array($cfg) ? $cfg : null;
}

/** Current config with defaults filled in. Never throws. */
function deck_config_read(): array
{
    $defaults = deck_defaults();
    $pdo = deck_pdo();
    if ($pdo) {
        try {
            deck_ensure_table($pdo);
            $row = $pdo->query('SELECT data FROM deck_config WHERE id = 1')->fetchColumn();
            if ($row !== false && $row !== null) {
                $cfg = json_decode((string) $row, true);
                if (is_array($cfg)) {
                    return array_merge($defaults, $cfg);
                }
            }
            // One-time migration: seed the table from the legacy file if present.
            $legacy = deck_config_read_file();
            if ($legacy !== null) {
                $merged = array_merge($defaults, $legacy);
                deck_config_write($merged);
                return $merged;
            }
            return $defaults;
        } catch (Throwable $e) {
            // fall through to the file fallback
        }
    }
    $legacy = deck_config_read_file();
    return $legacy !== null ? array_merge($defaults, $legacy) : $defaults;
}

/** Persist the config. DB first; atomic file write only if the DB is down. */
function deck_config_write(array $cfg): bool
{
    if (empty($cfg['updatedAt'])) {
        $cfg['updatedAt'] = date('c');
    }
    $json = json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $pdo = deck_pdo();
    if ($pdo) {
        try {
            deck_ensure_table($pdo);
            $st = $pdo->prepare(
                'INSERT INTO deck_config (id, data, updated_at) VALUES (1, :d1, NOW())'
                . ' ON DUPLICATE KEY UPDATE data = :d2, updated_at = NOW()'
            );
            $st->execute([':d1' => $json, ':d2' => $json]);
            return true;
        } catch (Throwable $e) {
            // fall through to the file fallback
        }
    }

    // Fallback: atomic file write (tmp + rename) so a reader never sees half.
    $dir = dirname(deck_config_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $tmp = deck_config_path() . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    return @rename($tmp, deck_config_path());
}

/**
 * Participant slide list from the working state (layered model): the instructor's
 * sequence, minus hidden slides, minus slides marked Non-user.
 */
function deck_compute_published(array $cfg): array
{
    $order = deck_sanitize_ids($cfg['order'] ?? []);
    $drop  = array_merge(
        deck_sanitize_ids($cfg['instructorHidden'] ?? []),
        deck_sanitize_ids($cfg['participantHidden'] ?? [])
    );
    return array_values(array_filter($order, static fn($id) => !in_array($id, $drop, true)));
}
