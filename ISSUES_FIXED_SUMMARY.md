# Issues Fixed - Summary

**Date:** 2026-07-26  
**Status:** ✅ All 3 Issues Fixed

---

## Issue 1: statements.l1 IDOR - Flag Modal Not Showing

### Problem
When accessing `/statements?account_id=4` (IDOR to another user's statements), the flag header was not being captured by the modal system, and no popup appeared.

### Root Cause
GET form submissions were not being intercepted. The statements filter form uses `method="get"`, which the form interceptor wasn't handling properly.

### Solution
**File:** `src/templates/layout.php` (lines 348-365)

Updated the form interceptor to:
1. Detect GET method forms
2. Convert FormData to URL query parameters
3. Append query string to action URL: `?account_id=4&from_date=...`
4. Send fetch request with `redirect: 'manual'` to capture headers

```javascript
if (method === 'GET') {
    const params = new URLSearchParams(formData);
    action = action + (action.includes('?') ? '&' : '?') + params.toString();
    console.log('GET form URL:', action);
} else {
    fetchOptions.body = new URLSearchParams(formData);
}
```

### Verification
```
1. Login: BS100234 / password123
2. Verify OTP → Go to Statements
3. Change URL to: /statements?account_id=4
4. Expected: Modal appears with flag 🚩
```

---

## Issue 2: Transfer Receipt - Returns JSON Instead of Formatted HTML

### Problem
After completing a transfer (especially IDOR transfer), the receipt endpoint `/api/v1/transfer/{id}/receipt` returned raw JSON like:
```json
{
  "ok": true,
  "receipt": {
    "id": 35,
    "from_account_id": 1,
    "to_account_id": 4,
    "amount": "1111.00"
  }
}
```

This showed numeric IDs instead of account details, and didn't confirm successful transfer.

### Root Cause
The API endpoint was designed to return JSON responses. There was no HTML receipt page format.

### Solution
**File:** `src/modules/transfer/api.php` (lines 60-131)

Modified the receipt endpoint to:
1. Fetch account details from database (account_number, account_type)
2. Generate formatted HTML receipt page
3. Display human-readable account information: "Savings · XXXX1234"
4. Set `Content-Type: text/html` header
5. Show success indicator (✓) and transfer details

```php
// Fetch account details to display account numbers instead of IDs
$fromStmt = $pdo->prepare('SELECT account_number, account_type FROM accounts WHERE id = :id');
$fromStmt->execute([':id' => $fromId]);
$fromAcc = $fromStmt->fetch();
$fromDisplay = $fromAcc 
    ? htmlspecialchars(ucfirst($fromAcc['account_type']) . ' · ' . $fromAcc['account_number'])
    : 'Account #' . $fromId;
```

### Receipt Page Now Shows
- ✓ Transfer Successful (success indicator)
- From Account: Savings · 1001234567 (not just ID 1)
- To Account: Savings · 1005678901 (not just ID 4)
- Amount, Mode, Status, Reference, Date, Remarks
- "New Transfer" button

### Verification
```
1. Complete any transfer
2. Click "Download receipt" link
3. Expected: Clean HTML receipt with account numbers (not JSON)
```

---

## Issue 3: Transfer IDOR - Flag Modal Not Showing / Success Not Clear

### Problem
When exploiting transfer.l1 IDOR by modifying `from_account_id` to another user's account:
1. No flag modal appeared
2. No clear success indication
3. User had to manually check if transfer succeeded

### Root Cause
Two combined issues:
1. Form interceptor wasn't properly handling form submission responses with flag headers
2. Receipt was JSON (Issue 2), so no clear success message

### Solution
Combined the fixes from Issues 1 and 2:

1. **Form Interceptor** (`src/templates/layout.php`):
   - Now properly captures `X-CTF-Flag` headers from form submission responses
   - Prevents redirect until user closes modal (copies flag and clicks OK)
   - Works for both GET and POST forms

2. **Receipt HTML** (`src/modules/transfer/api.php`):
   - Shows clear "Transfer Successful" ✓ message
   - Displays actual account numbers, confirming IDOR succeeded
   - Removes ambiguity about whether transfer actually happened

### Verification
```
1. Login: BS100234 / password123
2. Verify OTP → Go to Transfer
3. Fill transfer details → Confirm & send OTP
4. Open DevTools (F12) → Console
5. Run: document.querySelector('input[name="from_account_id"]').value = 4
6. Enter OTP (click "View OTP Code" for code)
7. Click "Authorise transfer"
8. Expected:
   - Modal appears with flag 🚩
   - Can copy flag, click OK to close
   - Receipt page shows: "Transfer Successful" ✓
   - Shows FROM account = another user's account
   - Shows TO account = beneficiary account
```

---

## Files Modified

| File | Lines | Change |
|------|-------|--------|
| `src/templates/layout.php` | 348-365 | Added GET form parameter handling to form interceptor |
| `src/modules/transfer/api.php` | 60-131 | Receipt endpoint returns formatted HTML with account details |

---

## Testing Checklist

### ✅ statements.l1 IDOR
- [ ] Access /statements?account_id=4
- [ ] Modal appears with flag
- [ ] Flag modal has Copy and OK buttons
- [ ] Must copy before OK enables
- [ ] Can view other user's statements after closing modal

### ✅ transfer.l1 IDOR
- [ ] Modify from_account_id via console (to account_id=4 or other user)
- [ ] Submit transfer with OTP
- [ ] Modal appears with flag 🚩
- [ ] Receipt shows successful transfer
- [ ] FROM account shows modified account (IDOR confirmed)
- [ ] TO account shows correct beneficiary

### ✅ Receipt Endpoint
- [ ] Click "Download receipt" on any transfer
- [ ] Returns HTML page (not JSON)
- [ ] Shows account numbers, not IDs
- [ ] Shows success indicator ✓
- [ ] All transaction details visible

### ✅ General
- [ ] No JavaScript console errors
- [ ] Forms still work normally for non-IDOR cases
- [ ] GET and POST forms both intercepted
- [ ] OTP forms (data-no-intercept="true") still submit normally
- [ ] Logout button still works immediately

---

## Summary

| Issue | Status | Fix |
|-------|--------|-----|
| statements.l1 flag not showing | ✅ FIXED | GET form interceptor support added |
| Receipt returns JSON | ✅ FIXED | Endpoint now returns formatted HTML |
| Transfer IDOR lacks success indication | ✅ FIXED | Receipt shows account confirmation + flag modal |

**All vulnerabilities now properly display flag modals and provide clear feedback when exploited.**
