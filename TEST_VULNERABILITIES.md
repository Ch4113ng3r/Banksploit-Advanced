# CTF Flag Modal - Vulnerability Test Checklist

**Date:** 2026-07-26  
**Test Environment:** http://10.141.199.78:9443

---

## Test 1: auth.g1 — SQL Injection (Login Page) ✅

### Steps:
1. Open DevTools (F12) → Console tab (clear any old logs)
2. Go to `/login`
3. Enter:
   - **Customer ID:** `BS100234' or '1'='1`
   - **Password:** `anything`
4. Click "Sign In"

### Expected Behavior:
- [ ] Console shows: "Form submit event fired"
- [ ] Console shows: "Flag found in header: BANKSPLOIT{..."
- [ ] Modal appears with 🚩 FLAG CAPTURED!
- [ ] Flag text displays correctly
- [ ] Copy button is active, OK button is DISABLED
- [ ] Click "Copy Flag" → OK button ENABLES and shows "✅ OK"
- [ ] Click OK → page reloads
- [ ] OTP verification form appears
- [ ] Modal closes and is gone

### Next:
- [ ] Go to `/otp-inbox`, copy the OTP for BS100234
- [ ] Return to login page, enter OTP
- [ ] **Should redirect to Dashboard** (NOT back to login page)
- [ ] Verify logged in as BS100234 in navbar

### Status: ___________

---

## Test 2: auth.l10 — OTP Brute Force (No Rate Limit)

### Steps:
1. Go to `/login`
2. Enter valid credentials:
   - **Customer ID:** `BS100234`
   - **Password:** `password123`
3. Click "Sign In"
4. OTP form appears
5. Go to `/otp-inbox`, find the OTP code (e.g., `1234`)
6. Open DevTools → Console tab
7. Manually test brute force to verify no rate limiting:
   ```javascript
   // Run this in console to test rapid OTP submissions
   for(let i = 0; i <= 9999; i++) {
       const otp = String(i).padStart(4, '0');
       fetch('/login', {
           method: 'POST',
           body: new URLSearchParams({
               otp: otp,
               csrf_token: document.querySelector('input[name="csrf_token"]').value
           })
       }).then(r => {
           if(r.status === 200 && r.headers.get('X-CTF-Flag')) {
               console.log('FLAG FOUND at OTP:', otp);
           }
       });
   }
   ```

### Expected Behavior:
- [ ] Script finds correct OTP without rate limiting
- [ ] Modal appears with flag
- [ ] Console shows: "Flag found in header: BANKSPLOIT{..."
- [ ] Can copy and close modal
- [ ] Redirects to Dashboard successfully

### Status: ___________

---

## Test 3: statements.l1 — IDOR (Account Statements)

### Steps:
1. Go to `/login`
2. Login with:
   - **Customer ID:** `BS100234`
   - **Password:** `password123`
3. Verify OTP
4. Navigate to **Statements** page
5. Check current URL: `http://10.141.199.78:9443/statements?account_id=1` (or similar)
6. Change `account_id=1` to `account_id=4` (another user's account)
7. Open DevTools → Console tab
8. Press Enter to load the new URL

### Expected Behavior:
- [ ] Loads another user's account statements
- [ ] Console shows: "Flag found in header: BANKSPLOIT{..."
- [ ] Modal appears with 🚩 FLAG CAPTURED!
- [ ] Copy button active, OK button disabled
- [ ] After copy, OK button enables
- [ ] Click OK → page reloads showing the IDOR statements
- [ ] Modal closes

### Status: ___________

---

## Test 4: transfer.l1 — IDOR (Fund Transfer)

### Steps:
1. Go to `/login`, login with BS100234 / password123
2. Verify OTP, reach Dashboard
3. Navigate to **Transfer** page
4. Open DevTools (F12) → Console tab
5. In the Transfer form, run this command to modify from_account_id:
   ```javascript
   document.querySelector('input[name="from_account_id"]').value = 4;
   ```
6. Fill in the transfer form (To Account, Amount, etc.) with valid data
7. Click "Submit Transfer"
8. Complete the transfer (may require OTP)

### Expected Behavior:
- [ ] Transfer form allows modification to another account
- [ ] When transfer succeeds:
  - [ ] Console shows: "Flag found in header: BANKSPLOIT{..."
  - [ ] Modal appears with flag
  - [ ] Copy button active, OK disabled
  - [ ] After copy, OK enables
  - [ ] Click OK to continue
  - [ ] Redirects to transfer confirmation/dashboard
- [ ] Modal closes

### Status: ___________

---

## Test 5: auth.g1 (API) — SQL Injection (REST API)

### Steps:
1. Open terminal/PowerShell
2. Run this curl command:
   ```bash
   curl -X POST http://10.141.199.78:9443/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d "{\"customer_id\":\"' OR '1'='1\",\"password\":\"test\"}"
   ```

### Expected Behavior:
- [ ] Response includes `"ctf_flag": "BANKSPLOIT{...}"`
- [ ] In browser, if any tab has the app open:
  - [ ] Modal appears with flag
  - [ ] Console shows: "Flag found in JSON body: BANKSPLOIT{..."

### Status: ___________

---

## Test 6: Transfer form OTP (Bonus)

### Steps:
1. From Test 4, after clicking transfer and reaching OTP verification
2. Open DevTools → Console
3. You should NOT see any interception logs (transfer OTP form should be normal)

### Expected Behavior:
- [ ] Transfer OTP form submits normally
- [ ] No form interceptor interference
- [ ] Redirects properly after OTP verification

### Status: ___________

---

## Summary

| Vulnerability | Test 1 | Test 2 | Test 3 | Test 4 | Test 5 | Test 6 |
|---|---|---|---|---|---|---|
| **auth.g1 (SQLi Login)** | _____ | - | - | - | - | - |
| **auth.l10 (OTP Brute)** | - | _____ | - | - | - | - |
| **statements.l1 (IDOR)** | - | - | _____ | - | - | - |
| **transfer.l1 (IDOR)** | - | - | - | _____ | - | - |
| **auth.g1 (API SQLi)** | - | - | - | - | _____ | - |
| **transfer OTP flow** | - | - | - | - | - | _____ |

---

## Known Test Accounts

```
BS100234 | Rahul Sharma    | password123
BS100235 | Priya Menon     | priya@123
BS100236 | Vikram Singh    | vikram@123
BS100237 | Aditya Rao      | aditya@123
BS100238 | Ankit Kumar     | 1234
ADMIN001 | Admin User      | admin@123
```

---

## Report Format

For each test, report:
- **Status:** ✅ PASS / ❌ FAIL / ⚠️ PARTIAL
- **Issues:** Any errors or unexpected behavior
- **Console Output:** Any relevant console logs
- **Notes:** Additional observations

