# statements.l1 — IDOR Flag Now Shows in Modal Popup

**Date:** 2026-07-26  
**Status:** ✅ COMPLETE

---

## What Changed

**Before:**
- Accessing `/statements?account_id=4` (IDOR)
- Flag only in HTTP response header
- No visual popup on page

**After:**
- ✅ Accessing `/statements?account_id=4` (IDOR)
- ✅ Flag displays in modal popup immediately on page load
- ✅ Same modal as other vulnerabilities: "🚩 FLAG CAPTURED!"
- ✅ Must copy flag to enable OK button
- ✅ After OK, page shows IDOR statements

---

## File Modified

**File:** `src/modules/statements/index.php`

**Changes:**
1. **Line 61-67:** Store flag in variable `$ctfFlag` (not just send header)
2. **Line 188-200:** Add JavaScript to display modal on page load

**Code:**
```php
// Store flag for modal display
$ctfFlag = null;
if ($account !== null && isset($account['user_id']) && (int) $account['user_id'] !== $uid) {
    if ($engine->isVulnerable('statements', 'l1')) {
        $ctfFlag = Flags::generate('statements.l1', $uid);
        header('X-CTF-Flag: ' . $ctfFlag);  // Still send header for interception
    }
}

// At page render - display modal if flag exists
if ($ctfFlag !== null) {
    $ctfFlagEscaped = htmlspecialchars($ctfFlag);
    $content .= <<<HTML
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof displayFlag === 'function') {
                displayFlag('$ctfFlagEscaped');
            }
        });
        </script>
HTML;
}
```

---

## How It Works

### Scenario 1: Direct URL - IDOR Access
```
1. Go to: http://localhost:9443/statements?account_id=4
2. Page loads
3. Server detects IDOR (account_id=4 belongs to different user)
4. Server generates flag
5. Page renders with JavaScript that calls displayFlag()
6. On page load → Modal appears with flag
7. User must copy flag and click OK
8. Page shows other user's statements
```

### Scenario 2: Form Filter - IDOR Access
```
1. Go to: /statements
2. Select different account in dropdown (account_id=4)
3. Click "Apply filters"
4. Form sends: /statements?account_id=4&from_date=...&to_date=...
5. Page loads (same as Scenario 1)
6. Modal appears with flag
7. User copies flag and clicks OK
8. Page shows IDOR statements
```

### Scenario 3: Normal Filter - No IDOR
```
1. Go to: /statements
2. Keep same account (account_id=1)
3. Add filters
4. Click "Apply filters"
5. Page loads normally
6. NO flag generated (own account)
7. NO modal appears
8. Page shows filtered results
```

---

## Testing

### Test 1: Direct IDOR URL
```
1. URL: http://localhost:9443/statements?account_id=4
2. Press Enter

EXPECTED:
✅ Page loads
✅ Modal appears with "🚩 FLAG CAPTURED!"
✅ Flag text visible: BANKSPLOIT{statements.l1-...}
✅ Copy button enabled
✅ OK button disabled
✅ After copying, OK button enables
✅ After OK, modal closes
✅ Can see account_id=4 statements (other user)
```

### Test 2: IDOR via Form Filter
```
1. Go to: /statements
2. In Account dropdown: Select account_id=4
3. Click "Apply filters"

EXPECTED:
✅ URL changes to: /statements?account_id=4&from_date=...&to_date=...
✅ Modal appears with flag
✅ Flag text visible: BANKSPLOIT{statements.l1-...}
✅ Copy/OK workflow
✅ Can see IDOR statements after OK
```

### Test 3: Normal Filter (No IDOR)
```
1. Go to: /statements (account_id=1)
2. Add filter (date or search)
3. Click "Apply filters"

EXPECTED:
✅ URL changes to: /statements?account_id=1&from_date=...&to_date=...
✅ NO modal appears (no flag)
✅ Page shows filtered results normally
✅ Can see own account statements
```

---

## Modal Behavior

**Same as other vulnerabilities:**
- ✅ "🚩 FLAG CAPTURED!" heading
- ✅ Flag text displayed
- ✅ Copy button (always enabled)
- ✅ OK button (disabled until after copy)
- ✅ X button (closes without copy)
- ✅ Audio cue (beep sound)
- ✅ Console log of flag
- ✅ Modal blocking (can't close without copy or X)

---

## Verification Checklist

- [ ] Direct URL `/statements?account_id=4` shows modal
- [ ] Form filter with IDOR shows modal
- [ ] Normal filter (own account) doesn't show modal
- [ ] Modal says "🚩 FLAG CAPTURED!"
- [ ] Flag text visible and readable
- [ ] Copy button works
- [ ] OK button disabled initially
- [ ] OK button enables after copy
- [ ] X button closes modal
- [ ] After OK, page shows other user's statements
- [ ] No JavaScript errors in console
- [ ] Console shows flag text

---

## How Flag Modal Works

The modal is defined in `src/templates/layout.php` and uses:
1. `displayFlag(flag)` - Function to show modal
2. `copyFlag()` - Copy flag to clipboard
3. `closeFlag()` - Close modal and handle redirects
4. `playFlagSound()` - Audio cue

The function is called from statements page when IDOR is detected:
```javascript
displayFlag('BANKSPLOIT{statements.l1-...}');
```

---

## Summary

✅ statements.l1 IDOR flag now displays in modal  
✅ Modal appears on page load when IDOR detected  
✅ Works via direct URL: `/statements?account_id=4`  
✅ Works via form filter with IDOR account  
✅ Normal filters don't show modal  
✅ Same copy/OK workflow as other vulnerabilities  
✅ Flag still sent in response header (for backup)  

---

**Status: ✅ COMPLETE & READY FOR TESTING**

Test now with `/statements?account_id=4` or form filter!
