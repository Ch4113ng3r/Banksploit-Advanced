<?php

declare(strict_types=1);

require_once __DIR__ . '/Session.php';

/**
 * Auth — authentication, authorization, and JWT issuing.
 *
 * Level-governed behaviour:
 *   auth.g1  (SQLi / weak hash): level 0 concatenated query + MD5 compare;
 *            level 1 naive blacklist + MD5; level 3 prepared + password_verify().
 *   auth.l12 (JWT):              L0 sig unverified · L1 alg=none · L2 case-sensitive
 *                                alg check (None/NONE bypass) · L3 HS256 verified.
 *   auth.l17 (PII in JWT):       level 0 embeds pan/aadhaar/balance; level 3 minimal.
 *   auth.l16 (enumeration):      level 0 distinct messages; level 3 generic.
 */
final class Auth
{
    private PDO $pdo;
    private TrainingEngine $engine;

    /** Last login error message (enumeration-aware). */
    public string $lastError = '';

    /** Per-request cache for the Bearer-JWT-resolved user. */
    private bool $jwtChecked = false;
    /** @var array<string, mixed>|null */
    private ?array $jwtUserCache = null;

    public function __construct(PDO $pdo, TrainingEngine $engine)
    {
        $this->pdo    = $pdo;
        $this->engine = $engine;
    }

    /**
     * Attempt a login by Customer ID. Returns the user row on success and
     * populates the session, or null on failure (with $lastError set).
     *
     * @return array<string, mixed>|null
     */
    public function login(string $customerId, string $password): ?array
    {
        $level = $this->engine->getVulnLevel('auth', 'g1');

        $user = $level >= 3
            ? $this->loginSecure($customerId, $password)
            : $this->loginVulnerable($customerId, $password, $level);

        if ($user !== null) {
            Session::set('user', [
                'id'          => (int) $user['id'],
                'customer_id' => $user['customer_id'],
                'name'        => $user['name'],
                'role'        => $user['role'],
            ]);
            // L14: regenerate id only when fixation is fixed.
            Session::onLogin();
        }

        return $user;
    }

    /**
     * VULNERABLE login (levels 0-2). Concatenated SQL + MD5 comparison.
     * Level 1 adds a naive blacklist that blocks " OR " but not UNION.
     *
     * @return array<string, mixed>|null
     */
    private function loginVulnerable(string $customerId, string $password, int $level): ?array
    {
        $cid = $customerId;
        if ($level >= 1) {
            // Naive blacklist: strips OR-based tautologies, misses UNION.
            $cid = preg_replace('/\bor\b/i', '', $cid) ?? $cid;
        }

        // Single-line concatenated query so an injected `-- ` comment swallows
        // the rest of the clause. Compares the insecure md5 column.
        $sql = "SELECT id, customer_id, name, role FROM users "
             . "WHERE customer_id = '" . $cid . "' "
             . "AND password_md5 = MD5('" . $password . "') LIMIT 1";

        try {
            $stmt = $this->pdo->query($sql);
            $row  = $stmt ? $stmt->fetch() : false;
        } catch (PDOException $e) {
            // core.l18 graduated error disclosure (verbose -> SQLSTATE -> ref -> generic).
            $this->lastError = l18_error($e, 'Login failed.');
            return null;
        }

        if ($row) {
            return $row;
        }
        $this->setAuthError($customerId);
        return null;
    }

    /**
     * SECURE login (level 3). Parameterized lookup + bcrypt verify.
     *
     * @return array<string, mixed>|null
     */
    private function loginSecure(string $customerId, string $password): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, name, role, password_hash
               FROM users WHERE customer_id = :cid LIMIT 1'
        );
        $stmt->execute([':cid' => $customerId]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, (string) $row['password_hash'])) {
            unset($row['password_hash']);
            return $row;
        }
        // Generic message — no enumeration.
        $this->lastError = 'Invalid Customer ID or password.';
        return null;
    }

    /**
     * L16: distinct messages at low levels leak which accounts exist.
     */
    private function setAuthError(string $customerId): void
    {
        if (!$this->engine->isVulnerable('auth', 'l16')) {
            $this->lastError = 'Invalid Customer ID or password.';
            return;
        }
        $exists = $this->pdo->prepare('SELECT 1 FROM users WHERE customer_id = :cid LIMIT 1');
        $exists->execute([':cid' => $customerId]);
        $this->lastError = $exists->fetchColumn()
            ? 'Incorrect password.'
            : 'Customer ID not found.';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCurrentUser(): ?array
    {
        // JWT takes precedence if present (allows JWT exploitation without logout)
        $jwtUser = $this->jwtUser();
        if ($jwtUser !== null) {
            return $jwtUser;
        }
        // Fall back to session if no JWT present
        $u = Session::get('user');
        if (is_array($u)) {
            return $u;
        }
        // No authentication
        return null;
    }

    /**
     * Resolve the current user from an `Authorization: Bearer <jwt>` request
     * (or, for lab convenience, an `access_token` cookie/param). Returns a user
     * row shaped like the session user, or null. Cached per request.
     *
     * @return array<string, mixed>|null
     */
    private function jwtUser(): ?array
    {
        if ($this->jwtChecked) {
            return $this->jwtUserCache;
        }
        $this->jwtChecked = true;

        $token = $this->bearerToken();
        if ($token === null) {
            return null;
        }
        $claims = $this->verifyJWT($token);   // level-governed (alg:none vs HS256)
        if ($claims === null) {
            return null;
        }
        // Honour expiry if present.
        if (isset($claims['exp']) && is_numeric($claims['exp']) && (int) $claims['exp'] < time()) {
            return null;
        }

        $sub  = (string) ($claims['sub'] ?? '');
        $role = (string) ($claims['role'] ?? 'user');   // trusted from token (forgeable at l12)

        // Resolve a numeric id from the customer_id (sub) so ownership-scoped
        // code keeps working. A forged sub that doesn't exist still authenticates
        // (id 0), but real targets (e.g. sub=ADMIN001) resolve to their row.
        $id = 0;
        $name = $sub !== '' ? $sub : 'jwt-user';
        if ($sub !== '') {
            $st = $this->pdo->prepare('SELECT id, name FROM users WHERE customer_id = :c LIMIT 1');
            $st->execute([':c' => $sub]);
            if ($r = $st->fetch()) {
                $id   = (int) $r['id'];
                $name = (string) $r['name'];
            }
        }

        $this->jwtUserCache = [
            'id'          => $id,
            'customer_id' => $sub,
            'name'        => $name,
            'role'        => $role,
            'via'         => 'jwt',
        ];
        return $this->jwtUserCache;
    }

    /**
     * Extract a bearer token from the Authorization header (with fallbacks for
     * SAPIs that strip it), or from an `access_token` cookie/param (lab demo).
     */
    private function bearerToken(): ?string
    {
        $hdr = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $hdr = (string) $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $hdr = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) { $hdr = (string) $v; break; }
            }
        }
        if (stripos($hdr, 'bearer ') === 0) {
            $t = trim(substr($hdr, 7));
            if ($t !== '') {
                return $t;
            }
        }
        // Lab convenience: a token in an `access_token` cookie or request param
        // (so the JWT path is testable purely from the browser / DevTools).
        $alt = (string) ($_COOKIE['access_token'] ?? ($_REQUEST['access_token'] ?? ''));
        return $alt !== '' ? $alt : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->getCurrentUser() !== null;
    }

    public function isAdmin(): bool
    {
        $u = $this->getCurrentUser();
        return $u !== null && ($u['role'] ?? '') === 'admin';
    }

    /**
     * Whether the current auth is via JWT (not a real PHP session).
     * JWT-authenticated users are restricted to read-only operations.
     */
    public function isViaJwt(): bool
    {
        $u = $this->getCurrentUser();
        return $u !== null && ($u['via'] ?? '') === 'jwt';
    }

    /**
     * Redirect to /login unless authenticated.
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            $this->redirect('/login');
        }
    }

    /**
     * Require a real PHP session (not JWT). Use this on any write endpoint
     * (transfers, profile updates, payments, etc.) to block forged-JWT access.
     * Returns a 403 JSON error for API requests or redirects for HTML pages.
     */
    public function requireSession(): void
    {
        $this->requireAuth();
        if ($this->isViaJwt()) {
            // Check if a real session also exists. If it does, trust the session
            // over the JWT for write operations (session + JWT cookie is legitimate).
            $sessionUser = Session::get('user');
            if ($sessionUser !== null) {
                // Real session exists alongside JWT — allow the operation
                return;
            }
            // JWT-only auth (no session) — block write access
            // Detect if this is an API call
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $uri    = $_SERVER['REQUEST_URI'] ?? '';
            if (str_contains($accept, 'application/json') || str_contains($uri, '/api/')) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Session authentication required. JWT tokens are read-only.',
                ]);
                exit;
            }
            $this->redirect('/login');
        }
    }

    /**
     * Redirect unless the current user is an admin. NOTE: admin.l4 (broken
     * function-level auth) means individual admin endpoints may skip this at
     * level 0 — that bypass is exercised in the admin module, not here.
     */
    public function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
        }
    }

    private function redirect(string $path): void
    {
        if (function_exists('redirect')) {
            redirect($path);          // helpers.php — builds absolute URL from APP_URL
        }
        header('Location: ' . (defined('APP_URL') ? APP_URL : '') . $path);
        exit;
    }

    // ----------------------------------------------------------------- JWT --

    /**
     * Issue a JWT for a user payload.
     *   auth.l12: level 0 issues alg=none (unsigned); level 3 HS256.
     *   auth.l17: level 0 embeds PII (pan/aadhaar/balance) in claims.
     *
     * @param array<string, mixed> $payload
     */
    public function generateJWT(array $payload): string
    {
        // The server always issues a correctly HS256-signed token; the auth.l12
        // weakness lives entirely in the VERIFIER (verifyJWT), which is too lenient
        // at vuln levels. Issuing a valid signature keeps legitimate login working
        // at every level.
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $claims = $payload;
        // @fix-start: minimal claims — PII stripped from the token (auth.l17)
        if (!$this->engine->isVulnerable('auth', 'l17')) {
            // Secure: strip PII, keep only minimal claims.
            $claims = array_intersect_key($claims, array_flip(['sub', 'role', 'exp', 'iat']));
        }
        // @fix-end
        // @vuln-start: PII (pan/aadhaar/balance) embedded in JWT at vuln levels (auth.l17)
        // (when l17 is vulnerable the caller's full claims — incl. PII — are kept verbatim)
        // @vuln-end

        $segments = [
            self::b64url((string) json_encode($header)),
            self::b64url((string) json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $sig = hash_hmac('sha256', $signingInput, self::secret(), true);
        return $signingInput . '.' . self::b64url($sig);
    }

    /**
     * Verify a JWT and return its claims, or null if invalid. Graduated (auth.l12):
     *   L0: signature present but HMAC never verified (tamper freely).
     *   L1: alg=none accepted (unsigned) — otherwise HMAC is checked.
     *   L2: only exact-lowercase 'none' rejected; 'None'/'NONE' still accepted unsigned.
     *   L3: HS256 pinned, HMAC verified, exp validated, everything else rejected.
     *
     * @return array<string, mixed>|null
     */
    public function verifyJWT(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;
        $header = json_decode(self::b64urlDecode($h), true);
        $claims = json_decode(self::b64urlDecode($p), true);
        if (!is_array($header) || !is_array($claims)) {
            return null;
        }

        $alg      = (string) ($header['alg'] ?? '');
        $level    = $this->engine->getVulnLevel('auth', 'l12');
        $expected = self::b64url(hash_hmac('sha256', "$h.$p", self::secret(), true));

        // @vuln-start: [L0] the signature segment must merely be present — its HMAC
        // is never checked, so any tampered token with any non-empty signature is
        // accepted (auth.l12)
        if ($level === 0) {
            return $s !== '' ? $claims : null;
        }
        // @vuln-end

        // @vuln-start: [L1] unsigned tokens are explicitly accepted — when alg is
        // none, signature verification is skipped entirely (auth.l12)
        if ($level === 1) {
            if (strtolower($alg) === 'none') {
                return $claims;
            }
            return hash_equals($expected, $s) ? $claims : null;
        }
        // @vuln-end

        // @vuln-start: [L2] inconsistent/case-sensitive algorithm check — only the
        // exact lowercase 'none' is rejected, but the unsigned-accept path uses a
        // case-insensitive compare, so 'None'/'NONE' slip past and are accepted
        // unsigned (auth.l12)
        if ($level === 2) {
            if ($alg === 'none') {
                return null;                       // case-sensitive reject (the bug)
            }
            if (strtolower($alg) === 'none') {
                return $claims;                    // reached by 'None','NONE',… — unsigned accepted
            }
            return hash_equals($expected, $s) ? $claims : null;
        }
        // @vuln-end

        // @fix-start: [L3] pin HS256, verify the HMAC with a constant-time compare,
        // reject every other alg (incl. any-case none), and validate exp (auth.l12)
        if ($alg !== 'HS256') {
            return null;
        }
        if (!hash_equals($expected, $s)) {
            return null;
        }
        if (isset($claims['exp']) && is_numeric($claims['exp']) && time() >= (int) $claims['exp']) {
            return null;
        }
        return $claims;
        // @fix-end
    }

    private static function secret(): string
    {
        return getenv('JWT_SECRET') ?: 'insecure_jwt_secret_for_training';
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
