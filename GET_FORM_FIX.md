# GET Form Fix - statements.l1 Now Works with Apply Filters

**Date:** 2026-07-26  
**Status:** ✅ FIXED

---

## Problem Fixed

**Before:** Clicking "Apply filters" on statements page didn't navigate to the filtered URL

**Now:** ✅ Clicking "Apply filters" properly sends GET parameters in URL

---

## What Changed

**File:** `src/templates/layout.php` (lines 344-455)

**Key Fix:** When a GET form has no flag (normal filter, not IDOR):
- Instead of reloading current page → **Navigate to the filtered URL**
- Proper handling: `window.location.href = action`

**Code Logic:**
```javascript
if (isGetForm) {
    // For GET forms, navigate to the filtered URL
    console.log('No flag captured, navigating to:', action);
    window.location.href = action;
} else {
    // For POST forms, reload
    console.log('No flag captured, reloading');
    window.location.reload();
}
```

---

## How It Works Now

### Scenario 1: Normal Filter (Your Own Account)
```
1. At /statements?account_id=1
2. Click "Apply filters" (with default account_id=1)
3. Form creates URL: /statements?account_id=1&from_date=2024-01-01&to_date=2026-07-26&q=
4. NO flag header (own account)
5. ✅ Page navigates to filtered URL
6. Console shows: "No flag captured, navigating to: /statements?account_id=1&..."
```

### Scenario 2: IDOR Filter (Another User's Account)
```
1. At /statements?account_id=1
2. Change account dropdown to account_id=4
3. Click "Apply filters"
4. Form creates URL: /statements?account_id=4&from_date=...&to_date=...&q=...
5. ✅ Flag header present (IDOR detected)
6. ✅ Modal appears with flag
7. User must copy flag and click OK
8. ✅ Page navigates to /statements?account_id=4 (showing IDOR data)
9. Console shows: "Flag found in header: BANKSPLOIT{statements.l1-...}"
```

---

## Testing

### Test 1: Normal Filter (No IDOR)
```
1. Go to: /statements
2. Keep same account (account_id=1)
3. Add date filter or search term
4. Click "Apply filters"

EXPECTED:
✅ URL updates with parameters: ?account_id=1&from_date=...&to_date=...&q=...
✅ Page shows filtered results
✅ No flag modal (because it's your own account)
✅ Console shows: "No flag captured, navigating to: /statements?account_id=1..."
```

### Test 2: IDOR Filter
```
1. Go to: /statements
2. In Account dropdown, select another account (e.g., account_id=4)
3. Click "Apply filters"

EXPECTED:
✅ URL updates to: /statements?account_id=4&from_date=...&to_date=...&q=...
✅ Flag modal appears "🚩 FLAG CAPTURED!"
✅ Flag text: BANKSPLOIT{statements.l1-...}
✅ Must copy flag, click OK to see results
✅ Console shows: "Flag found in header: BANKSPLOIT{statements.l1-...}"
```

### Test 3: Direct URL (IDOR)
```
1. Go to: /statements?account_id=4

EXPECTED:
✅ Flag modal appears immediately
✅ Flag text: BANKSPLOIT{statements.l1-...}
✅ Must copy flag, click OK
✅ Can see other user's statements
```

---

## Verification Checklist

- [ ] Clicking "Apply filters" navigates (not reloads)
- [ ] URL shows GET parameters: ?account_id=1&from_date=...&to_date=...&q=...
- [ ] Normal filters work (own account)
- [ ] IDOR filter shows flag modal
- [ ] Flag can be copied
- [ ] After OK, IDOR page displays
- [ ] Console shows proper logs
- [ ] No JavaScript errors

---

## Console Logs

**Normal Filter (No IDOR):**
```
GET form URL: /statements?account_id=1&from_date=2024-01-01&to_date=2026-07-26&q=
=== FORM RESPONSE ===
Status: 200
X-CTF-Flag header: null
=== PROCESSING RESPONSE ===
Captured flags count: 0
No flag captured, navigating to: /statements?account_id=1&from_date=2024-01-01&to_date=2026-07-26&q=
```

**IDOR Filter:**
```
GET form URL: /statements?account_id=4&from_date=...&to_date=...&q=...
=== FORM RESPONSE ===
Status: 200
X-CTF-Flag header: BANKSPLOIT{statements.l1-...}
Flag found in header: BANKSPLOIT{statements.l1-...}
=== PROCESSING RESPONSE ===
Captured flags count: 1
✓ Flag captured, page will navigate when modal closes
```

---

## Summary

✅ GET forms now work properly with "Apply filters"  
✅ Normal filters navigate to filtered URL  
✅ IDOR filters show flag modal  
✅ URL parameters sent correctly: `?account_id=...&from_date=...&to_date=...&q=...`  
✅ statements.l1 IDOR fully functional  

---

**Status: ✅ FIXED & READY FOR TESTING**
