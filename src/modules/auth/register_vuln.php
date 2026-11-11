<?php

declare(strict_types=1);

/**
 * VULNERABLE registration (levels 0-2).
 *
 * @param array<string,mixed> $in  Merged request input.
 * @return array{ok:bool,error:?string,user_id:?int,role:string,upload:?array<string,mixed>}
 *
 * ============================================================================
 *  VULNERABILITIES (for the code-viewer feature)
 * ----------------------------------------------------------------------------
 *  profile.l5  Mass assignment (CWE-915):
 *              Every field in the request body is written to the new user row,
 *              including `role`. POST role=admin -> instant admin account.
 *  (no input validation): email/phone/PAN are not checked, so garbage or
 *              injection payloads land in the DB.
 *  loans.g7    Unrestricted KYC upload (CWE-434):
 *              The KYC document is saved with no server-side extension check at
 *              level 0 (client-only .jpg filter at level 1) -> upload a .php
 *              webshell. Handled by auth_handle_kyc_upload() in _shared.php.
 * ============================================================================
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('auth_register_vuln')) {

    function auth_register_vuln(PDO $pdo, TrainingEngine $engine, array $in): array
    {
        // Columns that physically exist; we still let `role` through — THAT is
        // the mass-assignment flaw (an allow-list would exclude it).
        $columns = ['customer_id', 'name', 'email', 'phone', 'aadhaar_masked', 'pan', 'role'];

        // --------------------------------------------------------------------
        // MASS ASSIGNMENT: blindly take whatever the client sent for any known
        // column. No allow-list, no role pinning, no validation.
        // --------------------------------------------------------------------
        $data = [];
        foreach ($columns as $col) {
            if (array_key_exists($col, $in)) {
                $data[$col] = $in[$col];
            }
        }

        // Minimal required fields so the INSERT doesn't fail outright.
        $data['customer_id'] = $data['customer_id'] ?? ('BS' . random_int(100000, 999999));
        $data['name']        = $data['name']  ?? 'New User';
        $data['email']       = $data['email'] ?? ($data['customer_id'] . '@example.in');
        $data['phone']       = $data['phone'] ?? '+910000000000';

        $password = (string) ($in['password'] ?? 'changeme');

        // Passwords stored BOTH ways (md5 retained for the weak-hash levels).
        $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $data['password_md5']  = md5($password);

        $cols = array_keys($data);
        $place = array_map(static fn ($c) => ':' . $c, $cols);
        $sql = 'INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . implode(',', $place) . ')';

        try {
            $stmt = $pdo->prepare($sql);
            foreach ($data as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();
            $userId = (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            // core.l18 graduated error disclosure (verbose -> SQLSTATE -> ref -> generic).
            return ['ok' => false, 'error' => l18_error($e, 'Registration failed.'), 'user_id' => null, 'role' => 'user', 'upload' => null];
        }

        // Unrestricted KYC upload (loans.g7) when a file was sent.
        $upload = null;
        if (isset($_FILES['kyc_document'])) {
            $upload = auth_handle_kyc_upload($engine, $_FILES['kyc_document']);
        }

        return [
            'ok'      => true,
            'error'   => null,
            'user_id' => $userId,
            'role'    => (string) ($data['role'] ?? 'user'),   // reflects mass-assigned role
            'upload'  => $upload,
        ];
    }
}
