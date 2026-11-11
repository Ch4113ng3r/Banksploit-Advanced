<?php

declare(strict_types=1);

/**
 * SECURE card operations (level 3).
 *
 * ============================================================================
 *  SECURE CONTROLS (contrast with cards_vuln.php)
 * ----------------------------------------------------------------------------
 *  cards.l24: the PAN is returned MASKED (last 4 only) and the CVV is NEVER
 *      returned. The stored value is treated as sensitive — helper card_encrypt
 *      /card_decrypt use AES-256-CBC so at-rest data could be ciphertext. (The
 *      seed is plaintext; we mask on output regardless.)
 *  - Every operation enforces ownership (WHERE user_id = current user).
 *  - PIN change REQUIRES the correct old PIN.
 *  - Spending-limit changes are validated against a hard ceiling.
 * ============================================================================
 */

if (!function_exists('card_encrypt')) {

    /**
     * AES-256-CBC helpers. Key derived from JWT_SECRET (training only; a real
     * system would use a dedicated KMS-managed key).
     */
    function card_key(): string
    {
        return hash('sha256', getenv('JWT_SECRET') ?: 'insecure_jwt_secret_for_training', true);
    }

    function card_encrypt(string $plain): string
    {
        $iv = random_bytes(16);
        $ct = openssl_encrypt($plain, 'aes-256-cbc', card_key(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ct);
    }

    function card_decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $ct = substr($raw, 16);
        $pt = openssl_decrypt($ct, 'aes-256-cbc', card_key(), OPENSSL_RAW_DATA, $iv);
        return $pt === false ? '' : $pt;
    }

    /**
     * Mask a PAN to its last 4 digits.
     */
    function card_mask(string $pan): string
    {
        $last4 = substr($pan, -4);
        return str_repeat('*', max(0, strlen($pan) - 4)) . $last4;
    }
}

if (!function_exists('cards_list_secure')) {

    /**
     * List the user's cards — masked PAN, no CVV.
     *
     * @return list<array<string,mixed>>
     */
    function cards_list_secure(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare(
            'SELECT id, card_type, card_number_encrypted, expiry_date, spending_limit, is_blocked
               FROM cards WHERE user_id = :u ORDER BY id'
        );
        $s->execute([':u' => $userId]);
        $out = [];
        foreach ($s->fetchAll() as $c) {
            $out[] = [
                'id'             => (int) $c['id'],
                'card_type'      => $c['card_type'],
                'card_number'    => card_mask((string) $c['card_number_encrypted']),
                'expiry_date'    => $c['expiry_date'],
                'spending_limit' => $c['spending_limit'],
                'is_blocked'     => (int) $c['is_blocked'],
                // NOTE: no cvv field at all.
            ];
        }
        return $out;
    }

    /**
     * Fetch one card the user owns — masked, no CVV.
     *
     * @return array<string,mixed>|null
     */
    function cards_get_secure(PDO $pdo, int $userId, int $cardId): ?array
    {
        // @fix-start: ownership-scoped, masked PAN, no CVV (cards.l24)
        $s = $pdo->prepare(
            'SELECT id, card_type, card_number_encrypted, expiry_date, spending_limit, is_blocked
               FROM cards WHERE id = :id AND user_id = :u'
        );
        $s->execute([':id' => $cardId, ':u' => $userId]);
        $c = $s->fetch();
        if (!$c) {
            return null;
        }
        return [
            'id'             => (int) $c['id'],
            'card_type'      => $c['card_type'],
            'card_number'    => card_mask((string) $c['card_number_encrypted']),
            'expiry_date'    => $c['expiry_date'],
            'spending_limit' => $c['spending_limit'],
            'is_blocked'     => (int) $c['is_blocked'],
        ];
        // @fix-end
    }

    /**
     * Block/unblock — only the owner's card.
     *
     * @return bool whether a row matched (ownership check).
     */
    function cards_set_blocked_secure(PDO $pdo, int $userId, int $cardId, bool $blocked): bool
    {
        $st = $pdo->prepare('UPDATE cards SET is_blocked = :b WHERE id = :id AND user_id = :u');
        $st->execute([':b' => $blocked ? 1 : 0, ':id' => $cardId, ':u' => $userId]);
        return $st->rowCount() > 0;
    }

    /**
     * Change PIN — requires the correct old PIN AND ownership.
     *
     * @return array{ok:bool,error:?string}
     */
    function cards_change_pin_secure(PDO $pdo, int $userId, int $cardId, string $oldPin, string $newPin): array
    {
        if (!preg_match('/^[0-9]{4}$/', $newPin)) {
            return ['ok' => false, 'error' => 'PIN must be 4 digits.'];
        }
        $s = $pdo->prepare('SELECT pin_hash FROM cards WHERE id = :id AND user_id = :u');
        $s->execute([':id' => $cardId, ':u' => $userId]);
        $row = $s->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => 'Card not found.'];
        }
        if ((string) $row['pin_hash'] !== md5($oldPin)) {
            return ['ok' => false, 'error' => 'Current PIN is incorrect.'];
        }
        $pdo->prepare('UPDATE cards SET pin_hash = MD5(:p) WHERE id = :id AND user_id = :u')
            ->execute([':p' => $newPin, ':id' => $cardId, ':u' => $userId]);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Set the spending limit — owner only, validated against a ceiling.
     *
     * @return array{ok:bool,error:?string}
     */
    function cards_set_limit_secure(PDO $pdo, int $userId, int $cardId, float $limit): array
    {
        $ceiling = 500000.0;
        if ($limit <= 0 || $limit > $ceiling) {
            return ['ok' => false, 'error' => 'Limit must be between 1 and ' . (int) $ceiling . '.'];
        }
        $st = $pdo->prepare('UPDATE cards SET spending_limit = :l WHERE id = :id AND user_id = :u');
        $st->execute([':l' => $limit, ':id' => $cardId, ':u' => $userId]);
        return ['ok' => $st->rowCount() > 0, 'error' => $st->rowCount() > 0 ? null : 'Card not found.'];
    }
}
