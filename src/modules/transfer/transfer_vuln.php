<?php

declare(strict_types=1);

/**
 * VULNERABLE fund transfer execution (levels 0-2).
 *
 * ============================================================================
 *  VULNERABILITIES (for the code-viewer feature)
 * ----------------------------------------------------------------------------
 *  transfer.l1 IDOR on from_account (CWE-639):
 *      $from_account_id is taken from the request and used directly. There is
 *      NO check that the account belongs to the logged-in user, so you can set
 *      from_account to ANY account id and drain it.
 *
 *  transfer.l6 Negative amount (CWE-840):
 *      No `amount > 0` validation. The balance check `balance >= amount` PASSES
 *      for a negative amount (balance >= -5000 is always true), and subtracting
 *      a negative CREDITS the source account. Transfer -5000 -> +5000.
 *
 *  transfer.l7 Race condition / double-spend (CWE-362):
 *      The balance is read, checked, then written in separate non-atomic steps
 *      with NO row lock. Two concurrent requests both pass the check before
 *      either debits. At level 1 a deliberate sleep() widens the window to make
 *      it trivially exploitable.
 *
 *  transfer.l9 Step-skipping (CWE-841):
 *      Enforced by the caller — at vuln levels execute() runs with no wizard
 *      state, so /execute can be hit directly (see xfer_state_enforced()).
 *
 *  transfer.g5 CSRF (CWE-352): no token is validated (handled by the dispatcher
 *      via the CSRF class, which is a no-op at level 0).
 *
 *  Level 2 note: SQL is parameterized here even at vuln levels (the SQLi lives
 *  in the history filter), so L2's "parameterized but IDOR/race remain" is the
 *  natural state of this function — IDOR (l1) and the race (l7) persist until 3.
 * ============================================================================
 *
 * @return array{ok:bool,error:?string,txn_id:?int,txn_ref:?string,new_balance:?float}
 */

require_once __DIR__ . '/_shared.php';

if (!function_exists('xfer_execute_vuln')) {

    /**
     * @param array<string,mixed> $p  from_account_id, beneficiary_id, amount, mode, remarks
     */
    function xfer_execute_vuln(PDO $pdo, TrainingEngine $engine, int $userId, array $p): array
    {
        $fromId  = (int) ($p['from_account_id'] ?? 0);
        $benId   = (int) ($p['beneficiary_id'] ?? 0);
        $amount  = (float) ($p['amount'] ?? 0);
        $mode    = in_array($p['mode'] ?? '', ['IMPS', 'NEFT', 'RTGS'], true) ? $p['mode'] : 'IMPS';
        $remarks = (string) ($p['remarks'] ?? '');

        $raceLevel = $engine->getVulnLevel('transfer', 'l7');

        // --- transfer.l1 IDOR: load the source account with NO ownership check.
        // @vuln-start: IDOR on from_account (transfer.l1)
        $acc = $pdo->prepare('SELECT id, user_id, balance, daily_limit FROM accounts WHERE id = :id');
        $acc->execute([':id' => $fromId]);
        $from = $acc->fetch();
        // @vuln-end
        if (!$from) {
            return ['ok' => false, 'error' => 'Source account not found.', 'txn_id' => null, 'txn_ref' => null, 'new_balance' => null];
        }

        // Resolve the beneficiary -> optional internal destination account.
        $ben = $pdo->prepare('SELECT account_number FROM beneficiaries WHERE id = :id');
        $ben->execute([':id' => $benId]);
        $benRow = $ben->fetch();
        $toId = $benRow ? xfer_resolve_to_account($pdo, (string) $benRow['account_number']) : null;

        // --- transfer.l7 setup: drop the PHP session lock so concurrent ---
        // requests sharing this PHPSESSID can actually overlap. Without this,
        // PHP serialises same-session requests and the race can't be won. This
        // mirrors the real-world "session_write_close() called too early" bug.
        if ($engine->isVulnerable('transfer', 'l7') && class_exists('Session')) {
            Session::unlock();
        }

        // --- transfer.l6: NO `amount > 0` check. Negative amount allowed. ---
        // The only gate is `balance >= amount`, which a negative amount passes.
        // @vuln-start: negative-amount transfer — no amount>0 check (transfer.l6)
        if ((float) $from['balance'] < $amount) {
            return ['ok' => false, 'error' => 'Insufficient balance.', 'txn_id' => null, 'txn_ref' => null, 'new_balance' => null];
        }
        // @vuln-end

        // transfer.l21 daily-limit (per-transaction-only at vuln levels).
        $lim = xfer_check_daily_limit($pdo, $engine, $fromId, $amount, (float) $from['daily_limit']);
        if (!$lim['ok']) {
            return ['ok' => false, 'error' => $lim['error'], 'txn_id' => null, 'txn_ref' => null, 'new_balance' => null];
        }

        // --- transfer.l7 race condition: read-check-write with no lock. ---
        // Level 1 adds a sleep to widen the TOCTOU window so two concurrent
        // requests both reach the UPDATE after both passed the check above.
        // @vuln-start: race condition — non-atomic read-check-write, no row lock (transfer.l7)
        if ($raceLevel === 1) {
            usleep(800000); // 0.8s — makes the double-spend trivially winnable
        }
        // @vuln-end

        // Non-atomic debit: subtracting a negative amount INCREASES the balance
        // (transfer.l6 payoff).
        $newBalance = (float) $from['balance'] - $amount;
        $upd = $pdo->prepare('UPDATE accounts SET balance = :b WHERE id = :id');
        $upd->execute([':b' => $newBalance, ':id' => $fromId]);

        // Credit the internal destination if there is one.
        if ($toId !== null) {
            $pdo->prepare('UPDATE accounts SET balance = balance + :a WHERE id = :id')
                ->execute([':a' => $amount, ':id' => $toId]);
        }

        $ref = xfer_txn_ref($mode);
        $ins = $pdo->prepare(
            'INSERT INTO transactions (from_account_id, to_account_id, amount, mode, status, remarks, txn_ref, otp_verified)
             VALUES (:f, :t, :amt, :mode, "completed", :rem, :ref, 1)'
        );
        $ins->execute([
            ':f' => $fromId, ':t' => $toId, ':amt' => $amount,
            ':mode' => $mode, ':rem' => $remarks, ':ref' => $ref,
        ]);
        $txnId = (int) $pdo->lastInsertId();

        // transfer.l23 self-transfer cashback abuse: pay 1% cashback on every
        // transfer WITHOUT excluding transfers to the user's own accounts. So a
        // user can shuttle money between their own accounts to farm cashback.
        $cashback = xfer_apply_cashback($pdo, $engine, $userId, $fromId, $toId, $amount);

        $result = [
            'ok'          => true,
            'error'       => null,
            'txn_id'      => $txnId,
            'txn_ref'     => $ref,
            'new_balance' => $newBalance + $cashback,
            'cashback'    => $cashback,
        ];

        // CTF (transfer.l1): flag when from_account doesn't belong to the user
        // (IDOR proved) AND transfer.l1 is the active CTF challenge
        // (isCtfActive = ctf_eligible AND ctf_active), keyed to the submitter.
        if ($engine->isCtfActive('transfer', 'l1') && (int) $from['user_id'] !== $userId) {
            $result['ctf_flag'] = Flags::generate('transfer.l1', $userId);
        }

        return $result;
    }

    /**
     * 1% promotional cashback credited to the source account.
     *
     * transfer.l23 (CWE-840): at vuln levels self-transfers (destination owned
     * by the same user) still earn cashback. At the secure level same-owner
     * transfers are excluded.
     */
    function xfer_apply_cashback(PDO $pdo, TrainingEngine $engine, int $userId, int $fromId, ?int $toId, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }
        $selfTransfer = false;
        if ($toId !== null) {
            $own = $pdo->prepare('SELECT 1 FROM accounts WHERE id = :id AND user_id = :u');
            $own->execute([':id' => $toId, ':u' => $userId]);
            $selfTransfer = (bool) $own->fetchColumn();
        }

        if (!$engine->isVulnerable('transfer', 'l23')) {
            // @fix-start: cashback — exclude same-owner (self) transfers (transfer.l23)
            if ($selfTransfer) {
                return 0.0;
            }
            // @fix-end
        }
        // @vuln-start: cashback abuse — self-transfers still earn 1% (transfer.l23)
        $cashback = round($amount * 0.01, 2);
        $pdo->prepare('UPDATE accounts SET balance = balance + :c WHERE id = :id')
            ->execute([':c' => $cashback, ':id' => $fromId]);
        // @vuln-end
        return $cashback;
    }

    /**
     * Daily-transfer-limit check.
     *
     * transfer.l21 (CWE-840): at vuln levels only the PER-TRANSACTION amount is
     * compared to daily_limit, so splitting a large amount into many small
     * transfers (each under the limit) bypasses it. At the secure level the
     * sum of TODAY's transfers from the account is aggregated and enforced.
     *
     * @return array{ok:bool,error:?string}
     */
    function xfer_check_daily_limit(PDO $pdo, TrainingEngine $engine, int $fromId, float $amount, float $dailyLimit): array
    {
        if (!$engine->isVulnerable('transfer', 'l21')) {
            // @fix-start: daily limit — aggregate today's transfers (transfer.l21)
            $s = $pdo->prepare(
                "SELECT COALESCE(SUM(amount),0) FROM transactions
                  WHERE from_account_id = :id AND status = 'completed'
                    AND DATE(created_at) = CURDATE()"
            );
            $s->execute([':id' => $fromId]);
            $todays = (float) $s->fetchColumn();
            if ($todays + $amount > $dailyLimit) {
                return ['ok' => false, 'error' => 'Daily transfer limit exceeded.'];
            }
            return ['ok' => true, 'error' => null];
            // @fix-end
        }
        // @vuln-start: daily limit — per-transaction check only, splittable (transfer.l21)
        if ($amount > $dailyLimit) {
            return ['ok' => false, 'error' => 'Amount exceeds the per-transaction limit.'];
        }
        return ['ok' => true, 'error' => null];
        // @vuln-end
    }

    /**
     * Compute accrued interest on an FD balance.
     *
     * transfer.l20 rounding/salami (CWE-682): at vuln levels floating-point math
     * accumulates sub-paisa error across many accounts/periods (0.1+0.2 != 0.3),
     * which an attacker can harvest. At the secure level integer paisa math is
     * used so the arithmetic is exact.
     */
    function xfer_fd_interest(TrainingEngine $engine, float $principal, float $annualRate, int $days): float
    {
        if (!$engine->isVulnerable('transfer', 'l20')) {
            // @fix-start: interest — exact integer-paisa arithmetic (transfer.l20)
            $paisa  = (int) round($principal * 100);
            $accrue = intdiv((int) round($paisa * $annualRate * $days), 36500);
            return $accrue / 100;
            // @fix-end
        }
        // @vuln-start: interest — lossy floating-point accrual (transfer.l20)
        $daily = $principal * ($annualRate / 100.0) / 365.0;
        $total = 0.0;
        for ($i = 0; $i < $days; $i++) {
            $total += $daily;                  // FP error accumulates each day
        }
        return $total;
        // @vuln-end
    }
}
