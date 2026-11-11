<?php

declare(strict_types=1);

/**
 * VULNERABLE / SECURE bill-payment logic.
 *
 * Bill payments are recorded in a DEDICATED `bill_payments` table holding the
 * biller detail in proper columns (biller_type / biller_id / biller_name /
 * remarks). The actual money movement is still a debit row in `transactions`
 * (to_account_id NULL), linked by a shared txn_ref. The ledger row carries a
 * SAFE, enum-only label ("Bill payment: <type>") while the raw, user-controlled
 * biller_name — the stored-XSS sink — lives only in bill_payments.
 *
 * ============================================================================
 *  VULNERABILITY
 * ----------------------------------------------------------------------------
 *  beneficiary.g3 Stored XSS (CWE-79) [shared G3 dial]:
 *      The biller name / remarks are stored RAW and rendered UNescaped in the
 *      payment-history list at vuln levels, so <script>…</script> in the biller
 *      name executes when the history is viewed. At the secure level the value
 *      is still stored as-is but ESCAPED on output (and we also strip tags on
 *      input as defence in depth). Rendering is centralised via vuln_out().
 *
 *  Note: G3 is a single generic vuln in the taxonomy; this module shares the
 *  same dial as the beneficiary stored-XSS so flipping it secures both.
 * ============================================================================
 */

if (!function_exists('payments_pay')) {

    /**
     * Record a bill payment as a debit transaction from the user's account.
     *
     * @param array<string,mixed> $in  from_account_id, biller_type, biller_id,
     *                                  biller_name, amount, remarks
     * @return array{ok:bool,error:?string,txn_id:?int,txn_ref:?string}
     */
    function payments_pay(PDO $pdo, TrainingEngine $engine, int $userId, array $in): array
    {
        $fromId   = (int) ($in['from_account_id'] ?? 0);
        $amount   = (float) ($in['amount'] ?? 0);
        $validTypes = ['electricity', 'water', 'gas', 'mobile', 'DTH'];
        $type     = in_array($in['biller_type'] ?? '', $validTypes, true) ? (string) $in['biller_type'] : 'electricity';
        $billerId = (string) ($in['biller_id'] ?? '');
        $biller   = (string) ($in['biller_name'] ?? ($in['biller_id'] ?? 'Biller'));
        $note     = (string) ($in['remarks'] ?? '');

        $secure = !$engine->isVulnerable('beneficiary', 'g3');

        // SECURE input hardening (defence in depth): strip tags on the way in.
        // At vuln levels biller_name / remarks are stored RAW (incl. <script>).
        // @fix-start: stored XSS — strip tags on input (beneficiary.g3)
        if ($secure) {
            $biller = strip_tags($biller);
            $note   = strip_tags($note);
        }
        // @fix-end
        // @vuln-start: stored XSS — biller_name kept RAW at vuln levels (beneficiary.g3)
        // (when $secure is false, the value above is stored verbatim and later
        //  rendered unescaped by payments_render_remark() -> the XSS fires)
        // @vuln-end

        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Amount must be positive.', 'txn_id' => null, 'txn_ref' => null];
        }

        // Ownership of the source account.
        $acc = $pdo->prepare('SELECT balance FROM accounts WHERE id = :id AND user_id = :u');
        $acc->execute([':id' => $fromId, ':u' => $userId]);
        $row = $acc->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => 'Account not found.', 'txn_id' => null, 'txn_ref' => null];
        }
        if ((float) $row['balance'] < $amount) {
            return ['ok' => false, 'error' => 'Insufficient balance.', 'txn_id' => null, 'txn_ref' => null];
        }

        $ref = 'BILL' . strtoupper(bin2hex(random_bytes(5)));

        // 1) Debit the account in the ledger with a SAFE, enum-only label.
        //    The raw biller_name never touches `transactions`.
        $pdo->prepare('UPDATE accounts SET balance = balance - :a WHERE id = :id')
            ->execute([':a' => $amount, ':id' => $fromId]);
        $pdo->prepare(
            'INSERT INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified)
             VALUES (:f, NULL, :amt, "IMPS", "completed", :rem, :ref, 1)'
        )->execute([':f' => $fromId, ':amt' => $amount, ':rem' => 'Bill payment: ' . $type, ':ref' => $ref]);
        $txnId = (int) $pdo->lastInsertId();

        // 2) Record biller detail in the dedicated table. biller_name here is
        //    the stored-XSS sink (raw at vuln levels).
        $pdo->prepare(
            'INSERT INTO bill_payments (user_id, account_id, biller_type, biller_id, biller_name, amount, remarks, status, txn_ref)
             VALUES (:u, :acc, :type, :bid, :bname, :amt, :rem, "completed", :ref)'
        )->execute([
            ':u' => $userId, ':acc' => $fromId, ':type' => $type, ':bid' => $billerId,
            ':bname' => $biller, ':amt' => $amount, ':rem' => $note, ':ref' => $ref,
        ]);

        return ['ok' => true, 'error' => null, 'txn_id' => $txnId, 'txn_ref' => $ref];
    }

    /**
     * Payment history from the dedicated bill_payments table.
     *
     * @return list<array<string,mixed>>
     */
    function payments_history(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare(
            'SELECT id, biller_type, biller_id, biller_name, amount, remarks, status, txn_ref, created_at
               FROM bill_payments WHERE user_id = :u ORDER BY created_at DESC, id DESC'
        );
        $s->execute([':u' => $userId]);
        return $s->fetchAll();
    }
}
