<?php

declare(strict_types=1);

/**
 * SECURE registration (level 3).
 *
 * @param array<string,mixed> $in
 * @return array{ok:bool,error:?string,user_id:?int,role:string,upload:?array<string,mixed>}
 *
 * ============================================================================
 *  SECURE CONTROLS (contrast with register_vuln.php)
 * ----------------------------------------------------------------------------
 *  - Field ALLOW-LIST: only name/email/phone/password are accepted; `role` is
 *    pinned to 'user' server-side (defeats mass assignment, profile.l5).
 *  - Input VALIDATION: email + phone + name are validated; bad input rejected.
 *  - KYC upload: extension allow-list + MIME sniff + random name, stored
 *    outside the web root (defeats loans.g7) via auth_handle_kyc_upload().
 *  - bcrypt password hash (md5 column set to a non-matching placeholder).
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('auth_register_secure')) {

    function auth_register_secure(PDO $pdo, TrainingEngine $engine, array $in): array
    {
        // ---- ALLOW-LIST: never bind anything the client wasn't offered. ----
        $name     = trim((string) ($in['name'] ?? ''));
        $email    = trim((string) ($in['email'] ?? ''));
        $phone    = trim((string) ($in['phone'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        // ---- VALIDATION ----
        $errors = [];
        if ($name === '' || mb_strlen($name) > 128) {
            $errors[] = 'A valid name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            $errors[] = 'A valid phone number is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'error' => implode(' ', $errors), 'user_id' => null, 'role' => 'user', 'upload' => null];
        }

        // Validate KYC BEFORE creating the account (reject bad files outright).
        $upload = null;
        if (isset($_FILES['kyc_document']) && ($_FILES['kyc_document']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = auth_handle_kyc_upload($engine, $_FILES['kyc_document']);
            if (!$upload['ok']) {
                return ['ok' => false, 'error' => $upload['message'], 'user_id' => null, 'role' => 'user', 'upload' => $upload];
            }
        }

        // role is ALWAYS 'user' — ignore any client-supplied role.
        $customerId = 'BS' . random_int(100000, 999999);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (customer_id, name, email, phone, password_hash, password_md5, role)
                 VALUES (:cid, :name, :email, :phone, :ph, :pmd5, :role)'
            );
            $stmt->execute([
                ':cid'   => $customerId,
                ':name'  => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':ph'    => password_hash($password, PASSWORD_BCRYPT),
                // md5 column is NOT NULL; store a value that can never match a
                // login (secure levels use bcrypt only).
                ':pmd5'  => str_repeat('0', 32),
                ':role'  => 'user',
            ]);
            $userId = (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return ['ok' => false, 'error' => 'That email is already registered.', 'user_id' => null, 'role' => 'user', 'upload' => $upload];
            }
            return ['ok' => false, 'error' => 'Registration failed.', 'user_id' => null, 'role' => 'user', 'upload' => $upload];
        }

        return ['ok' => true, 'error' => null, 'user_id' => $userId, 'role' => 'user', 'upload' => $upload];
    }
}
