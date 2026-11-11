<?php

declare(strict_types=1);

/**
 * VULNERABLE card operations (levels 0-2).
 *
 * ============================================================================
 *  VULNERABILITIES (for the code-viewer feature)
 * ----------------------------------------------------------------------------
 *  cards.l24 Card data in plaintext (CWE-311):
 *      card_number / cvv are stored unencrypted (columns named *_encrypted but
 *      they are NOT). The API returns the FULL number and CVV; only the
 *      frontend masks it, so anyone reading the JSON gets the real PAN+CVV.
 *
 *  (block) IDOR + missing authz (CWE-639): block/unblock acts on any card id
 *      with no ownership check.
 *
 *  (pin) Broken PIN change (CWE-620): the old PIN is NOT required — a new PIN
 *      is accepted outright, so a hijacked session can silently reset it.
 *
 *  (limit) Mass-assignment / no validation (CWE-915): the spending limit is
 *      taken from the request and saved as-is (set it to anything).
 * ============================================================================
 */

if (!function_exists('cards_list_vuln')) {

    /**
     * List the user's cards. cards.l24: full number + CVV included.
     *
     * @return list<array<string,mixed>>
     */
    function cards_list_vuln(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare(
            'SELECT id, user_id, card_number_encrypted AS card_number, card_type,
                    cvv_encrypted AS cvv, expiry_date, spending_limit, is_blocked
               FROM cards WHERE user_id = :u ORDER BY id'
        );
        $s->execute([':u' => $userId]);
        return $s->fetchAll();
    }

    /**
     * Fetch one card. cards.l24 IDOR: NO ownership check — any id works, and
     * the full PAN + CVV are returned in plaintext.
     *
     * @return array<string,mixed>|null
     */
    function cards_get_vuln(PDO $pdo, int $cardId): ?array
    {
        // @vuln-start: plaintext PAN+CVV returned, no ownership check (cards.l24)
        $s = $pdo->prepare(
            'SELECT id, user_id, card_number_encrypted AS card_number, card_type,
                    cvv_encrypted AS cvv, expiry_date, spending_limit, is_blocked
               FROM cards WHERE id = :id'
        );
        $s->execute([':id' => $cardId]);
        $row = $s->fetch();
        return $row ?: null;
        // @vuln-end
    }

    /**
     * Block/unblock a card. IDOR: no ownership check, any card id.
     */
    function cards_set_blocked_vuln(PDO $pdo, int $cardId, bool $blocked): void
    {
        $pdo->prepare('UPDATE cards SET is_blocked = :b WHERE id = :id')
            ->execute([':b' => $blocked ? 1 : 0, ':id' => $cardId]);
    }

    /**
     * Change PIN. Broken: the old PIN is never checked. Any card id (IDOR).
     */
    function cards_change_pin_vuln(PDO $pdo, int $cardId, string $newPin): void
    {
        // Stored as md5 to match the seed (insecure hash, but the real flaw
        // here is that the OLD pin is not required).
        $pdo->prepare('UPDATE cards SET pin_hash = MD5(:p) WHERE id = :id')
            ->execute([':p' => $newPin, ':id' => $cardId]);
    }

    /**
     * Set the spending limit. Mass-assignment: trusts the client value, no
     * ceiling, no ownership check (IDOR).
     */
    function cards_set_limit_vuln(PDO $pdo, int $cardId, float $limit): void
    {
        $pdo->prepare('UPDATE cards SET spending_limit = :l WHERE id = :id')
            ->execute([':l' => $limit, ':id' => $cardId]);
    }
}
