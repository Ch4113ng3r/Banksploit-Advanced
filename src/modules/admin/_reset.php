<?php

declare(strict_types=1);

/**
 * Database reset — restore clean seed state after exploitation.
 *
 * Truncates the dynamic data tables and replays sql/02-seed-data.sql (mounted
 * read-only at /var/www/sql), so balances, users, cards, loans, beneficiaries
 * and transactions return to their seeded values. Training progress is reset to
 * 'hunting' and system_config to defaults. Vulnerability levels in
 * vuln_config.json are left untouched (that's the trainer's setting).
 *
 * Exposed via the admin panel (admin_guard) — see admin/api.php.
 */

if (!function_exists('admin_reset_database')) {

    /**
     * @return array{ok:bool,message:string}
     */
    function admin_reset_database(PDO $pdo): array
    {
        $seed = '/var/www/sql/02-seed-data.sql';
        if (!is_file($seed)) {
            return ['ok' => false, 'message' => 'Seed file not found (is ./sql mounted?).'];
        }
        $sql = (string) file_get_contents($seed);

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            // Clear everything the seed will re-create, plus runtime-only tables.
            foreach (['transactions', 'bill_payments', 'cards', 'loans', 'beneficiaries',
                      'accounts', 'otp_tokens', 'password_reset_tokens', 'user_preferences',
                      'users'] as $t) {
                $pdo->exec("TRUNCATE TABLE `$t`");
            }
            // Replay the seed (pdo_mysql runs multiple ;-separated statements).
            $pdo->exec($sql);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            // Re-seed the columns added after the original seed file was written.
            $pdo->exec("UPDATE users SET last_login_ip='203.0.113.10' WHERE customer_id='ADMIN001'");
            $pdo->exec("UPDATE users SET last_login_ip='198.51.100.22' WHERE customer_id='BS100234'");

            // Reset training progress + clear logs (fresh slate).
            $pdo->exec("UPDATE training_progress SET status='hunting', hints_used=0, found_at=NULL");
            $pdo->exec('DELETE FROM audit_log');
            $pdo->exec('DELETE FROM request_log');

            // Clean up filesystem artifacts dropped during exploitation
            // (webshells from G7 upload / G12 deserialization, traversal writes).
            $removed = admin_reset_filesystem();

            $msg = 'Database reset to clean seed state.';
            if ($removed > 0) {
                $msg .= " Removed {$removed} uploaded file(s).";
            }
            return ['ok' => true, 'message' => $msg];
        } catch (Throwable $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            return ['ok' => false, 'message' => 'Reset failed: ' . $e->getMessage()];
        }
    }

    /**
     * Remove filesystem artifacts left by exploitation: anything dropped into
     * the web-served uploads/ directory (webshells from loans.g7 / KYC upload),
     * plus stray files written into the web root via traversal / object
     * injection (g11 / g12 / g8). Returns the count removed.
     *
     * Only attacker-droppable content is removed — never app .php source.
     */
    function admin_reset_filesystem(): int
    {
        $count = 0;

        // 1) Empty the web-served uploads directory entirely.
        $uploads = __DIR__ . '/../../uploads';
        if (is_dir($uploads)) {
            foreach (glob($uploads . '/*') ?: [] as $f) {
                if (is_file($f) && @unlink($f)) {
                    $count++;
                }
            }
        }

        // 2) Remove secure-level KYC/loan document stores (level-3 uploads).
        foreach (['/var/kyc', '/var/loan_docs'] as $dir) {
            if (is_dir($dir)) {
                foreach (glob($dir . '/*') ?: [] as $f) {
                    if (is_file($f) && @unlink($f)) {
                        $count++;
                    }
                }
            }
        }

        // 3) Remove common g11/g12 drop targets in the web root, but never the
        //    app's own .php — only obvious attacker artifacts (.txt/.phtml).
        $webroot = __DIR__ . '/../..';
        foreach (glob($webroot . '/*.{txt,phtml}', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f) && @unlink($f)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Build a ZIP of all *_vuln.php source files for offline code review.
     * Returns the temp zip path, or null on failure.
     */
    function admin_zip_vuln_sources(): ?string
    {
        if (!class_exists('ZipArchive')) {
            return null;
        }
        $root = realpath(__DIR__ . '/..');           // modules/
        if ($root === false) {
            return null;
        }
        $zipPath = sys_get_temp_dir() . '/banksploit_vuln_source_' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            $name = $file->getFilename();
            // Ship the vulnerable implementations + their secure counterparts.
            if (str_ends_with($name, '_vuln.php') || str_ends_with($name, '_secure.php')) {
                $rel = 'banksploit-source/' . ltrim(str_replace($root, '', $file->getPathname()), '/\\');
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $rel));
            }
        }
        $zip->close();
        return $zipPath;
    }
}
