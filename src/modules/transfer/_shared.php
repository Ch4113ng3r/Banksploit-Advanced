<?php

declare(strict_types=1);

/**
 * Shared fund-transfer logic used by the wizard UI (index.php) AND the JSON
 * API (api.php), so the vulnerable/secure behaviour lives in one place per
 * concern and a level flip affects both surfaces.
 *
 * Governing vulns (see vuln_config.json):
 *   transfer.l1  IDOR on from_account      (CWE-639)
 *   transfer.l2  IDOR on receipt           (CWE-639)
 *   transfer.l6  Negative amount           (CWE-840)
 *   transfer.l7  Race condition            (CWE-362)
 *   transfer.l9  Step-skipping in wizard   (CWE-841)
 *   transfer.g5  CSRF                       (CWE-352, via core CSRF class)
 *   statements.g2 SQLi in history filter    (CWE-89)
 *
 * The OTP step reuses auth/_shared.php (purpose='transfer').
 */

require_once __DIR__ . '/../auth/_shared.php';

if (!function_exists('xfer_user_accounts')) {

    /**
     * Accounts owned by a user (for the From dropdown).
     *
     * @return list<array<string,mixed>>
     */
    function xfer_user_accounts(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare('SELECT id, account_number, account_type, balance, daily_limit
                              FROM accounts WHERE user_id = :u ORDER BY id');
        $s->execute([':u' => $userId]);
        return $s->fetchAll();
    }

    /**
     * Beneficiaries for the To dropdown (this user's).
     *
     * @return list<array<string,mixed>>
     */
    function xfer_user_beneficiaries(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare('SELECT id, name, account_number, ifsc_code, bank_name,
                                   is_verified, cooling_period_until
                              FROM beneficiaries WHERE user_id = :u ORDER BY id');
        $s->execute([':u' => $userId]);
        return $s->fetchAll();
    }

    /**
     * Generate a unique transaction reference.
     */
    function xfer_txn_ref(string $mode): string
    {
        return strtoupper($mode) . strtoupper(bin2hex(random_bytes(5)));
    }

    /**
     * Map a beneficiary's account number to an internal account id (so an
     * internal->internal transfer credits the destination), or null if the
     * payee is external to BankSploit.
     */
    function xfer_resolve_to_account(PDO $pdo, string $accountNumber): ?int
    {
        $s = $pdo->prepare('SELECT id FROM accounts WHERE account_number = :a LIMIT 1');
        $s->execute([':a' => $accountNumber]);
        $id = $s->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    // ----------------------------------------------------- state machine ---

    /**
     * transfer.l9 — the wizard's server-side state. At the SECURE level each
     * step must follow the previous one (initiated -> confirmed -> otp_verified
     * -> executed). At vulnerable levels there is NO enforcement, so /execute
     * can be called directly (step skipping).
     *
     * The current flow lives in $_SESSION['transfer_flow'].
     */
    function xfer_flow_get(): array
    {
        $f = Session::get('transfer_flow');
        return is_array($f) ? $f : [];
    }

    function xfer_flow_set(array $flow): void
    {
        Session::set('transfer_flow', $flow);
    }

    function xfer_flow_clear(): void
    {
        Session::remove('transfer_flow');
    }

    /**
     * Whether the wizard state machine is enforced (transfer.l9 fixed).
     */
    function xfer_state_enforced(TrainingEngine $engine): bool
    {
        return !$engine->isVulnerable('transfer', 'l9');
    }

    // ----------------------------------------------------- history (SQLi) --

    /**
     * Transaction history for the user's accounts, with an optional search
     * filter on remarks.
     *
     * statements.g2 SQLi: at vulnerable levels the search term is concatenated
     * straight into the WHERE clause; at the secure level it is parameterized.
     *
     * @return array{rows:list<array<string,mixed>>,error:?string}
     */
    function xfer_history(PDO $pdo, TrainingEngine $engine, int $userId, string $q): array
    {
        // Restrict to the user's own accounts (the history scoping itself is
        // not the vuln here — the SQLi in the filter is).
        $ids = array_column(xfer_user_accounts($pdo, $userId), 'id');
        if ($ids === []) {
            return ['rows' => [], 'error' => null];
        }
        $idList = implode(',', array_map('intval', $ids));

        if (!g2_is_secure()) {
            // VULNERABLE (levels 0-2): string-concatenated search. g2_term()
            // applies the graduated mitigation (raw / blacklist / quote-escape).
            //   level 0: q = x' UNION SELECT id,otp_code,purpose,1,'x',1,1,NOW() FROM otp_tokens --
            $qq = g2_term($q);
            $sql = "SELECT id, from_account_id, to_account_id, amount, mode, status, remarks, created_at
                      FROM transactions
                     WHERE (from_account_id IN ($idList) OR to_account_id IN ($idList))";
            if ($q !== '') {
                $sql .= " AND remarks LIKE '%$qq%'";
            }
            $sql .= ' ORDER BY created_at DESC';
            try {
                return ['rows' => $pdo->query($sql)->fetchAll(), 'error' => null];
            } catch (PDOException $e) {
                return ['rows' => [], 'error' => l18_error($e, 'Query failed.')];
            }
        }

        // SECURE: parameterized.
        $sql = "SELECT id, from_account_id, to_account_id, amount, mode, status, remarks, created_at
                  FROM transactions
                 WHERE (from_account_id IN ($idList) OR to_account_id IN ($idList))";
        $bind = [];
        if ($q !== '') {
            $sql .= ' AND remarks LIKE :q';
            $bind[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY created_at DESC';
        $st = $pdo->prepare($sql);
        $st->execute($bind);
        return ['rows' => $st->fetchAll(), 'error' => null];
    }

    // ----------------------------------------------------- receipt (IDOR) --

    /**
     * Fetch a transaction "receipt" by id.
     *
     * transfer.l2 IDOR: at vulnerable levels ANY transaction id is returned
     * (no ownership check) — enumerate receipts to read other users' transfer
     * details. At the secure level the transaction must touch one of the
     * current user's accounts.
     *
     * @return array<string,mixed>|null
     */
    function xfer_receipt(PDO $pdo, TrainingEngine $engine, int $userId, int $txnId): ?array
    {
        if ($engine->isVulnerable('transfer', 'l2')) {
            // @vuln-start: receipt IDOR — no ownership check (transfer.l2)
            $s = $pdo->prepare('SELECT * FROM transactions WHERE id = :id');
            $s->execute([':id' => $txnId]);
            // @vuln-end
        } else {
            // @fix-start: receipt scoped to the current user's accounts (transfer.l2)
            $s = $pdo->prepare(
                'SELECT t.* FROM transactions t
                  WHERE t.id = :id
                    AND (t.from_account_id IN (SELECT id FROM accounts WHERE user_id = :u1)
                      OR t.to_account_id   IN (SELECT id FROM accounts WHERE user_id = :u2))'
            );
            $s->execute([':id' => $txnId, ':u1' => $userId, ':u2' => $userId]);
            // @fix-end
        }
        $row = $s->fetch();
        return $row ?: null;
    }
}
