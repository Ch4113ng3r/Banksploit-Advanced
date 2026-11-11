<?php

declare(strict_types=1);

/**
 * Shared auth primitives used by BOTH the HTML handlers and the JSON API, so
 * the vulnerable/secure behaviour lives in exactly one place per concern:
 *
 *   - OTP generation/verification        (auth.l10 brute-force, auth.l11 leak)
 *   - Password-reset token issue/verify  (auth.l13 predictable tokens)
 *   - KYC file upload                     (loans.g7 unrestricted upload)
 *
 * Each function takes the TrainingEngine so it can branch on the live level.
 * Bootstrap (core classes, session, registry) has already run in index.php.
 */

if (!function_exists('auth_generate_otp')) {

    /**
     * Create a 4-digit OTP for a user/purpose and store it in otp_tokens.
     *
     * VULN NOTE (auth.l10): the OTP space is only 10^4 and nothing here rate
     * limits verification — see auth_verify_otp(). The token is also valid for a
     * full 24 hours, so a guess stays acceptable for the whole window. At a
     * secure level the digit count/lifetime matter less because verification is
     * locked down instead.
     *
     * @param string $purpose 'login' | 'transfer' | 'reset'
     */
    function auth_generate_otp(PDO $pdo, int $userId, string $purpose): string
    {
        // auth.l11 L1 (predictable OTP): for the LOGIN OTP, derive the code from
        // the token's own id, which is then leaked to the client as the "seed".
        // Insert first to get the auto-increment id, then set code = derive(id) —
        // so the leaked id always recomputes the exact OTP (no session state).
        if ($purpose === 'login' && engine()->getVulnLevel('auth', 'l11') === 1) {
            $pdo->prepare(
                'INSERT INTO otp_tokens (user_id, otp_code, purpose, expires_at, is_used)
                 VALUES (:uid, :tmp, :purpose, DATE_ADD(NOW(), INTERVAL 24 HOUR), 0)'
            )->execute([':uid' => $userId, ':tmp' => '0000', ':purpose' => $purpose]);
            $id   = (int) $pdo->lastInsertId();
            $code = auth_l11_derive_otp((string) $id, (string) $userId);
            $pdo->prepare('UPDATE otp_tokens SET otp_code = :c WHERE id = :id')
                ->execute([':c' => $code, ':id' => $id]);
            return $code;
        }

        // random_int is CSPRNG; predictability is NOT the OTP weakness at the
        // other levels (the weakness is leaking it / its hash to the client).
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare(
            'INSERT INTO otp_tokens (user_id, otp_code, purpose, expires_at, is_used)
             VALUES (:uid, :code, :purpose, DATE_ADD(NOW(), INTERVAL 24 HOUR), 0)'
        );
        $stmt->execute([':uid' => $userId, ':code' => $code, ':purpose' => $purpose]);
        return $code;
    }

    /**
     * Deterministically derive a 4-digit OTP from the token seed + the user id
     * (auth.l11 L1): take the first 8 hex chars (4 bytes) of sha256(seed . uid),
     * read them as an integer, and keep the last 4 digits (mod 10000). Inputs are
     * concatenated as decimal strings with no separator. No bignum needed —
     * trivially reproducible anywhere:
     *   parseInt(sha256hex(seed + uid).slice(0, 8), 16) % 10000   (JS)
     *   int(sha256hex(seed + uid)[:8], 16) % 10000                (Python)
     */
    function auth_l11_derive_otp(string $seed, string $uid): string
    {
        $hex = substr(hash('sha256', $seed . $uid), 0, 8);
        return str_pad((string) (hexdec($hex) % 10000), 4, '0', STR_PAD_LEFT);
    }

    /** The leaked "seed" for auth.l11 L1 = the token id whose code is this OTP. */
    function auth_l11_seed_for(int $userId, string $code): string
    {
        $stmt = db()->prepare(
            'SELECT id FROM otp_tokens
              WHERE user_id = :u AND otp_code = :c AND purpose = :p
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':u' => $userId, ':c' => $code, ':p' => 'login']);
        $id = $stmt->fetchColumn();
        return $id === false ? '' : (string) $id;
    }

    /**
     * Seal the flag with the OTP so it cannot be read without first recovering
     * the OTP: base64( flag XOR keystream ), keystream = sha256(otp) (extended if
     * needed). Reversible in the browser once the OTP is known.
     */
    function auth_l11_seal_flag(string $flag, string $otp): string
    {
        $ks = hash('sha256', $otp, true);
        while (strlen($ks) < strlen($flag)) {
            $ks .= hash('sha256', $ks, true);
        }
        return base64_encode($flag ^ substr($ks, 0, strlen($flag)));
    }

    /**
     * Whether the OTP code should be exposed to the client.
     *
     * VULN (auth.l11 — OTP in response body):
     *   level 0 -> returned at the top level of the response ("otp").
     *   level 1 -> still returned but nested deeper ("debug.sms.otp").
     *   level 2+ -> never returned (the secure behaviour).
     *
     * Returns the JSON fragment to merge into a response, or [] when hidden.
     *
     * @return array<string, mixed>
     */
    function auth_otp_leak_payload(TrainingEngine $engine, string $code, int $userId = 0): array
    {
        $level = $engine->getVulnLevel('auth', 'l11');
        // When HINTS are enabled we embed short "lighter nudge" breadcrumbs
        // (_algo / _flag_algo) that name the mechanism so players understand the
        // issue. With hints off (Training/Pentest default) the leak carries the raw
        // data only. Gating on the hints toggle (not an exact mode match) keeps this
        // in step with the participant hint banner and robust to other toggles.
        $ctf = is_feature_enabled('hints');

        // Plaintext flag for this user (null when flag delivery is off, e.g. Pentest).
        $flag = ($userId > 0 && flags_delivered())
            ? Flags::generate('auth.l11', $userId)
            : null;
        // At L1/L2 the flag is NOT handed over in plaintext: it is sealed with the
        // OTP, so you must FIRST recover the OTP (via this level's technique) and
        // then decrypt it. Same unseal step at both levels; only the OTP recovery
        // differs. (L0 exposes everything, so it ships the plaintext flag instead.)
        $flagEnc = $flag !== null ? auth_l11_seal_flag($flag, $code) : null;
        $seal = static function (array $p) use ($flagEnc, $ctf): array {
            if ($flagEnc !== null) {
                $p['_flag_enc'] = $flagEnc;
                if ($ctf) {
                    $p['_flag_algo'] = 'flag = base64decode(_flag_enc) xor sha256(otp)';
                }
            }
            return $p;
        };

        // @vuln-start: [L0] OTP + flag both returned to the client in plaintext (auth.l11)
        if ($level === 0) {
            $p = ['_otp' => $code];
            if ($flag !== null) { $p['_flag'] = $flag; }
            return $p;
        }
        // @vuln-end
        // @vuln-start: [L1] OTP omitted; the token id (_seed) and user id (_uid) it is derived from are leaked — recompute uint32(sha256(seed.uid)) % 10000 (auth.l11)
        if ($level === 1) {
            $p = ['_seed' => auth_l11_seed_for($userId, $code), '_uid' => $userId];
            if ($ctf) { $p['_algo'] = 'otp = parseInt(sha256(_seed + _uid)[:8], 16) % 10000'; }
            return $seal($p);
        }
        // @vuln-end
        // @vuln-start: [L2] OTP + flag are NOT in the JS body — they ship in RESPONSE
        // HEADERS (X-Otp-Check = sha256(otp) to brute-force, X-Flag-Enc = sealed flag,
        // set by auth_otp_leak_headers). The body keeps only breadcrumbs pointing at
        // the headers; the recovery mechanism (brute-force the 4 digits) is unchanged (auth.l11)
        if ($level === 2) {
            $p = [];
            if ($ctf) {
                $p['_algo']      = 'X-Otp-Check = sha256(otp)';
                $p['_flag_algo'] = 'flag = base64decode(X-Flag-Enc) xor sha256(otp)';
            }
            return $p;
        }
        // @vuln-end
        // @fix-start: nothing OTP-related returned; verification is server-side only (auth.l11)
        return [];
        // @fix-end
    }

    /**
     * auth.l11 L2 delivers the leak in RESPONSE HEADERS instead of the JS body:
     *   X-Otp-Check: sha256(otp)                   — brute-force the 4 digits
     *   X-Flag-Enc:  base64(flag XOR sha256(otp))  — the sealed flag
     * Returns the header map (empty unless level 2). The breadcrumbs that point at
     * these headers live in the body (auth_otp_leak_payload); only the delivery
     * channel changed from the old body-based L2, not the recovery mechanism.
     *
     * @return array<string, string>
     */
    function auth_otp_leak_headers(TrainingEngine $engine, string $code, int $userId = 0): array
    {
        if ($engine->getVulnLevel('auth', 'l11') !== 2) {
            return [];
        }
        $h = ['X-Otp-Check' => hash('sha256', $code)];
        if ($userId > 0 && flags_delivered()) {
            $h['X-Flag-Enc'] = auth_l11_seal_flag(Flags::generate('auth.l11', $userId), $code);
        }
        return $h;
    }

    /**
     * Verify an OTP for a user/purpose.
     *
     * VULN (auth.l10 — OTP brute force): at level 0-2 there is no per-user
     * attempt counter or lockout, so an attacker can try all 10^4 codes. At
     * level 3 we cap attempts via failed_attempts + a short lock window.
     *
     * NOTE: a correct OTP is NOT consumed — the token is left valid (is_used
     * stays 0) so the same code can be used repeatedly until it expires. There
     * is no per-OTP use-count limit in the schema, so none is enforced.
     */
    function auth_verify_otp(PDO $pdo, TrainingEngine $engine, int $userId, string $code, string $purpose): bool
    {
        $secure = !$engine->isVulnerable('auth', 'l10');

        // @vuln-start: OTP brute-force — no rate limit at vuln levels (auth.l10)
        // @fix-start: OTP brute-force — lockout after repeated failures (auth.l10)
        if ($secure) {
            // Rate-limit OTP verification the same way as login (shared counter).
            if (auth_is_locked($pdo, $userId)) {
                return false;
            }
        }
        // @vuln-end
        // @fix-end

        $stmt = $pdo->prepare(
            'SELECT id FROM otp_tokens
              WHERE user_id = :uid AND purpose = :p AND otp_code = :c
                AND is_used = 0 AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':p' => $purpose, ':c' => $code]);
        $tokenId = $stmt->fetchColumn();

        if ($tokenId === false) {
            if ($secure) {
                auth_register_failure($pdo, $userId);
            }
            return false;
        }

        // Intentionally NOT consumed: leave is_used = 0 so a correct OTP stays
        // valid for repeated use until it expires. There is no per-OTP use-count
        // limit in the schema, so none is enforced.

        if ($secure) {
            auth_reset_failures($pdo, $userId);
        }
        return true;
    }

    // ----------------------------------------------------- reset tokens ----

    /**
     * Issue a password-reset token for a user.
     *
     * VULN (auth.l13 — predictable reset tokens):
     *   level 0 -> base64(user_id + ':' + unix-timestamp). Fully guessable.
     *   level 1 -> base64 of the same but with a tiny static "salt" (still
     *              reversible/predictable; demonstrates a naive fix).
     *   level 2+ -> cryptographically random (the secure behaviour).
     */
    function auth_make_reset_token(PDO $pdo, TrainingEngine $engine, int $userId): string
    {
        $level = $engine->getVulnLevel('auth', 'l13');

        // @vuln-start: predictable reset token (auth.l13)
        if ($level === 0) {
            $token = base64_encode($userId . ':' . time());
        } elseif ($level === 1) {
            $token = base64_encode('bs:' . $userId . ':' . time());
        // @vuln-end
        // @fix-start: cryptographically-random reset token (auth.l13)
        } else {
            $token = bin2hex(random_bytes(32));
        }
        // @fix-end

        $pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token, expires_at, is_used)
             VALUES (:uid, :tok, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 0)'
        )->execute([':uid' => $userId, ':tok' => $token]);

        return $token;
    }

    /**
     * Resolve a reset token to a user id (and consume it), or null.
     */
    function auth_consume_reset_token(PDO $pdo, string $token): ?int
    {
        $stmt = $pdo->prepare(
            'SELECT id, user_id FROM password_reset_tokens
              WHERE token = :tok AND is_used = 0 AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':tok' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $pdo->prepare('UPDATE password_reset_tokens SET is_used = 1 WHERE id = :id')
            ->execute([':id' => (int) $row['id']]);
        return (int) $row['user_id'];
    }

    // -------------------------------------------------- rate limiting ------

    /**
     * Whether a user is currently locked out (secure rate limiting).
     */
    function auth_is_locked(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT (is_locked = 1 AND (locked_until IS NULL OR locked_until > NOW())) AS locked
               FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Record a failed attempt; lock for 15 minutes after 5 failures.
     */
    function auth_register_failure(PDO $pdo, int $userId): void
    {
        $pdo->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = :id')
            ->execute([':id' => $userId]);
        $pdo->prepare(
            'UPDATE users
                SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
              WHERE id = :id AND failed_attempts >= 5'
        )->execute([':id' => $userId]);
    }

    /**
     * Clear the failure counter / lock on success.
     */
    function auth_reset_failures(PDO $pdo, int $userId): void
    {
        $pdo->prepare('UPDATE users SET failed_attempts = 0, is_locked = 0, locked_until = NULL WHERE id = :id')
            ->execute([':id' => $userId]);
    }

    // -------------------------------------------------- KYC upload ---------

    /**
     * Handle a KYC document upload (loans.g7 — unrestricted file upload).
     *
     *   level 0 -> no checks at all; any extension saved to web-served uploads/.
     *   level 1 -> CLIENT-side .jpg check only (the server still saves anything;
     *              the "check" is just an accept= attr on the form, bypassable).
     *   level 2 -> server-side extension allow-list (jpg/png/pdf).
     *   level 3 -> extension allow-list + MIME sniff + random name, stored
     *              outside the web root.
     *
     * @param array<string, mixed> $file  An entry from $_FILES.
     * @return array{ok:bool,message:string,path?:string}
     */
    function auth_handle_kyc_upload(TrainingEngine $engine, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'message' => 'No document uploaded.'];
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Upload failed.'];
        }

        $level    = $engine->getVulnLevel('loans', 'g7');
        $name     = (string) ($file['name'] ?? 'document');
        $tmp      = (string) ($file['tmp_name'] ?? '');
        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'pdf'];

        // Web-served uploads dir (vulnerable target for a webshell at low levels).
        $webDir = __DIR__ . '/../../uploads';
        if (!is_dir($webDir)) {
            @mkdir($webDir, 0777, true);
        }

        if ($level >= 2) {
            // Server-side extension allow-list.
            if (!in_array($ext, $allowed, true)) {
                return ['ok' => false, 'message' => 'Only JPG, PNG or PDF documents are allowed.'];
            }
            if ($level >= 3) {
                // MIME sniff + random name + store OUTSIDE the web root.
                $mime = function_exists('mime_content_type') ? (string) mime_content_type($tmp) : '';
                $okMime = ['image/jpeg', 'image/png', 'application/pdf'];
                if (!in_array($mime, $okMime, true)) {
                    return ['ok' => false, 'message' => 'File content does not match an allowed type.'];
                }
                $safeDir = '/var/kyc';            // not under /var/www/html
                if (!is_dir($safeDir)) {
                    @mkdir($safeDir, 0700, true);
                }
                $dest = $safeDir . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
                @move_uploaded_file($tmp, $dest);
                return ['ok' => true, 'message' => 'Document stored securely.', 'path' => $dest];
            }
            // level 2: keep original name in uploads/ but extension is checked.
            $dest = $webDir . '/' . basename($name);
            @move_uploaded_file($tmp, $dest);
            return ['ok' => true, 'message' => 'Document uploaded.', 'path' => $dest];
        }

        // level 0/1: NO server-side check. Saves whatever was sent (e.g. .php)
        // into the web-served uploads/ directory -> potential webshell/RCE.
        $dest = $webDir . '/' . basename($name);
        @move_uploaded_file($tmp, $dest);
        $note = $level === 1
            ? ' (level 1: only a client-side .jpg filter, trivially bypassed)'
            : '';
        return ['ok' => true, 'message' => 'Document uploaded.' . $note, 'path' => $dest];
    }

    // -------------------------------------------------- security questions --

    /**
     * A user's 5 security questions (position + text only, no answers).
     *
     * @return list<array<string,mixed>>
     */
    function auth_security_questions(PDO $pdo, int $userId): array
    {
        $s = $pdo->prepare('SELECT position, question FROM security_questions WHERE user_id = :u ORDER BY position');
        $s->execute([':u' => $userId]);
        return $s->fetchAll();
    }

    /**
     * Verify ALL of a user's security answers (case-insensitive, trimmed).
     *
     * @param array<int,string> $answers  position => answer
     */
    function auth_check_security_answers(PDO $pdo, int $userId, array $answers): bool
    {
        $s = $pdo->prepare('SELECT position, answer FROM security_questions WHERE user_id = :u');
        $s->execute([':u' => $userId]);
        $rows = $s->fetchAll();
        if (count($rows) === 0) {
            return false;
        }
        foreach ($rows as $r) {
            $given = strtolower(trim((string) ($answers[(int) $r['position']] ?? '')));
            $want  = strtolower(trim((string) $r['answer']));
            if ($given === '' || $given !== $want) {
                return false;
            }
        }
        return true;
    }

    // -------------------------------------------------- reset-token helpers -

    /** URL-safe base64. */
    function bs_b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function bs_b64url_decode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * The leaked reset link's token (auth.l13): base64url(customer_id:md5(email)).
     * Predictable — derivable from a known email — and emitted in an HTML comment
     * rather than on screen.
     */
    function auth_reset_blob(string $customerId, string $email): string
    {
        return bs_b64url_encode($customerId . ':' . md5($email));
    }

    /**
     * Resolve a reset-password token to a user id.
     *   - auth.l13 vuln levels: a predictable base64url(cid:md5(email)) blob is
     *     accepted (forgeable from a known email).
     *   - Always falls back to a real stored token (auth_consume_reset_token).
     */
    function auth_resolve_reset_token(PDO $pdo, TrainingEngine $engine, string $token): ?int
    {
        if ($engine->isVulnerable('auth', 'l13')) {
            // @vuln-start: predictable/forgeable reset token accepted (auth.l13)
            $decoded = bs_b64url_decode($token);
            if (preg_match('/^(.+):([a-f0-9]{32})$/', $decoded, $m)) {
                $st = $pdo->prepare('SELECT id, email FROM users WHERE customer_id = :c LIMIT 1');
                $st->execute([':c' => $m[1]]);
                if (($row = $st->fetch()) && md5((string) $row['email']) === $m[2]) {
                    return (int) $row['id'];
                }
            }
            // @vuln-end
        }
        // @fix-start: only a real, stored, random token is accepted (auth.l13)
        return auth_consume_reset_token($pdo, $token);
        // @fix-end
    }
}
