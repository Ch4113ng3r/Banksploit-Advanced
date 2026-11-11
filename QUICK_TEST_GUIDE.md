# Quick Test Guide - After Fixes

**Date:** 2026-07-26

---

## Test 1: statements.l1 IDOR with account_id

**Step 1: Login**
- URL: http://localhost:9443/login
- Username: BS100234
- Password: password123
- Click "Sign In"

**Step 2: Verify OTP**
- Go to /otp-inbox to see OTP
- Enter OTP in form
- Click "Verify"

**Step 3: Access IDOR**
- Click "Statements" in sidebar
- URL shows: `/statements?account_id=1`
- **MODIFY URL TO:** `/statements?account_id=4`
- Press Enter

**Expected:**
```
✅ Modal popup appears with "🚩 FLAG CAPTURED!"
✅ Flag text visible (BANKSPLOIT{statements.l1-...})
✅ Copy button enabled
✅ OK button disabled
✅ Click Copy → OK button becomes enabled
✅ Click OK → Modal closes
✅ Page shows statements from account_id=4 (other user)
```

**Verify in Console (F12):**
```javascript
console.log  // Should show:
// "Flag found in header: BANKSPLOIT{statements.l1-...}"
```

---

## Test 2: transfer.l1 IDOR with from_account_id

**Step 1: Login & OTP (same as above)**

**Step 2: Go to Transfer**
- Click "Transfer" in sidebar
- Fill form:
  - Beneficiary: Select any (e.g., "John Doe")
  - Amount: 1000
  - Mode: IMPS
- Click "Continue"

**Step 3: Confirm Transfer**
- Review summary
- Click "Confirm & send OTP"
- OTP page appears

**Step 4: Get OTP Code**
- Click button: "📱 View OTP Code"
- Alert shows: 4-digit code (e.g., 1234)
- Remember this code
- Close alert

**Step 5: Modify from_account_id**
- Open DevTools: F12 → Console
- Paste this command:
  ```javascript
  document.querySelector('input[name="from_account_id"]').value = 4
  ```
- Press Enter in console

**Step 6: Submit Transfer**
- Enter OTP code in form field
- Click "Authorise transfer"

**Expected:**
```
✅ Modal popup appears with "🚩 FLAG CAPTURED!"
✅ Flag text visible (BANKSPLOIT{transfer.l1-...})
✅ Copy button enabled, OK button disabled
✅ Click Copy → OK button enables
✅ Click OK → Modal closes

✅ Receipt page appears with:
   - "✓ Transfer Successful" (with checkmark)
   - From Account: "Savings · 1004444444" (account_id=4, NOT your account)
   - To Account: "Savings · 1001111111" (beneficiary)
   - Amount: ₹1000.00
   - Mode: IMPS
   - Status: completed
   - Reference: IMPS...
   - Date: today's date
```

**Verify in Console (F12):**
```javascript
console.log  // Should show:
// "Flag found in header: BANKSPLOIT{transfer.l1-...}"
// "Stored pending redirect, waiting for user to close modal"
```

---

## Test 3: Receipt Endpoint (Direct Link)

**Step 1: After Transfer, Click Download Receipt**
- On receipt page, look for link/button: "Download receipt"
- Click it

**Expected:**
```
✅ Opens formatted HTML page (NOT JSON)
✅ Shows same information as receipt:
   - From Account: "Savings · XXXX"
   - To Account: "Savings · XXXX"
   - Amount, Mode, Status, Reference, Date
```

**OR Test via Browser Console:**
```javascript
// Go to any transfer receipt link like:
// http://localhost:9443/api/v1/transfer/1/receipt

// In Console check:
document.body.innerText  // Should show readable text, not JSON
// Look for account numbers like "Savings · 1001234567"
```

---

## Test 4: Verify GET Form Interceptor Works

**Step 1: At Statements Page**
- Open DevTools: F12 → Console
- Clear console (right-click → Clear)

**Step 2: Submit Statements Filter**
- Modify any filter (date, search)
- Click "Apply filters" button

**Expected in Console:**
```
✅ See logs like:
   "Form submit event fired"
   "Intercepting form submission: GET /statements"
   "GET form URL: /statements?account_id=..."
```

---

## Test 5: Verify No Errors

**Step 1: DevTools Console (F12)**
- Look for red error messages
- Should be NONE (or only warnings)

**Expected:**
```
✅ No "TypeError" messages
✅ No "fetch failed" errors
✅ No "Cannot read property" errors
✅ Logs should show successful interception
```

---

## Troubleshooting

### Issue: Modal doesn't appear for statements.l1

**Check:**
1. URL has `?account_id=4`
2. DevTools Console shows: "Flag found in header"
3. Response Headers show: `X-CTF-Flag: BANKSPLOIT{...}`

**Fix:**
```javascript
// Check if flag was captured
console.log(capturedFlags)  // Should not be empty

// Check if response had flag header
// DevTools → Network → (request) → Response Headers
// Look for: X-CTF-Flag
```

---

### Issue: Receipt shows JSON instead of HTML

**Check:**
1. Go to: http://localhost:9443/api/v1/transfer/1/receipt
2. Right-click → "View Page Source"
3. Should see HTML tags like `<div>`, `<h1>`, not `{json}`

**If still JSON:**
- Restart PHP server
- Clear browser cache (Ctrl+Shift+Delete)
- Check file: `src/modules/transfer/api.php` line 90 has `header('Content-Type: text/html')`

---

### Issue: Transfer from_account_id won't change

**Check:**
1. Form loads? (can see input fields)
2. DevTools Console shows no errors?
3. Run in console:
   ```javascript
   const input = document.querySelector('input[name="from_account_id"]');
   console.log(input);  // Should show input element
   console.log(input.value);  // Should show current value
   input.value = 4;  // Try setting
   console.log(input.value);  // Should show 4
   ```

---

### Issue: OTP Code button not working

**Check:**
1. Button visible? ("📱 View OTP Code")
2. Click button, does alert appear?
3. DevTools Console shows:
   ```javascript
   // Should see: fetch /get-transfer-otp
   // Response should have: {ok: true, otp: "1234"}
   ```

---

## Final Checklist

After all tests, verify:

- [ ] statements.l1 flag modal appears for account_id=4
- [ ] statements.l1 flag can be copied
- [ ] statements.l1 OK button works after copy
- [ ] transfer.l1 flag modal appears
- [ ] transfer.l1 flag can be copied
- [ ] Receipt shows "Transfer Successful" ✓
- [ ] Receipt shows From Account (Savings · XXXX)
- [ ] Receipt shows To Account (Savings · XXXX)
- [ ] Receipt is HTML (not JSON)
- [ ] Download receipt link works
- [ ] No JavaScript errors in console
- [ ] OTP Code button works

---

## Success Criteria

✅ All 12 checkboxes pass = **Implementation Complete**

If any fail, note which test and error from console (F12) for debugging.

---

## Expected Results Summary

| Test | Expected | Status |
|------|----------|--------|
| statements.l1 /statements?account_id=4 | Flag modal appears | ✅ READY |
| transfer.l1 from_account_id=4 | Flag modal appears | ✅ READY |
| Receipt endpoint | Returns HTML with accounts | ✅ READY |
| Receipt shows account names | "Savings · XXXX1234" | ✅ READY |
| OTP View Code button | Shows 4-digit OTP | ✅ READY |
| GET form intercept | Logs show interception | ✅ READY |
| No errors in console | Console clean | ✅ READY |

