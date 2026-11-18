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

if (!function_exists('xfer_resolve_from_ref')) {

    /** Current configured level of the from-account IDOR (transfer.l1). */
    function xfer_l1_level(TrainingEngine $engine): int
    {
        return $engine->getVulnLevel('transfer', 'l1');
    }

    /** Server-only secret for deriving account handles (reuses the flag secret). */
    function xfer_ref_secret(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        $file = $home . DIRECTORY_SEPARATOR . '.banksploit' . DIRECTORY_SEPARATOR . 'flag_secret';
        $val  = is_file($file) ? trim((string) file_get_contents($file)) : '';
        return $val !== '' ? $val : 'banksploit-dev-secret';
    }

    /**
     * [L1] Keyed HMAC of an account NUMBER. The secret stays server-side, so a
     * client cannot forge a token for an arbitrary account. The L1 flaw is that
     * the SAME scheme signs both the From and the To selectors, and the To
     * (beneficiary) dropdown carries valid tokens for accounts the caller does
     * NOT own — so a token legitimately usable as a destination can be replayed
     * as a source. (transfer.l1)
     */
    function xfer_hmac_token(string $accountNumber): string
    {
        return hash_hmac('sha256', 'ref:' . $accountNumber, xfer_ref_secret());
    }

    /**
     * [L2] Client-side AES of the account number. The key/IV/algorithm below are
     * ALSO shipped to the browser in /public/vendor/transfer-ref.js, so this is
     * obscurity, not security: an attacker reads the key from the JS and forges
     * the ciphertext for any account. aes-256-cbc / PKCS7, base64(raw) — matches
     * CryptoJS.AES.encrypt(num, Hex(KEY), {iv:Hex(IV), mode:CBC, padding:Pkcs7}).
     * (transfer.l1)
     */
    function xfer_aes_key(): string
    {
        return (string) hex2bin('5f4dcc3b5aa765d61d8327deb882cf99a1b2c3d4e5f6071829aabbccddeeff00');
    }

    function xfer_aes_iv(): string
    {
        return (string) hex2bin('0123456789abcdef0123456789abcdef');
    }

    function xfer_aes_decrypt(string $token): string
    {
        $raw = base64_decode($token, true);
        if ($raw === false) {
            return '';
        }
        $pt = openssl_decrypt($raw, 'aes-256-cbc', xfer_aes_key(), OPENSSL_RAW_DATA, xfer_aes_iv());
        return $pt === false ? '' : $pt;
    }

    /**
     * Decode the submitted "From account" reference to an account number. Each
     * vulnerable level only changes HOW the source account is referenced; none
     * of them verifies the account belongs to the caller — that ownership check
     * is the L3 fix, applied in the execute path. Returns '' for an invalid ref.
     *
     * $sig is the L1 client-supplied HMAC of the plain number ($ref).
     */
    function xfer_resolve_from_ref(PDO $pdo, TrainingEngine $engine, string $ref, string $sig = ''): string
    {
        $level = xfer_l1_level($engine);

        // @vuln-start: [L0] source account referenced by its plain number (transfer.l1)
        if ($level <= 0 || $level >= 3) {
            // L0: raw number used as-is. L3: still the raw number, but the execute
            // path enforces ownership, so the IDOR is refused there.
            return $ref;
        }
        // @vuln-end

        // @vuln-start: [L1] the client sends the plain number AND its HMAC signature.
        // The server recomputes hash_hmac(number) and compares — O(1), no table scan,
        // the realistic pattern. But it treats a valid signature as authorisation and
        // NEVER checks ownership, so any (number, valid-sig) pair is accepted — and
        // valid pairs for accounts the caller doesn't own leak in the page JS
        // (the payee/dstSig table). (transfer.l1)
        if ($level === 1) {
            if ($ref !== '' && $sig !== '' && hash_equals(xfer_hmac_token($ref), $sig)) {
                return $ref;
            }
            return '';
        }
        // @vuln-end

        // @vuln-start: [L2] reference is AES ciphertext whose key/IV ship in client
        // JS — forgeable by the client, and ownership is never checked (transfer.l1)
        return xfer_aes_decrypt($ref);
        // @vuln-end
    }

    /**
     * Render the "From account" selector per level. L0/L3: option value is the
     * plain account number. L1: a cosmetic selector whose choice client JS
     * encodes into a hidden token. L2: option value is the account's handle.
     */
    function xfer_from_field_html(array $accounts, int $level): string
    {
        $label = '<label class="block text-xs text-slate-400 mb-1">From account</label>';
        $cls   = 'w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3';
        $optText = static function (array $a): string {
            return ucfirst((string) $a['account_type']) . ' · ' . $a['account_number']
                 . ' (₹' . number_format((float) $a['balance'], 2) . ')';
        };

        if ($level === 1) {
            // @vuln-start: [L1] the signed token is NOT in the option value. The
            // dropdown carries the plain account number; an inline script maps the
            // selected number to its HMAC in a hidden field (from_account_ref).
            // Editing the number L0-style makes the lookup miss and fall back to a
            // DEFAULT (your own first account), so the naive tamper transfers from
            // your own account — no exploit. The signed tokens live only in JS, and
            // the payee table (rendered by the To selector, in a separate block) is
            // signed with the SAME scheme, so the real attack is: watch the wire,
            // find the token in the JS, locate the payee table and inject a
            // victim's token. (transfer.l1)
            $opts = '';
            $sig  = [];
            foreach ($accounts as $a) {
                $num = (string) $a['account_number'];
                $opts .= '<option value="' . htmlspecialchars($num) . '">' . htmlspecialchars($optText($a)) . '</option>';
                $sig[$num] = xfer_hmac_token($num);
            }
            $sigJson = json_encode($sig, JSON_UNESCAPED_SLASHES);
            return $label
                . '<select id="fromSel" class="' . $cls . '">' . $opts . '</select>'
                . '<input type="hidden" name="from_account_ref" id="fromRef">'
                . '<input type="hidden" name="from_account_sig" id="fromSig">'
                . '<script>(function(){'
                . 'var srcSig=' . $sigJson . ';'
                . 'function signFrom(){var v=document.getElementById("fromSel").value;'
                . 'document.getElementById("fromRef").value=v;'
                . 'document.getElementById("fromSig").value=srcSig[v]||"";}'
                . 'document.getElementById("fromSel").addEventListener("change",signFrom);signFrom();'
                . '})();</script>';
            // @vuln-end
        }

        if ($level === 2) {
            // @vuln-start: [L2] the dropdown carries plain account numbers; on
            // selection an inline script calls XferRef.encryptRef(number) (from
            // transfer-ref.js) and puts the AES ciphertext in a hidden
            // from_account_ref. The script only encrypts numbers on the user's own
            // allow-list, so editing a number L0-style yields an empty ref that the
            // server rejects. But the key/IV ship in transfer-ref.js, so an attacker
            // calls XferRef.encryptRef('<victim>') themselves; the server decrypts and
            // never checks ownership. (transfer.l1)
            $opts = '';
            $own  = [];
            foreach ($accounts as $a) {
                $num = (string) $a['account_number'];
                $opts .= '<option value="' . htmlspecialchars($num) . '">' . htmlspecialchars($optText($a)) . '</option>';
                $own[] = $num;
            }
            $ownJson = json_encode($own, JSON_UNESCAPED_SLASHES);
            return $label
                . '<select id="fromSel" class="' . $cls . '">' . $opts . '</select>'
                . '<input type="hidden" name="from_account_ref" id="fromRef">'
                . '<script src="/public/vendor/crypto-js.min.js"></script>'
                . '<script src="/public/vendor/transfer-ref.js"></script>'
                . '<script>(function(){'
                . 'var myAccounts=' . $ownJson . ';'
                . 'function signFrom(){var n=document.getElementById("fromSel").value;'
                . 'document.getElementById("fromRef").value=myAccounts.indexOf(n)>=0?XferRef.encryptRef(n):"";}'
                . 'document.getElementById("fromSel").addEventListener("change",signFrom);signFrom();'
                . '})();</script>';
            // @vuln-end
        }

        // L0 and L3: plain account number as the reference.
        $opts = '';
        foreach ($accounts as $a) {
            $opts .= '<option value="' . htmlspecialchars((string) $a['account_number']) . '">'
                   . htmlspecialchars($optText($a)) . '</option>';
        }
        return $label . '<select name="from_account_ref" class="' . $cls . '">' . $opts . '</select>';
    }

    /**
     * Render the "To account" (beneficiary) selector. Only L1 signs the option
     * values with the same HMAC scheme as the From selector — which is exactly
     * what leaks valid tokens for accounts the user does not own. Every other
     * level uses the plain beneficiary number. (transfer.l1)
     */
    function xfer_to_field_html(array $beneficiaries, int $level): string
    {
        $label = '<label class="block text-xs text-slate-400 mb-1">To account</label>';
        $cls   = 'w-full rounded-lg bg-slate-950 ring-1 ring-slate-700 px-3 py-2 text-slate-100 mb-3';

        if ($level === 1) {
            // @vuln-start: [L1] like the From selector, the destination submits the
            // plain number (to_account_number) AND its HMAC (to_account_sig) from a
            // SEPARATE inline script (dstSig). This payee table is the leak: it holds
            // valid (number, signature) pairs for accounts the user does not own,
            // signed with the same scheme as the source — so a pair lifted from here
            // is accepted as a "from" reference. Kept in its own block (not next to
            // srcSig) so the reuse is not obvious. (transfer.l1)
            $opts = '';
            $sig  = [];
            foreach ($beneficiaries as $b) {
                $num = (string) $b['account_number'];
                $opts .= '<option value="' . htmlspecialchars($num) . '">'
                       . htmlspecialchars($b['name'] . ' · ' . $num . ' · ' . $b['ifsc_code']) . '</option>';
                $sig[$num] = xfer_hmac_token($num);
            }
            $sigJson = json_encode($sig, JSON_UNESCAPED_SLASHES);
            return $label
                . '<select id="toSel" class="' . $cls . '">' . $opts . '</select>'
                . '<input type="hidden" name="to_account_number" id="toRef">'
                . '<input type="hidden" name="to_account_sig" id="toSig">'
                . '<script>(function(){'
                . 'var dstSig=' . $sigJson . ';'
                . 'function signTo(){var v=document.getElementById("toSel").value;'
                . 'document.getElementById("toRef").value=v;'
                . 'document.getElementById("toSig").value=dstSig[v]||"";}'
                . 'document.getElementById("toSel").addEventListener("change",signTo);signTo();'
                . '})();</script>';
            // @vuln-end
        }

        // L0/L2/L3: plain beneficiary number as the destination reference.
        $opts = '';
        foreach ($beneficiaries as $b) {
            $num = (string) $b['account_number'];
            $opts .= '<option value="' . htmlspecialchars($num) . '">'
                   . htmlspecialchars($b['name'] . ' · ' . $num . ' · ' . $b['ifsc_code']) . '</option>';
        }
        return $label . '<select name="to_account_number" class="' . $cls . '">' . $opts . '</select>';
    }

    /**
     * Resolve the submitted "To account" reference to an account number. At L1 the
     * client sends the plain number plus its HMAC signature; the server verifies
     * hash_hmac(number) === sig in O(1) (no table scan). Every other level passes
     * the plain number through. (transfer.l1)
     */
    function xfer_resolve_to_ref(TrainingEngine $engine, string $ref, string $sig = ''): string
    {
        if (xfer_l1_level($engine) !== 1) {
            return $ref;
        }
        // @vuln-start: [L1] destination verified by recomputing the HMAC (transfer.l1)
        if ($ref !== '' && $sig !== '' && hash_equals(xfer_hmac_token($ref), $sig)) {
            return $ref;
        }
        // @vuln-end
        return '';
    }
}
