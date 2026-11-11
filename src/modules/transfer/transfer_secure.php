<?php

declare(strict_types=1);

/**
 * SECURE fund transfer execution (level 3).
 *
 * ============================================================================
 *  SECURE CONTROLS (contrast with transfer_vuln.php)
 * ----------------------------------------------------------------------------
 *  - Ownership check: from_account MUST belong to the current user (fixes the
 *    transfer.l1 IDOR).
 *  - Amount validation: amount > 0 AND amount <= the account's daily_limit
 *    (fixes transfer.l6 negative amount).
 *  - Atomicity: SELECT ... FOR UPDATE inside a transaction serialises the
 *    balance read+write so concurrent requests can't double-spend
 *    (fixes transfer.l7 race condition).
 *  - Parameterized queries throughout.
 *  - The wizard state machine (transfer.l9) and CSRF (transfer.g5) are enforced
 *    by the caller before this runs.
 * ============================================================================
 *
 * @return array{ok:bool,error:?string,txn_id:?int,txn_ref:?string,new_balance:?float}
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('xfer_execute_secure')) {

    /**
     * @param array<string,mixed> $p  from_account_id, beneficiary_id, amount, mode, remarks
     */
    function xfer_execute_secure(PDO $pdo, TrainingEngine $engine, int $userId, array $p): array
    {
        $fromId  = (int) ($p['from_account_id'] ?? 0);
        $benId   = (int) ($p['beneficiary_id'] ?? 0);
        $amount  = (float) ($p['amount'] ?? 0);
        $mode    = in_array($p['mode'] ?? '', ['IMPS', 'NEFT', 'RTGS'], true) ? $p['mode'] : 'IMPS';
        $remarks = (string) ($p['remarks'] ?? '');

        $fail = static fn (string $m): array =>
            ['ok' => false, 'error' => $m, 'txn_id' => null, 'txn_ref' => null, 'new_balance' => null];

        // Validate amount BEFORE touching the DB.
        // @fix-start: reject non-positive amounts (transfer.l6)
        if ($amount <= 0) {
            return $fail('Amount must be greater than zero.');
        }
        // @fix-end

        // Beneficiary must belong to the user; resolve destination.
        $ben = $pdo->prepare('SELECT account_number FROM beneficiaries WHERE id = :id AND user_id = :u');
        $ben->execute([':id' => $benId, ':u' => $userId]);
        $benRow = $ben->fetch();
        if (!$benRow) {
            return $fail('Beneficiary not found.');
        }
        $toId = xfer_resolve_to_account($pdo, (string) $benRow['account_number']);

        // @fix-start: race condition — atomic transaction wrapper (transfer.l7)
        try {
            $pdo->beginTransaction();
            // @fix-end

            // Ownership check + row lock in one shot. FOR UPDATE both enforces
            // ownership (fixes l1) AND serialises concurrent transfers (fixes l7).
            // @fix-start: IDOR on from_account + row lock (transfer.l1)
            $sel = $pdo->prepare(
                'SELECT id, balance, daily_limit FROM accounts
                  WHERE id = :id AND user_id = :u FOR UPDATE'
            );
            $sel->execute([':id' => $fromId, ':u' => $userId]);
            $from = $sel->fetch();
            // @fix-end

            if (!$from) {
                $pdo->rollBack();
                return $fail('Source account not found or not yours.');
            }
            // transfer.l21: aggregate today's transfers, not just this one.
            $lim = xfer_check_daily_limit($pdo, $engine, $fromId, $amount, (float) $from['daily_limit']);
            if (!$lim['ok']) {
                $pdo->rollBack();
                return $fail($lim['error']);
            }
            if ((float) $from['balance'] < $amount) {
                $pdo->rollBack();
                return $fail('Insufficient balance.');
            }

            $newBalance = (float) $from['balance'] - $amount;
            $pdo->prepare('UPDATE accounts SET balance = :b WHERE id = :id')
                ->execute([':b' => $newBalance, ':id' => $fromId]);

            if ($toId !== null) {
                $pdo->prepare('UPDATE accounts SET balance = balance + :a WHERE id = :id')
                    ->execute([':a' => $amount, ':id' => $toId]);
            }

            $ref = xfer_txn_ref($mode);
            $pdo->prepare(
                'INSERT INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified)
                 VALUES (:f, :t, :amt, :mode, "completed", :rem, :ref, 1)'
            )->execute([
                ':f' => $fromId, ':t' => $toId, ':amt' => $amount,
                ':mode' => $mode, ':rem' => $remarks, ':ref' => $ref,
            ]);
            $txnId = (int) $pdo->lastInsertId();

            // transfer.l23: cashback that EXCLUDES self-transfers at this level.
            $cashback = xfer_apply_cashback($pdo, $engine, $userId, $fromId, $toId, $amount);

            $pdo->commit();

            return ['ok' => true, 'error' => null, 'txn_id' => $txnId, 'txn_ref' => $ref,
                    'new_balance' => $newBalance + $cashback, 'cashback' => $cashback];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $fail('Transfer failed.');
        }
    }
}
