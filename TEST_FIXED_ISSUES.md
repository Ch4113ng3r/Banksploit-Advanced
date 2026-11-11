# Fixed Issues Test Guide

## Issue 1: statements.l1 IDOR - Flag Modal Not Showing

**Root Cause:** GET form submissions (like the statements filter form) were not being intercepted.

**Fix Applied:** Updated form interceptor in `src/templates/layout.php` to handle GET forms by appending query parameters to the URL.

**How to Test:**
1. Login: BS100234 / password123
2. Verify OTP
3. Go to Statements page (URL shows `/statements?account_id=1`)
4. **Change URL** to `/statements?account_id=4` 
5. Press Enter
6. **Expected:** Modal popup appears with flag 🚩
7. Copy flag and click OK to view other user's statements

**Technical Details:**
- GET forms now convert FormData to query string: `?account_id=4&from_date=...&to_date=...`
- Flag header `X-CTF-Flag` is captured before page navigation
- Link interceptor captures the flag

---

## Issue 2: Transfer Receipt - Showing JSON Instead of Formatted Page

**Root Cause:** The `/api/v1/transfer/{id}/receipt` endpoint was returning raw JSON with numeric IDs instead of formatted HTML with account details.

**Fix Applied:** Updated `src/modules/transfer/api.php` to:
- Return formatted HTML receipt page instead of JSON
- Fetch account details (account_number, account_type) from database
- Display human-readable account information: "Savings · XXXX1234"
- Show all transaction details in a formatted table

**How to Test:**
1. Complete a transfer (any amount)
2. On receipt page, click "Download receipt" link
3. **Expected:** Clean HTML receipt showing:
   - From Account: Savings · XXXX1234 (not just ID)
   - To Account: Savings · XXXX5678 (not just ID)
   - Amount, Mode, Status, Reference, Date, Remarks

**Technical Details:**
- New code queries accounts table for account details
- Returns `Content-Type: text/html` instead of JSON
- Styled receipt with emerald success indicator

---

## Issue 3: Transfer IDOR - Flag Modal Not Showing / Success Not Clear

**Root Cause:** Two problems combined:
1. Receipt was JSON so no clear success indication
2. Flag modal wasn't displaying because form interceptor didn't properly handle the response

**Fix Applied:**
1. Receipt now shows formatted HTML with success indicator (✓)
2. Form interceptor updated to properly capture `X-CTF-Flag` headers from transfer response
3. Redirect is blocked until user copies flag and clicks OK

**How to Test:**
1. Login: BS100234 / password123
2. Verify OTP → Dashboard
3. Go to Transfer → Fill transfer details
4. Confirm & send OTP
5. Open DevTools (F12) → Console
6. Run: `document.querySelector('input[name="from_account_id"]').value = 4`
7. Enter OTP (click "View OTP Code" button to see it)
8. Click "Authorise transfer"
9. **Expected:**
   - Modal appears with flag 🚩
   - Copy flag, click OK
   - Receipt page shows "Transfer successful" ✓
   - Shows FROM and TO account numbers (not IDs)

---

## Testing Checklist

### Test 1: statements.l1 IDOR
- [ ] Login with BS100234 / password123
- [ ] Go to Statements
- [ ] Change URL to ?account_id=4
- [ ] Modal appears
- [ ] Flag displays in modal
- [ ] Copy button works
- [ ] OK button enables after copy
- [ ] Can view other user's statements

### Test 2: statements Filter Form (GET)
- [ ] Statements page loads
- [ ] Click "Apply filters" button
- [ ] If IDOR attempted (account_id=4), modal shows flag
- [ ] Form still works normally for own account

### Test 3: transfer.l1 IDOR Receipt
- [ ] Complete a transfer (any account to any beneficiary)
- [ ] Receipt page shows success message ✓
- [ ] Receipt shows account numbers, not IDs
- [ ] "Download receipt" link works
- [ ] Receipt PDF download contains full details

### Test 4: transfer.l1 IDOR with Flag
- [ ] Modify from_account_id via console to another user's account
- [ ] Submit transfer with OTP
- [ ] Modal appears with flag
- [ ] Receipt shows successful transfer from other user's account
- [ ] Both FROM and TO accounts display correctly

### Test 5: General
- [ ] No JavaScript console errors
- [ ] Link interceptor works for /statements?account_id=4
- [ ] Form interceptor works for GET forms
- [ ] X button on modal closes without requiring copy
- [ ] Page redirects after modal close

---

## Files Modified

| File | Change |
|------|--------|
| `src/templates/layout.php` | Added GET form support to form interceptor (lines 348-365) |
| `src/modules/transfer/api.php` | Receipt endpoint returns formatted HTML with account details (lines 61-131) |

---

## Expected Results

After these fixes:
1. ✅ statements.l1 IDOR flag appears in modal popup
2. ✅ Transfer receipt shows account numbers (Savings · XXXX1234) not just IDs
3. ✅ Transfer IDOR succeeds and flag modal displays before redirect
4. ✅ Receipt page has clear success indicator
5. ✅ Both GET and POST form submissions properly intercepted
